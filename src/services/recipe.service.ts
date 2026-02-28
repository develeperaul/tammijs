import { Recipe, CreateRecipeDto, RecipeIngredient } from 'src/types/recipe.types';
import productService from './product.service';
import { Product } from 'src/types/product.types';

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
   * Получить все рецепты
   */
  async getRecipes(): Promise<Recipe[]> {
    await this.delay(300);
    return JSON.parse(JSON.stringify(mockRecipes)); // возвращаем копию
  }

  /**
   * Получить рецепт по ID готового блюда
   */
  async getRecipeByProductId(productId: number): Promise<Recipe | null> {
    await this.delay(200);
    const recipe = mockRecipes.find(r => r.productId === productId);
    return recipe ? JSON.parse(JSON.stringify(recipe)) : null;
  }

  /**
   * Получить рецепт по его ID
   */
  async getRecipeById(id: number): Promise<Recipe | null> {
    await this.delay(200);
    const recipe = mockRecipes.find(r => r.id === id);
    return recipe ? JSON.parse(JSON.stringify(recipe)) : null;
  }

  /**
   * Создать новый рецепт
   */
  async createRecipe(data: CreateRecipeDto): Promise<Recipe> {
    await this.delay(600);
    // Проверим, что товар с таким productId существует и он типа finished
    const products = await getMockProducts();
    const product = products.find(p => p.id === data.productId);
    if (!product) throw new Error('Товар не найден');
    if (product.type !== 'finished') throw new Error('Рецепт можно создавать только для готовых товаров');

    const newId = mockRecipes.length + 1;
    const newRecipe: Recipe = {
      id: newId,
      productId: data.productId,
      productName: product.name,
      name: data.name,
      outputWeight: data.outputWeight,
      outputUnit: data.outputUnit,
      cookingTime: data.cookingTime,
      instructions: data.instructions,
      ingredients: data.ingredients.map((ing, idx) => ({
        id: idx + 1,
        recipeId: newId,
        ingredientId: ing.ingredientId,
        quantity: ing.quantity,
        unit: ing.unit,
        isOptional: ing.isOptional
      })),
      createdAt: new Date().toISOString()
    };

    mockRecipes.push(newRecipe);
    return newRecipe;
  }

  /**
   * Обновить рецепт
   */
  async updateRecipe(id: number, data: Partial<CreateRecipeDto>): Promise<Recipe> {
    await this.delay(600);
    const index = mockRecipes.findIndex(r => r.id === id);
    if (index === -1) throw new Error('Рецепт не найден');

    const products = await getMockProducts();
    if (data.productId) {
      const product = products.find(p => p.id === data.productId);
      if (!product) throw new Error('Товар не найден');
      mockRecipes[index].productName = product.name;
    }

    mockRecipes[index] = {
      ...mockRecipes[index],
      ...data,
      productId: data.productId ?? mockRecipes[index].productId,
      name: data.name ?? mockRecipes[index].name,
      outputWeight: data.outputWeight ?? mockRecipes[index].outputWeight,
      outputUnit: data.outputUnit ?? mockRecipes[index].outputUnit,
      cookingTime: data.cookingTime ?? mockRecipes[index].cookingTime,
      instructions: data.instructions ?? mockRecipes[index].instructions,
      updatedAt: new Date().toISOString()
    };

    // Если переданы ингредиенты, заменяем их (с генерацией id)
    if (data.ingredients) {
      mockRecipes[index].ingredients = data.ingredients.map((ing, idx) => ({
        id: idx + 1,
        recipeId: id,
        ingredientId: ing.ingredientId,
        quantity: ing.quantity,
        unit: ing.unit,
        isOptional: ing.isOptional
      }));
    }

    return mockRecipes[index];
  }

  /**
   * Удалить рецепт
   */
  async deleteRecipe(id: number): Promise<boolean> {
    await this.delay(400);
    const index = mockRecipes.findIndex(r => r.id === id);
    if (index === -1) return false;
    mockRecipes.splice(index, 1);
    return true;
  }

  /**
   * Рассчитать себестоимость блюда по id рецепта на основе текущих цен ингредиентов
   */
  async calculateCost(recipeId: number): Promise<number> {
    const recipe = await this.getRecipeById(recipeId);
    if (!recipe) throw new Error('Рецепт не найден');

    const products = await getMockProducts();
    let total = 0;
    for (const ing of recipe.ingredients) {
      const product = products.find(p => p.id === ing.ingredientId);
      if (!product) continue;
      // себестоимость ингредиента за единицу * количество в нужных единицах
      // требуется нормализация единиц, здесь для простоты предполагаем, что unit совпадает
      total += (product.costPrice || 0) * ing.quantity;
    }
    return total;
  }

  /**
   * Списать ингредиенты для данного рецепта (используется при продаже)
   */
  async consumeIngredients(recipeId: number, quantity: number = 1): Promise<void> {
    const recipe = await this.getRecipeById(recipeId);
    if (!recipe) throw new Error('Рецепт не найден');

    // Подготовим массив для списания
    const items = recipe.ingredients.map(ing => ({
      productId: ing.ingredientId,
      quantity: ing.quantity * quantity,
      reason: `Списание по рецепту ${recipe.name}`
    }));

    // Вызовем stockService.writeOff (нужно импортировать)
    // Но чтобы избежать циклической зависимости, можно передать через callback или импортировать напрямую
    const stockService = (await import('./stock.service')).default;
    await stockService.writeOff({ items, reason: `Продажа блюда ${recipe.name}` });
  }

  private delay(ms: number): Promise<void> {
    return new Promise(resolve => setTimeout(resolve, ms));
  }
}

export default RecipeService.getInstance();
