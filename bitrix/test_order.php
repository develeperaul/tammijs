<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

try {
    // Создаём заказ
    $orderId = OrderHelper::createOrder([
        'UF_TYPE' => 'dine-in',
        'UF_TABLE_NUMBER' => 5,
        'UF_STATUS' => 'new',
        'UF_SUBTOTAL' => 780,
        'UF_DISCOUNT' => 0,
        'UF_TOTAL' => 780,
        'UF_CREATED_BY' => 1,
        'UF_COMMENT' => 'Тестовый заказ'
    ]);
    echo "Создан заказ ID: $orderId\n";

    // Добавляем позиции
    $itemIds = OrderItemHelper::addItems($orderId, [
        [
            'UF_PRODUCT_ID' => 101, // ID ролла Калифорния
            'UF_QUANTITY' => 2,
            'UF_PRICE' => 350,
            'UF_COOKING_STATUS' => 'pending'
        ],
        [
            'UF_PRODUCT_ID' => 104, // ID Колы
            'UF_QUANTITY' => 1,
            'UF_PRICE' => 80,
            'UF_COOKING_STATUS' => 'pending'
        ]
    ]);
    echo "Добавлены позиции: " . implode(', ', $itemIds) . "\n";

    // Получаем заказы для кухни
    $kitchenOrders = OrderHelper::getKitchenOrders();
    echo "Заказов на кухне: " . count($kitchenOrders) . "\n";

} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage();
}