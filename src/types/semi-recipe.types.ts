import { Ingredient } from './ingredient.types';

export interface SemiRecipeIngredient {
  id?: number;
  ingredientId: number;
  ingredientName?: string;
  quantity: number;
  unit: string;
  cost?: number;
}

export interface SemiRecipe {
  id: number;
  semiFinishedId: number;
  semiFinishedName?: string;
  name: string;                    // название рецепта
  outputQuantity: number;          // количество на выходе
  outputUnit: string;              // единица измерения на выходе
  instructions?: string;
  ingredients: SemiRecipeIngredient[];
  createdAt?: string;
  updatedAt?: string;
}

export interface CreateSemiRecipeDto {
  semiFinishedId: number;
  name: string;
  outputQuantity: number;
  outputUnit: string;
  instructions?: string;
  ingredients: Omit<SemiRecipeIngredient, 'id' | 'ingredientName'>[];
}
