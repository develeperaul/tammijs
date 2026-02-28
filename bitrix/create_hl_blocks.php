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

    // Языковые названия можно добавить позже вручную, сейчас пропускаем
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

    // Если это поле-список, добавляем значения
    if ($fieldType === 'enumeration' && $enumValues) {
        $enum = new CUserFieldEnum();
        $enum->SetEnumValues($fieldId, $enumValues);
    }

    echo "Поле {$fieldName} создано<br>";
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
// 2. НАЧАЛО СОЗДАНИЯ ВСЕХ БЛОКОВ
// ------------------------------------

echo "<h1>Создание Highload-блоков</h1>";

Loader::includeModule('highloadblock');

// ----------------------------
// 2.1 БЛОК ЗАКАЗОВ (Orders)
// ----------------------------
$ordersId = createHlBlockIfNotExists('Orders', 'orders_table', 'Заказы');

if ($ordersId) {
    addUserField($ordersId, 'UF_NUMBER', 'string', 'Y', ['SIZE' => 20, 'MAX_LENGTH' => 50]);
    addUserField($ordersId, 'UF_TYPE', 'enumeration', 'Y', [], [
        ['XML_ID' => 'dine-in', 'VALUE' => 'На месте', 'DEF' => 'Y', 'SORT' => 10],
        ['XML_ID' => 'takeaway', 'VALUE' => 'С собой', 'DEF' => 'N', 'SORT' => 20],
        ['XML_ID' => 'delivery', 'VALUE' => 'Доставка', 'DEF' => 'N', 'SORT' => 30],
    ]);
    addUserField($ordersId, 'UF_TABLE_NUMBER', 'integer');
    addUserField($ordersId, 'UF_STATUS', 'enumeration', 'Y', [], [
        ['XML_ID' => 'new', 'VALUE' => 'Новый', 'DEF' => 'Y', 'SORT' => 10],
        ['XML_ID' => 'cooking', 'VALUE' => 'Готовится', 'DEF' => 'N', 'SORT' => 20],
        ['XML_ID' => 'ready', 'VALUE' => 'Готов', 'DEF' => 'N', 'SORT' => 30],
        ['XML_ID' => 'delivered', 'VALUE' => 'Доставлен', 'DEF' => 'N', 'SORT' => 40],
        ['XML_ID' => 'paid', 'VALUE' => 'Оплачен', 'DEF' => 'N', 'SORT' => 50],
        ['XML_ID' => 'cancelled', 'VALUE' => 'Отменён', 'DEF' => 'N', 'SORT' => 60],
    ]);
    addUserField($ordersId, 'UF_SUBTOTAL', 'double', 'Y', ['PRECISION' => 2]);
    addUserField($ordersId, 'UF_DISCOUNT', 'double', 'Y', ['PRECISION' => 2]);
    addUserField($ordersId, 'UF_TOTAL', 'double', 'Y', ['PRECISION' => 2]);
    addUserField($ordersId, 'UF_PAYMENT_METHOD', 'enumeration', 'N', [], [
        ['XML_ID' => 'cash', 'VALUE' => 'Наличные', 'SORT' => 10],
        ['XML_ID' => 'card', 'VALUE' => 'Карта', 'SORT' => 20],
        ['XML_ID' => 'online', 'VALUE' => 'Онлайн', 'SORT' => 30],
    ]);
    addUserField($ordersId, 'UF_CREATED_BY', 'integer', 'Y');
    addUserField($ordersId, 'UF_CREATED_AT', 'datetime', 'Y', ['DEFAULT_VALUE' => ['TYPE' => 'NOW', 'VALUE' => '']]);
    addUserField($ordersId, 'UF_COMMENT', 'string', 'N', ['SIZE' => 80, 'MAX_LENGTH' => 255]);

    // Индексы
    createIndexForField($ordersId, 'orders_table', 'UF_STATUS');
    createIndexForField($ordersId, 'orders_table', 'UF_CREATED_AT');
}

