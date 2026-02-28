<?php
// /local/php_interface/api/models/ProductModel.php

namespace Local\Api\Models;

use Bitrix\Iblock\Elements\ElementProductsTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;

class ProductModel
{
    const IBLOCK_ID = 1; // ID инфоблока "Товары"
    
    public function __construct()
    {
        Loader::includeModule('iblock');
    }
    
    /**
     * Получить список товаров
     */
    public function getList($filter = [], $select = [], $limit = 100)
    {
        $defaultSelect = [
            'ID',
            'NAME',
            'CODE',
            'ACTIVE',
            'SORT',
            'PREVIEW_TEXT',
            'DETAIL_TEXT',
            'PROPERTY_TYPE',
            'PROPERTY_UNIT',
            'PROPERTY_COST_PRICE',
            'PROPERTY_SELLING_PRICE',
            'PROPERTY_CURRENT_STOCK',
            'PROPERTY_MIN_STOCK',
            'PROPERTY_CATEGORY',
            'PROPERTY_PHOTO'
        ];
        
        $params = [
            'filter' => array_merge(['IBLOCK_ID' => self::IBLOCK_ID], $filter),
            'select' => !empty($select) ? $select : $defaultSelect,
            'limit' => $limit,
            'order' => ['SORT' => 'ASC', 'NAME' => 'ASC']
        ];
        
        $result = [];
        $res = ElementProductsTable::getList($params);
        
        while ($item = $res->fetch()) {
            $result[] = $this->formatProduct($item);
        }
        
        return $result;
    }
    
    /**
     * Получить товар по ID
     */
    public function getById($id)
    {
        $res = ElementProductsTable::getByPrimary($id, [
            'select' => ['*', 'PROPERTY_*']
        ]);
        
        if ($item = $res->fetch()) {
            return $this->formatProduct($item);
        }
        
        return null;
    }
    
    /**
     * Создать товар
     */
    public function create($data)
    {
        $fields = [
            'IBLOCK_ID' => self::IBLOCK_ID,
            'NAME' => $data['name'],
            'CODE' => $this->generateCode($data['name']),
            'ACTIVE' => $data['active'] ?? 'Y',
            'SORT' => $data['sort'] ?? 500,
            'PREVIEW_TEXT' => $data['description'] ?? '',
            'PROPERTY_VALUES' => [
                'TYPE' => $data['type'] ?? 'ingredient',
                'UNIT' => $data['unit'] ?? 'шт',
                'COST_PRICE' => $data['cost_price'] ?? 0,
                'SELLING_PRICE' => $data['selling_price'] ?? 0,
                'CURRENT_STOCK' => $data['current_stock'] ?? 0,
                'MIN_STOCK' => $data['min_stock'] ?? 0,
                'CATEGORY' => $data['category_id'] ?? null,
            ]
        ];
        
        $el = new \CIBlockElement();
        $id = $el->Add($fields);
        
        if (!$id) {
            throw new \Exception($el->LAST_ERROR);
        }
        
        return $this->getById($id);
    }
    
    /**
     * Обновить остаток товара
     */
    public function updateStock($productId, $quantity, $operation = 'set')
    {
        $current = $this->getById($productId);
        
        if (!$current) {
            throw new \Exception("Product not found");
        }
        
        $newStock = $current['current_stock'];
        
        switch ($operation) {
            case 'add':
                $newStock += $quantity;
                break;
            case 'subtract':
                $newStock -= $quantity;
                break;
            case 'set':
            default:
                $newStock = $quantity;
                break;
        }
        
        \CIBlockElement::SetPropertyValuesEx(
            $productId,
            self::IBLOCK_ID,
            ['CURRENT_STOCK' => $newStock]
        );
        
        return $newStock;
    }
    
    /**
     * Форматирование товара для API
     */
    private function formatProduct($item)
    {
        $props = [];
        
        // Собираем свойства
        foreach ($item as $key => $value) {
            if (strpos($key, 'PROPERTY_') === 0) {
                $propName = strtolower(str_replace('PROPERTY_', '', $key));
                $props[$propName] = $value;
            }
        }
        
        return [
            'id' => (int)$item['ID'],
            'name' => $item['NAME'],
            'code' => $item['CODE'],
            'active' => $item['ACTIVE'] === 'Y',
            'type' => $props['type'] ?? 'ingredient',
            'unit' => $props['unit'] ?? 'шт',
            'cost_price' => (float)($props['cost_price'] ?? 0),
            'selling_price' => (float)($props['selling_price'] ?? 0),
            'current_stock' => (float)($props['current_stock'] ?? 0),
            'min_stock' => (float)($props['min_stock'] ?? 0),
            'category_id' => (int)($props['category'] ?? 0),
            'photo' => $props['photo'] ?? null
        ];
    }
    
    /**
     * Генерация символьного кода
     */
    private function generateCode($name)
    {
        $code = \CUtil::translit($name, 'ru', [
            'max_len' => 100,
            'change_case' => 'L',
            'replace_space' => '-',
            'replace_other' => '-',
            'delete_repeat_replace' => true
        ]);
        
        return $code;
    }
}