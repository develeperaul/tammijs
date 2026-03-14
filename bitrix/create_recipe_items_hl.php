<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;

Loader::includeModule('highloadblock');

$name = 'RecipeItems';
$tableName = 'recipe_items';

$existing = HighloadBlockTable::getList(['filter' => ['=NAME' => $name]])->fetch();
if ($existing) {
    echo "HL-блок уже существует с ID: {$existing['ID']}\n";
    exit;
}

$result = HighloadBlockTable::add([
    'NAME' => $name,
    'TABLE_NAME' => $tableName,
]);

if ($result->isSuccess()) {
    $hlId = $result->getId();
    echo "Создан HL-блок с ID: {$hlId}\n";
    
    $oUserType = new CUserTypeEntity();
    $entityId = "HLBLOCK_{$hlId}";
    
    // ID рецепта
    $oUserType->Add([
        'ENTITY_ID' => $entityId,
        'FIELD_NAME' => 'UF_RECIPE_ID',
        'USER_TYPE_ID' => 'integer',
        'MANDATORY' => 'Y',
        'EDIT_FORM_LABEL' => ['ru' => 'ID рецепта'],
    ]);
    
    // Тип элемента (ingredient/semi-finished)
    $oUserType->Add([
        'ENTITY_ID' => $entityId,
        'FIELD_NAME' => 'UF_ITEM_TYPE',
        'USER_TYPE_ID' => 'enumeration',
        'MANDATORY' => 'Y',
        'EDIT_FORM_LABEL' => ['ru' => 'Тип'],
        'SETTINGS' => ['DISPLAY' => 'LIST'],
    ]);
    
    // Добавляем значения для списка типов
    $enum = new CUserFieldEnum();
    $enum->SetEnumValues(
        $oUserType->GetList([], ['FIELD_NAME' => 'UF_ITEM_TYPE', 'ENTITY_ID' => $entityId])->Fetch()['ID'],
        [
            ['XML_ID' => 'ingredient', 'VALUE' => 'Ингредиент', 'SORT' => 10],
            ['XML_ID' => 'semi-finished', 'VALUE' => 'Полуфабрикат', 'SORT' => 20],
        ]
    );
    
    // ID элемента (ингредиента или полуфабриката)
    $oUserType->Add([
        'ENTITY_ID' => $entityId,
        'FIELD_NAME' => 'UF_ITEM_ID',
        'USER_TYPE_ID' => 'integer',
        'MANDATORY' => 'Y',
        'EDIT_FORM_LABEL' => ['ru' => 'ID элемента'],
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
    
    // Опциональный
    $oUserType->Add([
        'ENTITY_ID' => $entityId,
        'FIELD_NAME' => 'UF_IS_OPTIONAL',
        'USER_TYPE_ID' => 'boolean',
        'EDIT_FORM_LABEL' => ['ru' => 'Опциональный'],
    ]);
    
    echo "Поля добавлены\n";
    echo "Добавьте в CustomRestMethods:\n";
    echo "const HL_RECIPE_ITEMS = {$hlId};\n";
} else {
    echo "Ошибка: " . implode(', ', $result->getErrorMessages()) . "\n";
}