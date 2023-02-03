<?php


namespace vippsas\login\services;

use Craft;
use stdClass;
use craft\base\Model;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use vippsas\login\components\Button;
use vippsas\login\exceptions\RequestTimeoutException;
use vippsas\login\models\Session;
use vippsas\login\VippsLogin;
use yii\base\Component;
use vippsas\login\models\Settings;
use yii\base\InvalidConfigException;
use yii\helpers\StringHelper;

class Login extends Component
{
    // Constants
    // =========================================================================

    /**
     * Base URL for the production API
     */
    const PROD_URL = 'https://api.vipps.no';

    /**
     * Base URL for the test API
     */
    const TEST_URL = 'https://apitest.vipps.no';

    // Properties
    // =========================================================================

    /**
     * Settings object
     */
    private ?Model $settings;

    /**
     * Guzzle Client object
     */
    private Client $client;

    /**
     * The Vipps Session if the User is logged in
     */
    private Session $session_object;

    // Public Methods
    // =========================================================================
    public function init()
    {
        $this->settings = VippsLogin::getInstance()->getSettings();

        parent::init();
    }

    public function loginButton() : Button
    {
        return (new Button())->login();
    }

    public function continueButton() : Button
    {
        return (new Button())->continue();
    }

    public function getLoginUrl($returnUrl) : string
    {
        $state = $this->createState($returnUrl);

        $parameters = [
            'client_id' => $this->getClientId(),
            'response_type' => 'code',
            'scope' => implode(' ', $this->settings->getLoginScopes()),
            'state' => $this->packState($state),
            'redirect_uri' => $this->settings->getRedirectUri(),
        ];

        if($this->settings->loginAutomaticReturn()) {
            $parameters['requested_flow'] = 'automatic_return_from_vipps_app';
            $parameters['code_challenge'] = $this->generateCodeChallenge($state->key);
            $parameters['code_challenge_method'] = 'S256';
        }

        $path = $this->getOpenIDConfiguration('authorization_endpoint', $this->getBaseUrl().'/access-management-1.0/access/oauth2/auth');

        return $path.'?'.http_build_query($parameters);
    }

    public function getContinueUrl($returnUrl) : string
    {
        $state = $this->createState($returnUrl);

        $parameters = [
            'client_id' => $this->getClientId(),
            'response_type' => 'code',
            'scope' => implode(' ', $this->settings->getContinueScopes()),
            'state' => $this->packState($state),
            'redirect_uri' => $this->settings->getRedirectUri('continue'),
        ];

        if($this->settings->continueAutomaticReturn()) {
            $parameters['requested_flow'] = 'automatic_return_from_vipps_app';
            $parameters['code_challenge'] = $this->generateCodeChallenge($state->key);
            $parameters['code_challenge_method'] = 'S256';
        }

        $path = $this->getOpenIDConfiguration('authorization_endpoint', $this->getBaseUrl().'/access-management-1.0/access/oauth2/auth');

        return $path.'?'.http_build_query($parameters);
    }

    public function getLogoutUrl($returnUrl = false): string
    {
        $url = Craft::$app->request->getHostInfo().'/vipps/logout';
        if($returnUrl) $url .= '?r=' . StringHelper::base64UrlEncode($returnUrl);
        return $url;
    }

