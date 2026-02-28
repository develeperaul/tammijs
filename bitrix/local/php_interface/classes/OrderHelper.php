<?php
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Bitrix\Highloadblock\HighloadBlockTable;

class OrderHelper
{
    private static $hlBlockId = 2; // ⚠️ ЗАМЕНИТЕ НА ID вашего HL-блока Orders
    private static $entityDataClass = null;

    private static function getDataClass()
    {
        if (self::$entityDataClass !== null) {
            return self::$entityDataClass;
        }

        Loader::includeModule('highloadblock');
        
        $hlblock = HighloadBlockTable::getById(self::$hlBlockId)->fetch();
        if (!$hlblock) {
            throw new \Exception('HL-блок Orders не найден');
        }
        
        $entity = HighloadBlockTable::compileEntity($hlblock);
        self::$entityDataClass = $entity->getDataClass();
        
        return self::$entityDataClass;
    }

    /**
     * Создать заказ
     * @param array $fields поля заказа (UF_*)
     * @return int ID созданного заказа
     */
    public static function createOrder(array $fields)
    {
        $dataClass = self::getDataClass();
        
        // Генерация номера заказа, если не указан
        if (empty($fields['UF_NUMBER'])) {
            $fields['UF_NUMBER'] = 'ORD-' . date('Ymd') . '-' . rand(1000, 9999);
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
     * Получить список заказов с фильтрацией
     */
    public static function getOrders(array $filter = [], array $order = ['ID' => 'DESC'], $limit = 100, $offset = 0)
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
     * Получить один заказ по ID
     */
    public static function getOrderById($orderId)
    {
        $dataClass = self::getDataClass();
        return $dataClass::getById($orderId)->fetch();
    }

    /**
     * Обновить статус заказа
     */
    public static function updateOrderStatus($orderId, $status)
    {
        $dataClass = self::getDataClass();
        
        $result = $dataClass::update($orderId, ['UF_STATUS' => $status]);
        
        if (!$result->isSuccess()) {
            throw new \Exception(implode(', ', $result->getErrorMessages()));
        }
        
        return true;
    }

    /**
     * Получить заказы для кухни (статусы new, cooking)
     */
    public static function getKitchenOrders()
    {
        return self::getOrders(
            ['=UF_STATUS' => ['new', 'cooking']],
            ['UF_CREATED_AT' => 'ASC']
        );
    }
}