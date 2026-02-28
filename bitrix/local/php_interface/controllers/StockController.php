<?php
// /local/php_interface/api/controllers/StockController.php

namespace Local\Api\Controllers;

use Local\Api\Models\ProductModel;
use Local\Api\Models\StockMovementModel;
use Local\Api\Helpers\ResponseHelper;

class StockController
{
    private $productModel;
    private $movementModel;
    
    public function __construct()
    {
        $this->productModel = new ProductModel();
        $this->movementModel = new StockMovementModel();
    }
    
    /**
     * GET /stock - текущие остатки
     */
    public function getCurrent($matches, $params)
    {
        $filter = [];
        
        if (isset($params['category_id'])) {
            $filter['PROPERTY_CATEGORY'] = $params['category_id'];
        }
        
        if (isset($params['type'])) {
            $filter['PROPERTY_TYPE'] = $params['type'];
        }
        
        if (isset($params['low_stock']) && $params['low_stock'] === 'true') {
            // Товары с остатком ниже минимального
            $filter['<=PROPERTY_CURRENT_STOCK'] = ['LOGIC' => 'OR', 'PROPERTY_MIN_STOCK'];
        }
        
        $products = $this->productModel->getList($filter);
        
        return [
            'success' => true,
            'data' => $products,
            'total' => count($products)
        ];
    }
    
    /**
     * POST /stock/movement - добавить движение
     */
    public function addMovement($matches, $params)
    {
        $required = ['product_id', 'quantity', 'type'];
        foreach ($required as $field) {
            if (!isset($params[$field])) {
                throw new \Exception("Missing required field: {$field}", 400);
            }
        }
        
        // Валидация
        $productId = (int)$params['product_id'];
        $quantity = (float)$params['quantity'];
        $type = $params['type']; // income, outcome, write-off
        
        // Проверяем товар
        $product = $this->productModel->getById($productId);
        if (!$product) {
            throw new \Exception("Product not found", 404);
        }
        
        // Добавляем движение
        $movementId = $this->movementModel->create([
            'product_id' => $productId,
            'type' => $type,
            'quantity' => $quantity,
            'document_type' => $params['document_type'] ?? 'manual',
            'document_id' => $params['document_id'] ?? null,
            'comment' => $params['comment'] ?? '',
            'created_by' => $params['user_id'] ?? 0
        ]);
        
        // Обновляем остаток
        $operation = ($type === 'income') ? 'add' : 'subtract';
        $newStock = $this->productModel->updateStock($productId, $quantity, $operation);
        
        return [
            'success' => true,
            'movement_id' => $movementId,
            'new_stock' => $newStock
        ];
    }
    
    /**
     * GET /stock/history - история движений
     */
    public function getHistory($matches, $params)
    {
        $filter = [];
        
        if (isset($params['product_id'])) {
            $filter['=PRODUCT_ID'] = (int)$params['product_id'];
        }
        
        if (isset($params['date_from'])) {
            $filter['>=CREATED_AT'] = $params['date_from'];
        }
        
        if (isset($params['date_to'])) {
            $filter['<=CREATED_AT'] = $params['date_to'];
        }
        
        $limit = $params['limit'] ?? 100;
        $offset = $params['offset'] ?? 0;
        
        $movements = $this->movementModel->getList($filter, $limit, $offset);
        $total = $this->movementModel->getCount($filter);
        
        return [
            'success' => true,
            'data' => $movements,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ];
    }
    
    /**
     * POST /stock/write-off - списание
     */
    public function writeOff($matches, $params)
    {
        $required = ['items', 'reason'];
        foreach ($required as $field) {
            if (!isset($params[$field])) {
                throw new \Exception("Missing required field: {$field}", 400);
            }
        }
        
        $results = [];
        
        foreach ($params['items'] as $item) {
            // Проверяем достаточно ли остатка
            $product = $this->productModel->getById($item['product_id']);
            
            if ($product['current_stock'] < $item['quantity']) {
                throw new \Exception(
                    "Insufficient stock for product {$product['name']}. " .
                    "Available: {$product['current_stock']}, requested: {$item['quantity']}",
                    400
                );
            }
            
            // Добавляем движение списания
            $movementId = $this->movementModel->create([
                'product_id' => $item['product_id'],
                'type' => 'write-off',
                'quantity' => $item['quantity'],
                'document_type' => 'write-off',
                'comment' => $params['reason'],
                'created_by' => $params['user_id'] ?? 0
            ]);
            
            // Обновляем остаток
            $newStock = $this->productModel->updateStock(
                $item['product_id'], 
                $item['quantity'], 
                'subtract'
            );
            
            $results[] = [
                'product_id' => $item['product_id'],
                'movement_id' => $movementId,
                'new_stock' => $newStock
            ];
        }
        
        return [
            'success' => true,
            'write_off_id' => uniqid('wo_'),
            'items' => $results
        ];
    }
}