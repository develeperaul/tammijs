<?php
// /local/php_interface/classes/CustomRestMethods.php

use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;

class CustomRestMethods
{
    // Константы для идентификаторов
    const IBLOCK_PRODUCTS = 1;           // ID инфоблока «Товары»
    const IBLOCK_INGREDIENTS = 3;        // Ингредиенты
    const IBLOCK_SEMI_FINISHED = 4;      // Полуфабрикаты
    
    const HL_ORDERS = 2;                 // ID HL-блока «Orders»
    const HL_ORDER_ITEMS = 3;            // ID HL-блока «OrderItems»
    const HL_RECIPES = 4;                // ID HL-блока «Recipes»
    const HL_RECIPE_INGREDIENTS = 5;     // ID HL-блока «RecipeIngredients»
    const HL_SUPPLIERS = 6;              // ID HL-блока Suppliers
    const HL_SEMI_RECIPES = 7;           // ID HL-блока SemiRecipes
    const HL_SEMI_RECIPE_INGREDIENTS = 10; // ID HL-блока SemiRecipeIngredients
    const HL_RECIPE_ITEMS = 8;           // ID HL-блока для состава рецептов
    const HL_STOCK_MOVEMENTS = 1;        // ID HL-блока «StockMovements»
    const HL_PRODUCTION_DETAILS = 9;     // ID HL-блока ProductionDetails
    
    // Кэш для DataClass HL-блоков
    private static $hlDataClasses = [];
    
    // Кэш для пользовательских полей
    private static $enumCache = [];
    private static $productCache = [];
    private static $ingredientCache = [];
    private static $supplierCache = [];
    
    // ==================== ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ ====================
    
    /**
     * Получить DataClass HL-блока с кэшированием
     * @param int $hlBlockId
     * @return string
     * @throws Exception
     */
    private static function getHlDataClass($hlBlockId)
    {
        if (!isset(self::$hlDataClasses[$hlBlockId])) {
            Loader::includeModule('highloadblock');
            
            $hlBlock = HighloadBlockTable::getById($hlBlockId)->fetch();
            if (!$hlBlock) {
                throw new Exception("HL-блок с ID {$hlBlockId} не найден");
            }
            
            $entity = HighloadBlockTable::compileEntity($hlBlock);
            self::$hlDataClasses[$hlBlockId] = $entity->getDataClass();
        }
        
        return self::$hlDataClasses[$hlBlockId];
    }
    
    /**
     * Получить ID значения списка по XML_ID с кэшированием
     * @param string $fieldName
     * @param string $xmlId
     * @return int|null
     */
    private static function getEnumId($fieldName, $xmlId)
    {
        if (empty($xmlId)) return null;
        
        $cacheKey = $fieldName . '_' . $xmlId;
        
        if (!isset(self::$enumCache[$cacheKey])) {
            $enum = \CUserFieldEnum::GetList([], [
                'USER_FIELD_NAME' => $fieldName,
                'XML_ID' => $xmlId
            ])->Fetch();
            
            self::$enumCache[$cacheKey] = $enum ? (int)$enum['ID'] : null;
        }
        
        return self::$enumCache[$cacheKey];
    }

    /**
     * Получить ID значения свойства-списка инфоблока
     * @param int $iblockId
     * @param string $propertyCode
     * @param string $value
     * @return int|null
     */
    private static function getIblockPropertyEnumId($iblockId, $propertyCode, $value)
    {
        if (empty($value)) return null;
        
        // Ищем по XML_ID
        $enumRes = \CIBlockPropertyEnum::GetList(
            [],
            ['IBLOCK_ID' => $iblockId, 'CODE' => $propertyCode, 'XML_ID' => $value]
        );
        if ($enum = $enumRes->Fetch()) {
            return (int)$enum['ID'];
        }
        
        // Ищем по VALUE
        $enumRes = \CIBlockPropertyEnum::GetList(
            [],
            ['IBLOCK_ID' => $iblockId, 'CODE' => $propertyCode, 'VALUE' => $value]
        );
        if ($enum = $enumRes->Fetch()) {
            return (int)$enum['ID'];
        }
        
        return null;
    }
    
    /**
     * Получить XML_ID значения списка по ID с кэшированием
     * @param string $fieldName
     * @param int $id
     * @return string|null
     */
    private static function getXmlById($fieldName, $id)
    {
        if (empty($id)) return null;
        
        $cacheKey = $fieldName . '_id_' . $id;
        
        if (!isset(self::$enumCache[$cacheKey])) {
            $enum = \CUserFieldEnum::GetList([], [
                'USER_FIELD_NAME' => $fieldName,
                'ID' => (int)$id
            ])->Fetch();
            
            self::$enumCache[$cacheKey] = $enum ? $enum['XML_ID'] : null;
        }
        
        return self::$enumCache[$cacheKey];
    }
    
    /**
     * Получить название товара по ID с кэшированием
     * @param int $productId
     * @return string|null
     */
    private static function getProductName($productId)
    {
        if (!$productId) return null;
        
        if (!isset(self::$productCache[$productId])) {
            Loader::includeModule('iblock');
            $res = \CIBlockElement::GetByID($productId);
            if ($product = $res->Fetch()) {
                self::$productCache[$productId] = $product['NAME'];
            } else {
                self::$productCache[$productId] = null;
            }
        }
        
        return self::$productCache[$productId];
    }
    
    /**
     * Получить товар по ID с кэшированием
     * @param int $productId
     * @param int $iblockId
     * @return array|null
     */
    private static function getProductById($productId, $iblockId = null)
    {
        if (!$productId) return null;
        
        $cacheKey = $productId . '_' . ($iblockId ?: 'any');
        
        if (!isset(self::$productCache[$cacheKey])) {
            Loader::includeModule('iblock');
            
            $filter = ['ID' => $productId];
            if ($iblockId) {
                $filter['IBLOCK_ID'] = $iblockId;
            }
            
            $select = [
                'ID', 'NAME',
                'PROPERTY_COST_PRICE',
                'PROPERTY_BASE_RATIO',
                'PROPERTY_BASE_UNIT',
                'PROPERTY_CURRENT_STOCK',
                'PROPERTY_UNIT'
            ];
            
            $res = \CIBlockElement::GetList([], $filter, false, false, $select);
            if ($fields = $res->GetNext()) {
                self::$productCache[$cacheKey] = [
                    'id' => (int)$fields['ID'],
                    'name' => $fields['NAME'],
                    'costPrice' => (float)$fields['PROPERTY_COST_PRICE_VALUE'],
                    'baseRatio' => (float)($fields['PROPERTY_BASE_RATIO_VALUE'] ?: 1),
                    'baseUnit' => $fields['PROPERTY_BASE_UNIT_VALUE'],
                    'currentStock' => (float)$fields['PROPERTY_CURRENT_STOCK_VALUE'],
                    'unit' => $fields['PROPERTY_UNIT_VALUE']
                ];
            } else {
                self::$productCache[$cacheKey] = null;
            }
        }
        
        return self::$productCache[$cacheKey];
    }
    
    /**
     * Получить название поставщика по ID
     * @param int $supplierId
     * @return string|null
     */
    private static function getSupplierName($supplierId)
    {
        if (!$supplierId) return null;
        
        if (!isset(self::$supplierCache[$supplierId])) {
            try {
                $dataClass = self::getHlDataClass(self::HL_SUPPLIERS);
                $supplier = $dataClass::getById($supplierId)->fetch();
                self::$supplierCache[$supplierId] = $supplier ? $supplier['UF_NAME'] : null;
            } catch (Exception $e) {
                self::$supplierCache[$supplierId] = null;
            }
        }
        
        return self::$supplierCache[$supplierId];
    }
    
    /**
     * Получить название ингредиента по ID
     * @param int $ingredientId
     * @return string|null
     */
    private static function getIngredientName($ingredientId)
    {
        return self::getProductName($ingredientId);
    }
    
    /**
     * Получить ингредиент по ID
     * @param int $id
     * @return array|null
     */
    private static function getIngredientById($id)
    {
        return self::getProductById($id, self::IBLOCK_INGREDIENTS);
    }
    
