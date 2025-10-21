<?php

namespace Legacy\Iblock;

use Legacy\General\Constants;
use Bitrix\Main\Entity\Query;
use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\ElementPropertyTable;
use Bitrix\Main\DB\SqlExpression;

class TestsTable extends \Bitrix\Iblock\ElementTable
{
    public static function setDefaultScope($query)
    {
        $query
            ->where('IBLOCK_ID', Constants::IB_TESTS)
            ->where('ACTIVE', true);
    }

    public static function withSelect(Query $query)
    {
        $query->registerRuntimeField(
            'PROPERTY_T',
            new ReferenceField(
                'PROPERTY_T',
                ElementPropertyTable::class,
                [
                    'this.ID' => 'ref.IBLOCK_ELEMENT_ID',
                    'ref.IBLOCK_PROPERTY_ID' => new SqlExpression('?', Constants::IB_PROP_TESTS_PROPERTY_T),
                ]
            )
        );

        $query->setSelect([
            'ID',
            'NAME',
            'CODE',
            'PROPERTY_T_VALUE' => 'PROPERTY_T.VALUE',
        ]);
    }
}
