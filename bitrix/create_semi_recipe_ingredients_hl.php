<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;

Loader::includeModule('highloadblock');

$name = 'SemiRecipeIngredients';
$tableName = 'semi_recipe_ingredients';

$existing = HighloadBlockTable::getList(['filter' => ['=NAME' => $name]])->fetch();
if ($existing) {
    echo "HL-блок уже существует с ID: {$existing['ID']}\n";
    echo "Добавьте в CustomRestMethods: const HL_SEMI_RECIPE_INGREDIENTS = {$existing['ID']};\n";
    exit;
}

$result = HighloadBlockTable::add([
    'NAME' => $name,
    'TABLE_NAME' => $tableName,
]);

if ($result->isSuccess()) {
    $hlId = $result->getId();
    echo "✅ Создан HL-блок {$name} с ID: {$hlId}\n";
    
    $oUserType = new CUserTypeEntity();
    $entityId = "HLBLOCK_{$hlId}";
    
    // UF_RECIPE_ID
    $oUserType->Add([
        'ENTITY_ID' => $entityId,
        'FIELD_NAME' => 'UF_RECIPE_ID',
        'USER_TYPE_ID' => 'integer',
        'MANDATORY' => 'Y',
        'EDIT_FORM_LABEL' => ['ru' => 'ID рецепта'],
    ]);
    
    // UF_INGREDIENT_ID
    $oUserType->Add([
        'ENTITY_ID' => $entityId,
        'FIELD_NAME' => 'UF_INGREDIENT_ID',
        'USER_TYPE_ID' => 'integer',
        'MANDATORY' => 'Y',
        'EDIT_FORM_LABEL' => ['ru' => 'ID ингредиента'],
    ]);
    
    // UF_QUANTITY
    $oUserType->Add([
        'ENTITY_ID' => $entityId,
        'FIELD_NAME' => 'UF_QUANTITY',
        'USER_TYPE_ID' => 'double',
        'MANDATORY' => 'Y',
        'SETTINGS' => ['PRECISION' => 3],
        'EDIT_FORM_LABEL' => ['ru' => 'Количество'],
    ]);
    
    // UF_UNIT
    $oUserType->Add([
        'ENTITY_ID' => $entityId,
        'FIELD_NAME' => 'UF_UNIT',
        'USER_TYPE_ID' => 'string',
        'MANDATORY' => 'Y',
        'EDIT_FORM_LABEL' => ['ru' => 'Единица измерения'],
    ]);
    
    echo "✅ Поля добавлены\n";
    echo "Добавьте в CustomRestMethods:\n";
    echo "const HL_SEMI_RECIPE_INGREDIENTS = {$hlId};\n";
} else {
    echo "❌ Ошибка: " . implode(', ', $result->getErrorMessages()) . "\n";
}
