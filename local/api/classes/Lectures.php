<?php

namespace Legacy\API;

use Bitrix\Main\Loader;
use CIBlockElement;
use CFile;
use Legacy\General\Constants;
use Legacy\Iblock\LecturesTable; // Подключаем модель

class Lectures
{
    private static function getFullUrl($relativePath)
    {
        if (!$relativePath) return null;
        if (strpos($relativePath, 'http') === 0) return $relativePath;
        $protocol = (\CMain::IsHTTPS() ? 'https://' : 'http://');
        $host = $_SERVER['HTTP_HOST'];
        return $protocol . $host . $relativePath;
    }

    public static function listByCourse($arRequest)
    {
        Loader::includeModule('iblock');
        $courseId = $arRequest['courseId'] ?? 0;

        $resultData = [];
        // Фильтрация по свойствам через CIBlockElement надежнее без ID свойств
        $rsLectures = CIBlockElement::GetList(
            ['PROPERTY_ORDER' => 'ASC'],
            [
                'IBLOCK_ID' => Constants::IB_LECTURES,
                'PROPERTY_COURSE' => $courseId,
                'PROPERTY_VISIBILITY_VALUE' => 'Открыт',
                'ACTIVE' => 'Y'
            ],
            false,
            false,
            ['ID', 'NAME', 'DETAIL_TEXT', 'PROPERTY_ORDER']
        );

        while ($lec = $rsLectures->Fetch()) {
            $resultData[] = [
                'id' => $lec['ID'],
                'name' => $lec['NAME'],
                'description' => $lec['DETAIL_TEXT'], // Используем Детальное описание как Description
                'order' => $lec['PROPERTY_ORDER_VALUE']
            ];
        }

        return $resultData;
    }

    public static function get($arRequest)
    {
        Loader::includeModule('iblock');
        $lectureId = $arRequest['lectureId'] ?? 0;

        $rsLec = CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => Constants::IB_LECTURES, 'ID' => $lectureId, 'ACTIVE' => 'Y'],
            false, false,
            [
                'ID', 'NAME', 'DETAIL_TEXT',
                'PROPERTY_TYPE',
                'PROPERTY_LECTURE_TEXT',
                'PROPERTY_LECTURE_FILE',
                'PROPERTY_LECTURE_LINK',
                'PROPERTY_ORDER'
            ]
        );

        $lec = $rsLec->Fetch();
        if (!$lec) {
            return Auth::errorResponse(404, 'Lecture not found');
        }

        $type = $lec['PROPERTY_TYPE_VALUE'];
        $content = null;
        $fileUrl = null;

        if ($type == 'ТЕКСТ') {
            $content = $lec['PROPERTY_LECTURE_TEXT_VALUE']['TEXT'] ?? $lec['PROPERTY_LECTURE_TEXT_VALUE'];
        } elseif ($type == 'ФАЙЛ') {
            $relativePath = CFile::GetPath($lec['PROPERTY_LECTURE_FILE_VALUE']);
            $fileUrl = self::getFullUrl($relativePath);
        } elseif ($type == 'ССЫЛКА') {
            $fileUrl = $lec['PROPERTY_LECTURE_LINK_VALUE'];
        }

        // Материалы
        $materials = [];
        $rsMat = CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => Constants::IB_MATERIALS, 'PROPERTY_LINK_PARENT' => $lectureId, 'ACTIVE' => 'Y'],
            false, false,
            ['ID', 'NAME', 'PROPERTY_TYPE', 'PROPERTY_FILE', 'PROPERTY_LINK']
        );

        while ($mat = $rsMat->Fetch()) {
            $matType = $mat['PROPERTY_TYPE_VALUE'];
            $matUrl = '';

            if ($matType == 'ФАЙЛ') {
                $relativePath = CFile::GetPath($mat['PROPERTY_FILE_VALUE']);
                $matUrl = self::getFullUrl($relativePath);
            } else {
                $matUrl = $mat['PROPERTY_LINK_VALUE'];
            }

            $materials[] = [
                'id' => $mat['ID'],
                'name' => $mat['NAME'],
                'type' => $matType,
                'fileUrl' => $matUrl
            ];
        }

        return [
            'id' => $lec['ID'],
            'name' => $lec['NAME'],
            'description' => $lec['DETAIL_TEXT'],
            'order' => $lec['PROPERTY_ORDER_VALUE'],
            'type' => $type,
            'content' => $content,
            'fileUrl' => $fileUrl,
            'materials' => $materials
        ];
    }
}