    /**
     * Получить фильтр по дате
     * @param string $period
     * @param string|null $customStartDate
     * @param string|null $customEndDate
     * @return array
     */
    private static function getDateFilter($period, $customStartDate = null, $customEndDate = null)
    {
        $filter = [];
        
        switch ($period) {
            case 'week':
                $startDate = new DateTime();
                $startDate->add('-7 days');
                $filter['>=UF_CREATED_AT'] = $startDate;
                break;
                
            case 'month':
                $startDate = new DateTime();
                $startDate->add('-30 days');
                $filter['>=UF_CREATED_AT'] = $startDate;
                break;
                
            case 'year':
                $startDate = new DateTime();
                $startDate->add('-365 days');
                $filter['>=UF_CREATED_AT'] = $startDate;
                break;
                
            case 'custom':
                if ($customStartDate) {
                    try {
                        $filter['>=UF_CREATED_AT'] = new DateTime($customStartDate);
                    } catch (Exception $e) {
                        // Игнорируем неверную дату
                    }
                }
                if ($customEndDate) {
                    try {
                        $filter['<=UF_CREATED_AT'] = new DateTime($customEndDate);
                    } catch (Exception $e) {
                        // Игнорируем неверную дату
                    }
                }
                break;
        }
        
        return $filter;
    }
    
    /**
     * Валидация входных данных
     * @param array $data
     * @param array $rules
     * @throws Exception
     */
    private static function validate($data, $rules)
    {
        foreach ($rules as $field => $rule) {
            if ($rule === 'required' && empty($data[$field])) {
                throw new Exception("Поле {$field} обязательно");
            }
        }
        return true;
    }
    
