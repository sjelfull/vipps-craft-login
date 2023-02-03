<?php


namespace vippsas\login\events;


use craft\elements\User;
use vippsas\login\models\Session;
use yii\base\Event;

class BaseVippsEvent extends Event
{
    private User $user;
    private Session $session;

    public function setUser(User $user) : self
    {
        $this->user = $user;
        return $this;
    }

    public function setSession(Session $session) : self
    {
        $this->session = $session;
        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getSession(): Session
    {
        return $this->session;
    }
}