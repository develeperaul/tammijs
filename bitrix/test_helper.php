<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

if (class_exists('StockMovementHelper')) {
    echo 'OK';
} else {
    echo 'Класс не найден';
}