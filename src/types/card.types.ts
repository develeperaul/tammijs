export interface CartItem {
  productId: number;
  name: string;
  originalPrice: number;      // цена из меню (базовая)
  currentPrice: number;       // текущая цена (после ручной правки или скидки)
  quantity: number;
  discountPercent?: number;   // % скидки на позицию
  comment?: string;
}
