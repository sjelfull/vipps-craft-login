<?php


namespace vippsas\login\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;
use vippsas\login\VippsLogin;
use yii\web\NotFoundHttpException;

class SettingsController extends Controller
{
    /**
     * Plugin settings
     *
     * @return Response The rendered result
     */
    public function actionIndex() : Response
    {
        $plugin = VippsLogin::getInstance();

        $variables = [];
        $variables['fullPageForm'] = true;
        $variables['pluginName'] = $plugin::PLUGIN_NAME;
        $variables['title'] = Craft::t('vipps-login', 'Vipps Settings');
        $variables['settings'] = $plugin->getSettings();
        $variables['userSettings'] = Craft::$app->getProjectConfig()->get('users') ?? [];

        return $this->renderTemplate('vipps-login/settings/settings', $variables);
    }

    /**
     * Saves a plugin’s settings.
     *
     * @return Response|null
     * @throws NotFoundHttpException if the requested plugin cannot be found
     * @throws \yii\web\BadRequestHttpException
     * @throws \craft\errors\MissingComponentException
     */
    public function actionSavePluginSettings() : Response
    {
        $this->requirePostRequest();

        $settings = Craft::$app->getRequest()->getBodyParam('settings', []);
        $plugin = Craft::$app->getPlugins()->getPlugin('vipps-login');

        if ($plugin === null) {
            throw new NotFoundHttpException('Plugin not found');
        }

        if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $settings)) {
            Craft::$app->getSession()->setError(Craft::t('app', "Couldn't save plugin settings."));

            // Send the plugin back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'plugin' => $plugin,
            ]);

            $response = new Response();
            $response->setStatusCode(500);
            $response->format = Response::FORMAT_JSON;
            $response->content = [
                'error' => Craft::t('app', "Couldn't save plugin settings.")
            ];
            return $response;
        }

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Plugin settings saved.'));
        Craft::$app->cache->delete('vipps-login-openid-configuration');

        return $this->redirectToPostedUrl();
    }
}