    /**
     * Сохранить base64 изображение
     * @param string $base64
     * @param string $type
     * @return string|null
     * @throws Exception
     */
    private static function saveBase64Image($base64, $type = 'recipe')
    {
        if (empty($base64)) return null;
        
        // Проверка размера (5 MB)
        if (strlen($base64) > 5 * 1024 * 1024) {
            throw new Exception('Слишком большой файл (макс. 5 MB)');
        }
        
        $data = explode(',', $base64);
        if (count($data) < 2) {
            throw new Exception('Неверный формат изображения');
        }
        
        $imageData = base64_decode($data[1]);
        if ($imageData === false) {
            throw new Exception('Ошибка декодирования изображения');
        }
        
        // Проверка формата
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $imageData);
        finfo_close($finfo);
        
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mimeType, $allowedTypes)) {
            throw new Exception('Неподдерживаемый формат изображения');
        }
        
        // Определяем расширение
        $extension = 'jpg';
        switch ($mimeType) {
            case 'image/png':
                $extension = 'png';
                break;
            case 'image/gif':
                $extension = 'gif';
                break;
            case 'image/webp':
                $extension = 'webp';
                break;
        }
        
        // Уникальное имя файла
        $fileName = uniqid($type . '_', true) . '.' . $extension;
        $relativePath = '/upload/' . $type . 's/' . $fileName;
        $filePath = $_SERVER['DOCUMENT_ROOT'] . $relativePath;
        
        // Создаем директорию если нужно
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Сохраняем файл
        if (file_put_contents($filePath, $imageData) === false) {
            throw new Exception('Ошибка сохранения изображения');
        }
        
        return $relativePath;
    }
    
    // ==================== ТОВАРЫ ====================
    
    /**
     * Получить список продуктов
     * @param array $data Параметры: type (produced/resale/all), category
     * @return array
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
            'PROPERTY_PHOTO',
            'PROPERTY_TYPE'
        ];
        
        $result = [];
        $res = \CIBlockElement::GetList(['SORT' => 'ASC'], $filter, false, false, $select);
        
        while ($fields = $res->GetNext()) {
            $isResale = !empty($fields['PROPERTY_IS_RESALE_ENUM_ID']);
            $type = $isResale ? 'resale' : 'produced';
            
            // Фильтруем по типу
            if (!empty($data['type']) && $data['type'] !== 'all' && $data['type'] !== $type) {
                continue;
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
     * Создать новый товар
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function createProduct($data = [])
    {
        Loader::includeModule('iblock');
        
        self::validate($data, [
            'name' => 'required',
            'type' => 'required'
        ]);
        
        // ✅ Используем getIblockPropertyEnumId для свойства TYPE
        $typeEnumId = self::getIblockPropertyEnumId(self::IBLOCK_PRODUCTS, 'TYPE', $data['type']);
        if (!$typeEnumId) {
            throw new Exception("Invalid type value: {$data['type']}");
        }
        
        $el = new \CIBlockElement();
        
        $code = \CUtil::translit($data['name'], 'ru', [
            'replace_space' => '-',
            'replace_other' => '-',
            'max_len' => 100,
            'change_case' => 'L'
        ]);
        
        $isResale = ($data['type'] === 'resale') ? 'Y' : 'N';
        // ✅ Используем getIblockPropertyEnumId для свойства IS_RESALE
        $isResaleEnumId = self::getIblockPropertyEnumId(self::IBLOCK_PRODUCTS, 'IS_RESALE', $isResale);
        
        $arFields = [
            'IBLOCK_SECTION_ID' => (int)($data['categoryId'] ?? null),
            'IBLOCK_ID' => self::IBLOCK_PRODUCTS,
            'NAME' => $data['name'],
            'CODE' => $code,
            'ACTIVE' => 'Y',
            'PREVIEW_TEXT' => $data['description'] ?? '',
            'PROPERTY_VALUES' => [
                'TYPE' => $typeEnumId,
                'IS_RESALE' => $isResaleEnumId,
                'SELLING_PRICE' => (float)($data['sellingPrice'] ?? 0),
                'CURRENT_STOCK' => (float)($data['currentStock'] ?? 0),
                'MIN_STOCK' => (float)($data['minStock'] ?? 0),
            ],
        ];
        
        // Добавляем специфичные для resale поля
        if ($isResale === 'Y') {
            if (!empty($data['unit'])) {
                $unitEnumId = self::getIblockPropertyEnumId(self::IBLOCK_PRODUCTS, 'UNIT', $data['unit']);
                if ($unitEnumId) {
                    $arFields['PROPERTY_VALUES']['UNIT'] = $unitEnumId;
                }
            }
            if (isset($data['costPrice'])) {
                $arFields['PROPERTY_VALUES']['COST_PRICE'] = (float)$data['costPrice'];
            }
        }
        
        $productId = $el->Add($arFields);
        if (!$productId) {
            throw new Exception('Ошибка создания товара: ' . $el->LAST_ERROR);
        }
        
        return [
            'id' => $productId,
            'name' => $data['name'],
        ];
    }
    
    /**
     * Обновить товар
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function updateProduct($data = [])
    {
        Loader::includeModule('iblock');
        
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            throw new Exception('ID товара не указан');
        }
        
        // Проверяем существование
        $res = \CIBlockElement::GetByID($id);
        if (!$res->Fetch()) {
            throw new Exception('Товар не найден');
        }
        
        $updateFields = [];
        $props = [];
        
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
        
        if (array_key_exists('categoryId', $data)) {
            $updateFields['IBLOCK_SECTION_ID'] = (int)$data['categoryId'] ?: false;
        }
        
        // Свойства
        if (array_key_exists('type', $data)) {
            $props['TYPE'] = self::getIblockPropertyEnumId(self::IBLOCK_PRODUCTS, 'TYPE', $data['type']);
        }
        
        if (array_key_exists('unit', $data)) {
            $props['UNIT'] = self::getIblockPropertyEnumId(self::IBLOCK_PRODUCTS, 'UNIT', $data['unit']);
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
            throw new Exception('Нет данных для обновления');
        }
        
        $el = new \CIBlockElement();
        if (!$el->Update($id, $updateFields)) {
            throw new Exception('Ошибка обновления товара: ' . $el->LAST_ERROR);
        }
        
        return ['success' => true, 'id' => $id];
    }
    
    /**
     * Удалить товар
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function deleteProduct($data = [])
    {
        Loader::includeModule('iblock');
        
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            throw new Exception('ID товара не указан');
        }
        
        $res = \CIBlockElement::GetByID($id);
        if (!$res->Fetch()) {
            throw new Exception('Товар не найден');
        }
        
        if (!\CIBlockElement::Delete($id)) {
            throw new Exception('Ошибка удаления товара');
        }
        
        return ['success' => true, 'id' => $id];
    }
    
    // ==================== КАТЕГОРИИ ТОВАРОВ ====================
    
    /**
     * Получить список категорий
     * @param array $data
     * @return array
     */
    public static function getCategories($data = [])
    {
        Loader::includeModule('iblock');
        
        $result = [];
        $res = \CIBlockSection::GetList(
            ['SORT' => 'ASC'],
            ['IBLOCK_ID' => self::IBLOCK_PRODUCTS, 'ACTIVE' => 'Y'],
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
     * Создать категорию
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function createCategory($data = [])
    {
        Loader::includeModule('iblock');
        
        self::validate($data, ['name' => 'required']);
        
        $bs = new \CIBlockSection;
        
        $arFields = [
            'ACTIVE' => 'Y',
            'IBLOCK_ID' => self::IBLOCK_PRODUCTS,
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
            throw new Exception('Ошибка создания категории: ' . $bs->LAST_ERROR);
        }
        
        return ['id' => $id];
    }
    
    /**
     * Обновить категорию
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function updateCategory($data = [])
    {
        Loader::includeModule('iblock');
        
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            throw new Exception('ID категории не указан');
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
            throw new Exception('Нет данных для обновления');
        }
        
        $res = $bs->Update($id, $arFields);
        if (!$res) {
            throw new Exception('Ошибка обновления категории: ' . $bs->LAST_ERROR);
        }
        
        return ['success' => true];
    }
    
    /**
     * Удалить категорию
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function deleteCategory($data = [])
    {
        Loader::includeModule('iblock');
        
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            throw new Exception('ID категории не указан');
        }
        
        // Проверяем наличие товаров
        $res = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => self::IBLOCK_PRODUCTS, 'SECTION_ID' => $id],
            false,
            false,
            ['ID']
        );
        
        if ($res->SelectedRowsCount() > 0) {
            throw new Exception('Нельзя удалить категорию, в которой есть товары');
        }
        
        $bs = new \CIBlockSection;
        if (!$bs->Delete($id)) {
            throw new Exception('Ошибка удаления категории');
        }
        
        return ['success' => true];
    }
    
    // ==================== СКЛАД ====================
    
    /**
     * Получить остатки
     * @param array $data
     * @return array
     */
    public static function getStock($data = [])
    {
        return self::getProducts($data);
    }
    
    /**
     * Получить историю движений
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function getStockHistory($data = [])
    {
        $limit = (int)($data['limit'] ?? 50);
        $offset = (int)($data['offset'] ?? 0);
        
        $filter = [];
        if (!empty($data['productId'])) {
            $filter['=UF_PRODUCT_ID'] = (int)$data['productId'];
        }
        
        if (!empty($data['type'])) {
            $typeId = self::getEnumId('UF_TYPE', $data['type']);
            if ($typeId) {
                $filter['=UF_TYPE'] = $typeId;
            }
        }
        
        if (!empty($data['dateFrom'])) {
            try {
                $filter['>=UF_CREATED_AT'] = new DateTime($data['dateFrom']);
            } catch (Exception $e) {
                // Игнорируем
            }
        }
        
        if (!empty($data['dateTo'])) {
            try {
                $filter['<=UF_CREATED_AT'] = new DateTime($data['dateTo']);
            } catch (Exception $e) {
                // Игнорируем
            }
        }
        
        $movements = StockMovementHelper::getMovements($filter, ['UF_CREATED_AT' => 'DESC'], $limit, $offset);
        
        $formatted = [];
        foreach ($movements as $movement) {
            $formatted[] = self::formatMovement($movement);
        }
        
        return [
            'data' => $formatted,
            'total' => StockMovementHelper::getCount($filter),
            'limit' => $limit,
            'offset' => $offset
        ];
    }
    
    /**
     * Добавить движение товара
     * @param array $data
     * @return array
     * @throws Exception
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

        // Ищем товар во всех инфоблоках
        $product = null;
        $iblockId = null;
        
        // Ищем в ингредиентах
        $res = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => self::IBLOCK_INGREDIENTS, 'ID' => $productId],
            false,
            false,
            ['ID', 'IBLOCK_ID', 'NAME', 'PROPERTY_CURRENT_STOCK', 'PROPERTY_COST_PRICE', 'PROPERTY_BASE_RATIO']
        );
        $product = $res->Fetch();
        if ($product) {
            $iblockId = self::IBLOCK_INGREDIENTS;
        }
        
        // Если не нашли, ищем в продуктах (resale)
        if (!$product) {
            $res = \CIBlockElement::GetList(
                [],
                ['IBLOCK_ID' => self::IBLOCK_PRODUCTS, 'ID' => $productId],
                false,
                false,
                ['ID', 'IBLOCK_ID', 'NAME', 'PROPERTY_CURRENT_STOCK', 'PROPERTY_COST_PRICE']
            );
            $product = $res->Fetch();
            if ($product) {
                $iblockId = self::IBLOCK_PRODUCTS;
            }
        }
        
        // Если не нашли в полуфабрикатах
        if (!$product) {
            $res = \CIBlockElement::GetList(
                [],
                ['IBLOCK_ID' => self::IBLOCK_SEMI_FINISHED, 'ID' => $productId],
                false,
                false,
                ['ID', 'IBLOCK_ID', 'NAME', 'PROPERTY_CURRENT_STOCK', 'PROPERTY_COST_PRICE']
            );
            $product = $res->Fetch();
            if ($product) {
                $iblockId = self::IBLOCK_SEMI_FINISHED;
            }
        }
        
        if (!$product) {
            throw new \Exception('Product not found');
        }

        $currentStock = (float)($product['PROPERTY_CURRENT_STOCK_VALUE'] ?? 0);
        $currentCostPrice = (float)($product['PROPERTY_COST_PRICE_VALUE'] ?? 0);
        $newStock = $currentStock;
        $newCostPrice = $currentCostPrice;

        if ($type === 'income') {
            $newStock += $quantity;
            
            // ✅ Обновляем цену закупа (средняя)
            $newPrice = (float)($data['price'] ?? 0);
            if ($newPrice > 0) {
                // Если уже есть остаток, считаем среднюю
                if ($currentStock > 0) {
                    $totalCost = ($currentStock * $currentCostPrice) + ($quantity * $newPrice);
                    $newCostPrice = $totalCost / $newStock;
                } else {
                    $newCostPrice = $newPrice;
                }
            }
            
        } elseif ($type === 'outcome' || $type === 'write-off') {
            $newStock -= $quantity;
            // При расходе цена закупа не меняется
        } else {
            throw new \Exception('Invalid movement type');
        }

        $typeId = self::getEnumId('UF_TYPE', $type);
        $documentTypeId = !empty($data['documentType']) 
            ? self::getEnumId('UF_DOCUMENT_TYPE', $data['documentType']) 
            : null;

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

        // Обновляем остаток и цену в инфоблоке
        $updateProps = ['CURRENT_STOCK' => $newStock];
        if ($type === 'income' && isset($newCostPrice)) {
            $updateProps['COST_PRICE'] = $newCostPrice;
        }
        
        \CIBlockElement::SetPropertyValuesEx($productId, $iblockId, $updateProps);

        return [
            'movementId' => $movementId,
            'newStock' => $newStock,
            'newCostPrice' => $newCostPrice,
        ];
    }
    
    /**
     * Форматирование движения
     * @param array $row
     * @return array
     */
    private static function formatMovement($row)
    {
        return [
            'id' => (int)$row['ID'],
            'productId' => (int)$row['UF_PRODUCT_ID'],
            'productName' => self::getProductName($row['UF_PRODUCT_ID']),
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
    
    // ==================== ЗАКАЗЫ ====================
    
    /**
     * Получить список заказов
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function getOrders($data = [])
    {
        Loader::includeModule('highloadblock');
        
        $filter = [];
        if (!empty($data['status'])) {
            $filter['=UF_STATUS'] = $data['status'];
        }
        
        if (!empty($data['dateFrom'])) {
            try {
                $filter['>=UF_CREATED_AT'] = new DateTime($data['dateFrom']);
            } catch (Exception $e) {}
        }
        
        if (!empty($data['dateTo'])) {
            try {
                $filter['<=UF_CREATED_AT'] = new DateTime($data['dateTo']);
            } catch (Exception $e) {}
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
     * Создать заказ с автоматическим списанием ингредиентов
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function createOrder($data = [])
    {
        Loader::includeModule('highloadblock');
        Loader::includeModule('iblock');
        
        self::validate($data, ['items' => 'required']);
        
        if (!is_array($data['items']) || empty($data['items'])) {
            throw new Exception('Items array is required and cannot be empty');
        }
        
        global $DB;
        $DB->StartTransaction();
        
        try {
            // 1. Получаем все рецепты
            $recipes = self::getAllRecipesMap();
            
            // 2. Разворачиваем все позиции заказа в ингредиенты
            $allIngredients = [];
            $subtotal = 0;
            
            foreach ($data['items'] as $item) {
                $productId = (int)$item['productId'];
                $quantity = (float)$item['quantity'];
                
                // Получаем информацию о товаре
                $product = self::getProductById($productId, self::IBLOCK_PRODUCTS);
                if (!$product) {
                    throw new Exception("Товар ID {$productId} не найден");
                }
                
                // ✅ Используем цену из запроса, если передана, иначе из товара
                $price = isset($item['price']) ? (float)$item['price'] : $product['sellingPrice'];
                $subtotal += $price * $quantity;
                
                // Находим рецепт для товара
                $recipe = isset($recipes[$productId]) ? $recipes[$productId] : null;
                
                if ($recipe) {
                    // Разворачиваем рецепт в ингредиенты
                    $ingredients = self::expandRecipeIngredients($recipe, $quantity, $recipes);
                    foreach ($ingredients as $ing) {
                        $key = $ing['ingredientId'];
                        if (!isset($allIngredients[$key])) {
                            $allIngredients[$key] = [
                                'ingredientId' => $ing['ingredientId'],
                                'name' => $ing['name'],
                                'quantity' => 0,
                                'unit' => $ing['unit'],
                                'costPrice' => $ing['costPrice']
                            ];
                        }
                        $allIngredients[$key]['quantity'] += $ing['quantity'];
                    }
                } else {
                    // Если нет рецепта, это товар перепродажи
                    $key = $productId;
                    if (!isset($allIngredients[$key])) {
                        $allIngredients[$key] = [
                            'ingredientId' => $productId,
                            'name' => $product['name'],
                            'quantity' => 0,
                            'unit' => $product['unit'] ?? 'шт',
                            'costPrice' => $product['costPrice'] ?? 0
                        ];
                    }
                    $allIngredients[$key]['quantity'] += $quantity;
                }
            }
            
            // 3. Применяем скидку, если указана
            $discount = (float)($data['discount'] ?? 0);
            $total = $subtotal - $discount;
            
            // 4. Создаём заказ
            $orderFields = [
                'UF_TYPE' => $data['type'] ?? 'dine-in',
                'UF_TABLE_NUMBER' => (int)($data['tableNumber'] ?? 0),
                'UF_STATUS' => 'new',
                'UF_SUBTOTAL' => $subtotal,
                'UF_DISCOUNT' => $discount,
                'UF_TOTAL' => $total,
                'UF_PAYMENT_METHOD' => $data['paymentMethod'] ?? null,
                'UF_CREATED_BY' => (int)($data['userId'] ?? 1),
                'UF_COMMENT' => $data['comment'] ?? '',
            ];
            
            $orderId = OrderHelper::createOrder($orderFields);
            if (!$orderId) {
                throw new Exception('Ошибка создания заказа');
            }
            
            // 5. Сохраняем позиции заказа
            $orderItems = [];
            foreach ($data['items'] as $item) {
                $productId = (int)$item['productId'];
                $quantity = (float)$item['quantity'];
                $price = isset($item['price']) ? (float)$item['price'] : 0;
                
                $product = self::getProductById($productId, self::IBLOCK_PRODUCTS);
                
                // ✅ Сохраняем позицию заказа с UF_PRICE
                $result = OrderItemHelper::addItems($orderId, [[
                    'UF_PRODUCT_ID' => $productId,
                    'UF_QUANTITY' => $quantity,
                    'UF_PRICE' => $price,
                    'UF_DISCOUNT_PERCENT' => 0,
                    'UF_COMMENT' => $item['comment'] ?? '',
                    'UF_COOKING_STATUS' => 'pending'
                ]]);
                $orderItems[] = $result[0];
            }
            
            // 6. Списываем ингредиенты
            $movementIds = [];
            foreach ($allIngredients as $ing) {
                if ($ing['quantity'] <= 0) continue;
                
                $movementId = self::addMovement([
                    'productId' => $ing['ingredientId'],
                    'type' => 'outcome',
                    'quantity' => $ing['quantity'],
                    'price' => $ing['costPrice'],
                    'documentType' => 'sale',
                    'comment' => "Списание по заказу №{$orderId}"
                ]);
                $movementIds[] = $movementId;
            }
            
            $DB->Commit();
            
            return [
                'orderId' => $orderId,
                'itemIds' => $orderItems,
                'movementIds' => $movementIds,
                'subtotal' => $subtotal,
                'total' => $total
            ];
            
        } catch (Exception $e) {
            $DB->Rollback();
            throw $e;
        }
    }

    /**
     * Получить все рецепты в виде карты [productId => recipe]
     * @return array
     */
    private static function getAllRecipesMap()
    {
        $recipes = self::getRecipes();
        $map = [];
        foreach ($recipes as $recipe) {
            $map[$recipe['productId']] = $recipe;
        }
        return $map;
    }

    /**
     * Рекурсивно развернуть рецепт в ингредиенты
     * @param array $recipe
     * @param float $quantity
     * @param array $allRecipes
     * @return array
     */
    private static function expandRecipeIngredients($recipe, $quantity, $allRecipes)
    {
        $result = [];
        
        foreach ($recipe['items'] as $item) {
            if ($item['itemType'] === 'ingredient') {
                // Это ингредиент
                $ingredient = self::getIngredientById($item['itemId']);
                if ($ingredient) {
                    $result[] = [
                        'ingredientId' => $ingredient['id'],
                        'name' => $ingredient['name'],
                        'quantity' => $item['quantity'] * $quantity,
                        'unit' => $item['unit'],
                        'costPrice' => $ingredient['costPrice'] / $ingredient['baseRatio']
                    ];
                }
            } else {
                // Это полуфабрикат
                $semiRecipe = isset($allRecipes[$item['itemId']]) ? $allRecipes[$item['itemId']] : null;
                if ($semiRecipe) {
                    $subIngredients = self::expandRecipeIngredients($semiRecipe, $item['quantity'] * $quantity, $allRecipes);
                    $result = array_merge($result, $subIngredients);
                } else {
                    // Если нет рецепта для полуфабриката, списываем как есть
                    $semi = self::getProductById($item['itemId'], self::IBLOCK_SEMI_FINISHED);
                    if ($semi) {
                        $result[] = [
                            'ingredientId' => $semi['id'],
                            'name' => $semi['name'],
                            'quantity' => $item['quantity'] * $quantity,
                            'unit' => $semi['unit'] ?? 'шт',
                            'costPrice' => $semi['costPrice'] ?? 0
                        ];
                    }
                }
            }
        }
        
        return $result;
    }

    /**
     * Получить товар для перепродажи
     * @param int $productId
     * @return array|null
     */
    private static function getResaleProductById($productId)
    {
        return self::getProductById($productId, self::IBLOCK_PRODUCTS);
    }
    
    /**
     * Обновить статус заказа
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function updateOrderStatus($data = [])
    {
        self::validate($data, [
            'orderId' => 'required',
            'status' => 'required'
        ]);
        
        OrderHelper::updateOrderStatus((int)$data['orderId'], $data['status']);
        return ['success' => true];
    }
    
    /**
     * Обновить статус приготовления позиции
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function updateOrderItemStatus($data = [])
    {
        self::validate($data, [
            'itemId' => 'required',
            'status' => 'required'
        ]);
        
        OrderItemHelper::updateCookingStatus((int)$data['itemId'], $data['status']);
        return ['success' => true];
    }
    
    // ==================== РЕЦЕПТЫ ====================
    
    /**
     * Получить список рецептов
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function getRecipes($data = [])
    {
        Loader::includeModule('highloadblock');
        
        $dataClass = self::getHlDataClass(self::HL_RECIPES);
        
        $filter = [];
        if (!empty($data['productId'])) {
            $filter['=UF_PRODUCT_ID'] = (int)$data['productId'];
        }
        
        $res = $dataClass::getList([
            'select' => ['*'],
            'filter' => $filter,
            'order' => ['ID' => 'DESC']
        ]);
        
        $rows = [];
        $recipeIds = [];
        
        // Сначала собираем все рецепты
        while ($row = $res->fetch()) {
            $rows[] = $row;
            $recipeIds[] = $row['ID'];
        }
        
        // Загружаем все ингредиенты для всех рецептов одним запросом
        $allItems = [];
        if (!empty($recipeIds)) {
            $itemsClass = self::getHlDataClass(self::HL_RECIPE_ITEMS);
            $itemsRes = $itemsClass::getList([
                'filter' => ['=UF_RECIPE_ID' => $recipeIds],
                'order' => ['ID' => 'ASC']
            ]);
            
            while ($item = $itemsRes->fetch()) {
                $allItems[$item['UF_RECIPE_ID']][] = $item;
            }
        }
        
        // Формируем результат
        $result = [];
        foreach ($rows as $row) {
            $items = isset($allItems[$row['ID']]) ? $allItems[$row['ID']] : [];
            $formattedItems = [];
            
            foreach ($items as $item) {
                $formattedItems[] = [
                    'id' => (int)$item['ID'],
                    'itemType' => $item['UF_ITEM_TYPE'],
                    'itemId' => (int)$item['UF_ITEM_ID'],
                    'quantity' => (float)$item['UF_QUANTITY'],
                    'unit' => $item['UF_UNIT'],
                    'isOptional' => (bool)$item['UF_IS_OPTIONAL'],
                ];
            }
            
            $result[] = [
                'id' => (int)$row['ID'],
                'productId' => (int)$row['UF_PRODUCT_ID'],
                'productName' => self::getProductName($row['UF_PRODUCT_ID']),
                'name' => $row['UF_NAME'],
                'outputWeight' => (float)$row['UF_OUTPUT_WEIGHT'],
                'outputUnit' => $row['UF_OUTPUT_UNIT'],
                'cookingTime' => (int)$row['UF_COOKING_TIME'],
                'instructions' => $row['UF_INSTRUCTIONS'] ?? '',
                'items' => $formattedItems,
                'photo' => $row['UF_PHOTO'] ?? null,
                'createdAt' => $row['UF_CREATED_AT'] ?? null,
            ];
        }
        
        return $result;
    }
    
    /**
     * Создать рецепт
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function createRecipe($data = [])
    {
        Loader::includeModule('highloadblock');
        
        self::validate($data, [
            'productId' => 'required',
            'name' => 'required',
            'outputWeight' => 'required',
            'outputUnit' => 'required',
            'items' => 'required'
        ]);
        
        // Начинаем транзакцию
        global $DB;
        $DB->StartTransaction();
        
        try {
            $dataClass = self::getHlDataClass(self::HL_RECIPES);
            
            $fields = [
                'UF_PRODUCT_ID' => (int)$data['productId'],
                'UF_NAME' => $data['name'],
                'UF_OUTPUT_WEIGHT' => (float)$data['outputWeight'],
                'UF_OUTPUT_UNIT' => $data['outputUnit'],
                'UF_COOKING_TIME' => (int)($data['cookingTime'] ?? 0),
                'UF_INSTRUCTIONS' => $data['instructions'] ?? '',
            ];
            
            if (!empty($data['photo'])) {
                $fields['UF_PHOTO'] = self::saveBase64Image($data['photo'], 'recipe');
            }
            
            $result = $dataClass::add($fields);
            if (!$result->isSuccess()) {
                throw new Exception(implode(', ', $result->getErrorMessages()));
            }
            
            $recipeId = $result->getId();
            
            // Сохраняем состав
            self::saveRecipeItems($recipeId, $data['items']);
            
            $DB->Commit();
            
            return ['recipeId' => $recipeId];
            
        } catch (Exception $e) {
            $DB->Rollback();
            throw $e;
        }
    }
    
    /**
     * Сохранить состав рецепта
     * @param int $recipeId
     * @param array $items
     * @throws Exception
     */
    private static function saveRecipeItems($recipeId, $items)
    {
        Loader::includeModule('highloadblock');
        
        $dataClass = self::getHlDataClass(self::HL_RECIPE_ITEMS);
        
        // Удаляем старые
        $old = $dataClass::getList(['filter' => ['=UF_RECIPE_ID' => $recipeId]]);
        while ($item = $old->fetch()) {
            $dataClass::delete($item['ID']);
        }
        
        // Добавляем новые
        foreach ($items as $item) {
            $result = $dataClass::add([
                'UF_RECIPE_ID' => $recipeId,
                'UF_ITEM_TYPE' => $item['itemType'],
                'UF_ITEM_ID' => (int)$item['itemId'],
                'UF_QUANTITY' => (float)$item['quantity'],
                'UF_UNIT' => $item['unit'],
                'UF_IS_OPTIONAL' => (int)($item['isOptional'] ?? 0)
            ]);
            
            if (!$result->isSuccess()) {
                throw new Exception('Ошибка сохранения ингредиента: ' . implode(', ', $result->getErrorMessages()));
            }
        }
    }
    
    /**
     * Обновить рецепт
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function updateRecipe($data = [])
    {
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            throw new Exception('Recipe ID required');
        }
        
        Loader::includeModule('highloadblock');
        
        // Начинаем транзакцию
        global $DB;
        $DB->StartTransaction();
        
        try {
            $dataClass = self::getHlDataClass(self::HL_RECIPES);
            
            $fields = [];
            if (!empty($data['productId'])) $fields['UF_PRODUCT_ID'] = (int)$data['productId'];
            if (!empty($data['name'])) $fields['UF_NAME'] = $data['name'];
            if (isset($data['outputWeight'])) $fields['UF_OUTPUT_WEIGHT'] = (float)$data['outputWeight'];
            if (!empty($data['outputUnit'])) $fields['UF_OUTPUT_UNIT'] = $data['outputUnit'];
            if (isset($data['cookingTime'])) $fields['UF_COOKING_TIME'] = (int)$data['cookingTime'];
            if (isset($data['instructions'])) $fields['UF_INSTRUCTIONS'] = $data['instructions'];
            
            if (!empty($data['photo'])) {
                $fields['UF_PHOTO'] = self::saveBase64Image($data['photo'], 'recipe');
            }
            
            if (!empty($fields)) {
                $result = $dataClass::update($id, $fields);
                if (!$result->isSuccess()) {
                    throw new Exception(implode(', ', $result->getErrorMessages()));
                }
            }
            
            // Обновляем состав
            if (isset($data['items'])) {
                self::saveRecipeItems($id, $data['items']);
            }
            
            $DB->Commit();
            
            return ['success' => true];
            
        } catch (Exception $e) {
            $DB->Rollback();
            throw $e;
        }
    }
    
    /**
     * Удалить рецепт
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function deleteRecipe($data = [])
    {
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            throw new Exception('Recipe ID required');
        }
        
        Loader::includeModule('highloadblock');
        
        // Начинаем транзакцию
        global $DB;
        $DB->StartTransaction();
        
        try {
            // Удаляем состав
            $itemsClass = self::getHlDataClass(self::HL_RECIPE_ITEMS);
            $old = $itemsClass::getList(['filter' => ['=UF_RECIPE_ID' => $id]]);
            while ($item = $old->fetch()) {
                $itemsClass::delete($item['ID']);
            }
            
            // Удаляем рецепт
            $dataClass = self::getHlDataClass(self::HL_RECIPES);
            $dataClass::delete($id);
            
            $DB->Commit();
            
            return ['success' => true];
            
        } catch (Exception $e) {
            $DB->Rollback();
            throw $e;
        }
    }
    
    /**
     * Рассчитать себестоимость блюда по рецепту
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function calculateRecipeCost($data = [])
    {
        $recipeId = (int)($data['recipeId'] ?? 0);
        if (!$recipeId) {
            throw new Exception('Recipe ID required');
        }
        
        $items = self::getRecipeItems($recipeId);
        if (empty($items)) {
            return ['cost' => 0];
        }
        
        $total = 0;
        foreach ($items as $item) {
            $product = self::getProductById($item['itemId']);
            if (!$product) continue;
            
            $costPerBaseUnit = $product['costPrice'] / $product['baseRatio'];
            $total += $costPerBaseUnit * $item['quantity'];
        }
        
        return ['cost' => round($total, 2)];
    }
    
    /**
     * Получить состав рецепта
     * @param int $recipeId
     * @return array
     */
    private static function getRecipeItems($recipeId)
    {
        Loader::includeModule('highloadblock');
        
        $hlId = self::HL_RECIPE_ITEMS;
        $hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById($hlId)->fetch();
        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
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
    
    // ==================== ПОСТАВЩИКИ ====================
    
    /**
     * Получить всех поставщиков
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function getSuppliers($data = [])
    {
        $dataClass = self::getHlDataClass(self::HL_SUPPLIERS);
        
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
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function createSupplier($data = [])
    {
        self::validate($data, ['name' => 'required']);
        
        $dataClass = self::getHlDataClass(self::HL_SUPPLIERS);
        
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
            throw new Exception(implode(', ', $result->getErrorMessages()));
        }
        
        return ['id' => $result->getId()];
    }
    
    /**
     * Обновить поставщика
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function updateSupplier($data = [])
    {
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            throw new Exception('ID поставщика не указан');
        }
        
        $dataClass = self::getHlDataClass(self::HL_SUPPLIERS);
        
        // Проверяем существование
        $existing = $dataClass::getById($id)->fetch();
        if (!$existing) {
            throw new Exception('Поставщик не найден');
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
            throw new Exception('Нет данных для обновления');
        }
        
        $result = $dataClass::update($id, $fields);
        if (!$result->isSuccess()) {
            throw new Exception(implode(', ', $result->getErrorMessages()));
        }
        
        return ['success' => true];
    }
    
    /**
     * Удалить поставщика
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function deleteSupplier($data = [])
    {
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            throw new Exception('ID поставщика не указан');
        }
        
        $dataClass = self::getHlDataClass(self::HL_SUPPLIERS);
        
        $result = $dataClass::delete($id);
        if (!$result->isSuccess()) {
            throw new Exception(implode(', ', $result->getErrorMessages()));
        }
        
        return ['success' => true];
    }
    
    /**
     * Получить историю цен поставщика
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function getSupplierPriceHistory($data = [])
    {
        $supplierId = (int)($data['supplierId'] ?? 0);
        $productId = (int)($data['productId'] ?? 0);
        $period = $data['period'] ?? 'month';
        $customStartDate = $data['startDate'] ?? null;
        $customEndDate = $data['endDate'] ?? null;
        
        if (!$supplierId || !$productId) {
            throw new Exception('supplierId и productId обязательны');
        }
        
        $dataClass = self::getHlDataClass(self::HL_STOCK_MOVEMENTS);
        
        $filter = [
            '=UF_PRODUCT_ID' => $productId,
            '=UF_SUPPLIER_ID' => $supplierId,
            '=UF_TYPE' => self::getEnumId('UF_TYPE', 'income'),
        ];
        
        $dateFilter = self::getDateFilter($period, $customStartDate, $customEndDate);
        if (!empty($dateFilter)) {
            $filter = array_merge($filter, $dateFilter);
        }
        
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
     * Рассчитать статистику по ценам
     * @param array $history
     * @return array
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
                'trend' => 0,
                'trendPercent' => 0
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
     * Сравнить цены поставщиков
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function compareSuppliersPrices($data = [])
    {
        $productId = (int)($data['productId'] ?? 0);
        if (!$productId) {
            throw new Exception('productId обязателен');
        }
        
        $dataClass = self::getHlDataClass(self::HL_STOCK_MOVEMENTS);
        
        $res = $dataClass::getList([
            'select' => ['UF_SUPPLIER_ID', 'UF_PRICE', 'UF_CREATED_AT'],
            'filter' => [
                '=UF_PRODUCT_ID' => $productId,
                '=UF_TYPE' => self::getEnumId('UF_TYPE', 'income'),
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
    
    // ==================== ИНГРЕДИЕНТЫ ====================
    
    /**
     * Получить список ингредиентов
     * @param array $data
     * @return array
     */
    public static function getIngredients($data = [])
    {
        Loader::includeModule('iblock');
        
        $filter = [
            'IBLOCK_ID' => self::IBLOCK_INGREDIENTS,
            'ACTIVE' => 'Y'
        ];
        
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
            $result[] = [
                'id' => (int)$fields['ID'],
                'name' => $fields['NAME'],
                'categoryId' => $fields['IBLOCK_SECTION_ID'] ? (int)$fields['IBLOCK_SECTION_ID'] : null,
                'code' => $fields['CODE'],
                'active' => ($fields['ACTIVE'] === 'Y'),
                // 'unit' => $fields['PROPERTY_UNIT_VALUE'],
                'baseUnit' => $fields['PROPERTY_BASE_UNIT_VALUE'],
                'baseRatio' => (float)$fields['PROPERTY_BASE_RATIO_VALUE'],
                // 'costPrice' => (float)$fields['PROPERTY_COST_PRICE_VALUE'],
                'currentStock' => (float)$fields['PROPERTY_CURRENT_STOCK_VALUE'],
                'minStock' => (float)$fields['PROPERTY_MIN_STOCK_VALUE'],
                'unit' => $fields['PROPERTY_UNIT_VALUE'] ?? null,
                'sellingPrice' => isset($fields['PROPERTY_SELLING_PRICE_VALUE']) ? (float)$fields['PROPERTY_SELLING_PRICE_VALUE'] : 0,
                'costPrice' => isset($fields['PROPERTY_COST_PRICE_VALUE']) ? (float)$fields['PROPERTY_COST_PRICE_VALUE'] : 0,
                'photo' => isset($fields['PROPERTY_PHOTO_VALUE']) && $fields['PROPERTY_PHOTO_VALUE'] 
                    ? \CFile::GetPath($fields['PROPERTY_PHOTO_VALUE']) 
                    : null,
            ];
        }
        
        return $result;
    }
    
    /**
     * Создать ингредиент
     * @param array $data
     * @return array
     * @throws Exception
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
        $unitEnumId = self::getIblockPropertyEnumId(self::IBLOCK_INGREDIENTS, 'UNIT', $data['unit']);
        $baseUnitEnumId = null;
        if (!empty($data['baseUnit'])) {
            $baseUnitEnumId = self::getIblockPropertyEnumId(self::IBLOCK_INGREDIENTS, 'BASE_UNIT', $data['baseUnit']);
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
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function updateIngredient($data = [])
    {
        Loader::includeModule('iblock');
        
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            throw new Exception('ID не указан');
        }
        
        $updateFields = [];
        $props = [];
        
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
        
        if (array_key_exists('categoryId', $data)) {
            $updateFields['IBLOCK_SECTION_ID'] = (int)$data['categoryId'] ?: false;
        }
        
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
            throw new Exception('Нет данных для обновления');
        }
        
        $el = new \CIBlockElement();
        if (!$el->Update($id, $updateFields)) {
            throw new Exception('Ошибка обновления: ' . $el->LAST_ERROR);
        }
        
        return ['success' => true];
    }
    
    /**
     * Удалить ингредиент
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function deleteIngredient($data = [])
    {
        Loader::includeModule('iblock');
        
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            throw new Exception('ID не указан');
        }
        
        if (!\CIBlockElement::Delete($id)) {
            throw new Exception('Ошибка удаления');
        }
        
        return ['success' => true];
    }
    
    // ==================== КАТЕГОРИИ ИНГРЕДИЕНТОВ ====================
    
    /**
     * Получить категории ингредиентов
     * @param array $data
     * @return array
     */
    public static function getIngredientCategories($data = [])
    {
        Loader::includeModule('iblock');
        
        $result = [];
        $res = \CIBlockSection::GetList(
            ['SORT' => 'ASC'],
            ['IBLOCK_ID' => self::IBLOCK_INGREDIENTS, 'ACTIVE' => 'Y'],
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
     * Создать категорию ингредиента
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function createIngredientCategory($data = [])
    {
        Loader::includeModule('iblock');
        
        self::validate($data, ['name' => 'required']);
        
        $bs = new \CIBlockSection;
        
        $arFields = [
            'ACTIVE' => 'Y',
            'IBLOCK_ID' => self::IBLOCK_INGREDIENTS,
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
            throw new Exception('Ошибка создания категории: ' . $bs->LAST_ERROR);
        }
        
        return ['id' => $id];
    }
    
    /**
     * Обновить категорию ингредиента
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function updateIngredientCategory($data = [])
    {
        Loader::includeModule('iblock');
        
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            throw new Exception('ID категории не указан');
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
            throw new Exception('Нет данных для обновления');
        }
        
        $res = $bs->Update($id, $arFields);
        if (!$res) {
            throw new Exception('Ошибка обновления категории: ' . $bs->LAST_ERROR);
        }
        
        return ['success' => true];
    }
    
    /**
     * Удалить категорию ингредиента
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function deleteIngredientCategory($data = [])
    {
        Loader::includeModule('iblock');
        
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            throw new Exception('ID категории не указан');
        }
        
        // Проверяем наличие ингредиентов
        $res = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => self::IBLOCK_INGREDIENTS, 'SECTION_ID' => $id],
            false,
            false,
            ['ID']
        );
        
        if ($res->SelectedRowsCount() > 0) {
            throw new Exception('Нельзя удалить категорию, в которой есть ингредиенты');
        }
        
        $bs = new \CIBlockSection;
        if (!$bs->Delete($id)) {
            throw new Exception('Ошибка удаления категории');
        }
        
        return ['success' => true];
    }
    
    // ==================== ПОЛУФАБРИКАТЫ ====================
    
    
    /**
     * Получить полуфабрикаты
     * @param array $data
     * @return array
     */
    public static function getSemiFinished($data = [])
    {
        Loader::includeModule('iblock');
        
        $filter = ['IBLOCK_ID' => self::IBLOCK_SEMI_FINISHED, 'ACTIVE' => 'Y'];
        $id = (int)($data['id'] ?? 0);
        if ($id) {
            $filter['ID'] = $id;
        }
        
        $select = [
            'ID', 'NAME', 'CODE', 'ACTIVE',
            'PROPERTY_UNIT',
            'PROPERTY_SELLING_PRICE',
            'PROPERTY_COST_PRICE',
            'PROPERTY_PHOTO'
        ];
        
        $result = [];
        $res = \CIBlockElement::GetList(['SORT' => 'ASC'], $filter, false, false, $select);
        
        while ($fields = $res->GetNext()) {
            // ✅ Получаем значение UNIT
            $unitValue = null;
            if (isset($fields['PROPERTY_UNIT_VALUE']) && !empty($fields['PROPERTY_UNIT_VALUE'])) {
                $unitValue = $fields['PROPERTY_UNIT_VALUE'];
            } elseif (isset($fields['PROPERTY_UNIT_ENUM_ID']) && !empty($fields['PROPERTY_UNIT_ENUM_ID'])) {
                $enum = \CIBlockPropertyEnum::GetByID($fields['PROPERTY_UNIT_ENUM_ID'])->Fetch();
                $unitValue = $enum['XML_ID'] ?? $enum['VALUE'];
            }
            
            // ✅ Получаем фото с проверкой
            $photo = null;
            if (isset($fields['PROPERTY_PHOTO_VALUE']) && !empty($fields['PROPERTY_PHOTO_VALUE'])) {
                $photo = \CFile::GetPath($fields['PROPERTY_PHOTO_VALUE']);
            }
            
            $recipe = self::getSemiFinishedRecipe($fields['ID']);
            $calculatedCost = self::calculateSemiFinishedCost($recipe);
            
            $item = [
                'id' => (int)$fields['ID'],
                'name' => $fields['NAME'],
                'code' => $fields['CODE'],
                'active' => ($fields['ACTIVE'] === 'Y'),
                'unit' => $unitValue,
                'sellingPrice' => isset($fields['PROPERTY_SELLING_PRICE_VALUE']) ? (float)$fields['PROPERTY_SELLING_PRICE_VALUE'] : 0,
                'costPrice' => $calculatedCost ?: (isset($fields['PROPERTY_COST_PRICE_VALUE']) ? (float)$fields['PROPERTY_COST_PRICE_VALUE'] : 0),
                'photo' => $photo,
                'ingredients' => $recipe
            ];
            
            if ($id) {
                return $item;
            }
            $result[] = $item;
        }
        
        return $result;
    }
    
    /**
     * Получить состав полуфабриката
     * @param int $semiFinishedId
     * @return array
     */
    private static function getSemiFinishedRecipe($semiFinishedId)
    {
        try {
            $dataClass = self::getHlDataClass(self::HL_SEMI_RECIPES);
            
            $res = $dataClass::getList([
                'select' => ['*'],
                'filter' => ['=UF_SEMI_FINISHED_ID' => $semiFinishedId]
            ]);
            
            $ingredients = [];
            while ($row = $res->fetch()) {
                // ✅ Получаем название ингредиента
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
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Рассчитать себестоимость полуфабриката
     * @param array $ingredients
     * @return float
     */
    private static function calculateSemiFinishedCost($ingredients)
    {
        $total = 0;
        foreach ($ingredients as $ing) {
            $ingredient = self::getIngredientById($ing['ingredientId']);
            if ($ingredient) {
                $pricePerBaseUnit = $ingredient['costPrice'] / $ingredient['baseRatio'];
                $total += $pricePerBaseUnit * $ing['quantity'];
            }
        }
        return round($total, 2);
    }
    
    /**
     * Создать полуфабрикат
     * @param array $data
     * @return array
     * @throws Exception
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

        // Получаем ID значения единицы измерения
        $unitEnumId = self::getIblockPropertyEnumId(self::IBLOCK_SEMI_FINISHED, 'UNIT', $data['unit']);
        
        if (!$unitEnumId) {
            throw new \Exception("Invalid unit value: {$data['unit']}");
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
                'UNIT' => $unitEnumId,
                'SELLING_PRICE' => (float)($data['sellingPrice'] ?? 0),
            ],
        ];

        $productId = $el->Add($arFields);
        if (!$productId) {
            throw new \Exception('Ошибка создания полуфабриката: ' . $el->LAST_ERROR);
        }

        // Сохраняем ингредиенты (состав)
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
     * @param int $semiFinishedId
     * @param array $ingredients
     * @throws Exception
     */
    private static function saveSemiFinishedIngredients($semiFinishedId, $ingredients)
    {
        $dataClass = self::getHlDataClass(self::HL_SEMI_RECIPES);
        
        // Удаляем старые
        $old = $dataClass::getList(['filter' => ['=UF_SEMI_FINISHED_ID' => $semiFinishedId]]);
        while ($item = $old->fetch()) {
            $dataClass::delete($item['ID']);
        }
        
        // Добавляем новые
        foreach ($ingredients as $ing) {
            $result = $dataClass::add([
                'UF_SEMI_FINISHED_ID' => $semiFinishedId,
                'UF_INGREDIENT_ID' => (int)$ing['ingredientId'],
                'UF_QUANTITY' => (float)$ing['quantity'],
                'UF_UNIT' => $ing['unit'] ?? 'г'
            ]);
            
            if (!$result->isSuccess()) {
                throw new Exception('Ошибка сохранения ингредиента: ' . implode(', ', $result->getErrorMessages()));
            }
        }
    }
    
    /**
     * Обновить полуфабрикат
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function updateSemiFinished($data = [])
    {
        Loader::includeModule('iblock');
        
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            throw new Exception('ID не указан');
        }
        
        $updateFields = [];
        $props = [];
        
        if (!empty($data['name'])) {
            $updateFields['NAME'] = $data['name'];
            $updateFields['CODE'] = \CUtil::translit($data['name'], 'ru', [
                'replace_space' => '-',
                'replace_other' => '-',
                'max_len' => 100,
                'change_case' => 'L'
            ]);
        }
        
        if (array_key_exists('unit', $data) && !empty($data['unit'])) {
            $props['UNIT'] = self::getEnumId('UNIT', $data['unit']);
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
                throw new Exception('Ошибка обновления: ' . $el->LAST_ERROR);
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
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function deleteSemiFinished($data = [])
    {
        Loader::includeModule('iblock');
        
        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            throw new Exception('ID не указан');
        }
        
        if (!\CIBlockElement::Delete($id)) {
            throw new Exception('Ошибка удаления');
        }
        
        return ['success' => true];
    }
    
    // ==================== ПРОИЗВОДСТВО ====================
    
    /**
     * Производство полуфабриката
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function produceSemiFinished($data = [])
    {
        Loader::includeModule('iblock');
        
        $semiFinishedId = (int)($data['semiFinishedId'] ?? 0);
        $producedQuantity = (float)($data['producedQuantity'] ?? 0);
        $ingredients = $data['ingredients'] ?? [];
        
        if (!$semiFinishedId || $producedQuantity <= 0 || empty($ingredients)) {
            throw new Exception('Не указаны все данные');
        }
        
        global $DB;
        $DB->StartTransaction();
        
        try {
            $totalCost = 0;
            $ingredientsToWriteOff = [];
            
            foreach ($ingredients as $ing) {
                $ingredient = self::getIngredientById($ing['ingredientId']);
                if (!$ingredient) {
                    throw new Exception("Ингредиент ID {$ing['ingredientId']} не найден");
                }
                
                $neededQuantity = $ing['quantity'];
                
                // ❌ Удаляем проверку остатков
                // if ($ingredient['currentStock'] < $neededQuantity) {
                //     throw new Exception("Недостаточно {$ingredient['name']} на складе");
                // }
                
                $cost = $ingredient['costPrice'] * $neededQuantity;
                $totalCost += $cost;
                
                $ingredientsToWriteOff[] = [
                    'productId' => $ingredient['id'],
                    'quantity' => $neededQuantity,
                    'price' => $ingredient['costPrice'],
                    'unit' => $ing['unit']
                ];
            }
            
            $newCostPrice = $totalCost / $producedQuantity;
            
            // 1. Списание ингредиентов (разрешаем уходить в минус)
            foreach ($ingredientsToWriteOff as $ing) {
                self::addMovement([
                    'productId' => $ing['productId'],
                    'type' => 'outcome',
                    'quantity' => $ing['quantity'],
                    'price' => $ing['price'],
                    'documentType' => 'production',
                    'comment' => "Списано на производство полуфабриката ID {$semiFinishedId}"
                ]);
            }
            
            // 2. Оприходование полуфабриката
            $movementId = self::addMovement([
                'productId' => $semiFinishedId,
                'type' => 'income',
                'quantity' => $producedQuantity,
                'price' => $newCostPrice,
                'documentType' => 'production',
                'comment' => "Произведено из сырья"
            ]);
            
            // 3. Обновляем себестоимость
            \CIBlockElement::SetPropertyValuesEx(
                $semiFinishedId,
                self::IBLOCK_SEMI_FINISHED,
                ['COST_PRICE' => $newCostPrice]
            );
            
            $DB->Commit();
            
            return [
                'success' => true,
                'semiFinishedId' => $semiFinishedId,
                'producedQuantity' => $producedQuantity,
                'newCostPrice' => $newCostPrice,
                'movementId' => $movementId
            ];
            
        } catch (Exception $e) {
            $DB->Rollback();
            throw $e;
        }
    }
    
    /**
     * Получить историю производства
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function getProductionHistory($data = [])
    {
        $filter = ['=UF_DOCUMENT_TYPE' => self::getEnumId('UF_DOCUMENT_TYPE', 'production')];
        
        if (!empty($data['semiFinishedId'])) {
            $filter['=UF_PRODUCT_ID'] = (int)$data['semiFinishedId'];
        }
        
        if (!empty($data['dateFrom'])) {
            try {
                $filter['>=UF_CREATED_AT'] = new DateTime($data['dateFrom']);
            } catch (Exception $e) {}
        }
        
        if (!empty($data['dateTo'])) {
            try {
                $filter['<=UF_CREATED_AT'] = new DateTime($data['dateTo']);
            } catch (Exception $e) {}
        }
        
        $limit = (int)($data['limit'] ?? 50);
        $offset = (int)($data['offset'] ?? 0);
        
        $movements = StockMovementHelper::getMovements($filter, ['UF_CREATED_AT' => 'DESC'], $limit, $offset);
        
        foreach ($movements as &$mov) {
            $product = self::getProductById($mov['UF_PRODUCT_ID'], self::IBLOCK_SEMI_FINISHED);
            $mov['PRODUCT_NAME'] = $product['name'] ?? 'Неизвестно';
        }
        
        return [
            'data' => $movements,
            'total' => StockMovementHelper::getCount($filter),
            'limit' => $limit,
            'offset' => $offset
        ];
    }
    
    /**
     * Отменить производство
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function revertProduction($data = [])
    {
        $movementId = (int)($data['movementId'] ?? 0);
        if (!$movementId) {
            throw new Exception('ID движения не указан');
        }
        
        $movement = StockMovementHelper::getMovementById($movementId);
        if (!$movement) {
            throw new Exception('Движение не найдено');
        }
        
        $productionTypeId = self::getEnumId('UF_DOCUMENT_TYPE', 'production');
        if ($movement['UF_DOCUMENT_TYPE'] != $productionTypeId) {
            throw new Exception('Это не производственная операция');
        }
        
        $details = self::getProductionDetails($movementId);
        if (empty($details)) {
            throw new Exception('Детали производства не найдены');
        }
        
        global $DB;
        $DB->StartTransaction();
        
        try {
            // Списать произведённый полуфабрикат
            self::addMovement([
                'productId' => $movement['UF_PRODUCT_ID'],
                'type' => 'outcome',
                'quantity' => $movement['UF_QUANTITY'],
                'price' => $movement['UF_PRICE'],
                'documentType' => 'production_revert',
                'comment' => 'Сторно производства ID ' . $movementId
            ]);
            
            // Вернуть ингредиенты
            foreach ($details as $detail) {
                self::addMovement([
                    'productId' => $detail['UF_INGREDIENT_ID'],
                    'type' => 'income',
                    'quantity' => $detail['UF_QUANTITY'],
                    'price' => $detail['UF_PRICE'],
                    'documentType' => 'production_revert',
                    'comment' => 'Возврат ингредиентов по сторно производства'
                ]);
            }
            
            $DB->Commit();
            
            return ['success' => true];
            
        } catch (Exception $e) {
            $DB->Rollback();
            throw $e;
        }
    }
    
    /**
     * Получить детали производства
     * @param int $productionMovementId
     * @return array
     */
    private static function getProductionDetails($productionMovementId)
    {
        try {
            $dataClass = self::getHlDataClass(self::HL_PRODUCTION_DETAILS);
            
            $res = $dataClass::getList([
                'filter' => ['=UF_PRODUCTION_MOVEMENT_ID' => $productionMovementId],
                'select' => ['*']
            ]);
            
            $details = [];
            while ($row = $res->fetch()) {
                $details[] = $row;
            }
            return $details;
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Сохранить детали производства
     * @param int $productionMovementId
     * @param array $ingredients
     * @throws Exception
     */
    private static function saveProductionDetails($productionMovementId, $ingredients)
    {
        $dataClass = self::getHlDataClass(self::HL_PRODUCTION_DETAILS);
        
        foreach ($ingredients as $ing) {
            $result = $dataClass::add([
                'UF_PRODUCTION_MOVEMENT_ID' => $productionMovementId,
                'UF_INGREDIENT_ID' => $ing['productId'],
                'UF_QUANTITY' => $ing['quantity'],
                'UF_UNIT' => $ing['unit'],
                'UF_PRICE' => $ing['price'],
            ]);
            
            if (!$result->isSuccess()) {
                throw new Exception('Ошибка сохранения деталей производства');
            }
        }
    }
    
    /**
     * Обновить производство
     * @param array $data
     * @return array
     * @throws Exception
     */
    public static function updateProduction($data = [])
    {
        $movementId = (int)($data['movementId'] ?? 0);
        $newQuantity = (float)($data['newQuantity'] ?? 0);
        
        if (!$movementId || $newQuantity <= 0) {
            throw new Exception('Неверные данные');
        }
        
        $movement = StockMovementHelper::getMovementById($movementId);
        if (!$movement) {
            throw new Exception('Движение не найдено');
        }
        
        $productionTypeId = self::getEnumId('UF_DOCUMENT_TYPE', 'production');
        if ($movement['UF_DOCUMENT_TYPE'] != $productionTypeId) {
            throw new Exception('Это не производственная операция');
        }
        
        $details = self::getProductionDetails($movementId);
        if (empty($details)) {
            throw new Exception('Детали производства не найдены');
        }
        
        $oldQuantity = $movement['UF_QUANTITY'];
        $ratio = $newQuantity / $oldQuantity;
        
        global $DB;
        $DB->StartTransaction();
        
        try {
            // Отменяем старую операцию
            self::revertProduction(['movementId' => $movementId]);
            
            $semiFinishedId = $movement['UF_PRODUCT_ID'];
            
            // Масштабируем ингредиенты
            $scaledIngredients = [];
            $totalCost = 0;
            
            foreach ($details as $detail) {
                $newIngQuantity = $detail['UF_QUANTITY'] * $ratio;
                $scaledIngredients[] = [
                    'productId' => $detail['UF_INGREDIENT_ID'],
                    'quantity' => $newIngQuantity,
                    'price' => $detail['UF_PRICE'],
                    'unit' => $detail['UF_UNIT']
                ];
                $totalCost += $detail['UF_PRICE'] * $newIngQuantity;
            }
            
            $newCostPrice = $totalCost / $newQuantity;
            
            // Списание ингредиентов
            foreach ($scaledIngredients as $ing) {
                self::addMovement([
                    'productId' => $ing['productId'],
                    'type' => 'outcome',
                    'quantity' => $ing['quantity'],
                    'price' => $ing['price'],
                    'documentType' => 'production',
                    'comment' => "Списано на производство полуфабриката ID {$semiFinishedId} (исправление)"
                ]);
            }
            
            // Оприходование
            $newMovementId = self::addMovement([
                'productId' => $semiFinishedId,
                'type' => 'income',
                'quantity' => $newQuantity,
                'price' => $newCostPrice,
                'documentType' => 'production',
                'comment' => "Произведено из сырья (исправление)"
            ]);
            
            // Сохраняем детали
            self::saveProductionDetails($newMovementId, $scaledIngredients);
            
            // Обновляем себестоимость
            \CIBlockElement::SetPropertyValuesEx(
                $semiFinishedId,
                self::IBLOCK_SEMI_FINISHED,
                ['COST_PRICE' => $newCostPrice]
            );
            
            $DB->Commit();
            
            return [
                'success' => true,
                'newMovementId' => $newMovementId,
                'newQuantity' => $newQuantity,
                'newCostPrice' => $newCostPrice
            ];
            
        } catch (Exception $e) {
            $DB->Rollback();
            throw $e;
        }
    }
}