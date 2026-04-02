export type OrderType = 'dine-in' | 'takeaway' | 'delivery';
export type OrderStatus = 'new' | 'cooking' | 'ready' | 'delivered' | 'paid' | 'cancelled';
export type PaymentMethod = 'cash' | 'card' | 'online';

export interface CartItem {
  productId: number;
  name: string;
  price: number;
  quantity: number;
  comment?: string;
}

export interface Order {
  id: number;
  number: string;
  type: OrderType;
  tableNumber?: number;
  status: OrderStatus;
  items: OrderItem[];
  subtotal: number;
  discount: number;
  total: number;
  paymentMethod?: PaymentMethod;
  createdAt: string;
  createdBy: number;
  comment?: string;
}

export interface OrderItem {
  id?: number;
  productId: number;
  productName: string;
  quantity: number;
  price: number;
  comment?: string;
}

export interface CreateOrderDto {
  type: OrderType;
  tableNumber?: number;
  items: {
    productId: number;
    quantity: number;
    comment?: string;
  }[];
  paymentMethod?: PaymentMethod;
  comment?: string;
}
