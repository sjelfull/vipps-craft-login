<?php

namespace vippsas\login\models;

use craft\base\Model;

class Settings extends Model
{
    // Properties
    // =========================================================================

    /**
     * Use Vipps Testing Environment
     */
    public bool $test = true;

    /**
     * Vipps Test Client ID
     */
    public string $test_client_id;

    /**
     * Vipps Test Client Secret
     */
    public string $test_client_secret;

    /**
     * Vipps Test Subscription Key
     */
    public string $test_subscription_key;

    /**
     * Vipps Production Client ID
     */
    public string $prod_client_id;

    /**
     * Vipps Production Client Secret
     */
    public string $prod_client_secret;

    /**
     * Vipps Production Subscription Key
     */
    public string $prod_subscription_key;

    /**
     * Automatic return after login
     * https://developer.vippsmobilepay.com/vipps-login-api/blob/master/vipps-login-api.md#automatic-return-from-vipps-app
     */
    public bool $login_automatic_return = false;

    /**
     * Request address
     */
    public bool $login_address = false;

    /**
     * Request birthDate
     */
    public bool $login_birthDate = false;

    /**
     * Request email
     */
    public bool $login_email = true;

    /**
     * Request name
     */
    public bool $login_name = true;

    /**
     * Request phoneNumber
     */
    public bool $login_phoneNumber = true;

    /**
     * Request nin (Norwegian National Identification Number)
     */
    public bool $login_nin = false;

    /**
     * Automatic return after continue
     * https://developer.vippsmobilepay.com/vipps-login-api/blob/master/vipps-login-api.md#automatic-return-from-vipps-app
     */
    public bool $continue_automatic_return = false;

    /**
     * Request address
     */
    public bool $continue_address = false;

    /**
     * Request birthDate
     */
    public bool $continue_birthDate = false;

    /**
     * Request email
     */
    public bool $continue_email = true;

    /**
     * Request name
     */
    public bool $continue_name = true;

    /**
     * Request phoneNumber
     */
    public bool $continue_phoneNumber = true;

    /**
     * Request nin (Norwegian National Identification Number)
     */
    public bool $continue_nin = false;

    /**
     * Custom verification template path
     */
    public string $verify_template = '';

    // Public Methods
    // =========================================================================

    public function scenarios(): array
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

    public function rules(): array
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

    public function beforeValidate(): bool
    {
        if($this->test == 1) $this->scenario = 'testMode';
        else $this->scenario = 'productionMode';

        return parent::beforeValidate();
    }

    public function attributeLabels(): array
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

    public function inProduction() : bool
    {
        return !$this->inTest();
    }

    public function inTest() : bool
    {
        return $this->test == 1;
    }

    public function loginAutomaticReturn() : bool
    {
        return $this->login_automatic_return == 1;
    }

    public function continueAutomaticReturn() : bool
    {
        return $this->continue_automatic_return == 1;
    }

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