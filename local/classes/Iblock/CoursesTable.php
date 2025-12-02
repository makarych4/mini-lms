<?php

namespace Legacy\Iblock;

use Legacy\General\Constants;
use Bitrix\Iblock\ElementTable;

class CoursesTable extends ElementTable
{
    public static function setDefaultScope($query)
    {
        $query
            ->where('IBLOCK_ID', Constants::IB_COURSES)
            ->where('ACTIVE', true);
    }
}