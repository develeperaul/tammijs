<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;

Loader::includeModule('highloadblock');

// ID вашего HL-блока StockMovements (посмотрите в админке)
$hlBlockId = 1; // Замените на реальный ID

$hlblock = HighloadBlockTable::getById($hlBlockId)->fetch();
$entity = HighloadBlockTable::compileEntity($hlblock);
$dataClass = $entity->getDataClass();

$oUserType = new CUserTypeEntity();
$entityId = "HLBLOCK_{$hlBlockId}";

$fieldId = $oUserType->Add([
    'ENTITY_ID' => $entityId,
    'FIELD_NAME' => 'UF_SUPPLIER_ID',
    'USER_TYPE_ID' => 'integer',
    'XML_ID' => '',
    'SORT' => 500,
    'MULTIPLE' => 'N',
    'MANDATORY' => 'N',
    'SHOW_FILTER' => 'Y',
    'SHOW_IN_LIST' => 'Y',
    'EDIT_IN_LIST' => 'Y',
    'IS_SEARCHABLE' => 'N',
    'SETTINGS' => [
        'DEFAULT_VALUE' => 0,
    ],
    'EDIT_FORM_LABEL' => ['ru' => 'Поставщик'],
    'LIST_COLUMN_LABEL' => ['ru' => 'Поставщик'],
]);

if ($fieldId) {
    echo "✅ Поле UF_SUPPLIER_ID успешно добавлено!";
} else {
    echo "❌ Ошибка при добавлении поля";
}