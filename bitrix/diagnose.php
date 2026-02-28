<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
use Bitrix\Main\Loader;
use Bitrix\Rest\APAuth\PasswordTable;
use Bitrix\Rest\APAuth\PermissionTable;

echo "<h1>Диагностика REST API (расширенная)</h1>";

// 1. Проверяем загрузку класса (как раньше)
if (class_exists('CustomRestMethods')) {
    echo "<p style='color:green;'>✓ Класс CustomRestMethods загружен.</p>";
} else {
    echo "<p style='color:red;'>✗ Класс CustomRestMethods НЕ загружен!</p>";
}

// 2. Проверяем наличие вебхуков и их прав
if (Loader::includeModule('rest')) {
    $dbRes = PasswordTable::getList([
        'select' => ['ID', 'TITLE', 'PASSWORD', 'USER_ID'],
        'order' => ['ID' => 'DESC']
    ]);
    echo "<h3>Существующие вебхуки:</h3>";
    if ($dbRes->getSelectedRowsCount() > 0) {
        while ($hook = $dbRes->fetch()) {
            echo "<p><b>ID: {$hook['ID']}</b>, Название: {$hook['TITLE']}, Ключ: {$hook['PASSWORD']}, Пользователь: {$hook['USER_ID']}</p>";
            // Получаем права
            $perms = PermissionTable::getList([
                'filter' => ['=PASSWORD_ID' => $hook['ID']],
                'select' => ['PERM']
            ]);
            $scopes = [];
            while ($perm = $perms->fetch()) {
                $scopes[] = $perm['PERM'];
            }
            echo "<p>Права: <b>" . (empty($scopes) ? 'нет прав' : implode(', ', $scopes)) . "</b></p>";
            echo "<hr>";
        }
    } else {
        echo "<p>Нет ни одного вебхука.</p>";
    }
} else {
    echo "<p style='color:red;'>Модуль rest не подключён.</p>";
}

// 3. Тест стандартного метода server.time для каждого вебхука
if (Loader::includeModule('rest')) {
    echo "<h3>Тест server.time для каждого вебхука:</h3>";
    $dbRes = PasswordTable::getList(['select' => ['ID', 'PASSWORD']]);
    while ($hook = $dbRes->fetch()) {
        $url = "https://tammi.2apps.ru/rest/{$hook['ID']}/{$hook['PASSWORD']}/server.time.json";
        echo "<p>Вебхук ID {$hook['ID']}: <a href='$url' target='_blank'>$url</a> – ";
        // Попытаемся получить содержимое через file_get_contents, но может быть ограничение. Лучше просто дать ссылку.
        echo " (откройте ссылку в новой вкладке)</p>";
    }
}
// require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// echo "<h1>Диагностика REST API</h1>";

// // 1. Проверяем, загружается ли наш класс
// if (class_exists('CustomRestMethods')) {
//     echo "<p style='color:green;'>✓ Класс CustomRestMethods загружен.</p>";
// } else {
//     echo "<p style='color:red;'>✗ Класс CustomRestMethods НЕ загружен!</p>";
//     // Попробуем подключить вручную
//     $file = $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/CustomRestMethods.php';
//     if (file_exists($file)) {
//         require_once($file);
//         echo "<p>Файл подключён вручную.</p>";
//         if (class_exists('CustomRestMethods')) {
//             echo "<p style='color:green;'>✓ После ручного подключения класс загружен.</p>";
//         } else {
//             echo "<p style='color:red;'>✗ Даже после ручного подключения класс не найден. Проверьте синтаксис файла.</p>";
//         }
//     } else {
//         echo "<p style='color:red;'>Файл /local/php_interface/classes/CustomRestMethods.php не существует!</p>";
//     }
// }

// // 2. Проверяем наличие метода onRestServiceBuildDescription
// if (class_exists('CustomRestMethods')) {
//     if (method_exists('CustomRestMethods', 'onRestServiceBuildDescription')) {
//         echo "<p style='color:green;'>✓ Метод onRestServiceBuildDescription существует.</p>";
//         // Вызовем его и посмотрим, что возвращает
//         $methods = CustomRestMethods::onRestServiceBuildDescription();
//         echo "<h3>Возвращаемое значение метода onRestServiceBuildDescription:</h3>";
//         echo "<pre>";
//         print_r($methods);
//         echo "</pre>";
//     } else {
//         echo "<p style='color:red;'>✗ Метод onRestServiceBuildDescription НЕ найден в классе!</p>";
//     }
// }

// // 3. Проверяем наличие вебхуков в БД
// if (\Bitrix\Main\Loader::includeModule('rest')) {
//     $dbRes = \Bitrix\Rest\APAuth\PasswordTable::getList([
//         'select' => ['ID', 'TITLE', 'PASSWORD', 'USER_ID'],
//         'order' => ['ID' => 'DESC']
//     ]);
//     echo "<h3>Существующие вебхуки:</h3>";
//     if ($dbRes->getSelectedRowsCount() > 0) {
//         while ($hook = $dbRes->fetch()) {
//             echo "<p>ID: {$hook['ID']}, Название: {$hook['TITLE']}, Ключ: {$hook['PASSWORD']}, Пользователь: {$hook['USER_ID']}</p>";
//         }
//     } else {
//         echo "<p>Нет ни одного вебхука.</p>";
//     }
// } else {
//     echo "<p style='color:red;'>Модуль rest не подключён.</p>";
// }

// // 4. Проверяем, есть ли наш метод в списке всех REST методов
// if (\Bitrix\Main\Loader::includeModule('rest')) {
//     $server = new \CRestServer(['CLASS' => 'CustomRestMethods', 'METHOD' => 'custom.products.get'], true);
//     // Попытаемся найти метод через рефлексию
//     $methods = \Bitrix\Rest\Engine\RestManager::getMethods();
//     echo "<h3>Доступные методы (первые 10):</h3>";
//     $i = 0;
//     foreach ($methods as $methodName => $methodDesc) {
//         if ($i++ > 10) break;
//         echo "<p>$methodName</p>";
//     }
// }