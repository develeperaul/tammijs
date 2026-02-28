<?php
// /local/php_interface/api/Router.php

namespace Local\Api;

use Local\Api\Controllers;

class Router
{
    private $routes = [];
    
    public function __construct()
    {
        $this->initRoutes();
    }
    
    private function initRoutes()
    {
        // Продукты / товары
        $this->addRoute('GET', '/products', 'ProductController@getList');
        $this->addRoute('GET', '/products/([0-9]+)', 'ProductController@getOne');
        $this->addRoute('POST', '/products', 'ProductController@create');
        $this->addRoute('PUT', '/products/([0-9]+)', 'ProductController@update');
        $this->addRoute('DELETE', '/products/([0-9]+)', 'ProductController@delete');
        
        // Остатки
        $this->addRoute('GET', '/stock', 'StockController@getCurrent');
        $this->addRoute('POST', '/stock/movement', 'StockController@addMovement');
        $this->addRoute('GET', '/stock/history', 'StockController@getHistory');
        $this->addRoute('POST', '/stock/write-off', 'StockController@writeOff');
        
        // Накладные
        $this->addRoute('GET', '/invoices', 'InvoiceController@getList');
        $this->addRoute('GET', '/invoices/([0-9]+)', 'InvoiceController@getOne');
        $this->addRoute('POST', '/invoices', 'InvoiceController@create');
        $this->addRoute('POST', '/invoices/from-ai', 'InvoiceController@createFromAI');
        $this->addRoute('POST', '/invoices/([0-9]+)/confirm', 'InvoiceController@confirm');
        
        // Заказы (продажи)
        $this->addRoute('GET', '/orders', 'OrderController@getList');
        $this->addRoute('GET', '/orders/active', 'OrderController@getActive');
        $this->addRoute('POST', '/orders', 'OrderController@create');
        $this->addRoute('PUT', '/orders/([0-9]+)', 'OrderController@update');
        $this->addRoute('POST', '/orders/([0-9]+)/pay', 'OrderController@pay');
        
        // Сотрудники
        $this->addRoute('GET', '/employees', 'EmployeeController@getList');
        $this->addRoute('GET', '/employees/([0-9]+)', 'EmployeeController@getOne');
        $this->addRoute('POST', '/employees', 'EmployeeController@create');
        $this->addRoute('PUT', '/employees/([0-9]+)', 'EmployeeController@update');
        
        // Смены
        $this->addRoute('POST', '/shifts/open', 'ShiftController@open');
        $this->addRoute('POST', '/shifts/close', 'ShiftController@close');
        $this->addRoute('GET', '/shifts/current', 'ShiftController@getCurrent');
        
        // AI интеграция
        $this->addRoute('POST', '/ai/recognize-invoice', 'AIController@recognizeInvoice');
    }
    
    private function addRoute($method, $path, $handler)
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => '#^' . $path . '$#',
            'handler' => $handler
        ];
    }
    
    public function dispatch($method, $path, $params = [])
    {
        // Проверка API ключа
        $this->checkAuth();
        
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            
            if (preg_match($route['pattern'], $path, $matches)) {
                array_shift($matches); // Убираем полное совпадение
                
                // Разбираем handler
                list($controllerName, $methodName) = explode('@', $route['handler']);
                
                $controllerClass = "Local\\Api\\Controllers\\{$controllerName}";
                
                if (!class_exists($controllerClass)) {
                    throw new \Exception("Controller not found", 500);
                }
                
                $controller = new $controllerClass();
                
                if (!method_exists($controller, $methodName)) {
                    throw new \Exception("Method not found", 500);
                }
                
                // Вызываем контроллер
                return $controller->$methodName($matches, $params);
            }
        }
        
        throw new \Exception("Route not found", 404);
    }
    
    private function checkAuth()
    {
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
        $validKey = 'ваш-секретный-ключ'; // Вынести в конфиг
        
        if ($apiKey !== $validKey) {
            throw new \Exception("Unauthorized", 401);
        }
    }
}