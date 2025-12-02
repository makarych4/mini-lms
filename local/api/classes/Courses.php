<?php

namespace Legacy\API;

use Bitrix\Main\Loader;
use CIBlockElement;
use Legacy\General\Constants;
use Legacy\Iblock\CoursesTable;

class Courses
{
    public static function userList($arRequest)
    {
        global $USER;
        Loader::includeModule('iblock');

        if (!$USER->IsAuthorized()) {
            return Auth::errorResponse(401, 'User not authenticated');
        }

        $userId = $USER->GetID();
        $courseIds = [];

        // 1. Получаем ID курсов из Enrollments
        $rsEnroll = CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => Constants::IB_ENROLLMENTS, 'PROPERTY_STUDENT' => $userId, 'ACTIVE' => 'Y'],
            false, false, ['PROPERTY_COURSE']
        );
        while ($enroll = $rsEnroll->Fetch()) {
            if ($enroll['PROPERTY_COURSE_VALUE']) {
                $courseIds[] = $enroll['PROPERTY_COURSE_VALUE'];
            }
        }

        if (empty($courseIds)) {
            return [];
        }

        // 2. Получаем данные курсов
        $resultData = [];
        $rsCourses = CIBlockElement::GetList(
            ['SORT' => 'ASC'],
            [
                'IBLOCK_ID' => Constants::IB_COURSES,
                'ID' => $courseIds,
                'ACTIVE' => 'Y',
                'PROPERTY_VISIBILITY_VALUE' => 'Открыт'
            ],
            false,
            false,
            ['ID', 'NAME', 'DETAIL_TEXT']
        );

        while ($course = $rsCourses->Fetch()) {
            $resultData[] = [
                'id' => $course['ID'],
                'name' => $course['NAME'],
                'description' => $course['DETAIL_TEXT']
            ];
        }

        return  $resultData;
    }
}