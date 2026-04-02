import { Ingredient } from './ingredient.types';
import { Product } from './product.types';

export type PurchasableItem = (Ingredient | Product) & {
  // Дополнительные поля для унификации
  itemType: 'ingredient' | 'resale';
  displayName: string;
  unit: string;           // единица хранения
  baseUnit?: string;      // только для ингредиентов
  baseRatio?: number;     // только для ингредиентов
  costPrice: number;      // цена закупа
};

export function isIngredient(item: PurchasableItem): item is Ingredient {
  return item.itemType === 'ingredient';
}

export function isResale(item: PurchasableItem): item is Product {
  return item.itemType === 'resale';
}