// ----------------------------
// 2.2 БЛОК ПОЗИЦИЙ ЗАКАЗОВ (OrderItems)
// ----------------------------
$itemsId = createHlBlockIfNotExists('OrderItems', 'order_items_table', 'Позиции заказов');

if ($itemsId) {
    addUserField($itemsId, 'UF_ORDER_ID', 'integer', 'Y');
    addUserField($itemsId, 'UF_PRODUCT_ID', 'integer', 'Y');
    addUserField($itemsId, 'UF_QUANTITY', 'double', 'Y', ['PRECISION' => 3]);
    addUserField($itemsId, 'UF_PRICE', 'double', 'Y', ['PRECISION' => 2]);
    addUserField($itemsId, 'UF_DISCOUNT_PERCENT', 'double', 'N', ['PRECISION' => 2]);
    addUserField($itemsId, 'UF_COMMENT', 'string', 'N', ['SIZE' => 80, 'MAX_LENGTH' => 255]);
    addUserField($itemsId, 'UF_COOKING_STATUS', 'enumeration', 'N', [], [
        ['XML_ID' => 'pending', 'VALUE' => 'Ожидает', 'DEF' => 'Y', 'SORT' => 10],
        ['XML_ID' => 'cooking', 'VALUE' => 'Готовится', 'SORT' => 20],
        ['XML_ID' => 'ready', 'VALUE' => 'Готово', 'SORT' => 30],
    ]);

    createIndexForField($itemsId, 'order_items_table', 'UF_ORDER_ID');
}

// ----------------------------
// 2.3 БЛОК РЕЦЕПТОВ (Recipes)
// ----------------------------
$recipesId = createHlBlockIfNotExists('Recipes', 'recipes_table', 'Рецепты');

if ($recipesId) {
    addUserField($recipesId, 'UF_PRODUCT_ID', 'integer', 'Y');
    addUserField($recipesId, 'UF_NAME', 'string', 'Y', ['SIZE' => 80, 'MAX_LENGTH' => 255]);
    addUserField($recipesId, 'UF_OUTPUT_WEIGHT', 'double', 'Y', ['PRECISION' => 3]);
    addUserField($recipesId, 'UF_OUTPUT_UNIT', 'string', 'Y', ['SIZE' => 10]);
    addUserField($recipesId, 'UF_COOKING_TIME', 'integer');
    addUserField($recipesId, 'UF_INSTRUCTIONS', 'string', 'N', ['SIZE' => 255, 'MAX_LENGTH' => 2000]);
    addUserField($recipesId, 'UF_PHOTO', 'file');

    createIndexForField($recipesId, 'recipes_table', 'UF_PRODUCT_ID');
}

// ----------------------------
// 2.4 БЛОК ИНГРЕДИЕНТОВ РЕЦЕПТОВ (RecipeIngredients)
// ----------------------------
$ingredientsId = createHlBlockIfNotExists('RecipeIngredients', 'recipe_ingredients_table', 'Ингредиенты рецептов');

if ($ingredientsId) {
    addUserField($ingredientsId, 'UF_RECIPE_ID', 'integer', 'Y');
    addUserField($ingredientsId, 'UF_INGREDIENT_ID', 'integer', 'Y');
    addUserField($ingredientsId, 'UF_QUANTITY', 'double', 'Y', ['PRECISION' => 3]);
    addUserField($ingredientsId, 'UF_UNIT', 'string', 'Y', ['SIZE' => 10]);
    addUserField($ingredientsId, 'UF_IS_OPTIONAL', 'boolean', 'N', [
        'DEFAULT_VALUE' => 0,
        'LABEL' => ['ru' => 'Опциональный', 'en' => 'Optional'],
    ]);

    createIndexForField($ingredientsId, 'recipe_ingredients_table', 'UF_RECIPE_ID');
    createIndexForField($ingredientsId, 'recipe_ingredients_table', 'UF_INGREDIENT_ID');
}

echo "<h2 style='color:green;'>Все Highload-блоки успешно созданы!</h2>";