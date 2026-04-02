<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;

// Включаем отображение ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Создание HL-блока ProductionDetails</h1>";

if (!Loader::includeModule('highloadblock')) {
    die('Модуль highloadblock не установлен');
}

// Имя и таблица для нового HL-блока
$name = 'ProductionDetails';
$tableName = 'production_details';

// Проверяем, существует ли уже такой блок
$existing = HighloadBlockTable::getList(['filter' => ['=NAME' => $name]])->fetch();
if ($existing) {
    echo "❌ HL-блок '{$name}' уже существует с ID: {$existing['ID']}<br>";
    echo "Добавьте в CustomRestMethods константу:<br>";
    echo "<b>const HL_PRODUCTION_DETAILS = {$existing['ID']};</b><br>";
    exit;
}

// Создаём HL-блок
$result = HighloadBlockTable::add([
    'NAME' => $name,
    'TABLE_NAME' => $tableName,
]);

if (!$result->isSuccess()) {
    die("❌ Ошибка создания HL-блока: " . implode(', ', $result->getErrorMessages()));
}

$hlId = $result->getId();
echo "✅ Создан HL-блок '{$name}' с ID: {$hlId}<br>";

// Добавляем поля
$oUserType = new CUserTypeEntity();
$entityId = "HLBLOCK_{$hlId}";

// Поле 1: UF_PRODUCTION_MOVEMENT_ID - ID движения прихода полуфабриката
$fieldId = $oUserType->Add([
    'ENTITY_ID' => $entityId,
    'FIELD_NAME' => 'UF_PRODUCTION_MOVEMENT_ID',
    'USER_TYPE_ID' => 'integer',
    'XML_ID' => '',
    'SORT' => 100,
    'MULTIPLE' => 'N',
    'MANDATORY' => 'Y',
    'SHOW_FILTER' => 'Y',
    'SHOW_IN_LIST' => 'Y',
    'EDIT_IN_LIST' => 'Y',
    'IS_SEARCHABLE' => 'N',
    'SETTINGS' => [
        'DEFAULT_VALUE' => 0,
    ],
    'EDIT_FORM_LABEL' => ['ru' => 'ID производственного движения'],
    'LIST_COLUMN_LABEL' => ['ru' => 'ID движения'],
]);
echo $fieldId ? "✅ Поле UF_PRODUCTION_MOVEMENT_ID создано<br>" : "❌ Ошибка создания поля UF_PRODUCTION_MOVEMENT_ID<br>";

// Поле 2: UF_INGREDIENT_ID - ID ингредиента
$fieldId = $oUserType->Add([
    'ENTITY_ID' => $entityId,
    'FIELD_NAME' => 'UF_INGREDIENT_ID',
    'USER_TYPE_ID' => 'integer',
    'XML_ID' => '',
    'SORT' => 200,
    'MULTIPLE' => 'N',
    'MANDATORY' => 'Y',
    'SHOW_FILTER' => 'Y',
    'SHOW_IN_LIST' => 'Y',
    'EDIT_IN_LIST' => 'Y',
    'IS_SEARCHABLE' => 'N',
    'SETTINGS' => [
        'DEFAULT_VALUE' => 0,
    ],
    'EDIT_FORM_LABEL' => ['ru' => 'ID ингредиента'],
    'LIST_COLUMN_LABEL' => ['ru' => 'ID ингредиента'],
]);
echo $fieldId ? "✅ Поле UF_INGREDIENT_ID создано<br>" : "❌ Ошибка создания поля UF_INGREDIENT_ID<br>";

// Поле 3: UF_QUANTITY - количество списанного ингредиента
$fieldId = $oUserType->Add([
    'ENTITY_ID' => $entityId,
    'FIELD_NAME' => 'UF_QUANTITY',
    'USER_TYPE_ID' => 'double',
    'XML_ID' => '',
    'SORT' => 300,
    'MULTIPLE' => 'N',
    'MANDATORY' => 'Y',
    'SHOW_FILTER' => 'Y',
    'SHOW_IN_LIST' => 'Y',
    'EDIT_IN_LIST' => 'Y',
    'IS_SEARCHABLE' => 'N',
    'SETTINGS' => [
        'DEFAULT_VALUE' => 0,
        'PRECISION' => 3,
    ],
    'EDIT_FORM_LABEL' => ['ru' => 'Количество'],
    'LIST_COLUMN_LABEL' => ['ru' => 'Количество'],
]);
echo $fieldId ? "✅ Поле UF_QUANTITY создано<br>" : "❌ Ошибка создания поля UF_QUANTITY<br>";

// Поле 4: UF_UNIT - единица измерения ингредиента
$fieldId = $oUserType->Add([
    'ENTITY_ID' => $entityId,
    'FIELD_NAME' => 'UF_UNIT',
    'USER_TYPE_ID' => 'string',
    'XML_ID' => '',
    'SORT' => 400,
    'MULTIPLE' => 'N',
    'MANDATORY' => 'Y',
    'SHOW_FILTER' => 'Y',
    'SHOW_IN_LIST' => 'Y',
    'EDIT_IN_LIST' => 'Y',
    'IS_SEARCHABLE' => 'N',
    'SETTINGS' => [
        'SIZE' => 10,
        'MAX_LENGTH' => 10,
    ],
    'EDIT_FORM_LABEL' => ['ru' => 'Единица измерения'],
    'LIST_COLUMN_LABEL' => ['ru' => 'Ед.'],
]);
echo $fieldId ? "✅ Поле UF_UNIT создано<br>" : "❌ Ошибка создания поля UF_UNIT<br>";

// Поле 5: UF_PRICE - цена ингредиента на момент списания (опционально, для истории)
$fieldId = $oUserType->Add([
    'ENTITY_ID' => $entityId,
    'FIELD_NAME' => 'UF_PRICE',
    'USER_TYPE_ID' => 'double',
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
        'PRECISION' => 2,
    ],
    'EDIT_FORM_LABEL' => ['ru' => 'Цена за единицу'],
    'LIST_COLUMN_LABEL' => ['ru' => 'Цена'],
]);
echo $fieldId ? "✅ Поле UF_PRICE создано<br>" : "❌ Ошибка создания поля UF_PRICE<br>";

// Индексы для ускорения поиска
$connection = \Bitrix\Main\Application::getConnection();
$table = $connection->getSqlHelper()->quote($tableName);

try {
    $connection->queryExecute("ALTER TABLE {$table} ADD INDEX idx_production_movement (UF_PRODUCTION_MOVEMENT_ID)");
    echo "✅ Индекс по UF_PRODUCTION_MOVEMENT_ID создан<br>";
} catch (Exception $e) {
    echo "⚠️ Индекс уже существует или ошибка: " . $e->getMessage() . "<br>";
}

try {
    $connection->queryExecute("ALTER TABLE {$table} ADD INDEX idx_ingredient (UF_INGREDIENT_ID)");
    echo "✅ Индекс по UF_INGREDIENT_ID создан<br>";
} catch (Exception $e) {
    echo "⚠️ Индекс уже существует или ошибка: " . $e->getMessage() . "<br>";
}

echo "<h2 style='color:green;'>✅ HL-блок ProductionDetails успешно создан!</h2>";
echo "<p>Добавьте в CustomRestMethods константу:</p>";
echo "<pre>const HL_PRODUCTION_DETAILS = {$hlId};</pre>";