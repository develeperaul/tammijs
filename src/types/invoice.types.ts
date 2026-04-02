import { Ingredient } from './ingredient.types';
import { Supplier } from './supplier.types';

export interface InvoiceItem {
  id: number;
  ingredientId: number;
  ingredientName: string;
  quantity: number;
  price: number;
  amount: number;
  unit: string;
  baseUnit?: string;
  baseRatio?: number;
}

export interface Invoice {
  id: number;
  number: string;
  date: string;
  supplierId: number;
  supplierName?: string;
  totalAmount: number;
  items: InvoiceItem[];
  status: 'draft' | 'confirmed';
  createdAt: string;
}
