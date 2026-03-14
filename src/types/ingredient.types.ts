export interface Ingredient {
  id: number;
  name: string;
  code: string;
  active: boolean;
  unit: string;
  baseUnit: string;
  baseRatio: number;
  costPrice: number;
  currentStock: number;
  minStock: number;
  categoryId?: number | null;
  photo?: string | null;
  description?: string;
}

export interface CreateIngredientDto {
  name: string;
  unit: string;
  baseUnit: string;
  baseRatio: number;
  costPrice: number;
  currentStock?: number;
  minStock: number;
  categoryId?: number | null;
  description?: string;
}
