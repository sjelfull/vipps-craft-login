<?php


namespace vippsas\login\models;


use craft\base\Model;

class ConfirmPasswordForm extends Model
{
    public string $password;

    public function rules(): array
    {
        return [
            ['password', 'string']
        ];
    }
}