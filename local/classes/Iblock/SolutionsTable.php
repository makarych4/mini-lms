<?php

namespace Legacy\Iblock;

use Legacy\General\Constants;
use Bitrix\Iblock\ElementTable;

class SolutionsTable extends ElementTable
{
    public static function setDefaultScope($query)
    {
        $query
            ->where('IBLOCK_ID', Constants::IB_SOLUTIONS)
            ->where('ACTIVE', true);
    }
}