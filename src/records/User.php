<?php


namespace vippsas\login\records;

use craft\db\ActiveRecord;
use vippsas\login\VippsLogin;

class User extends ActiveRecord
{
    public function fields(): array
    {
        return [
            'vipps_sub',
            'user_id'
        ];
    }

    public static function tableName(): string
    {
        return VippsLogin::SUB_DATABASE_TABLE;
    }

    public function getUser(): \craft\elements\User|null
    {
        return \craft\elements\User::findOne(['id' => $this->user_id]);
    }
}