<?php

namespace Legacy\API;

use CUser;

class User
{
    public static function get($arRequest)
    {
        global $USER;

        if ($USER->IsAuthorized()) {
            $userId = $USER->GetID();
            $rsUser = CUser::GetByID($userId);
            $arUser = $rsUser->Fetch();

            return [
                'id' => $arUser['ID'],
                'login' => $arUser['LOGIN'],
                'email' => $arUser['EMAIL'],
                'firstName' => $arUser['NAME'],
                'lastName' => $arUser['LAST_NAME']
            ];
        } else {
            return [
                'message' => 'User not authenticated'
            ];
        }
    }
}