<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Пример вызова метода getProducts
$result = CustomRestMethods::getProducts([], null, null);
echo '<pre>'; print_r($result); echo '</pre>';