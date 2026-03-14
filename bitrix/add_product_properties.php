<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;
Loader::includeModule('iblock');

$iblockId = 1; // ID инфоблока товаров

$prop = new CIBlockProperty;

// 1. Флаг "товар для перепродажи"
$prop->Add([
    'NAME' => 'Товар для перепродажи',
    'ACTIVE' => 'Y',
    'SORT' => 500,
    'CODE' => 'IS_RESALE',
    'PROPERTY_TYPE' => 'L',
    'IBLOCK_ID' => $iblockId,
    'VALUES' => [
        ['VALUE' => 'Да', 'XML_ID' => 'Y', 'DEF' => 'N', 'SORT' => 100],
    ]
]);

// 2. Единица измерения (для перепродажи)
$prop->Add([
    'NAME' => 'Единица измерения',
    'ACTIVE' => 'Y',
    'SORT' => 510,
    'CODE' => 'UNIT',
    'PROPERTY_TYPE' => 'L',
    'IBLOCK_ID' => $iblockId,
    'VALUES' => [
        ['VALUE' => 'шт', 'XML_ID' => 'шт', 'DEF' => 'Y', 'SORT' => 100],
        ['VALUE' => 'кг', 'XML_ID' => 'кг', 'DEF' => 'N', 'SORT' => 200],
        ['VALUE' => 'л', 'XML_ID' => 'л', 'DEF' => 'N', 'SORT' => 300],
        ['VALUE' => 'уп', 'XML_ID' => 'уп', 'DEF' => 'N', 'SORT' => 400],
    ]
]);

// 3. Цена закупа
$prop->Add([
    'NAME' => 'Цена закупа',
    'ACTIVE' => 'Y',
    'SORT' => 520,
    'CODE' => 'COST_PRICE',
    'PROPERTY_TYPE' => 'N',
    'IBLOCK_ID' => $iblockId,
]);

// 4. Текущий остаток
$prop->Add([
    'NAME' => 'Текущий остаток',
    'ACTIVE' => 'Y',
    'SORT' => 530,
    'CODE' => 'CURRENT_STOCK',
    'PROPERTY_TYPE' => 'N',
    'IBLOCK_ID' => $iblockId,
    'DEFAULT_VALUE' => 0,
]);

// 5. Минимальный остаток
$prop->Add([
    'NAME' => 'Минимальный остаток',
    'ACTIVE' => 'Y',
    'SORT' => 540,
    'CODE' => 'MIN_STOCK',
    'PROPERTY_TYPE' => 'N',
    'IBLOCK_ID' => $iblockId,
]);

echo "Все поля успешно добавлены!";
?>