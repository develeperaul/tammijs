<?php
// /local/php_interface/classes/CustomRestMethods.php

use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;

class CustomRestMethods
{
    // Константы для идентификаторов (ЗАМЕНИТЕ НА СВОИ)
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
            'ID', 'NAME', 'CODE', 'ACTIVE',
            'PROPERTY_TYPE', 'PROPERTY_UNIT',
            'PROPERTY_COST_PRICE', 'PROPERTY_SELLING_PRICE',
            'PROPERTY_CURRENT_STOCK', 'PROPERTY_MIN_STOCK',
            'PROPERTY_CATEGORY', 'PROPERTY_PHOTO'
        ];

        $result = [];
        $res = \CIBlockElement::GetList(['SORT' => 'ASC'], $filter, false, false, $select);
        while ($item = $res->GetNextElement()) {
            $fields = $item->GetFields();
            $props = $item->GetProperties();
            $result[] = [
                'id' => (int)$fields['ID'],
                'name' => $fields['NAME'],
                'code' => $fields['CODE'],
                'active' => $fields['ACTIVE'] === 'Y',
                'type' => $props['TYPE']['VALUE'],
                'unit' => $props['UNIT']['VALUE'],
                'cost_price' => (float)$props['COST_PRICE']['VALUE'],
                'selling_price' => (float)$props['SELLING_PRICE']['VALUE'],
                'current_stock' => (float)$props['CURRENT_STOCK']['VALUE'],
                'min_stock' => (float)$props['MIN_STOCK']['VALUE'],
                'category_id' => (int)$props['CATEGORY']['VALUE'],
                'photo' => $props['PHOTO']['VALUE'] ? \CFile::GetPath($props['PHOTO']['VALUE']) : null,
            ];
        }

        return $result;
    }

    /**
     * Получить остатки (текущие остатки товаров)
     */
    public static function getStock($data = [])
    {
        // По сути то же самое, что и getProducts, но можно добавить фильтр по остаткам
        return self::getProducts($data);
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

        // Получаем текущий остаток из инфоблока
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

        // Сохраняем движение в HL-блок (используем ранее созданный хелпер)
        $fields = [
            'UF_PRODUCT_ID' => $productId,
            'UF_TYPE' => $type,
            'UF_QUANTITY' => $quantity,
            'UF_PRICE' => (float)($data['price'] ?? 0),
            'UF_DOCUMENT_TYPE' => $data['documentType'] ?? 'manual',
            'UF_DOCUMENT_ID' => (int)($data['documentId'] ?? 0),
            'UF_COMMENT' => $data['comment'] ?? '',
            'UF_CREATED_BY' => (int)($data['userId'] ?? 0),
            'UF_CREATED_AT' => new DateTime(),
        ];

        // Предполагаем, что класс StockMovementHelper уже загружен автозагрузкой
        $movementId = StockMovementHelper::addMovement($fields);

        // Обновляем остаток в инфоблоке
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
     * Получить список заказов
     */
    public static function getOrders($data = [])
    {
        Loader::includeModule('highloadblock');
        // Используем OrderHelper
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
        // Прямой запрос к HL-блоку Recipes
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
            // Для каждого рецепта можно подгрузить ингредиенты (опционально)
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
}