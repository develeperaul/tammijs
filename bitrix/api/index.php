<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Разрешаем CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
header('Content-Type: application/json');

// Preflight-запросы (OPTIONS) – просто отвечаем 200
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Подключаем ядро Битрикса
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

// Проверяем токен (задайте свой секретный ключ)
$validToken = '29ad95e3891520df0823341bf53f77e4e5b3f6c6e3792baa3bb9bc94a4b601d9'; // ЗАМЕНИТЕ НА СВОЙ КЛЮЧ
$headers = getallheaders();
$providedToken = $headers['x-api-key'] ?? '';
if ($providedToken !== $validToken) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized',
        'debug' => [
            'provided' => $providedToken,
            'expected' => $validToken,
            'headers' => $headers
        ]
    ]);
    exit();
}
// if ($providedToken !== $validToken) {
//     http_response_code(401);
//     echo json_encode(['success' => false, 'error' => 'Unauthorized']);
//     exit();
// }

// Получаем действие из параметра action (например, /api/index.php?action=products.get)
$action = $_GET['action'] ?? '';

if (!$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No action specified']);
    exit();
}

// Получаем HTTP-метод
$method = $_SERVER['REQUEST_METHOD'];

// Получаем входные данные (JSON или form-data)

// $input = file_get_contents('php://input');
// $data = json_decode($input, true) ?? $_POST;

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$data = array_merge($_GET, $input, $_POST);

// Подключаем наши классы (если автозагрузка не работает – подключаем вручную)
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/classes/CustomRestMethods.php');
// При необходимости подключайте другие хелперы

// Маршрутизация
$routes = [
    // ===== ТОВАРЫ И КАТЕГОРИИ =====
    'products.get'      => ['method' => 'GET',    'callback' => ['CustomRestMethods', 'getProducts']],
    'product.create'    => ['method' => 'POST',   'callback' => ['CustomRestMethods', 'createProduct']],
    'product.update'    => ['method' => 'POST',   'callback' => ['CustomRestMethods', 'updateProduct']],
    'product.delete'    => ['method' => 'DELETE', 'callback' => ['CustomRestMethods', 'deleteProduct']],
    
    // Категории
    'categories.get'    => ['method' => 'GET',    'callback' => ['CustomRestMethods', 'getCategories']],
    'category.create'   => ['method' => 'POST',   'callback' => ['CustomRestMethods', 'createCategory']],
    'category.update'   => ['method' => 'POST',   'callback' => ['CustomRestMethods', 'updateCategory']],
    'category.delete'   => ['method' => 'DELETE', 'callback' => ['CustomRestMethods', 'deleteCategory']],

    // ===== ИНГРЕДИЕНТЫ =====
    'ingredients.get'   => ['method' => 'GET',    'callback' => ['CustomRestMethods', 'getIngredients']],
    'ingredient.create' => ['method' => 'POST',   'callback' => ['CustomRestMethods', 'createIngredient']],
    'ingredient.update' => ['method' => 'POST',   'callback' => ['CustomRestMethods', 'updateIngredient']],
    'ingredient.delete' => ['method' => 'DELETE', 'callback' => ['CustomRestMethods', 'deleteIngredient']],

    // ===== ПОЛУФАБРИКАТЫ =====
    'semi.get'          => ['method' => 'GET',    'callback' => ['CustomRestMethods', 'getSemiFinished']],
    'semi.create'       => ['method' => 'POST',   'callback' => ['CustomRestMethods', 'createSemiFinished']],
    'semi.update'       => ['method' => 'POST',   'callback' => ['CustomRestMethods', 'updateSemiFinished']],
    'semi.delete'       => ['method' => 'DELETE', 'callback' => ['CustomRestMethods', 'deleteSemiFinished']],

    // ===== РЕЦЕПТЫ =====
    'recipes.get'       => ['method' => 'GET',    'callback' => ['CustomRestMethods', 'getRecipes']],
    'recipe.create'     => ['method' => 'POST',   'callback' => ['CustomRestMethods', 'createRecipe']],
    'recipe.update'     => ['method' => 'POST',   'callback' => ['CustomRestMethods', 'updateRecipe']],
    'recipe.delete'     => ['method' => 'DELETE', 'callback' => ['CustomRestMethods', 'deleteRecipe']],
    'recipe.calculate.cost' => ['method' => 'GET', 'callback' => ['CustomRestMethods', 'calculateRecipeCost']],

    // ===== ЗАКАЗЫ =====
    'orders.get'        => ['method' => 'GET',    'callback' => ['CustomRestMethods', 'getOrders']],
    'order.create'      => ['method' => 'POST',   'callback' => ['CustomRestMethods', 'createOrder']],
    'order.update.status' => ['method' => 'PUT',  'callback' => ['CustomRestMethods', 'updateOrderStatus']],
    'order.item.update.status' => ['method' => 'PUT', 'callback' => ['CustomRestMethods', 'updateOrderItemStatus']],

    // ===== ОСТАТКИ =====
    'stock.get'         => ['method' => 'GET',    'callback' => ['CustomRestMethods', 'getStock']],
    'stock.movement.add' => ['method' => 'POST',  'callback' => ['CustomRestMethods', 'addMovement']],
    'stock.history.get' => ['method' => 'GET',    'callback' => ['CustomRestMethods', 'getStockHistory']],

    // ===== ПОСТАВЩИКИ =====
    'suppliers.get'     => ['method' => 'GET',    'callback' => ['CustomRestMethods', 'getSuppliers']],
    'supplier.create'   => ['method' => 'POST',   'callback' => ['CustomRestMethods', 'createSupplier']],
    'supplier.update'   => ['method' => 'POST',   'callback' => ['CustomRestMethods', 'updateSupplier']],
    'supplier.delete'   => ['method' => 'DELETE', 'callback' => ['CustomRestMethods', 'deleteSupplier']],
    'supplier.price.history' => ['method' => 'GET', 'callback' => ['CustomRestMethods', 'getSupplierPriceHistory']],

    // ===== КУХНЯ =====
    'kitchen.orders'    => ['method' => 'GET',    'callback' => ['CustomRestMethods', 'getKitchenOrders']],
];

if (!isset($routes[$action])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Action not found']);
    exit();
}

$route = $routes[$action];

if ($route['method'] !== $method) {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

try {
    // Вызываем метод, передавая $data (параметры запроса) и $method (если нужно)
    $result = call_user_func($route['callback'], $data, $method);
    echo json_encode(['success' => true, 'data' => $result]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}