    public function getNewLoginToken($code, $packed_state): ResponseInterface
    {
        $path = $this->getOpenIDConfiguration('token_endpoint', $this->getBaseUrl().'/access-management-1.0/access/oauth2/token');


        $body = [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic '.base64_encode($this->getClientId().':'.$this->getClientSecret())
            ],
            'form_params' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->settings->getRedirectUri(),
                'client_id' => $this->getClientId()
            ]
        ];

        if($this->settings->loginAutomaticReturn()) {
            $state = $this->unpackState($packed_state);
            $code_verifier = $this->retrieveCodeVerifier($state->key);
            if(!$code_verifier || is_null($code_verifier)) throw new RequestTimeoutException(Craft::t('vipps_login', 'Authorization was not completed within the timeframe. Please try again.'));
            $body['form_params']['code_verifier'] = $code_verifier;
        }

        return $this->getClient()->post($path, $body);
    }

    public function getNewContinueToken($code, $packed_state): ResponseInterface
    {
        $path = $this->getOpenIDConfiguration('token_endpoint', $this->getBaseUrl().'/access-management-1.0/access/oauth2/token');

        $body = [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic '.base64_encode($this->getClientId().':'.$this->getClientSecret())
            ],
            'form_params' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->settings->getRedirectUri('continue'),
                'client_id' => $this->getClientId()
            ]
        ];

        if($this->settings->continueAutomaticReturn()) {
            $state = $this->unpackState($packed_state);
            $code_verifier = $this->retrieveCodeVerifier($state->key);
            if(!$code_verifier || is_null($code_verifier)) throw new RequestTimeoutException(Craft::t('vipps_login', 'Authorization was not completed within the timeframe. Please try again.'));
            $body['form_params']['code_verifier'] = $code_verifier;
        }

        return $this->getClient()->post($path, $body);
    }

    public function getUserInfo($token): ResponseInterface
    {
        $path = $this->getOpenIDConfiguration('userinfo_endpoint', $this->getBaseUrl().'/vipps-userinfo-api/userinfo');

        return $this->getClient()->get($path, [
            'headers' => [
                'Authorization' => 'Bearer '.$token
            ]
        ]);
    }

    public function session(): ?Session
    {
        if(!isset($this->session_object))
        {
            $content = \Craft::$app->session->get('vipps_login');

            if(!$content)
            {
                return null;
            }

            $this->session_object = unserialize($content);

            // If the session is expired, delete it
            if($this->session_object->getExpiresIn() < 0)
            {
                Craft::$app->session->remove('vipps_login');
                return null;
            }
        }
        return $this->session_object;
    }

    // Protected Methods
    // =========================================================================

    protected function getOpenIDConfiguration(string $path = null, string $default = null): mixed
    {
        $config = Craft::$app->cache->get('vipps-login-openid-configuration');

        if(!$config)
        {
            $config = $this->fetchOpenIDConfiguration()->getBody()->getContents();
            Craft::$app->cache->set('vipps-login-openid-configuration', $config, 3600);
        }

        $array = json_decode($config, true);

        if($path !== null && !is_string($path)) throw new InvalidConfigException("OpenID Configuration path must be null or string");
        elseif($path === null) return $array;
        else {
            if(isset($array[$path])) return $array[$path];
            elseif($default !== null) return $default;
            else throw new InvalidConfigException("OpenID Configuration is not found and default value is not provided.");
        }
    }



    // Private Methods
    // =========================================================================

    private function fetchOpenIDConfiguration(): ResponseInterface
    {
        return $this->getClient()->get($this->getBaseUrl().'/access-management-1.0/access/.well-known/openid-configuration');
    }

    private function getBaseUrl() : string
    {
        return $this->settings->inTest() ? self::TEST_URL : self::PROD_URL;
    }

    private function getClientId() : string
    {
        return $this->settings->inTest() ? $this->settings->test_client_id : $this->settings->prod_client_id;
    }

    private function getClientSecret() : string
    {
        return $this->settings->inTest() ? $this->settings->test_client_secret : $this->settings->prod_client_secret;
    }

    private function getClient(): Client
    {
        if(!isset($this->client) || !($this->client instanceof Client)) $this->client = new Client();
        return $this->client;
    }

    /**
     * Generate a random code challenge and saves it to cache with a 5 minute duration.
     * The function returns a sha256 hashed version of the random code.
     *
     * https://tools.ietf.org/html/rfc7636#section-4.1
     */
    private function generateCodeChallenge($state): string
    {
        $code_verifier = \Craft::$app->security->generateRandomString(128);
        Craft::$app->cache->set('vipps_' . $state, $code_verifier, 300);
        // BASE64URL-ENCODE(SHA256(ASCII(code_verifier)))
        return rtrim(StringHelper::base64UrlEncode(hash('sha256', utf8_encode($code_verifier), true)),'=');
    }

    private function createState($returnUrl): stdClass
    {
        $state = new stdClass();
        $state->key = Craft::$app->security->generateRandomString(50);
        $state->returnUrl = $returnUrl;

        return $state;
    }

    private function packState($state): string
    {
        return base64_encode(serialize($state));
    }

    private function unpackState($packed_state): stdClass
    {
        return unserialize(base64_decode($packed_state));
    }
    
    private function retrieveCodeVerifier($state): mixed
    {
        return Craft::$app->cache->get('vipps_' . $state);
    }
}