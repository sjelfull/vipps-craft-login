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
        $returnUrl = $this->setReturnUrl();
        return $this->redirect(VippsLogin::getInstance()->vippsLogin->getLoginUrl($returnUrl));
    }

    public function actionContinue()
    {
        $returnUrl = $this->setReturnUrl();
        return $this->redirect(VippsLogin::getInstance()->vippsLogin->getContinueUrl($returnUrl));
    }

    private function setReturnUrl()
    {
        $r = \Craft::$app->request->get('r');
        if(is_string($r) && strlen($r) > 0)
        {
            $url = StringHelper::base64UrlDecode($r);
            if($url) {
                \Craft::$app->user->setReturnUrl($url);
                return $url;
            }
        }
        return null;
    }
}