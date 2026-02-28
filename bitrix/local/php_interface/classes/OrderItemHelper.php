<?php
use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;

class OrderItemHelper
{
    private static $hlBlockId = 3; // ⚠️ ЗАМЕНИТЕ НА ID вашего HL-блока OrderItems
    private static $entityDataClass = null;

    private static function getDataClass()
    {
        if (self::$entityDataClass !== null) {
            return self::$entityDataClass;
        }

        Loader::includeModule('highloadblock');
        
        $hlblock = HighloadBlockTable::getById(self::$hlBlockId)->fetch();
        if (!$hlblock) {
            throw new \Exception('HL-блок OrderItems не найден');
        }
        
        $entity = HighloadBlockTable::compileEntity($hlblock);
        self::$entityDataClass = $entity->getDataClass();
        
        return self::$entityDataClass;
    }

    /**
     * Добавить позиции к заказу
     * @param int $orderId ID заказа
     * @param array $items массив позиций (каждая позиция - массив UF_* полей)
     * @return array массив ID созданных позиций
     */
    public static function addItems($orderId, array $items)
    {
        $dataClass = self::getDataClass();
        $addedIds = [];
        
        foreach ($items as $item) {
            $item['UF_ORDER_ID'] = $orderId;
            $result = $dataClass::add($item);
            
            if (!$result->isSuccess()) {
                throw new \Exception(implode(', ', $result->getErrorMessages()));
            }
            
            $addedIds[] = $result->getId();
        }
        
        return $addedIds;
    }

    /**
     * Получить все позиции заказа
     */
    public static function getByOrderId($orderId)
    {
        $dataClass = self::getDataClass();
        
        $res = $dataClass::getList([
            'select' => ['*'],
            'filter' => ['=UF_ORDER_ID' => $orderId],
            'order' => ['ID' => 'ASC']
        ]);
        
        return $res->fetchAll();
    }

    /**
     * Обновить статус готовки позиции
     */
    public static function updateCookingStatus($itemId, $status)
    {
        $dataClass = self::getDataClass();
        
        $result = $dataClass::update($itemId, ['UF_COOKING_STATUS' => $status]);
        
        if (!$result->isSuccess()) {
            throw new \Exception(implode(', ', $result->getErrorMessages()));
        }
        
        return true;
    }

    /**
     * Удалить позицию (если потребуется)
     */
    public static function deleteItem($itemId)
    {
        $dataClass = self::getDataClass();
        $result = $dataClass::delete($itemId);
        return $result->isSuccess();
    }
}