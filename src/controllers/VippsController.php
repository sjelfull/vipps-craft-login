<?php


namespace vippsas\login\controllers;

use Craft;
use craft\elements\User;
use craft\records\User as UserRecord;
use craft\web\Controller;
use GuzzleHttp\Exception\ClientException;
use vippsas\login\events\ConnectEvent;
use vippsas\login\events\ContinueEvent;
use vippsas\login\events\LoggedInEvent;
use vippsas\login\events\RegisterEvent;
use vippsas\login\exceptions\CreateUserException;
use vippsas\login\exceptions\VerifiedEmailRequiredException;
use vippsas\login\models\ConfirmPasswordForm;
use vippsas\login\models\Session;
use vippsas\login\records\User as VippsUser;
use yii\web\Response;
use vippsas\login\VippsLogin;
//use craft\commerce\records\Country;

class VippsController extends Controller
{
    /**
     * Allow guest users to access these actions
     * @var array
     */
    protected $allowAnonymous = [
        'login',
        'continue',
        'forget',
        'verify'
    ];

    /**
     * Catches the user when returned from Vipps
     * with the Continue option
     * @return Response
     * @throws CreateUserException
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     */
    public function actionLogin()
    {
        $get = Craft::$app->request->get();

        if(!Craft::$app->user->isGuest)
        {
            Craft::$app->session->setFlash('warning', Craft::t('vipps-login', 'You\'re already logged in as {name}.', [
                'name' => Craft::$app->user->identity->friendlyName,
            ]));
            return $this->goBack();
        }

        if($session = $this->setSessionFromLoginResponse($get))
        {
            if(!$session->isEmailVerified()) throw new VerifiedEmailRequiredException('You need to have a vipps-verified email to use this feature.');

            $vipps_user = VippsUser::findOne($session->getSub());
            if($vipps_user && $user = $vipps_user->getUser())
            {
                $this->update($user, $session);
                $this->login($user, $session);
            }
            else
            {
                $user = User::findOne(['email' => $session->getEmail()]);
                if($user)
                {
                    return $this->redirect('vipps/login/verify');
                }
                else
                {
                    $userSettings = Craft::$app->getProjectConfig()->get('users');
                    if(!isset($userSettings['allowPublicRegistration']) || $userSettings['allowPublicRegistration'] !== true)
                    {
                        Craft::$app->session->setFlash('warning', Craft::t('vipps-login', 'You are not registered with this site. New registrations is currently turned off.'));
                    }
                    else
                    {
                        if(!$this->createUser($session)) throw new CreateUserException('Unable to create a new user');
                    }
                }
            }
        }

        return $this->goBack();
    }

    /**
     * Catches the user when returned from Vipps
     * with the Continue option
     *
     * @return Response
     */
    public function actionContinue()
    {
        $get = Craft::$app->request->get();

        $this->setSessionFromContinueResponse($get);

        return $this->goBack();
    }

    /**
     * Verify users password to connect an existing
     * user to the vipps account
     *
     * @return Response
     */
    public function actionVerify()
    {
        $session = unserialize(Craft::$app->session->get('vipps_login'));
        if(!$session)
        {
            Craft::$app->session->setFlash('danger', Craft::t('vipps-login', 'Session expired. Please log in again.'));
            return $this->goHome();
        }

        $form = new ConfirmPasswordForm();

        if($form->load(Craft::$app->request->post()))
        {
            $userRecord = UserRecord::findOne(['email' => $session->getEmail()]);

            if(Craft::$app->getSecurity()->validatePassword($form->password, $userRecord->password))
            {
                $user = User::findOne(['email' => $session->getEmail()]);
                $this->connect($user, $session);
                VippsLogin::getInstance()->trigger(
                    VippsLogin::EVENT_USER_CONNECTED_ACCOUNT,
                    (new ConnectEvent())->setUser($user)->setSession($session)
                );
                $this->login($user, $session);
                return $this->goBack();
            }
            else
            {
                $form->addError('password', Craft::t('vipps-login', 'Invalid password'));
            }
        }


        $vippsConfig = VippsLogin::getInstance()->getSettings();

        if(strlen($vippsConfig->verify_template) > 0)
        {
            return $this->renderTemplate($vippsConfig->verify_template, ['form' => $form]);
        }
        return $this->renderTemplate('vipps-login/verify', ['form' => $form]);
    }

    /**
     * Forgets the users Vipps-session from the PHP session
     *
     * @return Response
     */
    public function actionForget()
    {
        Craft::$app->session->remove('vipps_login');
        return $this->goBack();
    }

