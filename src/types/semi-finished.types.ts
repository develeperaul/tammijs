export interface SemiFinishedIngredient {
  id?: number;
  ingredientId: number;
  ingredientName?: string;
  quantity: number;
  unit: string;
}

export interface SemiFinished {
  id: number;
  name: string;
  code: string;
  active: boolean;
  unit: string;
  sellingPrice: number;
  costPrice: number;
  photo?: string | null;
  description?: string;
  ingredients: SemiFinishedIngredient[];
}

export interface CreateSemiFinishedDto {
  name: string;
  unit: string;
  sellingPrice: number;
  description?: string;
  ingredients: Omit<SemiFinishedIngredient, 'id' | 'ingredientName'>[];
}
