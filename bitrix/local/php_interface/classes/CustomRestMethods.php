<?php
// /local/php_interface/classes/CustomRestMethods.php

use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;

class CustomRestMethods
{
    // Константы для идентификаторов (замените на свои)
    const IBLOCK_PRODUCTS = 1;           // ID инфоблока «Товары»
    const IBLOCK_INGREDIENTS = 3;      // Ингредиенты
    const IBLOCK_SEMI_FINISHED = 4;    // Полуфабрикаты
    const HL_ORDERS = 2;                 // ID HL-блока «Orders»
    const HL_ORDER_ITEMS = 3;            // ID HL-блока «OrderItems»
    const HL_RECIPES = 4;                // ID HL-блока «Recipes»
    const HL_RECIPE_INGREDIENTS = 5;     // ID HL-блока «RecipeIngredients»
    const HL_SUPPLIERS = 6;     // ID HL-блока Suppliers
    const HL_SEMI_RECIPES = 7;
    const HL_RECIPE_ITEMS = 8; // ID нового HL-блока для состава рецептов
    const HL_STOCK_MOVEMENTS = 1;        // ID HL-блока «StockMovements» (если используется)

    /**
     * Получить список продуктов с возможностью фильтрации по типу
     * @param array $data Параметры: type (produced/resale/all), category
     */
 public static function getProducts($data = [])
{
    Loader::includeModule('iblock');
    
    $filter = [
        'IBLOCK_ID' => self::IBLOCK_PRODUCTS,
        'ACTIVE' => 'Y'
    ];

    if (!empty($data['category'])) {
        $filter['SECTION_ID'] = (int)$data['category'];
    }

    $select = [
        'ID', 'NAME', 'IBLOCK_SECTION_ID', 'CODE', 'ACTIVE',
        'PROPERTY_IS_RESALE',
        'PROPERTY_UNIT',
        'PROPERTY_COST_PRICE',
        'PROPERTY_SELLING_PRICE',
        'PROPERTY_CURRENT_STOCK',
        'PROPERTY_MIN_STOCK',
        'PROPERTY_PHOTO'
    ];

    $result = [];
    $res = \CIBlockElement::GetList(['SORT' => 'ASC'], $filter, false, false, $select);
    
    while ($fields = $res->GetNext()) {
        // Определяем тип товара по наличию IS_RESALE
        $isResale = !empty($fields['PROPERTY_IS_RESALE_ENUM_ID']);
        
        // Определяем тип товара
        $type = $isResale ? 'resale' : 'produced';
        
        // Фильтруем по типу, если указан
        if (!empty($data['type']) && $data['type'] !== 'all') {
            if ($data['type'] !== $type) {
                continue;
            }
        }
        
        $item = [
            'id' => (int)$fields['ID'],
            'name' => $fields['NAME'],
            'type' => $type,
            'categoryId' => $fields['IBLOCK_SECTION_ID'] ? (int)$fields['IBLOCK_SECTION_ID'] : null,
            'code' => $fields['CODE'],
            'active' => ($fields['ACTIVE'] === 'Y'),
            'sellingPrice' => (float)$fields['PROPERTY_SELLING_PRICE_VALUE'],
            'photo' => $fields['PROPERTY_PHOTO_VALUE']
                    ? \CFile::GetPath($fields['PROPERTY_PHOTO_VALUE'])
                    : null,
        ];
        
        if ($isResale) {
            // Товар для перепродажи
            $item['unit'] = $fields['PROPERTY_UNIT_VALUE'];
            $item['costPrice'] = (float)$fields['PROPERTY_COST_PRICE_VALUE'];
            $item['currentStock'] = (float)$fields['PROPERTY_CURRENT_STOCK_VALUE'];
            $item['minStock'] = (float)$fields['PROPERTY_MIN_STOCK_VALUE'];
        }
        
        $result[] = $item;
    }
    
    return $result;
}

    /**
     * Получить остатки (текущие остатки товаров)
     */
    public static function getStock($data = [])
    {
        // Пока просто возвращаем товары, можно добавить фильтр lowStock
        return self::getProducts($data);
    }

    /**
     * Получить историю движений товаров
     * @param array $data Параметры: productId, dateFrom, dateTo, limit, offset
     * @return array
     */
    public static function getStockHistory($data = [])
    {
        \Bitrix\Main\Loader::includeModule('highloadblock');
        
        $filter = [];
        if (!empty($data['productId'])) {
            $filter['=UF_PRODUCT_ID'] = (int)$data['productId'];
        }
        // ... остальные фильтры

        $movements = StockMovementHelper::getMovements($filter, ['UF_CREATED_AT' => 'DESC'], $limit, $offset);
        
        // Трансформируем каждый элемент
        $formatted = [];
        foreach ($movements as $movement) {
            $formatted[] = self::formatMovement($movement);
        }
        $limit = (int)($data['limit'] ?? 50);
        $offset = (int)($data['offset'] ?? 0);
        
        return [
            'data' => $formatted,
            'total' => StockMovementHelper::getCount($filter),
            'limit' => $limit,
            'offset' => $offset
        ];
    }
    

    /**
     * Добавить движение товара (приход/расход/списание)
     */
    public static function addMovement($data = [])
    {
        Loader::includeModule('highloadblock');
        Loader::includeModule('iblock');

        $required = ['productId', 'type', 'quantity'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \Exception("Field {$field} is required");
            }
        }

        $productId = (int)$data['productId'];
        $type = $data['type'];
        $quantity = (float)$data['quantity'];

