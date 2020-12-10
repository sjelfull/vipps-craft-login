<?php
namespace vippsas\login;

use Craft;
use vippsas\login\events\ConnectEvent;
use vippsas\login\events\ContinueEvent;
use vippsas\login\events\LoggedInEvent;
use vippsas\login\events\RegisterEvent;
use yii\base\Event;
use craft\base\Plugin;
use craft\web\twig\variables\CraftVariable;
use vippsas\login\services\Login;
use vippsas\login\models\Settings;
use craft\web\UrlManager;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\web\View;
use craft\events\RegisterTemplateRootsEvent;

class VippsLogin extends Plugin
{
    // Constants
    // =========================================================================

    /**
     * Plugin name
     *
     * @var string
     */
    const PLUGIN_NAME = 'Vipps Login';

    /**
     * Plugin name
     *
     * @var string
     */
    const SUB_DATABASE_TABLE = '{{%vippsusers}}';

    // Events
    // =========================================================================

    /**
     * User logged in event
     */
    const EVENT_USER_LOGGED_IN = 'eventUserLoggedIn';

    /**
     * User created event
     */
    const EVENT_USER_CREATED = 'eventUserCreated';

    /**
     * User continued event
     */
    const EVENT_USER_CONTINUED = 'eventUserContinued';

    /**
     * User connected account event
     */
    const EVENT_USER_CONNECTED_ACCOUNT = 'eventUserConnectedAccount';

    // Properties
    // =========================================================================

    /**
     * Enable Settings
     *
     * @var bool
     */
    public $hasCpSettings = true;

    // Public Methods
    // =========================================================================

    /**
     * Plugin Initiator
     *
     * @return void
     */
    public function init() : void
    {
        parent::init();

        $this->_registerSiteUrlRules();
        $this->_registerCpUrlRules();
        $this->_registerService();
        $this->_registerEventHandlers();

        Event::on(View::class, View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS, function (RegisterTemplateRootsEvent $event) {
            $event->roots['vipps-login'] = __DIR__ . '/templates';
        });
        Craft::info(
            Craft::t(
                'vipps-login',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    /**
     * @inheritdoc
     */
    public function getSettingsResponse()
    {
        Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('vipps-login/settings'));
    }

    // Protected Methods
    // =========================================================================

    /**
     * Returns the plugin settings model
     *
     * @return Settings
     */
    protected function createSettingsModel() : Settings
    {
        return new Settings();
    }

    // Private Methods
    // =========================================================================

    /**
     * Register site url rules
     */
    private function _registerSiteUrlRules()
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['vipps/asset/button/<file>'] = 'vipps-login/asset/button';
                $event->rules['vipps/login'] = 'vipps-login/vipps/login';
                $event->rules['vipps/login/continue'] = 'vipps-login/vipps/continue';
                $event->rules['vipps/login/forget'] = 'vipps-login/vipps/forget';
                $event->rules['vipps/login/verify'] = 'vipps-login/vipps/verify';
                $event->rules['vipps/redirect/login'] = 'vipps-login/redirect/login';
                $event->rules['vipps/redirect/continue'] = 'vipps-login/redirect/continue';
            }
        );
    }

    /**
     * Register cp url rules
     */
    private function _registerCpUrlRules()
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['vipps-login/settings'] = 'vipps-login/settings';
            }
        );
    }

    /**
     * Register the vippsLogin Service
     */
    private function _registerService()
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $e) {
            $e->sender->set('vippsLogin', Login::class);
        });

        $this->setComponents([
            'vippsLogin' => Login::class,
        ]);
    }

    /**
     * Registers handlers to events
     */
    private function _registerEventHandlers()
    {
        Event::on(
            self::class,
            self::EVENT_USER_CREATED,
            function(RegisterEvent $e) {
                $this->_logVippsEvent(
                    "{$e->getUser()->name} ({$e->getUser()->email}) registered with vipps {$e->getSession()->getSub()}",
                    __FUNCTION__,
                    __METHOD__
                );
            }
        );
        Event::on(
            self::class,
            self::EVENT_USER_LOGGED_IN,
            function(LoggedInEvent $e) {
                $this->_logVippsEvent(
                    "{$e->getUser()->name} ({$e->getUser()->email}) logged in with vipps {$e->getSession()->getSub()}",
                    __FUNCTION__,
                    __METHOD__
                );
            }
        );
        Event::on(
            self::class,
            self::EVENT_USER_CONTINUED,
            function(ContinueEvent $e) {
                $this->_logVippsEvent(
                    "{$e->getSession()->getName()} ({$e->getSession()->getEmail()}) continued with vipps {$e->getSession()->getSub()}",
                    __FUNCTION__,
                    __METHOD__
                );
            }
        );
        Event::on(
            self::class,
            self::EVENT_USER_CONNECTED_ACCOUNT,
            function(ConnectEvent $e) {
                $this->_logVippsEvent(
                    "{$e->getUser()->name} ({$e->getUser()->email}) connected vipps account {$e->getSession()->getSub()}",
                    __FUNCTION__,
                    __METHOD__
                );
            }
        );
    }

    /**
     * Log a vipps event to the runtime log
     * @param string $message
     * @param string $function
     * @param string $method
     */
    private function _logVippsEvent(string $message, string $function, string $method)
    {
        //Craft::$app->deprecator->log('vipps-craft', $message); // Used for testing
        Craft::info(
            Craft::t(
                'vipps-login',
                '{event} triggered',
                ['event' => $function]
            ),
            $method
        );
    }
}