<?php

namespace vippsas\login\models;

use craft\base\Model;

class Settings extends Model
{
    // Properties
    // =========================================================================

    /**
     * Use Vipps Testing Environment
     * @var string
     */
    public $test = true;

    /**
     * Vipps Test Client ID
     * @var string
     */
    public $test_client_id;

    /**
     * Vipps Test Client Secret
     * @var string
     */
    public $test_client_secret;

    /**
     * Vipps Test Subscription Key
     * @var string
     */
    public $test_subscription_key;

    /**
     * Vipps Production Client ID
     * @var string
     */
    public $prod_client_id;

    /**
     * Vipps Production Client Secret
     * @var string
     */
    public $prod_client_secret;

    /**
     * Vipps Production Subscription Key
     * @var string
     */
    public $prod_subscription_key;

    /**
     * Automatic return after login
     * https://github.com/vippsas/vipps-login-api/blob/master/vipps-login-api.md#automatic-return-from-vipps-app
     * @var boolean
     */
    public $login_automatic_return = false;

    /**
     * Request address
     * @var boolean
     */
    public $login_address = false;

    /**
     * Request birthDate
     * @var boolean
     */
    public $login_birthDate = false;

    /**
     * Request email
     * @var boolean
     */
    public $login_email = true;

    /**
     * Request name
     * @var boolean
     */
    public $login_name = true;

    /**
     * Request phoneNumber
     * @var boolean
     */
    public $login_phoneNumber = true;

    /**
     * Request nin (Norwegian National Identification Number)
     * @var boolean
     */
    public $login_nin = false;

    /**
     * Automatic return after continue
     * https://github.com/vippsas/vipps-login-api/blob/master/vipps-login-api.md#automatic-return-from-vipps-app
     * @var boolean
     */
    public $continue_automatic_return = false;

    /**
     * Request address
     * @var boolean
     */
    public $continue_address = false;

    /**
     * Request birthDate
     * @var boolean
     */
    public $continue_birthDate = false;

    /**
     * Request email
     * @var boolean
     */
    public $continue_email = true;

    /**
     * Request name
     * @var boolean
     */
    public $continue_name = true;

    /**
     * Request phoneNumber
     * @var boolean
     */
    public $continue_phoneNumber = true;

    /**
     * Request nin (Norwegian National Identification Number)
     * @var boolean
     */
    public $continue_nin = false;

    /**
     * Custom verification template path
     * @var string
     */
    public $verify_template = '';

    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function scenarios()
    {
        return [
            'testMode' => [
                'test_client_id',
                'test_client_secret',
                'test_subscription_key'
            ],
            'productionMode' => [
                'prod_client_id',
                'prod_client_secret',
                'prod_subscription_key'
            ]
        ];
    }

    /**
     * @inheritDoc
     */
    public function rules()
    {
        return [
            [['test', 'login_automatic_return', 'continue_automatic_return'], 'boolean', 'trueValue' => true, 'falseValue' => false],
            [['login_address', 'login_birthDate', 'login_email', 'login_name', 'login_phoneNumber', 'login_nin'], 'boolean', 'trueValue' => true, 'falseValue' => false],
            [['continue_address', 'continue_birthDate', 'continue_email', 'continue_name', 'continue_phoneNumber', 'continue_nin'], 'boolean', 'trueValue' => true, 'falseValue' => false],
            [['test_client_id', 'test_client_secret', 'test_subscription_key', 'prod_client_id', 'prod_client_secret', 'prod_subscription_key', 'verify_template'], 'string'],
            [['test_client_id', 'test_client_secret', 'test_subscription_key'], 'required', 'on' => ['testMode'], 'message' => '{attribute} cannot be blank in Test Mode'],
            [['prod_client_id', 'prod_client_secret', 'prod_subscription_key'], 'required', 'on' => ['productionMode'], 'message' => '{attribute} cannot be blank in Production Mode'],
        ];
    }

    /**
     * @inheritDoc
     */
    public function beforeValidate()
    {
        if($this->test == 1) $this->scenario = 'testMode';
        else $this->scenario = 'productionMode';

        return parent::beforeValidate();
    }

    /**
     * @inheritDoc
     */
    public function attributeLabels()
    {
        return [
            'test' => 'Test mode',
            'test_client_id' => 'Client ID (Test)',
            'test_client_secret' => 'Client Secret (Test)',
            'test_subscription_key' => 'Subscription Key (Test)',
            'prod_client_id' => 'Client ID (Production)',
            'prod_client_secret' => 'Client Secret (Production)',
            'prod_subscription_key' => 'Subscription Key (Production)',
            'login_address' => 'Request Address',
            'login_birthDate' => 'Request Birth Date',
            'login_email' => 'Request Email',
            'login_name' => 'Request Name',
            'login_phoneNumber' => 'Request Phone Number',
            'login_nin' => 'Request NIN',
            'continue_address' => 'Request Address',
            'continue_birthDate' => 'Request Birth Date',
            'continue_email' => 'Request Email',
            'continue_name' => 'Request Name',
            'continue_phoneNumber' => 'Request Phone Number',
            'continue_nin' => 'Request NIN',
            'verify_template' => 'Verification Template',
            'login_automatic_return' => 'Automatic return from Vipps app',
            'continue_automatic_return' => 'Automatic return from Vipps app'
        ];
    }

    /**
     * Is the integration in production mode?
     * @return bool
     */
    public function inProduction() : bool
    {
        return !$this->inTest();
    }

    /**
     * Is the integration in production mode?
     * @return bool
     */
    public function inTest() : bool
    {
        return $this->test == 1;
    }

    /**
     * Is the login flow using automatic return?
     * @return bool
     */
    public function loginAutomaticReturn() : bool
    {
        return $this->login_automatic_return == 1;
    }

    /**
     * Is the continue flow using automatic return?
     * @return bool
     */
    public function continueAutomaticReturn() : bool
    {
        return $this->continue_automatic_return == 1;
    }

    /**
     * Returns the scopes
     * @return array
     */
    public function getLoginScopes() : array
    {
        $scopes = ['openid', 'api_version_2'];
        if($this->login_address == 1) $scopes[] = 'address';
        if($this->login_birthDate == 1) $scopes[] = 'birthDate';
        if($this->login_email == 1) $scopes[] = 'email';
        if($this->login_name == 1) $scopes[] = 'name';
        if($this->login_phoneNumber == 1) $scopes[] = 'phoneNumber';
        if($this->login_nin == 1) $scopes[] = 'nin';
        return $scopes;
    }
    /**
     * Returns the scopes
     * @return array
     */
    public function getContinueScopes() : array
    {
        $scopes = ['openid', 'api_version_2'];
        if($this->continue_address == 1) $scopes[] = 'address';
        if($this->continue_birthDate == 1) $scopes[] = 'birthDate';
        if($this->continue_email == 1) $scopes[] = 'email';
        if($this->continue_name == 1) $scopes[] = 'name';
        if($this->continue_phoneNumber == 1) $scopes[] = 'phoneNumber';
        if($this->continue_nin == 1) $scopes[] = 'nin';
        return $scopes;
    }

    /**
     * Returns the redirect URI for the user when returned from Vipps
     *
     * @param string $action
     * @return string
     */
    public function getRedirectUri(string $action = 'login') : string
    {
        if($action == 'login') return \Craft::$app->request->getHostInfo().'/vipps/login';
        return \Craft::$app->request->getHostInfo().'/vipps/login/'.$action;
    }

    // Protected Methods
    // =========================================================================



    // Private Methods
    // =========================================================================
}