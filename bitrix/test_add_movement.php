<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

try {
    $movementId = StockMovementHelper::addMovementWithStockUpdate([
        'UF_PRODUCT_ID' => 1,          // ID вашего товара (например, рис)
        'UF_TYPE' => 'income',
        'UF_QUANTITY' => 5.5,           // приход 5.5 кг
        'UF_PRICE' => 80,                // цена закупки
        'UF_DOCUMENT_TYPE' => 'manual',
        'UF_COMMENT' => 'Тестовый приход',
        'UF_CREATED_BY' => 1              // ID админа
    ]);

    echo "Движение добавлено, ID: $movementId";
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage();
}