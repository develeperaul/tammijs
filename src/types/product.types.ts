export type ProductType = 'ingredient' | 'finished' | 'semi-finished';

export interface Product {
  id: number;
  name: string;
  code: string;
  active: boolean;
  type: ProductType;
  unit: string;                // единица хранения (кг, шт, л)
  unitLabel?: string;          // русское название единицы
  baseUnit: string;            // базовая единица для рецептов (г, мл, шт)
  baseUnitLabel?: string;      // русское название базовой единицы
  baseRatio: number;           // коэффициент: сколько базовых единиц в единице хранения
  costPrice: number;           // цена закупа (себестоимость)
  sellingPrice: number;        // цена продажи
  currentStock: number;        // текущий остаток
  minStock: number;            // минимальный остаток
  categoryId: number | null;
  categoryName?: string;
  photo?: string | null;
  description?: string;
  createdAt?: string;
  updatedAt?: string;
}

export interface ProductFilter {
  categoryId?: number;
  type?: ProductType;
  active?: boolean;
  lowStock?: boolean;
  search?: string;
}

export interface CreateProductDto {
  name: string;
  type: string;                // русское значение для бэкенда
  unit: string;                 // русское значение для бэкенда
  baseUnit: string;             // русское значение для бэкенда
  baseRatio: number;
  costPrice: number;
  sellingPrice: number;
  currentStock?: number;
  minStock: number;
  categoryId?: number | null;
  description?: string;
}

export interface ProductCategory {
  id: number;
  name: string;
  parentId?: number;
  sortOrder: number;
}
