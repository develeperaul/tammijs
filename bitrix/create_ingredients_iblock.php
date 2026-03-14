<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;
use Bitrix\Iblock\IblockTable;

// Включаем отображение ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Создание инфоблоков</h1>";

if (!Loader::includeModule('iblock')) {
    die('Модуль iblock не установлен');
}

// ID текущего сайта (обычно s1)
$siteId = 's1';

// 1. Создаём тип инфоблоков, если нет
$iblockTypeId = 'catalog';
$arFields = [
    'ID' => $iblockTypeId,
    'SECTIONS' => 'Y',
    'IN_RSS' => 'N',
    'SORT' => 500,
    'LANG' => [
        'ru' => [
            'NAME' => 'Каталог товаров',
            'SECTION_NAME' => 'Разделы',
            'ELEMENT_NAME' => 'Элементы'
        ]
    ]
];

$obBlocktype = new CIBlockType;
$dbBlockType = CIBlockType::GetList([], ['=ID' => $iblockTypeId]);
if (!$dbBlockType->Fetch()) {
    $res = $obBlocktype->Add($arFields);
    if ($res) {
        echo "✅ Тип инфоблоков '{$iblockTypeId}' создан<br>";
    } else {
        echo "❌ Ошибка создания типа: " . $obBlocktype->LAST_ERROR . "<br>";
    }
} else {
    echo "ℹ️ Тип инфоблоков '{$iblockTypeId}' уже существует<br>";
}

// Функция для создания инфоблока
function createIBlock($name, $code, $typeId, $siteId) {
    $iblock = new CIBlock;
    
    // Проверяем, существует ли уже
    $res = CIBlock::GetList([], ['CODE' => $code, 'IBLOCK_TYPE_ID' => $typeId]);
    if ($res->Fetch()) {
        echo "ℹ️ Инфоблок '{$name}' уже существует<br>";
        return false;
    }
    
    $arFields = [
        'ACTIVE' => 'Y',
        'NAME' => $name,
        'CODE' => $code,
        'IBLOCK_TYPE_ID' => $typeId,
        'LID' => $siteId, // 👈 Обязательная привязка к сайту!
        'LIST_PAGE_URL' => '',
        'DETAIL_PAGE_URL' => '',
        'SECTION_PAGE_URL' => '',
        'GROUP_ID' => ['2' => 'R'],
        'VERSION' => 2,
        'FIELDS' => [
            'CODE' => [
                'IS_REQUIRED' => 'N',
                'DEFAULT_VALUE' => [
                    'TRANSLITERATION' => 'Y',
                    'UNIQUE' => 'N'
                ]
            ]
        ]
    ];
    
    $id = $iblock->Add($arFields);
    if ($id) {
        echo "✅ Инфоблок '{$name}' создан с ID: {$id}<br>";
        return $id;
    } else {
        echo "❌ Ошибка создания инфоблока '{$name}': " . $iblock->LAST_ERROR . "<br>";
        return false;
    }
}

// Функция для добавления свойства
function addProperty($iblockId, $name, $code, $type, $values = null, $default = null) {
    if (!$iblockId) return false;
    
    $prop = new CIBlockProperty;
    
    // Проверяем, существует ли свойство
    $res = CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $code]);
    if ($res->Fetch()) {
        echo "ℹ️ Свойство '{$code}' уже существует<br>";
        return false;
    }
    
    $arFields = [
        'NAME' => $name,
        'ACTIVE' => 'Y',
        'SORT' => 100,
        'CODE' => $code,
        'PROPERTY_TYPE' => $type,
        'IBLOCK_ID' => $iblockId,
    ];
    
    if ($type === 'L' && $values) {
        $arFields['VALUES'] = $values;
    }
    
    if ($type === 'N' && $default !== null) {
        $arFields['DEFAULT_VALUE'] = $default;
    }
    
    $id = $prop->Add($arFields);
    if ($id) {
        echo "✅ Свойство '{$name}' создано<br>";
        return $id;
    } else {
        echo "❌ Ошибка создания свойства '{$name}': " . $prop->LAST_ERROR . "<br>";
        return false;
    }
}

// 2. Создаём инфоблок ингредиентов - ПЕРЕДАЁМ $siteId!
$ingredientsId = createIBlock('Ингредиенты', 'ingredients', $iblockTypeId, $siteId);

if ($ingredientsId) {
    // Единица хранения
    addProperty($ingredientsId, 'Единица хранения', 'UNIT', 'L', [
        ['VALUE' => 'кг', 'DEF' => 'N', 'SORT' => 100],
        ['VALUE' => 'шт', 'DEF' => 'N', 'SORT' => 200],
        ['VALUE' => 'л', 'DEF' => 'N', 'SORT' => 300],
        ['VALUE' => 'уп', 'DEF' => 'N', 'SORT' => 400],
    ]);
    
    // Базовая единица
    addProperty($ingredientsId, 'Базовая единица', 'BASE_UNIT', 'L', [
        ['VALUE' => 'г', 'DEF' => 'Y', 'SORT' => 100],
        ['VALUE' => 'мл', 'DEF' => 'N', 'SORT' => 200],
        ['VALUE' => 'шт', 'DEF' => 'N', 'SORT' => 300],
    ]);
    
    // Коэффициент
    addProperty($ingredientsId, 'Коэффициент', 'BASE_RATIO', 'N', null, 1);
    
    // Цена закупа
    addProperty($ingredientsId, 'Цена закупа', 'COST_PRICE', 'N');
    
    // Текущий остаток
    addProperty($ingredientsId, 'Текущий остаток', 'CURRENT_STOCK', 'N', null, 0);
    
    // Минимальный остаток
    addProperty($ingredientsId, 'Минимальный остаток', 'MIN_STOCK', 'N');
}

// 3. Создаём инфоблок готовых блюд (только если не существует)
if (!CIBlock::GetList([], ['CODE' => 'products', 'IBLOCK_TYPE_ID' => $iblockTypeId])->Fetch()) {
    $productsId = createIBlock('Готовые блюда', 'products', $iblockTypeId, $siteId);

    if ($productsId) {
        // Цена продажи
        addProperty($productsId, 'Цена продажи', 'SELLING_PRICE', 'N');
    }
} else {
    echo "ℹ️ Инфоблок 'Готовые блюда' уже существует<br>";
}

// 4. Создаём инфоблок полуфабрикатов
$semiId = createIBlock('Полуфабрикаты', 'semi_finished', $iblockTypeId, $siteId);

if ($semiId) {
    // Единица хранения
    addProperty($semiId, 'Единица хранения', 'UNIT', 'L', [
        ['VALUE' => 'кг', 'DEF' => 'N', 'SORT' => 100],
        ['VALUE' => 'шт', 'DEF' => 'N', 'SORT' => 200],
        ['VALUE' => 'уп', 'DEF' => 'N', 'SORT' => 300],
    ]);
    
    // Цена продажи
    addProperty($semiId, 'Цена продажи', 'SELLING_PRICE', 'N');
    
    // Себестоимость
    addProperty($semiId, 'Себестоимость', 'COST_PRICE', 'N');
}

echo "<h2 style='color:green;'>Готово! Проверьте вывод выше.</h2>";