<?php
// Включаем отображение ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Подключаем ядро Битрикса
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;

// ---------------------------
// 1. ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ---------------------------

/**
 * Безопасное создание HL-блока (проверяет, нет ли уже такого)
 */
function createHlBlockIfNotExists($name, $tableName, $langName)
{
    if (!Loader::includeModule('highloadblock')) {
        die('Модуль highloadblock не установлен.');
    }

    // Проверяем, существует ли уже блок с таким именем
    $existing = HighloadBlockTable::getList([
        'filter' => ['=NAME' => $name]
    ])->fetch();

    if ($existing) {
        echo "Блок {$name} уже существует, ID: " . $existing['ID'] . "<br>";
        return $existing['ID'];
    }

    // Создаём новый блок
    $result = HighloadBlockTable::add([
        'NAME' => $name,
        'TABLE_NAME' => $tableName,
    ]);

    if (!$result->isSuccess()) {
        die("Ошибка создания блока {$name}: " . implode(', ', $result->getErrorMessages()));
    }

    $hlId = $result->getId();
    echo "Создан блок {$name} с ID: {$hlId}<br>";

    return $hlId;
}

/**
 * Добавление пользовательского поля в HL-блок
 */
function addUserField($hlId, $fieldName, $fieldType, $mandatory = 'N', $settings = [], $enumValues = null)
{
    $oUserType = new CUserTypeEntity();
    $entityId = "HLBLOCK_{$hlId}";

    $arFields = [
        'ENTITY_ID' => $entityId,
        'FIELD_NAME' => $fieldName,
        'USER_TYPE_ID' => $fieldType,
        'XML_ID' => '',
        'SORT' => 100,
        'MULTIPLE' => 'N',
        'MANDATORY' => $mandatory,
        'SHOW_FILTER' => 'Y',
        'SHOW_IN_LIST' => 'Y',
        'EDIT_IN_LIST' => 'Y',
        'IS_SEARCHABLE' => 'Y',
        'SETTINGS' => $settings,
        'EDIT_FORM_LABEL' => ['ru' => $fieldName, 'en' => $fieldName],
        'LIST_COLUMN_LABEL' => ['ru' => $fieldName, 'en' => $fieldName],
    ];

    $fieldId = $oUserType->Add($arFields);
    if (!$fieldId) {
        global $APPLICATION;
        $exception = $APPLICATION->GetException();
        echo "Ошибка создания поля {$fieldName}: " . $exception->GetString() . "<br>";
        return false;
    }

    return $fieldId;
}

/**
 * Создание индекса MySQL для поля
 */
function createIndexForField($hlId, $tableName, $fieldName)
{
    $connection = Bitrix\Main\Application::getConnection();
    $table = $connection->getSqlHelper()->quote($tableName);

    // Проверяем, есть ли уже индекс
    $exists = $connection->query("SHOW INDEX FROM {$table} WHERE Column_name = '{$fieldName}'")->fetch();

    if (!$exists) {
        try {
            $connection->queryExecute("ALTER TABLE {$table} ADD INDEX idx_{$fieldName} (`{$fieldName}`)");
            echo "Индекс для поля {$fieldName} создан<br>";
        } catch (Exception $e) {
            echo "Ошибка создания индекса для {$fieldName}: " . $e->getMessage() . "<br>";
        }
    }
}

// ------------------------------------
// 2. СОЗДАНИЕ БЛОКА ПОСТАВЩИКОВ
// ------------------------------------

echo "<h1>Создание Highload-блока поставщиков</h1>";

Loader::includeModule('highloadblock');

// ----------------------------
// БЛОК ПОСТАВЩИКОВ (Suppliers)
// ----------------------------
$suppliersId = createHlBlockIfNotExists('Suppliers', 'suppliers_table', 'Поставщики');

if ($suppliersId) {
    // Основные поля
    addUserField($suppliersId, 'UF_NAME', 'string', 'Y', ['SIZE' => 100, 'MAX_LENGTH' => 255]);
    addUserField($suppliersId, 'UF_PHONE', 'string', 'N', ['SIZE' => 20, 'MAX_LENGTH' => 50]);
    addUserField($suppliersId, 'UF_EMAIL', 'string', 'N', ['SIZE' => 50, 'MAX_LENGTH' => 100]);
    addUserField($suppliersId, 'UF_ADDRESS', 'string', 'N', ['SIZE' => 100, 'MAX_LENGTH' => 500]);
    addUserField($suppliersId, 'UF_INN', 'string', 'N', ['SIZE' => 15, 'MAX_LENGTH' => 20]);
    addUserField($suppliersId, 'UF_KPP', 'string', 'N', ['SIZE' => 10, 'MAX_LENGTH' => 15]);
    addUserField($suppliersId, 'UF_COMMENT', 'string', 'N', ['SIZE' => 100, 'MAX_LENGTH' => 500]);
    addUserField($suppliersId, 'UF_CREATED_AT', 'datetime', 'Y', ['DEFAULT_VALUE' => ['TYPE' => 'NOW', 'VALUE' => '']]);

    // Индексы для быстрого поиска
    createIndexForField($suppliersId, 'suppliers_table', 'UF_NAME');
    createIndexForField($suppliersId, 'suppliers_table', 'UF_INN');
    createIndexForField($suppliersId, 'suppliers_table', 'UF_CREATED_AT');

    echo "<h3 style='color:green;'>✓ Блок поставщиков успешно создан!</h3>";
} else {
    echo "<h3 style='color:red;'>✗ Ошибка при создании блока поставщиков</h3>";
}

echo "<h2 style='color:green;'>Готово!</h2>";