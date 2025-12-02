<?php

namespace Legacy\API;

use Bitrix\Main\Loader;
use CIBlockElement;
use Legacy\General\Constants;
use Legacy\Iblock\TasksTable;

class Tasks
{
    public static function getByLecture($arRequest)
    {
        Loader::includeModule('iblock');
        $lectureId = $arRequest['lectureId'] ?? 0;

        $rsTask = CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => Constants::IB_TASKS,
                'PROPERTY_LECTURE' => $lectureId,
                'PROPERTY_VISIBILITY_VALUE' => 'Открыт',
                'ACTIVE' => 'Y'
            ],
            false,
            false,
            [
                'ID', 'NAME', 'DETAIL_TEXT',
                'PROPERTY_END_DATE',
                'PROPERTY_MAX_SCORE',
                'PROPERTY_ALLOW_EDIT'
            ]
        );

        $task = $rsTask->Fetch();

        if (!$task) {
            return  null;
        }

        return [
            'id' => $task['ID'],
            'name' => $task['NAME'],
            'description' => $task['DETAIL_TEXT'],
            'endDate' => $task['PROPERTY_END_DATE_VALUE'],
            'maxScore' => $task['PROPERTY_MAX_SCORE_VALUE'],
            'allowEdit' => ($task['PROPERTY_ALLOW_EDIT_VALUE'] == 'Да')
        ];
    }
}