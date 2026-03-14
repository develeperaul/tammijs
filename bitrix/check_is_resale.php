<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;
Loader::includeModule('iblock');

$iblockId = 1; // ID вашего инфоблока товаров

// Проверяем, существует ли свойство IS_RESALE
$propRes = CIBlockProperty::GetList([], [
    'IBLOCK_ID' => $iblockId,
    'CODE' => 'IS_RESALE'
]);

if ($prop = $propRes->Fetch()) {
    echo "Свойство IS_RESALE уже существует:\n";
    echo "ID: " . $prop['ID'] . "\n";
    echo "Тип: " . $prop['PROPERTY_TYPE'] . "\n";
    echo "Название: " . $prop['NAME'] . "\n";
    
    // Проверяем значения
    $enumRes = CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $prop['ID']]);
    echo "Значения:\n";
    while ($enum = $enumRes->Fetch()) {
        echo "  - ID: {$enum['ID']}, XML_ID: {$enum['XML_ID']}, VALUE: {$enum['VALUE']}\n";
    }
} else {
    echo "Свойство IS_RESALE не найдено! Создаём...\n";
    
    $prop = new CIBlockProperty;
    $propId = $prop->Add([
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
    
    if ($propId) {
        echo "✅ Свойство IS_RESALE создано с ID: $propId\n";
    } else {
        echo "❌ Ошибка создания свойства\n";
    }
}