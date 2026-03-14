import { Ingredient } from './ingredient.types';
import { SemiFinished } from './semi-finished.types';

export type RecipeItemType = 'ingredient' | 'semi-finished';

export interface RecipeItem {
  id?: number;
  itemType: RecipeItemType;     // тип: ингредиент или полуфабрикат
  itemId: number;               // ID ингредиента или полуфабриката
  itemName?: string;            // название для отображения
  quantity: number;             // количество
  unit: string;                 // единица измерения
  isOptional?: boolean;         // опциональный ингредиент
  cost?: number;                // расчётная стоимость
}

export interface Recipe {
  id: number;
  productId: number;            // готовое блюдо
  productName?: string;
  name: string;                 // название рецепта
  outputWeight: number;         // выход блюда
  outputUnit: string;           // единица измерения
  cookingTime?: number;
  instructions?: string;
  items: RecipeItem[];          // ингредиенты и полуфабрикаты
  photo?: string | null;
  createdAt?: string;
  updatedAt?: string;
}

export interface CreateRecipeDto {
  productId: number;
  name: string;
  outputWeight: number;
  outputUnit: string;
  cookingTime?: number;
  instructions?: string;
  items: Omit<RecipeItem, 'id' | 'itemName' | 'cost'>[];
  photo?: string;
}
