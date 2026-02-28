<?php
\Bitrix\Main\Loader::registerAutoLoadClasses(null, [
    'StockMovementHelper' => '/local/php_interface/classes/StockMovementHelper.php',
    'OrderHelper'         => '/local/php_interface/classes/OrderHelper.php',
    'OrderItemHelper'     => '/local/php_interface/classes/OrderItemHelper.php',
    'RecipeHelper'        => '/local/php_interface/classes/RecipeHelper.php',

    'CustomRestMethods' => '/local/php_interface/classes/CustomRestMethods.php',
]);

// Регистрируем обработчик для добавления наших методов в REST API
AddEventHandler('rest', 'OnRestServiceBuildDescription', ['CustomRestMethods', 'onRestServiceBuildDescription']);