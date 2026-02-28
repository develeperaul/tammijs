<?php
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Bitrix\Highloadblock\HighloadBlockTable;

class StockMovementHelper
{
    private static $hlBlockId = 1; // ⚠️ Укажите ID вашего HL-блока StockMovements
    private static $entityDataClass = null;

    /**
     * Получить класс данных для работы с HL-блоком
     */
    private static function getDataClass()
    {
        if (self::$entityDataClass !== null) {
            return self::$entityDataClass;
        }

        Loader::includeModule('highloadblock');
        
        $hlblock = HighloadBlockTable::getById(self::$hlBlockId)->fetch();
        if (!$hlblock) {
            throw new \Exception('HL-блок не найден');
        }
        
        $entity = HighloadBlockTable::compileEntity($hlblock);
        self::$entityDataClass = $entity->getDataClass();
        
        return self::$entityDataClass;
    }

    /**
     * Добавить запись о движении товара
     */
    public static function addMovement(array $fields)
    {
        $dataClass = self::getDataClass();
        
        // Проверка обязательных полей
        $required = ['UF_PRODUCT_ID', 'UF_TYPE', 'UF_QUANTITY'];
        foreach ($required as $field) {
            if (empty($fields[$field])) {
                throw new \Exception("Поле {$field} обязательно");
            }
        }

        // Добавляем дату создания, если не указана
        if (empty($fields['UF_CREATED_AT'])) {
            $fields['UF_CREATED_AT'] = new DateTime();
        }

        $result = $dataClass::add($fields);
        
        if (!$result->isSuccess()) {
            throw new \Exception(implode(', ', $result->getErrorMessages()));
        }
        
        return $result->getId();
    }

    /**
     * Получить список движений с фильтрацией
     */
    public static function getMovements(array $filter = [], array $order = ['ID' => 'DESC'], $limit = 100, $offset = 0)
    {
        $dataClass = self::getDataClass();
        
        $params = [
            'select' => ['*'],
            'filter' => $filter,
            'order' => $order,
            'limit' => $limit,
            'offset' => $offset
        ];
        
        $result = [];
        $res = $dataClass::getList($params);
        while ($row = $res->fetch()) {
            if ($row['UF_CREATED_AT'] instanceof DateTime) {
                $row['UF_CREATED_AT'] = $row['UF_CREATED_AT']->toString();
            }
            $result[] = $row;
        }
        
        return $result;
    }

    /**
     * Получить движения по конкретному товару
     */
    public static function getMovementsByProduct($productId, $limit = 50)
    {
        return self::getMovements(
            ['=UF_PRODUCT_ID' => $productId],
            ['UF_CREATED_AT' => 'DESC'],
            $limit
        );
    }

    /**
     * Обновить запись
     */
    public static function updateMovement($id, array $fields)
    {
        $dataClass = self::getDataClass();
        
        $result = $dataClass::update($id, $fields);
        
        if (!$result->isSuccess()) {
            throw new \Exception(implode(', ', $result->getErrorMessages()));
        }
        
        return true;
    }

    /**
     * Удалить запись
     */
    public static function deleteMovement($id)
    {
        $dataClass = self::getDataClass();
        
        $result = $dataClass::delete($id);
        
        if (!$result->isSuccess()) {
            throw new \Exception(implode(', ', $result->getErrorMessages()));
        }
        
        return true;
    }

    /**
     * Подсчитать количество записей
     */
    public static function getCount(array $filter = [])
    {
        $dataClass = self::getDataClass();
        return $dataClass::getCount($filter);
    }

    /**
     * Добавить движение и обновить остаток товара в инфоблоке
     */
    public static function addMovementWithStockUpdate(array $fields)
    {
        Loader::includeModule('iblock');
        
        $productId = (int)$fields['UF_PRODUCT_ID'];
        $iblockId = 1; // ⚠️ Укажите ID вашего инфоблока товаров

        // Получаем текущий остаток из инфоблока
        $res = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => $iblockId, 'ID' => $productId],
            false,
            false,
            ['ID', 'PROPERTY_CURRENT_STOCK']
        );
        $product = $res->Fetch();
        if (!$product) {
            throw new \Exception('Товар не найден');
        }

        $currentStock = (float)$product['PROPERTY_CURRENT_STOCK_VALUE'];
        $quantity = (float)$fields['UF_QUANTITY'];
        $newStock = $currentStock;

        switch ($fields['UF_TYPE']) {
            case 'income':
                $newStock += $quantity;
                break;
            case 'outcome':
            case 'write-off':
                if ($currentStock < $quantity) {
                    throw new \Exception('Недостаточно товара на складе');
                }
                $newStock -= $quantity;
                break;
            default:
                throw new \Exception('Неизвестный тип движения');
        }

        // Сохраняем движение в HL-блок
        $movementId = self::addMovement($fields);

        // Обновляем свойство CURRENT_STOCK в инфоблоке
        \CIBlockElement::SetPropertyValuesEx(
            $productId,
            $iblockId,
            ['CURRENT_STOCK' => $newStock]
        );

        return $movementId;
    }

    /**
     * Создать тестовые данные (для отладки)
     */
    public static function createTestData($count = 10)
    {
        $types = ['income', 'outcome', 'write-off'];
        $docTypes = ['manual', 'invoice', 'sale'];

        for ($i = 0; $i < $count; $i++) {
            $fields = [
                'UF_PRODUCT_ID' => rand(1, 5),
                'UF_TYPE' => $types[array_rand($types)],
                'UF_QUANTITY' => rand(1, 100) / 10,
                'UF_PRICE' => rand(100, 1000),
                'UF_DOCUMENT_TYPE' => $docTypes[array_rand($docTypes)],
                'UF_DOCUMENT_ID' => rand(100, 999),
                'UF_COMMENT' => 'Тестовая запись ' . ($i + 1),
                'UF_CREATED_BY' => 1,
                'UF_CREATED_AT' => new DateTime()
            ];

            try {
                $id = self::addMovement($fields);
                echo "Добавлена запись ID: {$id}\n";
            } catch (\Exception $e) {
                echo "Ошибка: " . $e->getMessage() . "\n";
            }
        }
    }
}