        // Получаем текущий остаток
        $res = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => self::IBLOCK_PRODUCTS, 'ID' => $productId],
            false,
            false,
            ['ID', 'PROPERTY_CURRENT_STOCK']
        );
        $product = $res->Fetch();
        if (!$product) {
            throw new \Exception('Product not found');
        }

        $currentStock = (float)$product['PROPERTY_CURRENT_STOCK_VALUE'];
        $newStock = $currentStock;

        if ($type === 'income') {
            $newStock += $quantity;
        } elseif ($type === 'outcome' || $type === 'write-off') {
            if ($currentStock < $quantity) {
                throw new \Exception('Insufficient stock');
            }
            $newStock -= $quantity;
        } else {
            throw new \Exception('Invalid movement type');
        }

        // Получаем ID типа движения по XML_ID
        $typeId = self::getEnumId('UF_TYPE', $type);
        
        // Получаем ID типа документа
        $documentTypeId = null;
        if (!empty($data['documentType'])) {
            $documentTypeId = self::getEnumId('UF_DOCUMENT_TYPE', $data['documentType']);
        }

        // Получаем ID текущего пользователя
        global $USER;
        $userId = $USER->IsAuthorized() ? (int)$USER->GetID() : 0;

        $fields = [
            'UF_PRODUCT_ID' => $productId,
            'UF_TYPE' => $typeId,
            'UF_QUANTITY' => $quantity,
            'UF_PRICE' => (float)($data['price'] ?? 0),
            'UF_DOCUMENT_TYPE' => $documentTypeId,
            'UF_DOCUMENT_ID' => (int)($data['documentId'] ?? 0),
            'UF_COMMENT' => $data['comment'] ?? '',
            'UF_CREATED_BY' => $userId,
            'UF_CREATED_AT' => new DateTime(),
            'UF_SUPPLIER_ID' => (int)($data['supplierId'] ?? 0),
        ];

        $movementId = StockMovementHelper::addMovement($fields);

        \CIBlockElement::SetPropertyValuesEx(
            $productId,
            self::IBLOCK_PRODUCTS,
            ['CURRENT_STOCK' => $newStock]
        );

        return [
            'movementId' => $movementId,
            'newStock' => $newStock,
        ];
    }

    /**
     * Получить ID значения списка по значению
     */
    
     private static function getEnumId($fieldName, $xmlId)
    {
        if (empty($xmlId)) return null;
        
        $enum = \CUserFieldEnum::GetList([], [
            'USER_FIELD_NAME' => $fieldName,
            'XML_ID' => $xmlId
        ])->Fetch();
        
        return $enum ? (int)$enum['ID'] : null;
    }

    /**
     * Получить список заказов
     */
    public static function getOrders($data = [])
    {
        Loader::includeModule('highloadblock');
        $filter = [];
        if (!empty($data['status'])) {
            $filter['=UF_STATUS'] = $data['status'];
        }
        if (!empty($data['dateFrom'])) {
            $filter['>=UF_CREATED_AT'] = new DateTime($data['dateFrom']);
        }
        if (!empty($data['dateTo'])) {
            $filter['<=UF_CREATED_AT'] = new DateTime($data['dateTo']);
        }

        $limit = (int)($data['limit'] ?? 50);
        $offset = (int)($data['offset'] ?? 0);

        $orders = OrderHelper::getOrders($filter, ['ID' => 'DESC'], $limit, $offset);
        foreach ($orders as &$order) {
            $order['ITEMS'] = OrderItemHelper::getByOrderId($order['ID']);
        }
        return $orders;
    }

    /**
     * Создать заказ
     */
    public static function createOrder($data = [])
    {
        if (empty($data['items']) || !is_array($data['items'])) {
            throw new \Exception('Items array is required');
        }

        $orderFields = [
            'UF_TYPE' => $data['type'] ?? 'dine-in',
            'UF_TABLE_NUMBER' => (int)($data['tableNumber'] ?? 0),
            'UF_STATUS' => 'new',
            'UF_SUBTOTAL' => (float)($data['subtotal'] ?? 0),
            'UF_DISCOUNT' => (float)($data['discount'] ?? 0),
            'UF_TOTAL' => (float)($data['total'] ?? 0),
            'UF_PAYMENT_METHOD' => $data['paymentMethod'] ?? null,
            'UF_CREATED_BY' => (int)($data['userId'] ?? 1),
            'UF_COMMENT' => $data['comment'] ?? '',
        ];

        $orderId = OrderHelper::createOrder($orderFields);

        $items = [];
        foreach ($data['items'] as $it) {
            $items[] = [
                'UF_PRODUCT_ID' => (int)$it['productId'],
                'UF_QUANTITY' => (float)$it['quantity'],
                'UF_PRICE' => (float)$it['price'],
                'UF_DISCOUNT_PERCENT' => (float)($it['discountPercent'] ?? 0),
                'UF_COMMENT' => $it['comment'] ?? '',
                'UF_COOKING_STATUS' => 'pending',
            ];
        }
        $itemIds = OrderItemHelper::addItems($orderId, $items);

        return [
            'orderId' => $orderId,
            'itemIds' => $itemIds,
        ];
    }

    /**
     * Обновить статус заказа
     */
    public static function updateOrderStatus($data = [])
    {
        if (empty($data['orderId']) || empty($data['status'])) {
            throw new \Exception('orderId and status required');
        }
        OrderHelper::updateOrderStatus((int)$data['orderId'], $data['status']);
        return true;
    }

    /**
     * Обновить статус приготовления позиции
     */
    public static function updateOrderItemStatus($data = [])
    {
        if (empty($data['itemId']) || empty($data['status'])) {
            throw new \Exception('itemId and status required');
        }
        OrderItemHelper::updateCookingStatus((int)$data['itemId'], $data['status']);
        return true;
    }

    
    /**
     * Получить список рецептов
     */
    public static function getRecipes($data = [])
    {
        Loader::includeModule('highloadblock');
        Loader::includeModule('iblock');
        
        $hlId = self::HL_RECIPES;
        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity(
            \Bitrix\Highloadblock\HighloadBlockTable::getById($hlId)->fetch()
        );
        $dataClass = $entity->getDataClass();
        
        $filter = [];
        if (!empty($data['productId'])) {
            $filter['=UF_PRODUCT_ID'] = (int)$data['productId'];
        }
        
        $res = $dataClass::getList([
            'select' => ['*'],
            'filter' => $filter,
            'order' => ['ID' => 'DESC']
        ]);
        
        $result = [];
        while ($row = $res->fetch()) {
            // Получаем состав
            $items = self::getRecipeItems($row['ID']);
            
            // Получаем название готового блюда
            $productName = self::getProductName($row['UF_PRODUCT_ID']);
            
            $result[] = [
                'id' => (int)$row['ID'],
                'productId' => (int)$row['UF_PRODUCT_ID'],
                'productName' => $productName,
                'name' => $row['UF_NAME'],
                'outputWeight' => (float)$row['UF_OUTPUT_WEIGHT'],
                'outputUnit' => $row['UF_OUTPUT_UNIT'],
                'cookingTime' => (int)$row['UF_COOKING_TIME'],
                'instructions' => $row['UF_INSTRUCTIONS'] ?? '',
                'items' => $items,
                'photo' => $row['UF_PHOTO'] ?? null,
                'createdAt' => $row['UF_CREATED_AT'] ?? null,
            ];
        }
        
        return $result;
    }

    /**
     * Получить состав рецепта
     */
    private static function getRecipeItems($recipeId)
    {
        Loader::includeModule('highloadblock');
        
        $hlId = self::HL_RECIPE_ITEMS;
        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity(
            \Bitrix\Highloadblock\HighloadBlockTable::getById($hlId)->fetch()
        );
        $dataClass = $entity->getDataClass();
        
        $res = $dataClass::getList([
            'filter' => ['=UF_RECIPE_ID' => $recipeId],
            'order' => ['ID' => 'ASC']
        ]);
        
        $items = [];
        while ($row = $res->fetch()) {
            $items[] = [
                'id' => (int)$row['ID'],
                'itemType' => $row['UF_ITEM_TYPE'],
                'itemId' => (int)$row['UF_ITEM_ID'],
                'quantity' => (float)$row['UF_QUANTITY'],
                'unit' => $row['UF_UNIT'],
                'isOptional' => (bool)$row['UF_IS_OPTIONAL'],
            ];
        }
        
        return $items;
    }

    /**
     * Создать рецепт
     */
    public static function createRecipe($data = [])
    {
        Loader::includeModule('highloadblock');
        
        $required = ['productId', 'name', 'outputWeight', 'outputUnit', 'items'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \Exception("Field {$field} is required");
            }
        }
        
        // Создаём рецепт
        $hlId = self::HL_RECIPES;
        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity(
            \Bitrix\Highloadblock\HighloadBlockTable::getById($hlId)->fetch()
        );
        $dataClass = $entity->getDataClass();
        
        $fields = [
            'UF_PRODUCT_ID' => (int)$data['productId'],
            'UF_NAME' => $data['name'],
            'UF_OUTPUT_WEIGHT' => (float)$data['outputWeight'],
            'UF_OUTPUT_UNIT' => $data['outputUnit'],
            'UF_COOKING_TIME' => (int)($data['cookingTime'] ?? 0),
            'UF_INSTRUCTIONS' => $data['instructions'] ?? '',
        ];
        
        if (!empty($data['photo'])) {
            $fields['UF_PHOTO'] = self::saveBase64Image($data['photo']);
        }
        
        $result = $dataClass::add($fields);
        if (!$result->isSuccess()) {
            throw new \Exception(implode(', ', $result->getErrorMessages()));
        }
        
        $recipeId = $result->getId();
        
        // Сохраняем состав
        self::saveRecipeItems($recipeId, $data['items']);
        
        return ['recipeId' => $recipeId];
    }

    /**
     * Сохранить состав рецепта
     */
    private static function saveRecipeItems($recipeId, $items)
    {
        Loader::includeModule('highloadblock');
        
        $hlId = self::HL_RECIPE_ITEMS;
        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity(
            \Bitrix\Highloadblock\HighloadBlockTable::getById($hlId)->fetch()
        );
        $dataClass = $entity->getDataClass();
        
        // Удаляем старые
        $old = $dataClass::getList(['filter' => ['=UF_RECIPE_ID' => $recipeId]]);
        while ($item = $old->fetch()) {
            $dataClass::delete($item['ID']);
        }
        
        // Добавляем новые
        foreach ($items as $item) {
            $dataClass::add([
                'UF_RECIPE_ID' => $recipeId,
                'UF_ITEM_TYPE' => $item['itemType'],
                'UF_ITEM_ID' => (int)$item['itemId'],
                'UF_QUANTITY' => (float)$item['quantity'],
                'UF_UNIT' => $item['unit'],
                'UF_IS_OPTIONAL' => (int)($item['isOptional'] ?? 0)
            ]);
        }
    }

    /**
     * Обновить рецепт
     */
    public static function updateRecipe($data = [])
    {
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            throw new \Exception('Recipe ID required');
        }
        
        Loader::includeModule('highloadblock');
        
        $hlId = self::HL_RECIPES;
        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity(
            \Bitrix\Highloadblock\HighloadBlockTable::getById($hlId)->fetch()
        );
        $dataClass = $entity->getDataClass();
        
        $fields = [];
        if (!empty($data['productId'])) $fields['UF_PRODUCT_ID'] = (int)$data['productId'];
        if (!empty($data['name'])) $fields['UF_NAME'] = $data['name'];
        if (isset($data['outputWeight'])) $fields['UF_OUTPUT_WEIGHT'] = (float)$data['outputWeight'];
        if (!empty($data['outputUnit'])) $fields['UF_OUTPUT_UNIT'] = $data['outputUnit'];
        if (isset($data['cookingTime'])) $fields['UF_COOKING_TIME'] = (int)$data['cookingTime'];
        if (isset($data['instructions'])) $fields['UF_INSTRUCTIONS'] = $data['instructions'];
        
        if (!empty($fields)) {
            $dataClass::update($id, $fields);
        }
        
        // Обновляем состав
        if (isset($data['items'])) {
            self::saveRecipeItems($id, $data['items']);
        }
        
        return ['success' => true];
    }

    /**
     * Удалить рецепт
     */
    public static function deleteRecipe($data = [])
    {
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            throw new \Exception('Recipe ID required');
        }
        
        Loader::includeModule('highloadblock');
        
        // Удаляем состав
        $hlItems = self::HL_RECIPE_ITEMS;
        $entityItems = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity(
            \Bitrix\Highloadblock\HighloadBlockTable::getById($hlItems)->fetch()
        );
        $itemsClass = $entityItems->getDataClass();
        
        $old = $itemsClass::getList(['filter' => ['=UF_RECIPE_ID' => $id]]);
        while ($item = $old->fetch()) {
            $itemsClass::delete($item['ID']);
        }
        
        // Удаляем рецепт
        $hlId = self::HL_RECIPES;
        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity(
            \Bitrix\Highloadblock\HighloadBlockTable::getById($hlId)->fetch()
        );
        $dataClass = $entity->getDataClass();
        
        $dataClass::delete($id);
        
        return ['success' => true];
    }

    /**
     * Получить ингредиенты рецепта
     */
    private static function getRecipeIngredients($recipeId)
    {
        $hlBlockId = self::HL_RECIPE_INGREDIENTS;
        
        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity(
            \Bitrix\Highloadblock\HighloadBlockTable::getById($hlBlockId)->fetch()
        );
        $dataClass = $entity->getDataClass();
        
        $res = $dataClass::getList([
            'select' => ['*'],
            'filter' => ['=UF_RECIPE_ID' => $recipeId]
        ]);
        
        $ingredients = [];
        while ($row = $res->fetch()) {
            $ingredients[] = $row;
        }
        
        return $ingredients;
    }

    /**
     * Получить название товара по ID
     */
    private static function getProductName($productId)
    {
        if (!$productId) return null;
        
        \Bitrix\Main\Loader::includeModule('iblock');
        $res = \CIBlockElement::GetByID($productId);
        if ($product = $res->Fetch()) {
            return $product['NAME'];
        }
        return null;
    }

    

    


    
    /**
     * Рассчитать себестоимость блюда по рецепту
     */
    public static function calculateRecipeCost($data = [])
    {
        $recipeId = (int)($data['recipeId'] ?? 0);
        if (!$recipeId) {
            throw new \Exception('Recipe ID required');
        }
        
        $recipe = self::getRecipeById($recipeId);
        if (!$recipe) {
            throw new \Exception('Recipe not found');
        }
        
        $total = 0;
        foreach ($recipe['INGREDIENTS'] as $ing) {
            $product = self::getProductById($ing['UF_INGREDIENT_ID']);
            if (!$product) continue;
            
            // Рассчитываем стоимость одной базовой единицы
            $costPerBaseUnit = $product['costPrice'] / $product['baseRatio'];
            
            // Умножаем на количество в рецепте (уже в базовых единицах)
            $total += $costPerBaseUnit * $ing['UF_QUANTITY'];
        }
        
        return ['cost' => round($total, 2)];
    }

    /**
     * Списать ингредиенты по рецепту
     */
    public static function consumeRecipeIngredients($data = [])
    {
        $recipeId = (int)($data['recipeId'] ?? 0);
        $quantity = (int)($data['quantity'] ?? 1);
        
        if (!$recipeId) {
            throw new \Exception('Recipe ID required');
        }
        
        $recipe = self::getRecipeById($recipeId);
        if (!$recipe) {
            throw new \Exception('Recipe not found');
        }
        
        $results = [];
        foreach ($recipe['INGREDIENTS'] as $ing) {
            $product = self::getProductById($ing['UF_INGREDIENT_ID']);
            if (!$product) continue;
            
            // Сколько базовых единиц нужно (умножаем на количество порций)
            $baseUnitsNeeded = $ing['UF_QUANTITY'] * $quantity;
            
            // Переводим в единицы хранения
            $storageUnitsNeeded = $baseUnitsNeeded / $product['baseRatio'];
            
            // Списываем со склада
            $movementData = [
                'productId' => $product['id'],
                'type' => 'outcome',
                'quantity' => $storageUnitsNeeded,
                'documentType' => 'sale',
                'comment' => "Списание по рецепту {$recipe['UF_NAME']} ({$quantity} порц)"
            ];
            
            $result = self::addMovement($movementData);
            $results[] = $result;
        }
        
        return [
            'success' => true,
            'movements' => $results
        ];
    }

    /**
     * Вспомогательный метод для получения товара по ID
     */
    private static function getProductById($productId)
    {
        Loader::includeModule('iblock');
        
        $select = [
            'ID', 'NAME',
            'PROPERTY_COST_PRICE',
            'PROPERTY_BASE_RATIO',
            'PROPERTY_BASE_UNIT'
        ];
        
        $res = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => self::IBLOCK_PRODUCTS, 'ID' => $productId],
            false,
            false,
            $select
        );
        
        if ($fields = $res->GetNext()) {
            return [
                'id' => (int)$fields['ID'],
                'name' => $fields['NAME'],
                'costPrice' => (float)$fields['PROPERTY_COST_PRICE_VALUE'],
                'baseRatio' => (float)$fields['PROPERTY_BASE_RATIO_VALUE'] ?: 1,
                'baseUnit' => $fields['PROPERTY_BASE_UNIT_VALUE'],
            ];
        }
        
        return null;
    }

    /**
     * Форматирование рецепта для отправки на фронтенд
     */
    private static function formatRecipe($recipe)
    {
        // Получаем название готового блюда из инфоблока
        $productName = null;
        if (!empty($recipe['UF_PRODUCT_ID'])) {
            \Bitrix\Main\Loader::includeModule('iblock');
            $res = \CIBlockElement::GetByID($recipe['UF_PRODUCT_ID']);
            if ($product = $res->Fetch()) {
                $productName = $product['NAME'];
            }
        }

        return [
            'id' => (int)$recipe['ID'],
            'productId' => (int)$recipe['UF_PRODUCT_ID'],
            'productName' => $productName,
            'name' => $recipe['UF_NAME'],
            'outputWeight' => (float)$recipe['UF_OUTPUT_WEIGHT'],
            'outputUnit' => $recipe['UF_OUTPUT_UNIT'],
            'cookingTime' => isset($recipe['UF_COOKING_TIME']) ? (int)$recipe['UF_COOKING_TIME'] : null,
            'instructions' => $recipe['UF_INSTRUCTIONS'] ?? '',
            'ingredients' => self::formatIngredients($recipe['INGREDIENTS'] ?? []),
            'photo' => $recipe['UF_PHOTO'] ?? null,
            'createdAt' => $recipe['UF_CREATED_AT'] ?? null,
            'updatedAt' => $recipe['UF_UPDATED_AT'] ?? null,
        ];
    }

    /**
     * Форматирование ингредиентов
     */
    private static function formatIngredients($ingredients)
    {
        $result = [];
        foreach ($ingredients as $ing) {
            // Получаем название ингредиента из инфоблока
            $ingredientName = null;
            if (!empty($ing['UF_INGREDIENT_ID'])) {
                \Bitrix\Main\Loader::includeModule('iblock');
                $res = \CIBlockElement::GetByID($ing['UF_INGREDIENT_ID']);
                if ($product = $res->Fetch()) {
                    $ingredientName = $product['NAME'];
                }
            }

            $result[] = [
                'id' => (int)$ing['ID'],
                'recipeId' => (int)$ing['UF_RECIPE_ID'],
                'ingredientId' => (int)$ing['UF_INGREDIENT_ID'],
                'ingredientName' => $ingredientName,
                'quantity' => (float)$ing['UF_QUANTITY'],
                'unit' => $ing['UF_UNIT'],
                'isOptional' => (bool)$ing['UF_IS_OPTIONAL'],
                'cost' => isset($ing['UF_COST']) ? (float)$ing['UF_COST'] : null,
            ];
        }
        return $result;
    }

    /**
     * Сохранить base64 изображение
     */
    private static function saveBase64Image($base64)
    {
        $data = explode(',', $base64);
        $image = base64_decode($data[1]);
        
        $fileName = 'recipe_' . time() . '.jpg';
        $filePath = $_SERVER['DOCUMENT_ROOT'] . '/upload/recipes/' . $fileName;
        
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }
        
        file_put_contents($filePath, $image);
        
        return '/upload/recipes/' . $fileName;
    }

    /**
     * Получить список категорий (разделов) для товаров
     */
    public static function getCategories($data = [])
    {
        \Bitrix\Main\Loader::includeModule('iblock');
        
        // Используем инфоблок готовых блюд для категорий
        $iblockId = self::IBLOCK_PRODUCTS;
        
        $result = [];
        $res = \CIBlockSection::GetList(
            ['SORT' => 'ASC'],
            ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'],
            false,
            ['ID', 'NAME', 'CODE', 'SORT', 'DEPTH_LEVEL', 'IBLOCK_SECTION_ID']
        );
        
        while ($section = $res->Fetch()) {
            $result[] = [
                'id' => (int)$section['ID'],
                'name' => $section['NAME'],
                'code' => $section['CODE'],
                'sortOrder' => (int)$section['SORT'],
                'parentId' => $section['IBLOCK_SECTION_ID'] ? (int)$section['IBLOCK_SECTION_ID'] : null,
                'depth' => (int)$section['DEPTH_LEVEL']
            ];
        }
        
        return $result;
    }

    /**
     * Создать новую категорию (раздел)
     */
    public static function createCategory($data = [])
    {
        \Bitrix\Main\Loader::includeModule('iblock');
        
        if (empty($data['name'])) {
            throw new \Exception('Название категории обязательно');
        }
        
        $iblockId = self::IBLOCK_PRODUCTS;
        
        $bs = new \CIBlockSection;
        
        $arFields = [
            'ACTIVE' => 'Y',
            'IBLOCK_ID' => $iblockId,
            'NAME' => $data['name'],
            'CODE' => \CUtil::translit($data['name'], 'ru', [
                'replace_space' => '-',
                'replace_other' => '-',
                'max_len' => 100,
                'change_case' => 'L'
            ]),
            'SORT' => (int)($data['sort'] ?? 500),
        ];
        
        $id = $bs->Add($arFields);
        
        if (!$id) {
            throw new \Exception('Ошибка создания категории: ' . $bs->LAST_ERROR);
        }
        
        return ['id' => $id];
    }

    /**
     * Обновить категорию
     */
    public static function updateCategory($data = [])
    {
        \Bitrix\Main\Loader::includeModule('iblock');
        
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            throw new \Exception('ID категории не указан');
        }
        
        $bs = new \CIBlockSection;
        
        $arFields = [];
        if (!empty($data['name'])) {
            $arFields['NAME'] = $data['name'];
            $arFields['CODE'] = \CUtil::translit($data['name'], 'ru', [
                'replace_space' => '-',
                'replace_other' => '-',
                'max_len' => 100,
                'change_case' => 'L'
            ]);
        }
        if (isset($data['sort'])) {
            $arFields['SORT'] = (int)$data['sort'];
        }
        
        if (empty($arFields)) {
            throw new \Exception('Нет данных для обновления');
        }
        
        $res = $bs->Update($id, $arFields);
        
        if (!$res) {
            throw new \Exception('Ошибка обновления категории: ' . $bs->LAST_ERROR);
        }
        
        return ['success' => true];
    }

    /**
     * Удалить категорию
     */
    public static function deleteCategory($data = [])
    {
        \Bitrix\Main\Loader::includeModule('iblock');
        
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            throw new \Exception('ID категории не указан');
        }
        
        $bs = new \CIBlockSection;
        
        // Проверяем, есть ли товары в категории
        $res = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => self::IBLOCK_PRODUCTS, 'SECTION_ID' => $id],
            false,
            false,
            ['ID']
        );
        
        if ($res->SelectedRowsCount() > 0) {
            throw new \Exception('Нельзя удалить категорию, в которой есть товары');
        }
        
        $res = $bs->Delete($id);
        
        if (!$res) {
            throw new \Exception('Ошибка удаления категории');
        }
        
        return ['success' => true];
    }

    /**
     * Создать новый товар
     */
    public static function createProduct($data = [])
{
    \Bitrix\Main\Loader::includeModule('iblock');

    // Логируем входные данные
    file_put_contents($_SERVER['DOCUMENT_ROOT'].'/create_debug.log', 
        "========== " . date('Y-m-d H:i:s') . " ==========\n", 
        FILE_APPEND);
    file_put_contents($_SERVER['DOCUMENT_ROOT'].'/create_debug.log', 
        "Входные данные:\n" . print_r($data, true) . "\n", 
        FILE_APPEND);

    $required = ['name', 'type'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new \Exception("Field {$field} is required");
        }
    }

    // Преобразуем тип (type) в ID варианта списка
    $typeEnumId = null;
    if (!empty($data['type'])) {
        $enumRes = \CIBlockPropertyEnum::GetList(
            [],
            ['IBLOCK_ID' => self::IBLOCK_PRODUCTS, 'CODE' => 'TYPE', 'XML_ID' => $data['type']]
        );
        if ($enum = $enumRes->Fetch()) {
            $typeEnumId = $enum['ID'];
            file_put_contents($_SERVER['DOCUMENT_ROOT'].'/create_debug.log', 
                "TYPE найден: ID={$enum['ID']}, XML_ID={$enum['XML_ID']}\n", 
                FILE_APPEND);
        } else {
            throw new \Exception("Invalid type value: {$data['type']}");
        }
    }

    $el = new \CIBlockElement();

    $code = \CUtil::translit($data['name'], 'ru', [
        'replace_space' => '-',
        'replace_other' => '-',
        'max_len' => 100,
        'change_case' => 'L'
    ]);

    // Определяем флаг перепродажи
    $isResale = ($data['type'] === 'resale') ? 'Y' : 'N';
    file_put_contents($_SERVER['DOCUMENT_ROOT'].'/create_debug.log', 
        "isResale вычислен: {$isResale}\n", 
        FILE_APPEND);

    // Получаем ID значения для IS_RESALE
    $isResaleEnumId = null;
    if ($isResale === 'Y') {
        $enumRes = \CIBlockPropertyEnum::GetList(
            [],
            ['IBLOCK_ID' => self::IBLOCK_PRODUCTS, 'CODE' => 'IS_RESALE', 'XML_ID' => 'Y']
        );
        if ($enum = $enumRes->Fetch()) {
            $isResaleEnumId = $enum['ID'];
            file_put_contents($_SERVER['DOCUMENT_ROOT'].'/create_debug.log', 
                "IS_RESALE ID найден: {$isResaleEnumId}\n", 
                FILE_APPEND);
        }
    }

    $arFields = [
        'IBLOCK_SECTION_ID' => (int)($data['categoryId'] ?? null),
        'IBLOCK_ID' => self::IBLOCK_PRODUCTS,
        'NAME' => $data['name'],
        'CODE' => $code,
        'ACTIVE' => 'Y',
        'PREVIEW_TEXT' => $data['description'] ?? '',
        'PROPERTY_VALUES' => [
            'TYPE' => $typeEnumId,
            'IS_RESALE' => $isResaleEnumId,  // Передаём ID, а не строку
            'SELLING_PRICE' => (float)($data['sellingPrice'] ?? 0),
            'CURRENT_STOCK' => (float)($data['currentStock'] ?? 0),
            'MIN_STOCK' => (float)($data['minStock'] ?? 0),
        ],
    ];

    file_put_contents($_SERVER['DOCUMENT_ROOT'].'/create_debug.log', 
        "Подготовленные поля:\n" . print_r($arFields, true) . "\n", 
        FILE_APPEND);

    $productId = $el->Add($arFields);
    if (!$productId) {
        $error = 'Ошибка создания товара: ' . $el->LAST_ERROR;
        file_put_contents($_SERVER['DOCUMENT_ROOT'].'/create_debug.log', 
            "Ошибка: {$error}\n", 
            FILE_APPEND);
        throw new \Exception($error);
    }

    // Проверяем, сохранилось ли IS_RESALE
    $checkRes = \CIBlockElement::GetProperty(
        self::IBLOCK_PRODUCTS,
        $productId,
        [],
        ['CODE' => 'IS_RESALE']
    );
    if ($prop = $checkRes->Fetch()) {
        file_put_contents($_SERVER['DOCUMENT_ROOT'].'/create_debug.log', 
            "IS_RESALE после сохранения: VALUE={$prop['VALUE']}, VALUE_ENUM={$prop['VALUE_ENUM']}\n", 
            FILE_APPEND);
    }

    file_put_contents($_SERVER['DOCUMENT_ROOT'].'/create_debug.log', 
        "✅ Товар создан с ID: {$productId}\n", 
        FILE_APPEND);
    file_put_contents($_SERVER['DOCUMENT_ROOT'].'/create_debug.log', 
        "========== КОНЕЦ ==========\n\n", 
        FILE_APPEND);

    return [
        'id' => $productId,
        'name' => $data['name'],
    ];
}

    /**
     * Получить ID значения свойства-списка по значению или XML_ID
     */
    private static function getPropertyEnumId($iblockId, $propertyCode, $value)
    {
        if (empty($value)) return null;
        
        // Пробуем найти по XML_ID
        $enumRes = \CIBlockPropertyEnum::GetList(
            [],
            ['IBLOCK_ID' => $iblockId, 'CODE' => $propertyCode, 'XML_ID' => $value]
        );
        if ($enum = $enumRes->Fetch()) {
            return $enum['ID'];
        }
        
        // Пробуем найти по VALUE
        $enumRes = \CIBlockPropertyEnum::GetList(
            [],
            ['IBLOCK_ID' => $iblockId, 'CODE' => $propertyCode, 'VALUE' => $value]
        );
        if ($enum = $enumRes->Fetch()) {
            return $enum['ID'];
        }
        
        return null;
    }

    /**
     * Удалить товар по ID
     */
    public static function deleteProduct($data = [])
    {
        \Bitrix\Main\Loader::includeModule('iblock');
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            throw new \Exception('ID товара не указан');
        }

        $res = \CIBlockElement::GetByID($id);
        if (!$res->Fetch()) {
            throw new \Exception('Товар не найден');
        }

        if (!\CIBlockElement::Delete($id)) {
            throw new \Exception('Ошибка удаления товара');
        }

        return ['success' => true, 'id' => $id];
    }

    /**
     * Обновить товар по ID (PUT)
     */
    public static function updateProduct($data = [])
    {
        \Bitrix\Main\Loader::includeModule('iblock');
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            throw new \Exception('ID товара не указан');
        }

        $res = \CIBlockElement::GetByID($id);
        if (!$res->Fetch()) {
            throw new \Exception('Товар не найден');
        }

        $updateFields = [];

        // Основные поля
        if (!empty($data['name'])) {
            $updateFields['NAME'] = $data['name'];
            $updateFields['CODE'] = \CUtil::translit($data['name'], 'ru', [
                'replace_space' => '-',
                'replace_other' => '-',
                'max_len' => 100,
                'change_case' => 'L'
            ]);
        }
        if (array_key_exists('description', $data)) {
            $updateFields['PREVIEW_TEXT'] = $data['description'];
        }

        // Категория (раздел)
        if (array_key_exists('categoryId', $data)) {
            $updateFields['IBLOCK_SECTION_ID'] = (int)$data['categoryId'] ?: null;
        }

        // Свойства товара
        $props = [];
        
        if (array_key_exists('type', $data)) {
            $props['TYPE'] = self::getEnumId('TYPE', $data['type']);
        }
        
        if (array_key_exists('unit', $data)) {
            $props['UNIT'] = self::getEnumId('UNIT', $data['unit']);
        }
        
        if (array_key_exists('baseUnit', $data)) {
            $props['BASE_UNIT'] = self::getEnumId('BASE_UNIT', $data['baseUnit']);
        }
        
        if (array_key_exists('baseRatio', $data)) {
            $props['BASE_RATIO'] = (float)$data['baseRatio'];
        }
        
        if (array_key_exists('costPrice', $data)) {
            $props['COST_PRICE'] = (float)$data['costPrice'];
        }
        
        if (array_key_exists('sellingPrice', $data)) {
            $props['SELLING_PRICE'] = (float)$data['sellingPrice'];
        }
        
        if (array_key_exists('currentStock', $data)) {
            $props['CURRENT_STOCK'] = (float)$data['currentStock'];
        }
        
        if (array_key_exists('minStock', $data)) {
            $props['MIN_STOCK'] = (float)$data['minStock'];
        }

        if (!empty($props)) {
            $updateFields['PROPERTY_VALUES'] = $props;
        }

        if (empty($updateFields)) {
            throw new \Exception('Нет данных для обновления');
        }

        $el = new \CIBlockElement();
        if (!$el->Update($id, $updateFields)) {
            throw new \Exception('Ошибка обновления товара: ' . $el->LAST_ERROR);
        }

        return ['success' => true, 'id' => $id];
    }



    /**
     * Преобразовать данные из HL-блока в формат для фронтенда
     */
    private static function formatMovement($row)
    {
        // Получаем название товара
        $productName = null;
        if (!empty($row['UF_PRODUCT_ID'])) {
            \Bitrix\Main\Loader::includeModule('iblock');
            $res = \CIBlockElement::GetByID($row['UF_PRODUCT_ID']);
            if ($product = $res->Fetch()) {
                $productName = $product['NAME'];
            }
        }

        return [
            'id' => (int)$row['ID'],
            'productId' => (int)$row['UF_PRODUCT_ID'],
            'productName' => $productName,
            'type' => self::getXmlById('UF_TYPE', $row['UF_TYPE']),
            'quantity' => (float)$row['UF_QUANTITY'],
            'price' => (float)($row['UF_PRICE'] ?? 0),
            'documentType' => self::getXmlById('UF_DOCUMENT_TYPE', $row['UF_DOCUMENT_TYPE']),
            'documentId' => (int)($row['UF_DOCUMENT_ID'] ?? 0),
            'comment' => $row['UF_COMMENT'] ?? '',
            'createdBy' => (int)($row['UF_CREATED_BY'] ?? 0),
            'createdAt' => $row['UF_CREATED_AT'] instanceof DateTime 
                ? $row['UF_CREATED_AT']->format('c') 
                : (string)$row['UF_CREATED_AT'],
        ];
    }

    /**
     * Получить XML_ID значения списка по ID
     */
    private static function getXmlById($fieldName, $id)
    {
        if (empty($id)) return null;
        
        $enum = \CUserFieldEnum::GetList([], [
            'USER_FIELD_NAME' => $fieldName,
            'ID' => (int)$id
        ])->Fetch();
        
        return $enum ? $enum['XML_ID'] : null;
    }

    /**
     * Преобразовать ID типа движения в строковое значение
     */
    private static function mapMovementType($typeId)
    {
        $map = [
            1 => 'income',
            2 => 'outcome',
            3 => 'write-off',
            // добавьте другие соответствия
        ];
        return $map[(int)$typeId] ?? 'unknown';
    }

    /**
     * Преобразовать ID типа документа в строковое значение
     */
    private static function mapDocumentType($typeId)
    {
        $map = [
            1 => 'manual',
            2 => 'invoice',
            3 => 'sale',
            // добавьте другие соответствия
        ];
        return $map[(int)$typeId] ?? 'manual';
    }

    public static function calculateIngredientCost($productId, $quantityInRecipe, $recipeUnit)
    {
        $product = self::getProductById($productId);
        
        // Если единица в рецепте совпадает с базовой единицей товара
        if ($recipeUnit === $product['BASE_UNIT']) {
            // Стоимость одной базовой единицы
            $costPerBaseUnit = $product['costPrice'] / $product['baseRatio'];
            return $costPerBaseUnit * $quantityInRecipe;
        }
        
        // Если нужно дополнительное преобразование (например, рецепт в граммах, а базовый в мл)
        // Тут нужна логика конвертации
        throw new Exception('Несоответствие единиц измерения');
    }

    public static function consumeIngredients($recipeId, $quantity = 1)
    {
        $recipe = self::getRecipeById($recipeId);
        
        foreach ($recipe['ingredients'] as $ing) {
            $product = self::getProductById($ing['ingredientId']);
            
            // Пересчитываем количество в единицы хранения
            if ($ing['unit'] === $product['BASE_UNIT']) {
                // Сколько базовых единиц нужно
                $baseUnitsNeeded = $ing['quantity'] * $quantity;
                
                // Переводим в единицы хранения
                $storageUnitsNeeded = $baseUnitsNeeded / $product['baseRatio'];
                
                // Списание со склада
                StockMovementHelper::addMovement([
                    'UF_PRODUCT_ID' => $product['id'],
                    'UF_TYPE' => 'outcome',
                    'UF_QUANTITY' => $storageUnitsNeeded,
                    'UF_COMMENT' => "Списание по рецепту {$recipe['name']}"
                ]);
            }
        }
    }


    /**
     * Получить всех поставщиков
     */
    public static function getSuppliers($data = [])
    {
        \Bitrix\Main\Loader::includeModule('highloadblock');
        
        $hlBlockId = self::HL_SUPPLIERS; // ID HL-блока Suppliers
        
        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity(
            \Bitrix\Highloadblock\HighloadBlockTable::getById($hlBlockId)->fetch()
        );
        $dataClass = $entity->getDataClass();
        
        $res = $dataClass::getList([
            'select' => ['*'],
            'order' => ['UF_NAME' => 'ASC']
        ]);
        
        $result = [];
        while ($row = $res->fetch()) {
            $result[] = [
                'id' => (int)$row['ID'],
                'name' => $row['UF_NAME'],
                'phone' => $row['UF_PHONE'] ?? '',
                'email' => $row['UF_EMAIL'] ?? '',
                'address' => $row['UF_ADDRESS'] ?? '',
                'inn' => $row['UF_INN'] ?? '',
                'kpp' => $row['UF_KPP'] ?? '',
                'comment' => $row['UF_COMMENT'] ?? '',
                'createdAt' => $row['UF_CREATED_AT'] instanceof DateTime 
                    ? $row['UF_CREATED_AT']->format('c') 
                    : $row['UF_CREATED_AT']
            ];
        }
        
        return $result;
    }

    /**
     * Создать поставщика
     */
    public static function createSupplier($data = [])
    {
        \Bitrix\Main\Loader::includeModule('highloadblock');
        
        if (empty($data['name'])) {
            throw new \Exception('Название поставщика обязательно');
        }
        
        $hlBlockId = self::HL_SUPPLIERS;
        
        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity(
            \Bitrix\Highloadblock\HighloadBlockTable::getById($hlBlockId)->fetch()
        );
        $dataClass = $entity->getDataClass();
        
        $fields = [
            'UF_NAME' => $data['name'],
            'UF_PHONE' => $data['phone'] ?? '',
            'UF_EMAIL' => $data['email'] ?? '',
            'UF_ADDRESS' => $data['address'] ?? '',
            'UF_INN' => $data['inn'] ?? '',
            'UF_KPP' => $data['kpp'] ?? '',
            'UF_COMMENT' => $data['comment'] ?? '',
            'UF_CREATED_AT' => new DateTime(),
        ];
        
        $result = $dataClass::add($fields);
        
        if (!$result->isSuccess()) {
            throw new \Exception(implode(', ', $result->getErrorMessages()));
        }
        
        return ['id' => $result->getId()];
    }

    /**
     * Обновить поставщика
     */
    public static function updateSupplier($data = [])
    {
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            throw new \Exception('ID поставщика не указан');
        }
        
        \Bitrix\Main\Loader::includeModule('highloadblock');
        
        $hlBlockId = self::HL_SUPPLIERS;
        
        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity(
            \Bitrix\Highloadblock\HighloadBlockTable::getById($hlBlockId)->fetch()
        );
        $dataClass = $entity->getDataClass();
        
        // Проверяем существование
        $existing = $dataClass::getById($id)->fetch();
        if (!$existing) {
            throw new \Exception('Поставщик не найден');
        }
        
        $fields = [];
        if (!empty($data['name'])) $fields['UF_NAME'] = $data['name'];
        if (isset($data['phone'])) $fields['UF_PHONE'] = $data['phone'];
        if (isset($data['email'])) $fields['UF_EMAIL'] = $data['email'];
        if (isset($data['address'])) $fields['UF_ADDRESS'] = $data['address'];
        if (isset($data['inn'])) $fields['UF_INN'] = $data['inn'];
        if (isset($data['kpp'])) $fields['UF_KPP'] = $data['kpp'];
        if (isset($data['comment'])) $fields['UF_COMMENT'] = $data['comment'];
        
        if (empty($fields)) {
            throw new \Exception('Нет данных для обновления');
        }
        
        $result = $dataClass::update($id, $fields);
        
        if (!$result->isSuccess()) {
            throw new \Exception(implode(', ', $result->getErrorMessages()));
        }
        
        return ['success' => true];
    }

    /**
     * Удалить поставщика
     */
    public static function deleteSupplier($data = [])
    {
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            throw new \Exception('ID поставщика не указан');
        }
        
        \Bitrix\Main\Loader::includeModule('highloadblock');
        
        $hlBlockId = self::HL_SUPPLIERS;
        
        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity(
            \Bitrix\Highloadblock\HighloadBlockTable::getById($hlBlockId)->fetch()
        );
        $dataClass = $entity->getDataClass();
        
        $result = $dataClass::delete($id);
        
        if (!$result->isSuccess()) {
            throw new \Exception(implode(', ', $result->getErrorMessages()));
        }
        
        return ['success' => true];
    }

    /**
     * Получить историю цен поставщика по товару с фильтрацией по дате
     */
    public static function getSupplierPriceHistory($data = [])
    {
        $supplierId = (int)($data['supplierId'] ?? 0);
        $productId = (int)($data['productId'] ?? 0);
        $period = $data['period'] ?? 'month'; // week, month, year, all
        $customStartDate = $data['startDate'] ?? null; // для кастомного периода
        $customEndDate = $data['endDate'] ?? null;
        
        if (!$supplierId || !$productId) {
            throw new \Exception('supplierId и productId обязательны');
        }
        
        \Bitrix\Main\Loader::includeModule('highloadblock');
        \Bitrix\Main\Loader::includeModule('iblock');
        
        $hlBlockId = self::HL_STOCK_MOVEMENTS;
        
        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity(
            \Bitrix\Highloadblock\HighloadBlockTable::getById($hlBlockId)->fetch()
        );
        $dataClass = $entity->getDataClass();
        
        // Базовый фильтр
        $filter = [
            '=UF_PRODUCT_ID' => $productId,
            '=UF_SUPPLIER_ID' => $supplierId,
            '=UF_TYPE' => self::getEnumId('UF_TYPE', 'income'),
        ];
        
        // Добавляем фильтр по дате
        $dateFilter = self::getDateFilter($period, $customStartDate, $customEndDate);
        if (!empty($dateFilter)) {
            $filter = array_merge($filter, $dateFilter);
        }
        
        // Получаем движения
        $res = $dataClass::getList([
            'select' => ['*'],
            'filter' => $filter,
            'order' => ['UF_CREATED_AT' => 'ASC']
        ]);
        
        $history = [];
        while ($row = $res->fetch()) {
            $history[] = [
                'date' => $row['UF_CREATED_AT'] instanceof DateTime 
                    ? $row['UF_CREATED_AT']->format('Y-m-d H:i:s')
                    : $row['UF_CREATED_AT'],
                'quantity' => (float)$row['UF_QUANTITY'],
                'price' => (float)$row['UF_PRICE'],
                'documentId' => (int)$row['UF_DOCUMENT_ID'],
                'comment' => $row['UF_COMMENT'] ?? ''
            ];
        }
        
        // Получаем статистику
        $stats = self::calculatePriceStats($history);
        
        return [
            'productId' => $productId,
            'productName' => self::getProductName($productId),
            'supplierId' => $supplierId,
            'supplierName' => self::getSupplierName($supplierId),
            'period' => $period,
            'history' => $history,
            'stats' => $stats
        ];
    }

    /**
     * Получить фильтр по дате
     */
    private static function getDateFilter($period, $customStartDate = null, $customEndDate = null)
    {
        $filter = [];
        
        switch ($period) {
            case 'week':
                $startDate = new \Bitrix\Main\Type\DateTime();
                $startDate->add('-7 days');  // ✅ правильный синтаксис
                $filter['>=UF_CREATED_AT'] = $startDate;
                break;
                
            case 'month':
                $startDate = new \Bitrix\Main\Type\DateTime();
                $startDate->add('-30 days');
                $filter['>=UF_CREATED_AT'] = $startDate;
                break;
                
            case 'year':
                $startDate = new \Bitrix\Main\Type\DateTime();
                $startDate->add('-365 days');
                $filter['>=UF_CREATED_AT'] = $startDate;
                break;
                
            case 'custom':
                if ($customStartDate) {
                    $filter['>=UF_CREATED_AT'] = new \Bitrix\Main\Type\DateTime($customStartDate);
                }
                if ($customEndDate) {
                    $filter['<=UF_CREATED_AT'] = new \Bitrix\Main\Type\DateTime($customEndDate);
                }
                break;
                
            case 'all':
            default:
                // без фильтра по дате
                break;
        }
        
        return $filter;
    }

    /**
     * Рассчитать статистику по ценам
     */
    private static function calculatePriceStats($history)
    {
        if (empty($history)) {
            return [
                'min' => 0,
                'max' => 0,
                'avg' => 0,
                'first' => 0,
                'last' => 0,
                'trend' => 0
            ];
        }
        
        $prices = array_column($history, 'price');
        $min = min($prices);
        $max = max($prices);
        $avg = array_sum($prices) / count($prices);
        $first = $prices[0];
        $last = $prices[count($prices) - 1];
        $trend = $last - $first;
        
        return [
            'min' => $min,
            'max' => $max,
            'avg' => $avg,
            'first' => $first,
            'last' => $last,
            'trend' => $trend,
            'trendPercent' => $first ? round(($trend / $first) * 100, 2) : 0
        ];
    }

    /**
     * Сравнить цены всех поставщиков на товар
     */
    public static function compareSuppliersPrices($data = [])
    {
        $productId = (int)($data['productId'] ?? 0);
        if (!$productId) {
            throw new \Exception('productId обязателен');
        }
        
        \Bitrix\Main\Loader::includeModule('highloadblock');
        
        $hlBlockId = self::HL_STOCK_MOVEMENTS;
        
        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity(
            \Bitrix\Highloadblock\HighloadBlockTable::getById($hlBlockId)->fetch()
        );
        $dataClass = $entity->getDataClass();
        
        // Получаем последние цены от каждого поставщика
        $res = $dataClass::getList([
            'select' => ['UF_SUPPLIER_ID', 'UF_PRICE', 'UF_CREATED_AT'],
            'filter' => [
                '=UF_PRODUCT_ID' => $productId,
                '=UF_TYPE' => 'income',
                '=UF_DOCUMENT_TYPE' => 'invoice',
                '>UF_SUPPLIER_ID' => 0
            ],
            'order' => ['UF_CREATED_AT' => 'DESC']
        ]);
        
        $suppliers = [];
        $seen = [];
        
        while ($row = $res->fetch()) {
            $supplierId = (int)$row['UF_SUPPLIER_ID'];
            if (!in_array($supplierId, $seen)) {
                $seen[] = $supplierId;
                $suppliers[] = [
                    'supplierId' => $supplierId,
                    'supplierName' => self::getSupplierName($supplierId),
                    'lastPrice' => (float)$row['UF_PRICE'],
                    'lastDate' => $row['UF_CREATED_AT'] instanceof DateTime 
                        ? $row['UF_CREATED_AT']->format('Y-m-d')
                        : $row['UF_CREATED_AT']
                ];
            }
        }
        
        return $suppliers;
    }

    /**
     * Получить название поставщика по ID
     */
    private static function getSupplierName($supplierId)
    {
        if (!$supplierId) return null;
        
        \Bitrix\Main\Loader::includeModule('highloadblock');
        
        $hlBlockId = self::HL_SUPPLIERS;
        
        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity(
            \Bitrix\Highloadblock\HighloadBlockTable::getById($hlBlockId)->fetch()
        );
        $dataClass = $entity->getDataClass();
        
        $supplier = $dataClass::getById($supplierId)->fetch();
        return $supplier ? $supplier['UF_NAME'] : null;
    }

    /**
     * Получить XML_ID значения списка по значению (VALUE)
     */
    private static function getEnumXmlId($fieldName, $value)
    {
        if (empty($value)) return null;
        
        // Ищем по VALUE
        $enum = \CUserFieldEnum::GetList([], [
            'USER_FIELD_NAME' => $fieldName,
            'VALUE' => $value
        ])->Fetch();
        
        // Если не нашли, ищем по XML_ID
        if (!$enum) {
            $enum = \CUserFieldEnum::GetList([], [
                'USER_FIELD_NAME' => $fieldName,
                'XML_ID' => $value
            ])->Fetch();
        }
        
        return $enum ? $enum['XML_ID'] : null;
    }
    /**
     * Получить список ингредиентов
     */
    public static function getIngredients($data = [])
    {
        Loader::includeModule('iblock');
        $filter = ['IBLOCK_ID' => self::IBLOCK_INGREDIENTS, 'ACTIVE' => 'Y'];

        if (!empty($data['category'])) {
            $filter['SECTION_ID'] = (int)$data['category'];
        }

        $select = [
            'ID', 'NAME', 'IBLOCK_SECTION_ID', 'CODE', 'ACTIVE',
            'PROPERTY_UNIT',
            'PROPERTY_BASE_UNIT',
            'PROPERTY_BASE_RATIO',
            'PROPERTY_COST_PRICE',
            'PROPERTY_CURRENT_STOCK',
            'PROPERTY_MIN_STOCK',
            'PROPERTY_PHOTO'
        ];

        $result = [];
        $res = \CIBlockElement::GetList(['SORT' => 'ASC'], $filter, false, false, $select);
        while ($fields = $res->GetNext()) {
            // Получаем XML_ID для единиц
            $unitXmlId = self::getEnumXmlId('UNIT', $fields['PROPERTY_UNIT_VALUE']);
            $baseUnitXmlId = self::getEnumXmlId('BASE_UNIT', $fields['PROPERTY_BASE_UNIT_VALUE']);

            $result[] = [
                'id' => (int)$fields['ID'],
                'name' => $fields['NAME'],
                'categoryId' => $fields['IBLOCK_SECTION_ID'] ? (int)$fields['IBLOCK_SECTION_ID'] : null,
                'code' => $fields['CODE'],
                'active' => ($fields['ACTIVE'] === 'Y'),
                'unit' => $unitXmlId,
                'unitLabel' => $fields['PROPERTY_UNIT_VALUE'],
                'baseUnit' => $baseUnitXmlId,
                'baseUnitLabel' => $fields['PROPERTY_BASE_UNIT_VALUE'],
                'baseRatio' => (float)$fields['PROPERTY_BASE_RATIO_VALUE'],
                'costPrice' => (float)$fields['PROPERTY_COST_PRICE_VALUE'],
                'currentStock' => (float)$fields['PROPERTY_CURRENT_STOCK_VALUE'],
                'minStock' => (float)$fields['PROPERTY_MIN_STOCK_VALUE'],
                'photo' => $fields['PROPERTY_PHOTO_VALUE']
                        ? \CFile::GetPath($fields['PROPERTY_PHOTO_VALUE'])
                        : null,
            ];
        }
        return $result;
    }

    /**
     * Создать ингредиент
     */
    public static function createIngredient($data = [])
    {
        Loader::includeModule('iblock');

        $required = ['name', 'unit'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \Exception("Field {$field} is required");
            }
        }

        // Получаем ID значений списков
        $unitEnumId = self::getEnumId('UNIT', $data['unit']);
        $baseUnitEnumId = null;
        if (!empty($data['baseUnit'])) {
            $baseUnitEnumId = self::getEnumId('BASE_UNIT', $data['baseUnit']);
        }

        $el = new \CIBlockElement();

        $code = \CUtil::translit($data['name'], 'ru', [
            'replace_space' => '-',
            'replace_other' => '-',
            'max_len' => 100,
            'change_case' => 'L'
        ]);

        $arFields = [
            'IBLOCK_SECTION_ID' => (int)($data['categoryId'] ?? null),
            'IBLOCK_ID' => self::IBLOCK_INGREDIENTS,
            'NAME' => $data['name'],
            'CODE' => $code,
            'ACTIVE' => 'Y',
            'PREVIEW_TEXT' => $data['description'] ?? '',
            'PROPERTY_VALUES' => [
                'UNIT' => $unitEnumId,
                'BASE_UNIT' => $baseUnitEnumId,
                'BASE_RATIO' => (float)($data['baseRatio'] ?? 1),
                'COST_PRICE' => (float)($data['costPrice'] ?? 0),
                'CURRENT_STOCK' => (float)($data['currentStock'] ?? 0),
                'MIN_STOCK' => (float)($data['minStock'] ?? 0),
            ],
        ];

        $productId = $el->Add($arFields);
        if (!$productId) {
            throw new \Exception('Ошибка создания ингредиента: ' . $el->LAST_ERROR);
        }

        return [
            'id' => $productId,
            'name' => $data['name'],
        ];
    }

    /**
     * Обновить ингредиент
     */
    public static function updateIngredient($data = [])
    {
        Loader::includeModule('iblock');
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            throw new \Exception('ID не указан');
        }

        $updateFields = [];

        if (!empty($data['name'])) {
            $updateFields['NAME'] = $data['name'];
            $updateFields['CODE'] = \CUtil::translit($data['name'], 'ru', [
                'replace_space' => '-',
                'replace_other' => '-',
                'max_len' => 100,
                'change_case' => 'L'
            ]);
        }

        $props = [];
        if (!empty($data['unit'])) {
            $props['UNIT'] = self::getEnumId('UNIT', $data['unit']);
        }
        if (array_key_exists('baseUnit', $data)) {
            $props['BASE_UNIT'] = $data['baseUnit'] ? self::getEnumId('BASE_UNIT', $data['baseUnit']) : null;
        }
        if (array_key_exists('baseRatio', $data)) {
            $props['BASE_RATIO'] = (float)$data['baseRatio'];
        }
        if (array_key_exists('costPrice', $data)) {
            $props['COST_PRICE'] = (float)$data['costPrice'];
        }
        if (array_key_exists('currentStock', $data)) {
            $props['CURRENT_STOCK'] = (float)$data['currentStock'];
        }
        if (array_key_exists('minStock', $data)) {
            $props['MIN_STOCK'] = (float)$data['minStock'];
        }

        if (!empty($props)) {
            $updateFields['PROPERTY_VALUES'] = $props;
        }

        if (empty($updateFields)) {
            throw new \Exception('Нет данных для обновления');
        }

        $el = new \CIBlockElement();
        if (!$el->Update($id, $updateFields)) {
            throw new \Exception('Ошибка обновления: ' . $el->LAST_ERROR);
        }

        return ['success' => true];
    }

    /**
     * Удалить ингредиент
     */
    public static function deleteIngredient($data = [])
    {
        Loader::includeModule('iblock');
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            throw new \Exception('ID не указан');
        }

        if (!\CIBlockElement::Delete($id)) {
            throw new \Exception('Ошибка удаления');
        }

        return ['success' => true];
    }

    

    /**
     * Создать полуфабрикат
     */
    public static function createSemiFinished($data = [])
    {
        Loader::includeModule('iblock');
        Loader::includeModule('highloadblock');

        $required = ['name', 'unit'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \Exception("Field {$field} is required");
            }
        }

        $el = new \CIBlockElement();

        $code = \CUtil::translit($data['name'], 'ru', [
            'replace_space' => '-',
            'replace_other' => '-',
            'max_len' => 100,
            'change_case' => 'L'
        ]);

        $arFields = [
            'IBLOCK_ID' => self::IBLOCK_SEMI_FINISHED,
            'NAME' => $data['name'],
            'CODE' => $code,
            'ACTIVE' => 'Y',
            'PROPERTY_VALUES' => [
                'UNIT' => $data['unit'],
                'SELLING_PRICE' => (float)($data['sellingPrice'] ?? 0),
            ],
        ];

        $productId = $el->Add($arFields);
        if (!$productId) {
            throw new \Exception('Ошибка создания полуфабриката: ' . $el->LAST_ERROR);
        }

        // Сохраняем ингредиенты
        if (!empty($data['ingredients'])) {
            self::saveSemiFinishedIngredients($productId, $data['ingredients']);
        }

        return [
            'id' => $productId,
            'name' => $data['name'],
        ];
    }

    /**
     * Сохранить ингредиенты полуфабриката
     */
    private static function saveSemiFinishedIngredients($semiFinishedId, $ingredients)
    {
        Loader::includeModule('highloadblock');
        
        $hlId = self::HL_SEMI_RECIPES;
        $hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById($hlId)->fetch();
        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
        $dataClass = $entity->getDataClass();
        
        // Удаляем старые ингредиенты
        $old = $dataClass::getList(['filter' => ['=UF_SEMI_FINISHED_ID' => $semiFinishedId]]);
        while ($item = $old->fetch()) {
            $dataClass::delete($item['ID']);
        }
        
        // Добавляем новые
        foreach ($ingredients as $ing) {
            $dataClass::add([
                'UF_SEMI_FINISHED_ID' => $semiFinishedId,
                'UF_INGREDIENT_ID' => (int)$ing['ingredientId'],
                'UF_QUANTITY' => (float)$ing['quantity'],
                'UF_UNIT' => self::getIngredientBaseUnit($ing['ingredientId']) // получаем единицу из ингредиента
            ]);
        }
    }

    /**
     * Получить название ингредиента по ID
     */
    private static function getIngredientName($ingredientId)
    {
        if (!$ingredientId) return null;
        
        Loader::includeModule('iblock');
        
        $res = \CIBlockElement::GetByID($ingredientId);
        if ($item = $res->Fetch()) {
            return $item['NAME'];
        }
        return null;
    }

    /**
     * Получить базовую единицу ингредиента
     */
    private static function getIngredientBaseUnit($ingredientId)
    {
        if (!$ingredientId) return 'г';
        
        Loader::includeModule('iblock');
        
        $res = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => self::IBLOCK_INGREDIENTS, 'ID' => $ingredientId],
            false,
            false,
            ['ID', 'PROPERTY_BASE_UNIT']
        );
        
        if ($item = $res->GetNext()) {
            return $item['PROPERTY_BASE_UNIT_VALUE'] ?? 'г';
        }
        return 'г';
    }

    /**
     * Обновить полуфабрикат
     */
    public static function updateSemiFinished($data = [])
    {
        Loader::includeModule('iblock');
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            throw new \Exception('ID не указан');
        }

        $updateFields = [];

        if (!empty($data['name'])) {
            $updateFields['NAME'] = $data['name'];
            $updateFields['CODE'] = \CUtil::translit($data['name'], 'ru', [
                'replace_space' => '-',
                'replace_other' => '-',
                'max_len' => 100,
                'change_case' => 'L'
            ]);
        }

        $props = [];
        if (array_key_exists('unit', $data)) {
            $props['UNIT'] = $data['unit'];
        }
        if (array_key_exists('sellingPrice', $data)) {
            $props['SELLING_PRICE'] = (float)$data['sellingPrice'];
        }

        if (!empty($props)) {
            $updateFields['PROPERTY_VALUES'] = $props;
        }

        if (!empty($updateFields)) {
            $el = new \CIBlockElement();
            if (!$el->Update($id, $updateFields)) {
                throw new \Exception('Ошибка обновления: ' . $el->LAST_ERROR);
            }
        }

        // Обновляем ингредиенты
        if (isset($data['ingredients'])) {
            self::saveSemiFinishedIngredients($id, $data['ingredients']);
        }

        return ['success' => true];
    }

    /**
     * Удалить полуфабрикат
     */
    public static function deleteSemiFinished($data = [])
    {
        Loader::includeModule('iblock');
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            throw new \Exception('ID не указан');
        }

        if (!\CIBlockElement::Delete($id)) {
            throw new \Exception('Ошибка удаления');
        }

        return ['success' => true];
    }


    /**
     * Получить полуфабрикат с его составом
     */
    public static function getSemiFinished($data = [])
    {
        Loader::includeModule('iblock');
        Loader::includeModule('highloadblock');
        
        $filter = ['IBLOCK_ID' => self::IBLOCK_SEMI_FINISHED, 'ACTIVE' => 'Y'];
        $id = (int)($data['id'] ?? 0);
        if ($id) {
            $filter['ID'] = $id;
        }

        $select = [
            'ID', 'NAME', 'CODE', 'ACTIVE',
            'PROPERTY_UNIT',
            'PROPERTY_SELLING_PRICE',
            'PROPERTY_PHOTO'
        ];

        $result = [];
        $res = \CIBlockElement::GetList(['SORT' => 'ASC'], $filter, false, false, $select);
        while ($fields = $res->GetNext()) {
            // Получаем состав полуфабриката
            $recipe = self::getSemiFinishedRecipe($fields['ID']);
            
            // Рассчитываем себестоимость на основе ингредиентов
            $calculatedCost = self::calculateSemiFinishedCost($recipe);
            
            $item = [
                'id' => (int)$fields['ID'],
                'name' => $fields['NAME'],
                'code' => $fields['CODE'],
                'active' => ($fields['ACTIVE'] === 'Y'),
                'unit' => $fields['PROPERTY_UNIT_VALUE'] ?? $fields['PROPERTY_UNIT_ENUM_ID'] ?? null,
                'sellingPrice' => (float)$fields['PROPERTY_SELLING_PRICE_VALUE'],
                'costPrice' => $calculatedCost,
                'photo' => $fields['PROPERTY_PHOTO_VALUE']
                        ? \CFile::GetPath($fields['PROPERTY_PHOTO_VALUE'])
                        : null,
                'ingredients' => $recipe
            ];
            
            if ($id) {
                return $item; // Возвращаем один объект
            }
            $result[] = $item;
        }
        
        return $result;
    }

    /**
     * Получить состав полуфабриката
     */
    private static function getSemiFinishedRecipe($semiFinishedId)
    {
        Loader::includeModule('highloadblock');
        
        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity(
            \Bitrix\Highloadblock\HighloadBlockTable::getById(self::HL_SEMI_RECIPES)->fetch()
        );
        $dataClass = $entity->getDataClass();
        
        $res = $dataClass::getList([
            'select' => ['*'],
            'filter' => ['=UF_SEMI_FINISHED_ID' => $semiFinishedId]
        ]);
        
        $ingredients = [];
        while ($row = $res->fetch()) {
            // Получаем название ингредиента
            $ingredientName = self::getIngredientName($row['UF_INGREDIENT_ID']);
            
            $ingredients[] = [
                'id' => (int)$row['ID'],
                'ingredientId' => (int)$row['UF_INGREDIENT_ID'],
                'ingredientName' => $ingredientName,
                'quantity' => (float)$row['UF_QUANTITY'],
                'unit' => $row['UF_UNIT']
            ];
        }
        
        return $ingredients;
    }

    /**
     * Рассчитать себестоимость полуфабриката
     */
    private static function calculateSemiFinishedCost($ingredients)
    {
        $total = 0;
        foreach ($ingredients as $ing) {
            // Получаем цену ингредиента
            $ingredient = self::getIngredientById($ing['ingredientId']);
            if ($ingredient) {
                // Цена за базовую единицу ингредиента
                $pricePerBaseUnit = $ingredient['costPrice'] / $ingredient['baseRatio'];
                $total += $pricePerBaseUnit * $ing['quantity'];
            }
        }
        return $total;
    }

    /**
     * Получить ингредиент по ID
     */
    private static function getIngredientById($id)
    {
        Loader::includeModule('iblock');
        
        $res = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => self::IBLOCK_INGREDIENTS, 'ID' => $id],
            false,
            false,
            ['ID', 'NAME', 'PROPERTY_COST_PRICE', 'PROPERTY_BASE_RATIO', 'PROPERTY_BASE_UNIT']
        );
        
        if ($fields = $res->GetNext()) {
            return [
                'id' => (int)$fields['ID'],
                'name' => $fields['NAME'],
                'costPrice' => (float)$fields['PROPERTY_COST_PRICE_VALUE'],
                'baseRatio' => (float)$fields['PROPERTY_BASE_RATIO_VALUE'] ?: 1,
                'baseUnit' => $fields['PROPERTY_BASE_UNIT_VALUE']
            ];
        }
        return null;
    }
    
}