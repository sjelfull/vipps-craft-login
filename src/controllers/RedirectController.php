<?php


namespace vippsas\login\controllers;

use craft\web\Controller;
use vippsas\login\VippsLogin;

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
        return $this->redirect(VippsLogin::getInstance()->vippsLogin->getLoginUrl());
    }

    public function actionContinue()
    {
        return $this->redirect(VippsLogin::getInstance()->vippsLogin->getContinueUrl());
    }
}