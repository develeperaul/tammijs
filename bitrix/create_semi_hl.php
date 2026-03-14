<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;

Loader::includeModule('highloadblock');

// Создаём HL-блок для состава полуфабрикатов
$name = 'SemiFinishedRecipes';
$tableName = 'semi_finished_recipes';

$existing = HighloadBlockTable::getList(['filter' => ['=NAME' => $name]])->fetch();
if (!$existing) {
    $result = HighloadBlockTable::add([
        'NAME' => $name,
        'TABLE_NAME' => $tableName,
    ]);
    
    if ($result->isSuccess()) {
        $hlId = $result->getId();
        echo "Создан HL-блок {$name} с ID: {$hlId}\n";
        
        // Добавляем поля
        $oUserType = new CUserTypeEntity();
        $entityId = "HLBLOCK_{$hlId}";
        
        // ID полуфабриката
        $oUserType->Add([
            'ENTITY_ID' => $entityId,
            'FIELD_NAME' => 'UF_SEMI_FINISHED_ID',
            'USER_TYPE_ID' => 'integer',
            'MANDATORY' => 'Y',
            'EDIT_FORM_LABEL' => ['ru' => 'ID полуфабриката'],
        ]);
        
        // ID ингредиента
        $oUserType->Add([
            'ENTITY_ID' => $entityId,
            'FIELD_NAME' => 'UF_INGREDIENT_ID',
            'USER_TYPE_ID' => 'integer',
            'MANDATORY' => 'Y',
            'EDIT_FORM_LABEL' => ['ru' => 'ID ингредиента'],
        ]);
        
        // Количество
        $oUserType->Add([
            'ENTITY_ID' => $entityId,
            'FIELD_NAME' => 'UF_QUANTITY',
            'USER_TYPE_ID' => 'double',
            'MANDATORY' => 'Y',
            'SETTINGS' => ['PRECISION' => 3],
            'EDIT_FORM_LABEL' => ['ru' => 'Количество'],
        ]);
        
        // Единица измерения
        $oUserType->Add([
            'ENTITY_ID' => $entityId,
            'FIELD_NAME' => 'UF_UNIT',
            'USER_TYPE_ID' => 'string',
            'MANDATORY' => 'Y',
            'EDIT_FORM_LABEL' => ['ru' => 'Единица измерения'],
        ]);
        
        echo "Поля добавлены\n";
    } else {
        echo "Ошибка: " . implode(', ', $result->getErrorMessages()) . "\n";
    }
} else {
    echo "HL-блок уже существует с ID: {$existing['ID']}\n";
}