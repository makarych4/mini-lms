<?php

namespace Legacy\API;

use Bitrix\Main\Loader;
use CIBlockElement;
use CIBlockPropertyEnum;
use CFile;
use Legacy\General\Constants;
use Legacy\Iblock\SolutionsTable;

class Solutions
{
    private static function getEnumIdByValue($iblockId, $code, $value)
    {
        $propertyEnum = CIBlockPropertyEnum::GetList(
            [],
            ['IBLOCK_ID' => $iblockId, 'CODE' => $code, 'VALUE' => $value]
        );
        if ($enum = $propertyEnum->Fetch()) {
            return $enum['ID'];
        }
        return false;
    }

    private static function getFullUrl($relativePath)
    {
        if (!$relativePath) return null;
        if (strpos($relativePath, 'http') === 0) return $relativePath;
        $protocol = (\CMain::IsHTTPS() ? 'https://' : 'http://');
        $host = $_SERVER['HTTP_HOST'];
        return $protocol . $host . $relativePath;
    }

    public static function getByTask($arRequest)
    {
        global $USER;
        Loader::includeModule('iblock');

        $taskId = $arRequest['taskId'] ?? 0;
        $userId = $USER->GetID();

        if (!$userId) return Auth::errorResponse(401, 'Auth required');

        $rsSol = CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => Constants::IB_SOLUTIONS,
                'PROPERTY_TASK' => $taskId,
                'PROPERTY_STUDENT' => $userId,
                'ACTIVE' => 'Y'
            ],
            false, false,
            [
                'ID', 'PROPERTY_TYPE', 'PROPERTY_SOLUTION_FILE',
                'PROPERTY_SOLUTION_LINK', 'PROPERTY_SOLUTION_TEXT',
                'PROPERTY_STATUS', 'PROPERTY_SCORE', 'PROPERTY_TEACHER_COMMENT'
            ]
        );

        $sol = $rsSol->Fetch();

        if (!$sol) {
            return null;
        }

        $type = $sol['PROPERTY_TYPE_VALUE'];
        $fileUrl = null;

        if ($type == 'ФАЙЛ') {
            $relativePath = CFile::GetPath($sol['PROPERTY_SOLUTION_FILE_VALUE']);
            $fileUrl = self::getFullUrl($relativePath);
        } elseif ($type == 'ССЫЛКА') {
            $fileUrl = $sol['PROPERTY_SOLUTION_LINK_VALUE'];
        }

        // Материалы
        $materials = [];
        $rsMat = CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => Constants::IB_MATERIALS, 'PROPERTY_LINK_PARENT' => $sol['ID']],
            false, false, ['ID', 'NAME', 'PROPERTY_TYPE', 'PROPERTY_FILE', 'PROPERTY_LINK']
        );
        while ($mat = $rsMat->Fetch()) {
            $matType = $mat['PROPERTY_TYPE_VALUE'];
            $mUrl = '';
            if ($matType == 'ФАЙЛ') {
                $relativePath = CFile::GetPath($mat['PROPERTY_FILE_VALUE']);
                $mUrl = self::getFullUrl($relativePath);
            } else {
                $mUrl = $mat['PROPERTY_LINK_VALUE'];
            }
            $materials[] = ['type' => $matType, 'fileUrl' => $mUrl, 'name' => $mat['NAME']];
        }

        return [
            'id' => $sol['ID'],
            'type' => $type,
            'fileUrl' => $fileUrl,
            'status' => $sol['PROPERTY_STATUS_VALUE'],
            'score' => $sol['PROPERTY_SCORE_VALUE'],
            'teacherComment' => $sol['PROPERTY_TEACHER_COMMENT_VALUE']['TEXT'] ?? '',
            'materials' => $materials
        ];
    }

    public static function add($arRequest)
    {
        global $USER;
        Loader::includeModule('iblock');

        $taskId = $_POST['taskId'] ?? 0;
        $userId = $USER->GetID();

        if (!$userId) return Auth::errorResponse(401, 'Auth required');

        // 1. Проверяем ALLOW_EDIT
        $rsTask = CIBlockElement::GetList([], ['IBLOCK_ID' => Constants::IB_TASKS, 'ID' => $taskId], false, false, ['PROPERTY_ALLOW_EDIT']);
        $task = $rsTask->Fetch();
        $allowEdit = ($task['PROPERTY_ALLOW_EDIT_VALUE'] == 'Да');

        // Ищем старое решение
        $rsOldSol = CIBlockElement::GetList([], ['IBLOCK_ID' => Constants::IB_SOLUTIONS, 'PROPERTY_TASK' => $taskId, 'PROPERTY_STUDENT' => $userId], false, false, ['ID']);
        $oldSol = $rsOldSol->Fetch();

        if ($oldSol && !$allowEdit) {
            return Auth::errorResponse(403, 'Editing not allowed');
        }

        // 2. Подготовка данных
        $typeString = $_POST['type'];
        $typeEnumId = self::getEnumIdByValue(Constants::IB_SOLUTIONS, 'TYPE', $typeString);
        $statusEnumId = self::getEnumIdByValue(Constants::IB_SOLUTIONS, 'STATUS', 'Проверяется');
        $submitDate = ConvertTimeStamp(time(), "FULL");

        $props = [
            'TASK' => $taskId,
            'STUDENT' => $userId,
            'TYPE' => $typeEnumId,
            'SUBMIT_DATE' => $submitDate,
            'STATUS' => $statusEnumId
        ];

        if ($typeString == 'ТЕКСТ') {
            $props['SOLUTION_TEXT'] = $_POST['content'];
            $props['SOLUTION_LINK'] = false;
        } elseif ($typeString == 'ССЫЛКА') {
            $props['SOLUTION_LINK'] = $_POST['content'];
            $props['SOLUTION_TEXT'] = false;
        } elseif ($typeString == 'ФАЙЛ') {
            if (!empty($_FILES['content'])) {
                $props['SOLUTION_FILE'] = $_FILES['content'];
            }
            $props['SOLUTION_TEXT'] = false;
            $props['SOLUTION_LINK'] = false;
        }

        $el = new CIBlockElement;
        $solId = 0;

        if ($oldSol) {
            // Обновление
            $solId = $oldSol['ID'];
            CIBlockElement::SetPropertyValuesEx($solId, Constants::IB_SOLUTIONS, $props);

            // Удаление старых материалов
            $rsOldMats = CIBlockElement::GetList(
                [],
                ['IBLOCK_ID' => Constants::IB_MATERIALS, 'PROPERTY_LINK_PARENT' => $solId],
                false, false, ['ID']
            );
            while($om = $rsOldMats->Fetch()) {
                CIBlockElement::Delete($om['ID']);
            }
        } else {
            // Создание
            $solId = $el->Add([
                'IBLOCK_ID' => Constants::IB_SOLUTIONS,
                'NAME' => "Решение студента $userId к задаче $taskId",
                'ACTIVE' => 'Y',
                'PROPERTY_VALUES' => $props
            ]);
        }

        if (!$solId) {
            return Auth::errorResponse(500, $el->LAST_ERROR);
        }

        // 3. Обработка новых материалов
        $materialsPost = $_POST['materials'] ?? [];
        $materialsFiles = $_FILES['materials'] ?? [];

        foreach ($materialsPost as $index => $matData) {
            $mTypeString = $matData['type'];
            $mVal = $matData['value'] ?? '';
            $mTypeEnumId = self::getEnumIdByValue(Constants::IB_MATERIALS, 'TYPE', $mTypeString);

            $fileArray = null;
            if ($mTypeString == 'ФАЙЛ' && !empty($materialsFiles['name'][$index]['file'])) {
                $fileArray = [
                    'name'     => $materialsFiles['name'][$index]['file'],
                    'type'     => $materialsFiles['type'][$index]['file'],
                    'tmp_name' => $materialsFiles['tmp_name'][$index]['file'],
                    'error'    => $materialsFiles['error'][$index]['file'],
                    'size'     => $materialsFiles['size'][$index]['file'],
                ];
            }

            $el->Add([
                'IBLOCK_ID' => Constants::IB_MATERIALS,
                'NAME' => "Доп. материал к решению $solId",
                'PROPERTY_VALUES' => [
                    'LINK_PARENT' => $solId,
                    'TYPE' => $mTypeEnumId,
                    'LINK' => ($mTypeString == 'ССЫЛКА') ? $mVal : '',
                    'FILE' => $fileArray
                ]
            ]);
        }

        return ['id' => $solId, 'message' => 'Решение отправлено'];
    }
}