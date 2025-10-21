<?php

namespace Legacy\API;

use Bitrix\Main\Loader;
use Legacy\Iblock\TestsTable;

class Tests
{
    private static function processData($query)
    {
        $result = [];

        while ($arr = $query->fetch()) {
            $result[] = [
                'id' => $arr['ID'],
                'name' => $arr['NAME'],
                'code' => $arr['CODE'],
                'property_t' => $arr['PROPERTY_T_VALUE'],
            ];
        }

        return $result;
    }
    public static function get($arRequest)
    {
        $result = [];

        if (Loader::includeModule('iblock')) {
            $q = TestsTable::query()
                ->withSelect()
                ->exec();

            $result['items'] = self::processData($q);
        }

        return $result;
    }
}