    /**
     * @param $get
     * @return bool|Session
     */
    private function setSessionFromContinueResponse($get)
    {
        if(isset($get['error']))
        {
            if(isset($get['error_description'])) Craft::$app->session->setFlash('danger', $get['error_description']);
            else Craft::$app->session->setFlash('danger', Craft::t('vipps-login', 'Something went wrong while logging you in. Please try again, if the error persists contact the site administrator.'));
        }
        else
        {
            try {
                /* @var $response \Psr\Http\Message\ResponseInterface */
                $response = VippsLogin::getInstance()->vippsLogin->getNewContinueToken($get['code'], $get['state']);
                $res_obj = \GuzzleHttp\json_decode($response->getBody()->getContents());

                if(is_object($res_obj) && isset($res_obj->access_token))
                {
                    $session = new Session($res_obj);

                    if(!$session->isEmailVerified()) throw new VerifiedEmailRequiredException('You need to have a vipps-verified email to use this feature.');

                    Craft::$app->session->set('vipps_login', serialize($session));
                    Craft::$app->session->setFlash('success', Craft::t('vipps-login', 'You are now logged in'));
                    VippsLogin::getInstance()->trigger(
                        VippsLogin::EVENT_USER_CONTINUED,
                        (new ContinueEvent())->setSession($session)
                    );
                    return $session;
                }
                else
                {
                    Craft::$app->session->setFlash('danger', Craft::t('vipps-login', 'Login Failed: Invalid response from Vipps'));
                }
            } catch (ClientException $e) {
                Craft::$app->session->setFlash('danger', Craft::t('vipps-login', 'Login Failed: ' . $e->getResponse()->getBody()->getContents()));
            } catch (\Exception $e) {
                Craft::$app->session->setFlash('danger', Craft::t('vipps-login', 'Login Failed: ' . $e->getMessage()));
            }
        }
        return false;
    }

    /**
     * @param $get
     * @return bool|Session
     */
    private function setSessionFromLoginResponse($get)
    {
        if(isset($get['error']))
        {
            if(isset($get['error_description'])) Craft::$app->session->setFlash('danger', $get['error_description']);
            else Craft::$app->session->setFlash('danger', Craft::t('vipps-login', 'Something went wrong while logging you in. Please try again, if the error persists contact the site administrator.'));
        }
        else
        {
            try {
                /* @var $response \Psr\Http\Message\ResponseInterface */
                $response = VippsLogin::getInstance()->vippsLogin->getNewLoginToken($get['code'], $get['state']);
                $res_obj = \GuzzleHttp\json_decode($response->getBody()->getContents());

                if(is_object($res_obj) && isset($res_obj->access_token))
                {
                    $session = new Session($res_obj);
                    Craft::$app->session->set('vipps_login', serialize($session));
                    return $session;
                }
                else
                {
                    Craft::$app->session->setFlash('danger', Craft::t('vipps-login', 'Login Failed: Invalid response from Vipps'));
                }
            } catch (ClientException $e) {
                Craft::$app->session->setFlash('danger', Craft::t('vipps-login', 'Login Failed: ' . $e->getResponse()->getBody()->getContents()));
            } catch (\Exception $e) {
                Craft::$app->session->setFlash('danger', Craft::t('vipps-login', 'Login Failed:' . $e->getMessage()));
            }
        }
        return false;
    }

    /**
     * @param Session $session
     * @return bool
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     */
    private function createUser(Session $session)
    {
        $user = new User();

        $user->email = $session->getEmail();

        // Use email as username
        $user->username = $user->email;

        // Set long random password
        $user->newPassword = Craft::$app->security->generateRandomString(32);

        if (
            !$user->validate(null, false) ||
            !Craft::$app->getElements()->saveElement($user, false)
        ) {
            Craft::info('User not saved due to validation error.', __METHOD__);

            return false;
        }

        // Assign the user to the default group
        Craft::$app->getUsers()->assignUserToDefaultGroup($user);

        // Activate them
        Craft::$app->getUsers()->activateUser($user);

        $this->connect($user, $session);

        VippsLogin::getInstance()->trigger(
            VippsLogin::EVENT_USER_CREATED,
            (new RegisterEvent())->setUser($user)->setSession($session)
        );

        $this->update($user, $session);

        $this->login($user, $session);

        return true;
    }

    private function login(User $user, Session $session)
    {
        $generalConfig = Craft::$app->getConfig()->getGeneral();
        VippsLogin::getInstance()->trigger(
            VippsLogin::EVENT_USER_LOGGED_IN,
            (new LoggedInEvent())->setUser($user)->setSession($session)
        );
        Craft::$app->getUser()->login($user, $generalConfig->userSessionDuration);
    }

    private function connect(User $user, Session $session)
    {
        $vippsUser = new VippsUser();
        $vippsUser->user_id = $user->id;
        $vippsUser->vipps_sub = $session->getSub();
        return $vippsUser->save();
    }

    private function update(User $user, Session $session)
    {
        $user->firstName = $session->getGivenName();
        $user->lastName = $session->getFamilyName();

        // Beginning of Commerce Address Implementation
        /*
        if(Craft::$app->plugins->getPlugin('commerce', false))
        {
            $addresses = $session->getAddresses();
            if(is_array($addresses))
            {
                foreach ($addresses as $address)
                {
                    if(\craft\commerce\records\Address::findOne(['']))
                    $addr = new \craft\commerce\records\Address();

                    $country = Country::findOne(['iso' => $address->country]);
                    $addr->label = $address->address_type ?? null;
                    $addr->firstName = $session->getGivenName();
                    $addr->lastName = $session->getFamilyName();
                    $addr->countryId = $country ? $country->id : null;
                    $addr->address1 = $address->street_address ?? null;
                    $addr->zipCode = $address->postal_code ?? null;
                    $addr->city = $address->region ?? null;
                    $addr->save();
                }
                die(var_dump($address));
            }
        }
        */

        Craft::$app->getElements()->saveElement($user, false);

        return $user;
    }
}