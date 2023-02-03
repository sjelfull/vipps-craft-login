<?php


namespace vippsas\login\controllers;

use Craft;
use craft\web\Controller;
use vippsas\login\VippsLogin;
use craft\helpers\StringHelper;
use yii\web\Response;

class RedirectController extends Controller
{
    protected array|int|bool $allowAnonymous = [
        'login',
        'continue'
    ];

    public function actionLogin(): Response
    {
        $returnUrl = $this->setReturnUrl();
        return $this->redirect(VippsLogin::getInstance()->vippsLogin->getLoginUrl($returnUrl));
    }

    public function actionContinue(): Response
    {
        $returnUrl = $this->setReturnUrl();
        return $this->redirect(VippsLogin::getInstance()->vippsLogin->getContinueUrl($returnUrl));
    }

    private function setReturnUrl(): string|null
    {
        $r = Craft::$app->request->get('r');
        if(is_string($r) && strlen($r) > 0)
        {
            $url = StringHelper::base64UrlDecode($r);
            if($url) {
                Craft::$app->user->setReturnUrl($url);
                return $url;
            }
        }
        return null;
    }
}