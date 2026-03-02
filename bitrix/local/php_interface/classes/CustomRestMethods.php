<?php
// /local/php_interface/classes/CustomRestMethods.php

use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;

class CustomRestMethods
{
    // Константы для идентификаторов (замените на свои)
    const IBLOCK_PRODUCTS = 1;           // ID инфоблока «Товары»
    const HL_ORDERS = 2;                 // ID HL-блока «Orders»
    const HL_ORDER_ITEMS = 3;            // ID HL-блока «OrderItems»
    const HL_RECIPES = 4;                // ID HL-блока «Recipes»
    const HL_RECIPE_INGREDIENTS = 5;     // ID HL-блока «RecipeIngredients»
    const HL_SUPPLIERS = 6;     // ID HL-блока Suppliers
    const HL_STOCK_MOVEMENTS = 1;        // ID HL-блока «StockMovements» (если используется)

    /**
     * Получить список товаров
     * @param array $data Параметры запроса (category, type и т.д.)
     * @return array
     */
    public static function getProducts($data = [])
    {
        Loader::includeModule('iblock');
        $filter = ['IBLOCK_ID' => self::IBLOCK_PRODUCTS, 'ACTIVE' => 'Y'];

        if (!empty($data['category'])) {
            $filter['SECTION_ID'] = (int)$data['category'];
        }
        if (!empty($data['type'])) {
            $filter['PROPERTY_TYPE'] = $data['type'];
        }

        $select = [
            'ID', 'NAME', 'IBLOCK_SECTION_ID', 'CODE', 'ACTIVE',
            'PROPERTY_TYPE',
            'PROPERTY_UNIT',
            'PROPERTY_BASE_UNIT',
            'PROPERTY_BASE_RATIO',
            'PROPERTY_COST_PRICE',
            'PROPERTY_SELLING_PRICE',
            'PROPERTY_CURRENT_STOCK',
            'PROPERTY_MIN_STOCK',
            'PROPERTY_PHOTO'
        ];

        $result = [];
        $res = \CIBlockElement::GetList(['SORT' => 'ASC'], $filter, false, false, $select);
        while ($fields = $res->GetNext()) {
            // Получаем XML_ID по значению типа
            $typeXmlId = null;
            if (!empty($fields['PROPERTY_TYPE_VALUE'])) {
                // Для свойств инфоблоков используем CIBlockPropertyEnum!
                $enum = \CIBlockPropertyEnum::GetList(
                    [],
                    [
                        'IBLOCK_ID' => self::IBLOCK_PRODUCTS,
                        'CODE' => 'TYPE',
                        'VALUE' => $fields['PROPERTY_TYPE_VALUE']
                    ]
                )->Fetch();
                
                $typeXmlId = $enum ? $enum['XML_ID'] : null;
            }
            // Получаем XML_ID для единицы хранения
            $unitXmlId = null;
            if (!empty($fields['PROPERTY_UNIT_VALUE'])) {
                $enum = \CIBlockPropertyEnum::GetList([], [
                    'USER_FIELD_NAME' => 'UNIT',
                    'VALUE' => $fields['PROPERTY_UNIT_VALUE']
                ])->Fetch();
                $unitXmlId = $enum ? $enum['XML_ID'] : null;
            }

            // Получаем XML_ID для базовой единицы
            $baseUnitXmlId = null;
            if (!empty($fields['PROPERTY_BASE_UNIT_VALUE'])) {
                $enum = \CIBlockPropertyEnum::GetList([], [
                    'USER_FIELD_NAME' => 'BASE_UNIT',
                    'VALUE' => $fields['PROPERTY_BASE_UNIT_VALUE']
                ])->Fetch();
                $baseUnitXmlId = $enum ? $enum['XML_ID'] : null;
            }

            $result[] = [
                'id'            => (int)$fields['ID'],
                'name'          => $fields['NAME'],
                'categoryId'    => $fields['IBLOCK_SECTION_ID'] ? (int)$fields['IBLOCK_SECTION_ID'] : null,
                'type'          => $typeXmlId,
                'typeLabel'     => $fields['PROPERTY_TYPE_VALUE'],
                'code'          => $fields['CODE'],
                'active'        => ($fields['ACTIVE'] === 'Y'),
                'unit'          => $unitXmlId,
                'unitLabel'     => $fields['PROPERTY_UNIT_VALUE'],
                'baseUnit'      => $baseUnitXmlId,
                'baseUnitLabel' => $fields['PROPERTY_BASE_UNIT_VALUE'],
                'baseRatio'     => (float)$fields['PROPERTY_BASE_RATIO_VALUE'],
                'costPrice'     => (float)$fields['PROPERTY_COST_PRICE_VALUE'],
                'sellingPrice'  => (float)$fields['PROPERTY_SELLING_PRICE_VALUE'],
                'currentStock'  => (float)$fields['PROPERTY_CURRENT_STOCK_VALUE'],
                'minStock'      => (float)$fields['PROPERTY_MIN_STOCK_VALUE'],
                'photo'         => $fields['PROPERTY_PHOTO_VALUE']
                                ? \CFile::GetPath($fields['PROPERTY_PHOTO_VALUE'])
                                : null,
            ];
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
        
        // Получаем ID типа документа, если передан
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
        \Bitrix\Main\Loader::includeModule('highloadblock');
        
        $hlBlockId = self::HL_RECIPES;
        
        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity(
            \Bitrix\Highloadblock\HighloadBlockTable::getById($hlBlockId)->fetch()
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
            // Получаем ингредиенты
            $row['INGREDIENTS'] = self::getRecipeIngredients($row['ID']);
            
            // Форматируем рецепт
            $result[] = self::formatRecipe($row);
        }
        
        return $result;
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
        \Bitrix\Main\Loader::includeModule('iblock');
        $res = \CIBlockElement::GetByID($productId);
        if ($product = $res->Fetch()) {
            return $product['NAME'];
        }
        return null;
    }

    /**
     * Создать рецепт
     */
    public static function createRecipe($data = [])
    {
        \Bitrix\Main\Loader::includeModule('highloadblock');
        
        $required = ['productId', 'name', 'outputWeight', 'outputUnit', 'ingredients'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \Exception("Field {$field} is required");
            }
        }
        
        // Создаём рецепт
        $hlBlockId = self::HL_RECIPES;
        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity(
            \Bitrix\Highloadblock\HighloadBlockTable::getById($hlBlockId)->fetch()
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
        
        // Если есть фото
        if (!empty($data['photo']) && strpos($data['photo'], 'data:image') === 0) {
            $fields['UF_PHOTO'] = self::saveBase64Image($data['photo']);
        }
        
        $result = $dataClass::add($fields);
        if (!$result->isSuccess()) {
            throw new \Exception(implode(', ', $result->getErrorMessages()));
        }
        
        $recipeId = $result->getId();
        
        // Добавляем ингредиенты
        $ingredientsHlId = self::HL_RECIPE_INGREDIENTS;
        $ingEntity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity(
            \Bitrix\Highloadblock\HighloadBlockTable::getById($ingredientsHlId)->fetch()
        );
        $ingDataClass = $ingEntity->getDataClass();
        
        foreach ($data['ingredients'] as $ing) {
            $ingFields = [
                'UF_RECIPE_ID' => $recipeId,
                'UF_INGREDIENT_ID' => (int)$ing['ingredientId'],
                'UF_QUANTITY' => (float)$ing['quantity'],
                'UF_UNIT' => $ing['unit'],
                'UF_IS_OPTIONAL' => (int)($ing['isOptional'] ?? 0)
            ];
            
            $ingDataClass::add($ingFields);
        }
        
        return ['recipeId' => $recipeId];
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
        
        \Bitrix\Main\Loader::includeModule('highloadblock');
        
        $hlBlockId = self::HL_RECIPES;
        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity(
            \Bitrix\Highloadblock\HighloadBlockTable::getById($hlBlockId)->fetch()
        );
        $dataClass = $entity->getDataClass();
        
        $fields = [];
        if (!empty($data['productId'])) {
            $fields['UF_PRODUCT_ID'] = (int)$data['productId'];
        }
        if (!empty($data['name'])) {
            $fields['UF_NAME'] = $data['name'];
        }
        if (isset($data['outputWeight'])) {
            $fields['UF_OUTPUT_WEIGHT'] = (float)$data['outputWeight'];
        }
        if (!empty($data['outputUnit'])) {
            $fields['UF_OUTPUT_UNIT'] = $data['outputUnit'];
        }
        if (isset($data['cookingTime'])) {
            $fields['UF_COOKING_TIME'] = (int)$data['cookingTime'];
        }
        if (isset($data['instructions'])) {
            $fields['UF_INSTRUCTIONS'] = $data['instructions'];
        }
        
        if (!empty($fields)) {
            $dataClass::update($id, $fields);
        }
        
        // Обновление ингредиентов (удалить старые, добавить новые)
        if (!empty($data['ingredients'])) {
            // Удаляем старые ингредиенты
            $ingHlId = self::HL_RECIPE_INGREDIENTS;
            $ingEntity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity(
                \Bitrix\Highloadblock\HighloadBlockTable::getById($ingHlId)->fetch()
            );
            $ingDataClass = $ingEntity->getDataClass();
            
            $old = $ingDataClass::getList(['filter' => ['=UF_RECIPE_ID' => $id]]);
            while ($item = $old->fetch()) {
                $ingDataClass::delete($item['ID']);
            }
            
            // Добавляем новые
            foreach ($data['ingredients'] as $ing) {
                $ingFields = [
                    'UF_RECIPE_ID' => $id,
                    'UF_INGREDIENT_ID' => (int)$ing['ingredientId'],
                    'UF_QUANTITY' => (float)$ing['quantity'],
                    'UF_UNIT' => $ing['unit'],
                    'UF_IS_OPTIONAL' => (int)($ing['isOptional'] ?? 0)
                ];
                $ingDataClass::add($ingFields);
            }
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
        
        \Bitrix\Main\Loader::includeModule('highloadblock');
        
        // Удаляем ингредиенты
        $ingHlId = self::HL_RECIPE_INGREDIENTS;
        $ingEntity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity(
            \Bitrix\Highloadblock\HighloadBlockTable::getById($ingHlId)->fetch()
        );
        $ingDataClass = $ingEntity->getDataClass();
        
        $ingredients = $ingDataClass::getList(['filter' => ['=UF_RECIPE_ID' => $id]]);
        while ($ing = $ingredients->fetch()) {
            $ingDataClass::delete($ing['ID']);
        }
        
        // Удаляем рецепт
        $hlBlockId = self::HL_RECIPES;
        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity(
            \Bitrix\Highloadblock\HighloadBlockTable::getById($hlBlockId)->fetch()
        );
        $dataClass = $entity->getDataClass();
        
        $dataClass::delete($id);
        
        return ['success' => true];
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
     * Получить список категорий (разделов инфоблока товаров)
     */
    public static function getCategories($data = [])
    {
        \Bitrix\Main\Loader::includeModule('iblock');
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
                'id'   => (int)$section['ID'],
                'name' => $section['NAME'],
            ];
        }
        return $result;
    }

    /**
     * Создать новый товар
     */
    public static function createProduct($data = [])
    {
        \Bitrix\Main\Loader::includeModule('iblock');

        $required = ['name', 'type', 'unit'];
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
                ['IBLOCK_ID' => self::IBLOCK_PRODUCTS, 'CODE' => 'TYPE', 'VALUE' => $data['type']]
            );
            if ($enum = $enumRes->Fetch()) {
                $typeEnumId = $enum['ID'];
            } else {
                throw new \Exception("Invalid type value: {$data['type']}");
            }
        }

