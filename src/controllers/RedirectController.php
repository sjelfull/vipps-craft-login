<?php


namespace vippsas\login\controllers;

use craft\web\Controller;
use vippsas\login\VippsLogin;
use craft\helpers\StringHelper;

class RedirectController extends Controller
{
    /**
     * Allow guest users to access these actions
     * @var array
     */
    protected $allowAnonymous = [
        'login',
        'continue'
    ];

    public function actionLogin()
    {
        $this->setReturnUrl();
        return $this->redirect(VippsLogin::getInstance()->vippsLogin->getLoginUrl());
    }

    public function actionContinue()
    {
        $this->setReturnUrl();
        return $this->redirect(VippsLogin::getInstance()->vippsLogin->getContinueUrl());
    }

    private function setReturnUrl()
    {
        $r = \Craft::$app->request->get('r');
        if(is_string($r) && strlen($r) > 0)
        {
            $url = StringHelper::base64UrlDecode($r);
            if($url) \Craft::$app->user->setReturnUrl($url);
        }
    }
}