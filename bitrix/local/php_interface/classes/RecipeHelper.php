<?php
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Bitrix\Highloadblock\HighloadBlockTable;

class RecipeHelper
{
    private static $hlBlockId = 4; // ⚠️ ID HL-блока Recipes
    private static $ingredientsHlBlockId = 5; // ⚠️ ID HL-блока RecipeIngredients
    private static $recipeClass = null;
    private static $ingredientClass = null;

    private static function getDataClass($hlBlockId)
    {
        Loader::includeModule('highloadblock');
        
        $hlblock = HighloadBlockTable::getById($hlBlockId)->fetch();
        if (!$hlblock) {
            throw new \Exception("HL-блок с ID {$hlBlockId} не найден");
        }
        
        $entity = HighloadBlockTable::compileEntity($hlblock);
        return $entity->getDataClass();
    }

    private static function getRecipeClass()
    {
        if (self::$recipeClass === null) {
            self::$recipeClass = self::getDataClass(self::$hlBlockId);
        }
        return self::$recipeClass;
    }

    private static function getIngredientClass()
    {
        if (self::$ingredientClass === null) {
            self::$ingredientClass = self::getDataClass(self::$ingredientsHlBlockId);
        }
        return self::$ingredientClass;
    }

    /**
     * Создать рецепт с ингредиентами
     * @param array $recipeData поля рецепта (UF_*)
     * @param array $ingredients массив ингредиентов (каждый - массив UF_*)
     * @return int ID созданного рецепта
     */
    public static function createRecipe(array $recipeData, array $ingredients)
    {
        $recipeClass = self::getRecipeClass();
        
        // Создаём рецепт
        $result = $recipeClass::add($recipeData);
        if (!$result->isSuccess()) {
            throw new \Exception(implode(', ', $result->getErrorMessages()));
        }
        
        $recipeId = $result->getId();
        
        // Добавляем ингредиенты
        $ingredientClass = self::getIngredientClass();
        foreach ($ingredients as $ing) {
            $ing['UF_RECIPE_ID'] = $recipeId;
            $ingResult = $ingredientClass::add($ing);
            if (!$ingResult->isSuccess()) {
                // Если ошибка, можно откатить или просто выбросить исключение
                throw new \Exception(implode(', ', $ingResult->getErrorMessages()));
            }
        }
        
        return $recipeId;
    }

    /**
     * Получить рецепт с ингредиентами по ID готового блюда
     * @param int $productId ID товара (готового блюда) из инфоблока
     */
    public static function getByProductId($productId)
    {
        $recipeClass = self::getRecipeClass();
        
        $recipe = $recipeClass::getList([
            'select' => ['*'],
            'filter' => ['=UF_PRODUCT_ID' => $productId],
            'limit' => 1
        ])->fetch();
        
        if (!$recipe) {
            return null;
        }
        
        // Получаем ингредиенты
        $ingredientClass = self::getIngredientClass();
        $ingredients = $ingredientClass::getList([
            'select' => ['*'],
            'filter' => ['=UF_RECIPE_ID' => $recipe['ID']],
            'order' => ['ID' => 'ASC']
        ])->fetchAll();
        
        $recipe['INGREDIENTS'] = $ingredients;
        
        return $recipe;
    }

    /**
     * Рассчитать себестоимость блюда по ID рецепта
     * @param int $recipeId ID рецепта
     * @return float общая себестоимость
     */
    public static function calculateCost($recipeId)
    {
        $recipeClass = self::getRecipeClass();
        $recipe = $recipeClass::getById($recipeId)->fetch();
        if (!$recipe) {
            throw new \Exception('Рецепт не найден');
        }
        
        Loader::includeModule('iblock');
        $iblockId = 1; // ⚠️ ID инфоблока товаров
        
        $ingredientClass = self::getIngredientClass();
        $ingredients = $ingredientClass::getList([
            'filter' => ['=UF_RECIPE_ID' => $recipeId]
        ])->fetchAll();
        
        $total = 0;
        foreach ($ingredients as $ing) {
            // Получаем себестоимость ингредиента из инфоблока
            $res = \CIBlockElement::GetList(
                [],
                ['IBLOCK_ID' => $iblockId, 'ID' => $ing['UF_INGREDIENT_ID']],
                false,
                false,
                ['ID', 'PROPERTY_COST_PRICE']
            );
            $product = $res->Fetch();
            if ($product) {
                $costPrice = (float)$product['PROPERTY_COST_PRICE_VALUE'];
                $total += $costPrice * $ing['UF_QUANTITY'];
            }
        }
        
        return $total;
    }

    /**
     * Обновить рецепт (без ингредиентов – для простоты можно добавить отдельно)
     */
    public static function updateRecipe($recipeId, array $data)
    {
        $recipeClass = self::getRecipeClass();
        $result = $recipeClass::update($recipeId, $data);
        return $result->isSuccess();
    }

    /**
     * Удалить рецепт и все его ингредиенты
     */
    public static function deleteRecipe($recipeId)
    {
        // Удаляем ингредиенты
        $ingredientClass = self::getIngredientClass();
        $ingredients = $ingredientClass::getList([
            'filter' => ['=UF_RECIPE_ID' => $recipeId]
        ]);
        while ($ing = $ingredients->fetch()) {
            $ingredientClass::delete($ing['ID']);
        }
        
        // Удаляем сам рецепт
        $recipeClass = self::getRecipeClass();
        $result = $recipeClass::delete($recipeId);
        return $result->isSuccess();
    }
}