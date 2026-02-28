<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;
use Bitrix\Rest\APAuth\PasswordTable;

if (!Loader::includeModule('rest')) {
    die('Модуль REST не установлен');
}

// Удалим все старые вебхуки с таким же названием, чтобы не путаться (опционально)
$res = PasswordTable::getList(['filter' => ['=TITLE' => 'Quasar CRM API']]);
while ($item = $res->fetch()) {
    PasswordTable::delete($item['ID']);
}

// Создаём новый вебхук
$result = PasswordTable::add([
    'USER_ID' => 1,
    'PASSWORD' => \Bitrix\Main\Security\Random::getString(32),
    'TITLE' => 'Quasar CRM API',
    'COMMENT' => 'Для фронтенда',
    'SCOPE' => ['iblock', 'highloadblock'], // права
]);

if ($result->isSuccess()) {
    $webhookId = $result->getId();
    $webhook = PasswordTable::getById($webhookId)->fetch();
    $key = $webhook['PASSWORD'];
    $url = "https://tammi.2apps.ru/rest/{$webhookId}/{$key}/";
    echo "✅ Вебхук создан!\n";
    echo "URL для вызовов: <b>{$url}</b>\n";
    echo "Тест server.time: <a href='{$url}server.time.json' target='_blank'>{$url}server.time.json</a>\n";
    echo "Тест custom.products.get: <a href='{$url}custom.products.get.json' target='_blank'>{$url}custom.products.get.json</a>\n";
} else {
    echo "❌ Ошибка: " . implode(', ', $result->getErrorMessages());
}