import { api } from 'boot/axios';
import { Recipe, CreateRecipeDto, RecipeIngredient } from 'src/types/recipe.types';
import productService from './product.service';
import { Product } from 'src/types/product.types';
import { ApiResponse } from 'src/types/api.types';

// Мок-данные продуктов (ингредиентов) – используем те же, что и в product.service
// Для простоты скопируем нужные или импортируем, но избежим циклических зависимостей.
// Лучше сделать отдельный репозиторий или использовать уже имеющийся productService.
// В реальном проекте productService уже имеет метод getProducts.

// Вспомогательная функция для получения списка ингредиентов
const getMockProducts = async (): Promise<Product[]> => {
  // Возвращаем те же моки, что и в product.service (упрощённо)
  return [
    { id: 1, name: 'Рис круглозерный', type: 'ingredient', unit: 'кг', currentStock: 15.5, costPrice: 80, sellingPrice: 120, minStock: 5, categoryId: 1, code: 'rice', active: true },
    { id: 2, name: 'Нори листы', type: 'ingredient', unit: 'уп', currentStock: 50, costPrice: 180, sellingPrice: 250, minStock: 10, categoryId: 1, code: 'nori', active: true },
    { id: 3, name: 'Лосось слабой соли', type: 'ingredient', unit: 'кг', currentStock: 3.2, costPrice: 750, sellingPrice: 950, minStock: 2, categoryId: 2, code: 'salmon', active: true },
    { id: 4, name: 'Кола 0.5л', type: 'finished', unit: 'шт', currentStock: 48, costPrice: 45, sellingPrice: 80, minStock: 20, categoryId: 3, code: 'cola', active: true },
    { id: 101, name: 'Ролл Калифорния', type: 'finished', unit: 'шт', currentStock: 10, costPrice: 150, sellingPrice: 350, minStock: 5, categoryId: 4, code: 'california', active: true },
    { id: 102, name: 'Ролл Филадельфия', type: 'finished', unit: 'шт', currentStock: 8, costPrice: 180, sellingPrice: 420, minStock: 3, categoryId: 4, code: 'philadelphia', active: true },
  ];
};

// Мок-данные рецептов
let mockRecipes: Recipe[] = [
  {
    id: 1,
    productId: 101,
    productName: 'Ролл Калифорния',
    name: 'Рецепт Калифорния',
    outputWeight: 250,
    outputUnit: 'г',
    cookingTime: 15,
    instructions: 'Завернуть рис с начинкой в нори...',
    ingredients: [
      { id: 1, recipeId: 1, ingredientId: 1, ingredientName: 'Рис круглозерный', quantity: 0.1, unit: 'кг', cost: 8 },
      { id: 2, recipeId: 1, ingredientId: 2, ingredientName: 'Нори листы', quantity: 0.5, unit: 'лист', cost: 9 },
      { id: 3, recipeId: 1, ingredientId: 3, ingredientName: 'Лосось слабой соли', quantity: 0.05, unit: 'кг', cost: 37.5 },
    ]
  },
  {
    id: 2,
    productId: 102,
    productName: 'Ролл Филадельфия',
    name: 'Рецепт Филадельфия',
    outputWeight: 280,
    outputUnit: 'г',
    cookingTime: 18,
    instructions: 'Сливочный сыр, лосось, рис...',
    ingredients: [
      { id: 4, recipeId: 2, ingredientId: 1, ingredientName: 'Рис круглозерный', quantity: 0.12, unit: 'кг', cost: 9.6 },
      { id: 5, recipeId: 2, ingredientId: 2, ingredientName: 'Нори листы', quantity: 0.5, unit: 'лист', cost: 9 },
      { id: 6, recipeId: 2, ingredientId: 3, ingredientName: 'Лосось слабой соли', quantity: 0.07, unit: 'кг', cost: 52.5 },
    ]
  }
];



class RecipeService {
  private static instance: RecipeService;

  private constructor() {}

  public static getInstance(): RecipeService {
    if (!RecipeService.instance) {
      RecipeService.instance = new RecipeService();
    }
    return RecipeService.instance;
  }

  /**
   * Получить список рецептов
   */
  async getRecipes(filter?: RecipeFilter): Promise<ApiResponse<Recipe[]>> {
    const params: Record<string, any> = { action: 'recipes.get' };
    if (filter?.productId) params.productId = filter.productId;
    if (filter?.search) params.search = filter.search;

    const response = await api.get('/index.php', { params });
    console.log(response);

    return response.data;
  }

  /**
   * Получить рецепт по ID товара
   */
  async getRecipeByProductId(productId: number): Promise<Recipe | null> {
    const recipes = await this.getRecipes({ productId });
    return recipes[0] || null;
  }

  /**
   * Получить рецепт по ID
   */
  async getRecipeById(id: number): Promise<Recipe | null> {
    const params = { action: 'recipes.get', id };
    const response = await api.get('/index.php', { params });
    return response.data[0] || null;
  }

  /**
   * Создать рецепт
   */
  async createRecipe(data: CreateRecipeDto): Promise<{ recipeId: number }> {
    const response = await api.post('/index.php', data, {
      params: { action: 'recipe.create' }
    });
    return response.data;
  }

  /**
   * Обновить рецепт
   */
  async updateRecipe(id: number, data: Partial<CreateRecipeDto>): Promise<{ success: boolean }> {
    const response = await api.post('/index.php', data, {
      params: { action: 'recipe.update', id }
    });
    return response.data;
  }

  /**
   * Удалить рецепт
   */
  async deleteRecipe(id: number): Promise<{ success: boolean }> {
    const response = await api.delete('/index.php', {
      params: { action: 'recipe.delete', id }
    });
    return response.data;
  }

  /**
   * Рассчитать себестоимость
   */
  async calculateCost(recipeId: number): Promise<{ cost: number }> {
    const response = await api.get('/index.php', {
      params: { action: 'recipe.calculate.cost', recipeId }
    });
    return response.data;
  }
}

export default RecipeService.getInstance();
