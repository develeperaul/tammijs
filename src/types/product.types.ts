// export type ProductType = 'ingredient' | 'finished' | 'semi-finished';

export interface Product {
  id: number;
  name: string;
  code: string;
  active: boolean;
  type: number;
  unit: string;
  costPrice: number;
  sellingPrice: number;
  currentStock: number;
  minStock: number;
  categoryId: number;
  photo?: string | null;
  description?: string;
  createdAt?: string;
  updatedAt?: string;
}

export interface ProductFilter {
  categoryId?: number;
  type?: number;
  active?: boolean;
  lowStock?: boolean;
  search?: string;
}

export interface CreateProductDto {
  name: string;
  type: number;
  unit: number;
  costPrice?: number;
  sellingPrice?: number;
  currentStock?: number;
  minStock?: number;
  categoryId?: number;
  description?: string;
}

export interface ProductCategory {
  id: number;
  name: string;
  // parentId?: number;
  // sortOrder: number;
}
