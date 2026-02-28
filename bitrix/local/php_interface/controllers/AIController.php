<?php
// /local/php_interface/api/controllers/AIController.php

namespace Local\Api\Controllers;

use Local\Api\Models\ProductModel;
use Local\Api\Models\InvoiceModel;

class AIController
{
    private $productModel;
    private $invoiceModel;
    
    public function __construct()
    {
        $this->productModel = new ProductModel();
        $this->invoiceModel = new InvoiceModel();
    }
    
    /**
     * POST /ai/recognize-invoice - распознать накладную по фото
     */
    public function recognizeInvoice($matches, $params)
    {
        // Проверяем загружен ли файл
        if (empty($_FILES['photo'])) {
            throw new \Exception("Photo file required", 400);
        }
        
        $file = $_FILES['photo'];
        
        // Сохраняем файл
        $fileId = $this->saveFile($file);
        
        // Имитация вызова нейросети
        // В реальности здесь будет запрос к внешнему AI сервису
        $aiResult = $this->callAIService($fileId);
        
        // Сопоставляем с товарами в базе
        $matchedItems = $this->matchWithProducts($aiResult['items']);
        
        return [
            'success' => true,
            'photo_id' => $fileId,
            'photo_url' => $this->getFileUrl($fileId),
            'recognized' => $matchedItems,
            'total_amount' => $aiResult['total'] ?? 0
        ];
    }
    
    /**
     * Вызов внешнего AI сервиса
     */
    private function callAIService($fileId)
    {
        $filePath = $_SERVER['DOCUMENT_ROOT'] . \CFile::GetPath($fileId);
        
        // Здесь будет реальный запрос к нейросети
        // Например: curl -X POST https://api.neuro.ru/recognize -F "file=@{$filePath}"
        
        // Пока возвращаем заглушку
        return [
            'items' => [
                [
                    'recognized_name' => 'Рис круглозерный 5кг',
                    'quantity' => 2,
                    'price' => 450.00,
                    'unit' => 'шт',
                    'confidence' => 0.95
                ],
                [
                    'recognized_name' => 'Лосось филе 1кг',
                    'quantity' => 3.5,
                    'price' => 1250.00,
                    'unit' => 'кг',
                    'confidence' => 0.87
                ],
                [
                    'recognized_name' => 'Нори листы 10шт',
                    'quantity' => 5,
                    'price' => 180.00,
                    'unit' => 'уп',
                    'confidence' => 0.92
                ]
            ],
            'total' => 450*2 + 1250*3.5 + 180*5
        ];
    }
    
    /**
     * Сопоставление распознанных товаров с существующими в БД
     */
    private function matchWithProducts($aiItems)
    {
        $result = [];
        
        foreach ($aiItems as $item) {
            // Ищем похожие товары
            $products = $this->productModel->getList([
                '%NAME' => explode(' ', $item['recognized_name'])[0] // Первое слово
            ], ['ID', 'NAME', 'UNIT'], 5);
            
            $matches = [];
            foreach ($products as $product) {
                $similarity = 0;
                similar_text(
                    strtolower($item['recognized_name']), 
                    strtolower($product['name']), 
                    $similarity
                );
                
                $matches[] = [
                    'product_id' => $product['id'],
                    'product_name' => $product['name'],
                    'similarity' => round($similarity, 2)
                ];
            }
            
            // Сортируем по схожести
            usort($matches, function($a, $b) {
                return $b['similarity'] <=> $a['similarity'];
            });
            
            $result[] = [
                'recognized_name' => $item['recognized_name'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'unit' => $item['unit'],
                'confidence' => $item['confidence'],
                'matches' => $matches
            ];
        }
        
        return $result;
    }
    
    /**
     * Сохранение файла
     */
    private function saveFile($file)
    {
        $arFile = \CFile::MakeFileArray($file['tmp_name']);
        $arFile['MODULE_ID'] = 'api';
        
        $fileId = \CFile::SaveFile($arFile, 'invoices');
        
        if (!$fileId) {
            throw new \Exception("Failed to save file");
        }
        
        return $fileId;
    }
    
    /**
     * Получение URL файла
     */
    private function getFileUrl($fileId)
    {
        return \CFile::GetPath($fileId);
    }
}