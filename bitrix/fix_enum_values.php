<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;

Loader::includeModule('highloadblock');

/**
 * Добавить значения для поля-списка
 */
function addEnumValues($hlBlockName, $fieldName, $values)
{
    // Получаем ID HL-блока
    $hlblock = HighloadBlockTable::getList(['filter' => ['=NAME' => $hlBlockName]])->fetch();
    if (!$hlblock) {
        echo "Блок {$hlBlockName} не найден\n";
        return;
    }

    $entityId = "HLBLOCK_{$hlblock['ID']}";

    // Находим поле
    $oUserType = new CUserTypeEntity();
    $rsField = $oUserType->GetList([], ['ENTITY_ID' => $entityId, 'FIELD_NAME' => $fieldName]);
    $arField = $rsField->Fetch();

    if (!$arField) {
        echo "Поле {$fieldName} не найдено в блоке {$hlBlockName}\n";
        return;
    }

    $fieldId = $arField['ID'];

    // Добавляем значения
    $enum = new CUserFieldEnum();
    $result = $enum->SetEnumValues($fieldId, $values);

    if ($result) {
        echo "Значения для поля {$fieldName} успешно добавлены\n";
    } else {
        echo "Ошибка при добавлении значений для поля {$fieldName}\n";
    }
}

// Значения для Orders.UF_TYPE
addEnumValues('Orders', 'UF_TYPE', [
    'n1' => ['XML_ID' => 'dine-in', 'VALUE' => 'На месте', 'DEF' => 'Y', 'SORT' => 10],
    'n2' => ['XML_ID' => 'takeaway', 'VALUE' => 'С собой', 'DEF' => 'N', 'SORT' => 20],
    'n3' => ['XML_ID' => 'delivery', 'VALUE' => 'Доставка', 'DEF' => 'N', 'SORT' => 30],
]);

// Значения для Orders.UF_STATUS
addEnumValues('Orders', 'UF_STATUS', [
    'n1' => ['XML_ID' => 'new', 'VALUE' => 'Новый', 'DEF' => 'Y', 'SORT' => 10],
    'n2' => ['XML_ID' => 'cooking', 'VALUE' => 'Готовится', 'DEF' => 'N', 'SORT' => 20],
    'n3' => ['XML_ID' => 'ready', 'VALUE' => 'Готов', 'DEF' => 'N', 'SORT' => 30],
    'n4' => ['XML_ID' => 'delivered', 'VALUE' => 'Доставлен', 'DEF' => 'N', 'SORT' => 40],
    'n5' => ['XML_ID' => 'paid', 'VALUE' => 'Оплачен', 'DEF' => 'N', 'SORT' => 50],
    'n6' => ['XML_ID' => 'cancelled', 'VALUE' => 'Отменён', 'DEF' => 'N', 'SORT' => 60],
]);

// Значения для Orders.UF_PAYMENT_METHOD
addEnumValues('Orders', 'UF_PAYMENT_METHOD', [
    'n1' => ['XML_ID' => 'cash', 'VALUE' => 'Наличные', 'SORT' => 10],
    'n2' => ['XML_ID' => 'card', 'VALUE' => 'Карта', 'SORT' => 20],
    'n3' => ['XML_ID' => 'online', 'VALUE' => 'Онлайн', 'SORT' => 30],
]);

// Значения для OrderItems.UF_COOKING_STATUS
addEnumValues('OrderItems', 'UF_COOKING_STATUS', [
    'n1' => ['XML_ID' => 'pending', 'VALUE' => 'Ожидает', 'DEF' => 'Y', 'SORT' => 10],
    'n2' => ['XML_ID' => 'cooking', 'VALUE' => 'Готовится', 'SORT' => 20],
    'n3' => ['XML_ID' => 'ready', 'VALUE' => 'Готово', 'SORT' => 30],
]);

echo "Готово!";