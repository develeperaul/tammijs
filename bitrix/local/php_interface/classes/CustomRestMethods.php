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
            'PROPERTY_COST_PRICE',
            'PROPERTY_SELLING_PRICE',
            'PROPERTY_CURRENT_STOCK',
            'PROPERTY_MIN_STOCK',
            'PROPERTY_PHOTO'
        ];

        $result = [];
        $res = \CIBlockElement::GetList(['SORT' => 'ASC'], $filter, false, false, $select);
        while ($fields = $res->GetNext()) {
            $result[] = [
                'id'            => (int)$fields['ID'],
                'name'          => $fields['NAME'],
                'categoryId'    => $fields['IBLOCK_SECTION_ID'] ? (int)$fields['IBLOCK_SECTION_ID'] : null,
                'type'          => $fields['PROPERTY_TYPE_VALUE'],
                'code'          => $fields['CODE'],
                'active'        => ($fields['ACTIVE'] === 'Y'),
                'unit'          => $fields['PROPERTY_UNIT_VALUE'],
                'costPrice'    => (float)$fields['PROPERTY_COST_PRICE_VALUE'],
                'sellingPrice' => (float)$fields['PROPERTY_SELLING_PRICE_VALUE'],
                'currentStock' => (float)$fields['PROPERTY_CURRENT_STOCK_VALUE'],
                'minStock'     => (float)$fields['PROPERTY_MIN_STOCK_VALUE'],
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
     * Получить ID значения списка по XML_ID
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
        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity(
            \Bitrix\Highloadblock\HighloadBlockTable::getById(self::HL_RECIPES)->fetch()
        );
        $dataClass = $entity->getDataClass();

        $res = $dataClass::getList([
            'select' => ['*'],
            'order' => ['ID' => 'ASC']
        ]);
        $recipes = [];
        while ($row = $res->fetch()) {
            $row['INGREDIENTS'] = self::getRecipeIngredients($row['ID']);
            $recipes[] = $row;
        }
        return $recipes;
    }

    /**
     * Создать рецепт с ингредиентами
     */
    public static function createRecipe($data = [])
    {
        if (empty($data['productId']) || empty($data['ingredients'])) {
            throw new \Exception('productId and ingredients required');
        }

        $recipeData = [
            'UF_PRODUCT_ID' => (int)$data['productId'],
            'UF_NAME' => $data['name'] ?? '',
            'UF_OUTPUT_WEIGHT' => (float)($data['outputWeight'] ?? 0),
            'UF_OUTPUT_UNIT' => $data['outputUnit'] ?? 'г',
            'UF_COOKING_TIME' => (int)($data['cookingTime'] ?? 0),
            'UF_INSTRUCTIONS' => $data['instructions'] ?? '',
        ];

        $ingredients = [];
        foreach ($data['ingredients'] as $ing) {
            $ingredients[] = [
                'UF_INGREDIENT_ID' => (int)$ing['ingredientId'],
                'UF_QUANTITY' => (float)$ing['quantity'],
                'UF_UNIT' => $ing['unit'],
                'UF_IS_OPTIONAL' => ($ing['isOptional'] ?? false) ? 1 : 0,
            ];
        }

        $recipeId = RecipeHelper::createRecipe($recipeData, $ingredients);
        return ['recipeId' => $recipeId];
    }

    /**
     * Рассчитать себестоимость блюда по рецепту
     */
    public static function calculateRecipeCost($data = [])
    {
        if (empty($data['recipeId'])) {
            throw new \Exception('recipeId required');
        }
        $cost = RecipeHelper::calculateCost((int)$data['recipeId']);
        return ['cost' => $cost];
    }

    /**
     * Получить заказы для кухни (статусы new, cooking)
     */
    public static function getKitchenOrders($data = [])
    {
        $orders = OrderHelper::getKitchenOrders();
        foreach ($orders as &$order) {
            $order['ITEMS'] = OrderItemHelper::getByOrderId($order['ID']);
        }
        return $orders;
    }

    /**
     * Вспомогательный метод для получения ингредиентов рецепта
     */
    private static function getRecipeIngredients($recipeId)
    {
        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity(
            \Bitrix\Highloadblock\HighloadBlockTable::getById(self::HL_RECIPE_INGREDIENTS)->fetch()
        );
        $dataClass = $entity->getDataClass();
        $res = $dataClass::getList([
            'filter' => ['=UF_RECIPE_ID' => $recipeId],
            'select' => ['*']
        ]);
        return $res->fetchAll();
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

        // Категория (раздел) обновляется отдельно, это не свойство
        if (array_key_exists('categoryId', $data)) {
            $updateFields['IBLOCK_SECTION_ID'] = (int)$data['categoryId'] ?: null;
        }

        // Свойства товара (кроме categoryId)
        $props = [];
        $propFields = ['type', 'unit', 'costPrice', 'sellingPrice', 'currentStock', 'minStock'];
        foreach ($propFields as $prop) {
            if (array_key_exists($prop, $data)) {
                $value = $data[$prop];
                
                // Для type и unit преобразуем в ID варианта списка
                if ($prop === 'type' || $prop === 'unit') {
                    $enumRes = \CIBlockPropertyEnum::GetList(
                        [],
                        ['IBLOCK_ID' => self::IBLOCK_PRODUCTS, 'CODE' => strtoupper($prop), 'VALUE' => $value]
                    );
                    if ($enum = $enumRes->Fetch()) {
                        $value = $enum['ID'];
                    } else {
                        throw new \Exception("Invalid {$prop} value: {$value}");
                    }
                } elseif (in_array($prop, ['costPrice', 'sellingPrice', 'currentStock', 'minStock'])) {
                    $value = (float)$value;
                }
                
                $props[strtoupper($prop)] = $value;
            }
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
}