        // Преобразуем единицу измерения (unit) в ID варианта списка
        $unitEnumId = null;
        if (!empty($data['unit'])) {
            $enumRes = \CIBlockPropertyEnum::GetList(
                [],
                ['IBLOCK_ID' => self::IBLOCK_PRODUCTS, 'CODE' => 'UNIT', 'VALUE' => $data['unit']]
            );
            if ($enum = $enumRes->Fetch()) {
                $unitEnumId = $enum['ID'];
            } else {
                throw new \Exception("Invalid unit value: {$data['unit']}");
            }
        }

        $baseUnitEnumId = null;
        if (!empty($data['baseUnit'])) {
            $enumRes = \CIBlockPropertyEnum::GetList(
                [],
                ['IBLOCK_ID' => self::IBLOCK_PRODUCTS, 'CODE' => 'BASE_UNIT', 'VALUE' => $data['baseUnit']]
            );
            if ($enum = $enumRes->Fetch()) {
                $baseUnitEnumId = $enum['ID'];
            } else {
                throw new \Exception("Invalid baseUnit value: {$data['baseUnit']}");
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
            'IBLOCK_SECTION_ID' => (int)($data['categoryId'] ?? null),
            'IBLOCK_ID' => self::IBLOCK_PRODUCTS,
            'NAME' => $data['name'],
            'CODE' => $code,
            'ACTIVE' => 'Y',
            'PREVIEW_TEXT' => $data['description'] ?? '',
            'PROPERTY_VALUES' => [
                'TYPE' => $typeEnumId,        // ✅ ID варианта
                'UNIT' => $unitEnumId,         // ✅ ID варианта
                'COST_PRICE' => (float)($data['costPrice'] ?? 0),
                'SELLING_PRICE' => (float)($data['sellingPrice'] ?? 0),
                'CURRENT_STOCK' => (float)($data['currentStock'] ?? 0),
                'MIN_STOCK' => (float)($data['minStock'] ?? 0),
                'BASE_UNIT' => $baseUnitEnumId,
                'BASE_RATIO' => (float)($data['baseRatio'] ?? 1),
            ],
        ];

        $productId = $el->Add($arFields);
        if (!$productId) {
            throw new \Exception('Ошибка создания товара: ' . $el->LAST_ERROR);
        }

        return [
            'id' => $productId,
            'name' => $data['name'],
        ];
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
     * Получить историю цен поставщика по товару
     */
    public static function getSupplierPriceHistory($data = [])
    {
        $supplierId = (int)($data['supplierId'] ?? 0);
        $productId = (int)($data['productId'] ?? 0);
        
        if (!$supplierId || !$productId) {
            throw new \Exception('supplierId и productId обязательны');
        }
        
        \Bitrix\Main\Loader::includeModule('highloadblock');
        
        // Получаем все движения товара от этого поставщика
        $hlBlockId = self::HL_STOCK_MOVEMENTS;
        
        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity(
            \Bitrix\Highloadblock\HighloadBlockTable::getById($hlBlockId)->fetch()
        );
        $dataClass = $entity->getDataClass();
        
        $res = $dataClass::getList([
            'select' => ['*'],
            'filter' => [
                '=UF_PRODUCT_ID' => $productId,
                '=UF_DOCUMENT_TYPE' => 'invoice',
                '=UF_TYPE' => 'income'
            ],
            'order' => ['UF_CREATED_AT' => 'ASC']
        ]);
        
        $history = [];
        while ($row = $res->fetch()) {
            // Здесь нужно будет связать с накладной и поставщиком
            // Это сложная часть - нужно добавить поле UF_SUPPLIER_ID в движения
            // Пока возвращаем пустой массив
        }
        
        return $history;
    }

    /**
     * Сравнить цены поставщиков на товар
     */
    public static function compareSuppliersPrices($data = [])
    {
        $productId = (int)($data['productId'] ?? 0);
        if (!$productId) {
            throw new \Exception('productId обязателен');
        }
        
        // TODO: сложная логика сравнения цен
        // Пока возвращаем заглушку
        return [];
    }
}