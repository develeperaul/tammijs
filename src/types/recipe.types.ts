import { Product } from './product.types';

export interface Recipe {
  id: number;
  productId: number;           // готовое блюдо, к которому относится рецепт
  productName?: string;        // для отображения
  name: string;                // название рецепта (может совпадать с названием товара)
  outputWeight: number;        // выход готового блюда (в граммах или штуках)
  outputUnit: string;          // единица измерения (г, шт, мл)
  cookingTime?: number;        // время приготовления в минутах
  instructions?: string;       // текстовое описание процесса
  ingredients: RecipeIngredient[];
  createdAt?: string;
  updatedAt?: string;
}

export interface RecipeIngredient {
  id?: number;
  recipeId?: number;
  ingredientId: number;        // товар-ингредиент (сырьё)
  ingredientName?: string;     // для отображения
  quantity: number;            // количество ингредиента на одну порцию
  unit: string;                // единица измерения ингредиента (кг, г, шт, л)
  isOptional?: boolean;        // опциональный ингредиент (например, можно убрать)
  cost?: number;               // расчётная стоимость ингредиента в данной порции (для информации)
}

export interface CreateRecipeDto {
  productId: number;
  name: string;
  outputWeight: number;
  outputUnit: string;
  cookingTime?: number;
  instructions?: string;
  ingredients: Omit<RecipeIngredient, 'id' | 'recipeId'>[];
  photo: string
}
