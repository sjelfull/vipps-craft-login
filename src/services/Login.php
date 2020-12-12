<?php


namespace vippsas\login\services;

use Craft;
use GuzzleHttp\Client;
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
     * @var string
     */
    const PROD_URL = 'https://api.vipps.no';

    /**
     * Base URL for the test API
     * @var string
     */
    const TEST_URL = 'https://apitest.vipps.no';

    // Properties
    // =========================================================================

    /**
     * Settings object
     * @var Settings
     */
    private $settings;

    /**
     * Guzzle Client object
     * @var Client
     */
    private $client;

    /**
     * The Vipps Session if the User is logged in
     * @var Session
     */
    private $session_object;

    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function init()
    {
        $this->settings = VippsLogin::getInstance()->getSettings();

        parent::init();
    }

    /**
     * Get the LogInButton object
     * @return Button
     * @throws InvalidConfigException
     * @throws \yii\base\Exception
     */
    public function loginButton() : Button
    {
        return (new Button())->login();
    }

    /**
     * Get the LogInButton object
     * @return Button
     * @throws InvalidConfigException
     * @throws \yii\base\Exception
     */
    public function continueButton() : Button
    {
        return (new Button())->continue();
    }

    /**
     * Returns the auth URL
     * @param string
     * @return string
     * @throws InvalidConfigException
     * @throws \yii\base\Exception
     */
    public function getLoginUrl($returnUrl) : string
    {
        $state_key = \Craft::$app->security->generateRandomString(50);
        $state = new \stdClass();
        $state->key = $state_key;
        $state->returnUrl = $returnUrl;

        $parameters = [
            'client_id' => $this->getClientId(),
            'response_type' => 'code',
            'scope' => implode(' ', $this->settings->getLoginScopes()),
            'state' => base64_encode(serialize($state)),
            'redirect_uri' => $this->settings->getRedirectUri(),
        ];

        if($this->settings->loginAutomaticReturn()) {
            $parameters['requested_flow'] = 'automatic_return_from_vipps_app';
            $parameters['code_challenge'] = $this->generateCodeChallenge($state_key);
            $parameters['code_challenge_method'] = 'S256';
        }

        $path = $this->getOpenIDConfiguration('authorization_endpoint', $this->getBaseUrl().'/access-management-1.0/access/oauth2/auth');

        return $path.'?'.http_build_query($parameters);
    }

    /**
     * Returns the auth URL
     * @param string
     * @return string
     * @throws InvalidConfigException
     * @throws \yii\base\Exception
     */
    public function getContinueUrl($returnUrl) : string
    {
        $state_key = \Craft::$app->security->generateRandomString(50);
        $state = new \stdClass();
        $state->key = $state_key;
        $state->returnUrl = $returnUrl;

        $parameters = [
            'client_id' => $this->getClientId(),
            'response_type' => 'code',
            'scope' => implode(' ', $this->settings->getContinueScopes()),
            'state' => base64_encode(serialize($state)),
            'redirect_uri' => $this->settings->getRedirectUri('continue'),
        ];

        if($this->settings->continueAutomaticReturn()) {
            $parameters['requested_flow'] = 'automatic_return_from_vipps_app';
            $parameters['code_challenge'] = $this->generateCodeChallenge($state_key);
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

    /**
     * Request a new login token from vipps based on a code
     * @param $code
     * @param $state
     * @return \Psr\Http\Message\ResponseInterface
     * @throws InvalidConfigException
     */
    public function getNewLoginToken($code, $state)
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
            $state = unserialize(base64_decode($state));
            $code_verifier = $this->retrieveCodeVerifier($state->key);
            if(!$code_verifier || is_null($code_verifier)) throw new RequestTimeoutException(Craft::t('vipps_login', 'Authorization was not completed within the timeframe. Please try again.'));
            $body['form_params']['code_verifier'] = $code_verifier;
        }

        return $this->getClient()->post($path, $body);
    }

    /**
     * Request a new continue token from vipps based on a code
     * @param $code
     * @param $state
     * @return \Psr\Http\Message\ResponseInterface
     * @throws InvalidConfigException
     */
    public function getNewContinueToken($code, $state)
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
            $state = unserialize(base64_decode($state));
            $code_verifier = $this->retrieveCodeVerifier($state->key);
            if(!$code_verifier || is_null($code_verifier)) throw new RequestTimeoutException(Craft::t('vipps_login', 'Authorization was not completed within the timeframe. Please try again.'));
            $body['form_params']['code_verifier'] = $code_verifier;
        }

        return $this->getClient()->post($path, $body);
    }

    /**
     * Request userinfo from Vipps
     * @param $token
     * @return \Psr\Http\Message\ResponseInterface
     * @throws InvalidConfigException
     */
    public function getUserInfo($token)
    {
        $path = $this->getOpenIDConfiguration('userinfo_endpoint', $this->getBaseUrl().'/vipps-userinfo-api/userinfo');

        return $this->getClient()->get($path, [
            'headers' => [
                'Authorization' => 'Bearer '.$token
            ]
        ]);
    }

    /**
     * Returns the Vipps Session if the user is logged in.
     * Returns null if there is no session for the current user.
     * @return Session|null
     */
    public function session()
    {
        if(!$this->session_object)
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

    /**
     * Get the OpenID Configuration from the
     * .well-known/openid-configuration endpoint
     * @param string|null $path
     * @param string|null $default
     * @return mixed|string
     * @throws InvalidConfigException
     */
    protected function getOpenIDConfiguration(string $path = null, string $default = null)
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

    private function fetchOpenIDConfiguration()
    {
        return $this->getClient()->get($this->getBaseUrl().'/access-management-1.0/access/.well-known/openid-configuration');
    }

    /**
     * Get the base API URL based on environment
     * @return string
     */
    private function getBaseUrl() : string
    {
        return $this->settings->inTest() ? self::TEST_URL : self::PROD_URL;
    }

    /**
     * Returns the Client ID for the current environment
     * @return string
     */
    private function getClientId() : string
    {
        return $this->settings->inTest() ? $this->settings->test_client_id : $this->settings->prod_client_id;
    }

    /**
     * Returns the Client Secret for the current environment
     * @return string
     */
    private function getClientSecret() : string
    {
        return $this->settings->inTest() ? $this->settings->test_client_secret : $this->settings->prod_client_secret;
    }

    /**
     * Get the GuzzleHTTP Client object
     * @return Client
     */
    private function getClient()
    {
        if(!$this->client || $this->client instanceof Client) $this->client = new Client();
        return $this->client;
    }

    /**
     * Generate a random code challenge and saves it to cache with a 5 minute duration.
     * The function returns a sha256 hashed version of the random code.
     *
     * https://tools.ietf.org/html/rfc7636#section-4.1
     *
     * @param $state
     *
     * @return string
     * @throws \yii\base\Exception
     */
    private function generateCodeChallenge($state)
    {
        $code_verifier = \Craft::$app->security->generateRandomString(128);
        Craft::$app->cache->set('vipps_' . $state, $code_verifier, 300);
        // BASE64URL-ENCODE(SHA256(ASCII(code_verifier)))
        return rtrim(StringHelper::base64UrlEncode(hash('sha256', utf8_encode($code_verifier), true)),'=');
    }

    /**
     * Retrieves a code from cache based on a state.
     * @param $state
     * @return mixed
     */
    private function retrieveCodeVerifier($state)
    {
        return Craft::$app->cache->get('vipps_' . $state);
    }
}