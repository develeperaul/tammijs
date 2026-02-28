export type OrderType = 'dine-in' | 'takeaway' | 'delivery';
export type OrderStatus =
  | 'preorder'      // предзаказ
  | 'new'           // новый
  | 'collected'     // собран
  | 'in_delivery'   // в доставке
  | 'in_production' // в производстве
  | 'produced'      // произведен
  | 'paid'
  | 'cancelled';
export type PaymentMethod = 'cash' | 'card' | 'online';
export type DiscountType = 'percent' | 'amount';

export interface Order {
  id: number;
  number: string;
  type: OrderType;
  tableNumber?: number;
  customerId?: number;
  customerName?: string;
  status: OrderStatus;
  items: OrderItem[];
  subtotal: number;        // сумма до скидок
  discount: number;        // общая скидка (в валюте)
  total: number;           // итог
  paymentMethod?: PaymentMethod;
  createdAt: string;
  completedAt?: string;
  createdBy: number;
  comment?: string;
}

export interface OrderItem {
  id?: number;
  productId: number;
  productName: string;
  quantity: number;
  price: number;           // цена со скидкой (фактическая)
  originalPrice?: number;  // цена без скидки
  discountPercent?: number;
  comment?: string;
  cookingStatus?: 'pending' | 'cooking' | 'ready';
}

export interface CreateOrderItemDto {
  productId: number;
  quantity: number;
  price?: number;          // если не указано, берётся стандартная
  discountPercent?: number;
  comment?: string;
}

export interface CreateOrderDto {
  type: OrderType;
  tableNumber?: number;
  items: CreateOrderItemDto[];
  paymentMethod?: PaymentMethod;
  totalDiscountType?: DiscountType;
  totalDiscountValue?: number;
  comment?: string;
}
