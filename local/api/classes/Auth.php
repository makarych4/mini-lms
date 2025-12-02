<?php

namespace Legacy\API;

use CUser;

class Auth
{
    public static function login($arRequest)
    {
        global $USER;

        $login = $arRequest['login'] ?? null;
        $password = $arRequest['password'] ?? null;

        if (!$login || !$password) {
            return self::errorResponse(1, 'Login and password are required');
        }

        $authResult = $USER->Login($login, $password, 'Y');

        if ($authResult === true) {
            return [
                'message' => 'Successfully authenticated'
            ];
        } else {
            return self::errorResponse(2, 'Invalid login or password');
        }
    }

    public static function logout($arRequest)
    {
        global $USER;
        $USER->Logout();

        return [
            'message' => 'Successfully logged out'
        ];
    }
    public static function errorResponse($code, $message)
    {
        return null;
    }
}