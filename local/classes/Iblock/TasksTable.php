<?php

namespace Legacy\Iblock;

use Legacy\General\Constants;
use Bitrix\Iblock\ElementTable;

class TasksTable extends ElementTable
{
    public static function setDefaultScope($query)
    {
        $query
            ->where('IBLOCK_ID', Constants::IB_TASKS)
            ->where('ACTIVE', true);
    }
}