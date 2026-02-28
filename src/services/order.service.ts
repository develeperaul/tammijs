import { Order, CreateOrderDto, OrderStatus } from 'src/types/order.types';
import stockService from './stock.service';
import { Product } from 'src/types/product.types';


// В начале файла, после объявления mockOrders
let mockOrders: Order[] = [
  {
    id: 1,
    number: '101',
    type: 'dine-in',
    tableNumber: 5,
    status: 'new',
    items: [
      { id: 1, productId: 101, productName: 'Ролл Калифорния', quantity: 2, price: 350, cookingStatus: 'pending' },
      { id: 2, productId: 104, productName: 'Кола 0.5л', quantity: 1, price: 80, cookingStatus: 'pending' }
    ],
    subtotal: 780,
    discount: 0,
    total: 780,
    createdAt: new Date().toISOString(),
    createdBy: 1
  },
  {
    id: 2,
    number: '102',
    type: 'takeaway',
    status: 'cooking',
    items: [
      { id: 3, productId: 102, productName: 'Ролл Филадельфия', quantity: 1, price: 420, cookingStatus: 'cooking' },
      { id: 4, productId: 105, productName: 'Сендвич с курицей', quantity: 1, price: 180, cookingStatus: 'pending' }
    ],
    subtotal: 600,
    discount: 0,
    total: 600,
    createdAt: new Date(Date.now() - 30*60000).toISOString(),
    createdBy: 2
  }
];
class OrderService {
  private static instance: OrderService;

  private constructor() {}

  public static getInstance(): OrderService {
    if (!OrderService.instance) {
      OrderService.instance = new OrderService();
    }
    return OrderService.instance;
  }

  /**
   * Получить меню (все готовые товары)
   */
  async getMenu(): Promise<Product[]> {
    await this.delay(300);
    return mockMenu;
  }

  /**
   * Получить активные заказы (для кухни)
   */
  async getActiveOrders(): Promise<Order[]> {
    await this.delay(400);
    return mockOrders.filter(o => !['paid', 'cancelled'].includes(o.status));
  }

  /**
   * Получить историю заказов
   */
  async getOrdersHistory(limit = 50, offset = 0): Promise<{ data: Order[]; total: number }> {
    await this.delay(500);
    const sorted = [...mockOrders].sort((a,b) =>
      new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime()
    );
    return {
      data: sorted.slice(offset, offset + limit),
      total: sorted.length
    };
  }

  /**
   * Создать новый заказ
   * Здесь должна быть логика списания ингредиентов (через рецепты), пока просто эмулируем
   */
  async createOrder(data: CreateOrderDto): Promise<Order> {
    await this.delay(800);

    const menu = await this.getMenu();
    const items = data.items.map((item, index) => {
      const product = menu.find(p => p.id === item.productId);
      if (!product) throw new Error(`Товар с id ${item.productId} не найден`);
      return {
        id: Date.now() + index, // генерируем уникальный id (для мока)
        productId: product.id,
        productName: product.name,
        quantity: item.quantity,
        price: item.price ?? product.sellingPrice,
        originalPrice: product.sellingPrice,
        discountPercent: item.discountPercent,
        comment: item.comment,
        cookingStatus: 'pending'
      };
    });

    const subtotal = items.reduce((sum, item) => sum + item.price * item.quantity, 0);
    let discount = 0;
    if (data.totalDiscountType && data.totalDiscountValue) {
      if (data.totalDiscountType === 'percent') {
        discount = (subtotal * data.totalDiscountValue) / 100;
      } else {
        discount = data.totalDiscountValue;
      }
    }
    const total = subtotal - discount;

    const newOrder: Order = {
      id: mockOrders.length + 1,
      number: String(100 + mockOrders.length + 1),
      type: data.type,
      tableNumber: data.tableNumber,
      status: 'new',
      items,
      subtotal,
      discount,
      total,
      paymentMethod: data.paymentMethod,
      createdAt: new Date().toISOString(),
      createdBy: 1,
      comment: data.comment
    };

    mockOrders.push(newOrder);
    return newOrder;
    }

  /**
   * Обновить статус заказа
   */
  // async updateOrderStatus(orderId: number, status: OrderStatus): Promise<Order> {
  //   await this.delay(400);
  //   const order = mockOrders.find(o => o.id === orderId);
  //   if (!order) throw new Error('Заказ не найден');
  //   order.status = status;
  //   if (status === 'paid' || status === 'delivered') {
  //     order.completedAt = new Date().toISOString();
  //   }
  //   return order;
  // }



  /**
   * Получить активные заказы для кухни (статус new, cooking)
   */
  async getKitchenOrders(): Promise<Order[]> {
    await this.delay(400);
    return mockOrders.filter(o => ['new', 'cooking'].includes(o.status));
  }

  /**
   * Обновить статус приготовления позиции в заказе
   */
  async updateOrderItemStatus(orderId: number, itemId: number, status: 'pending' | 'cooking' | 'ready'): Promise<Order> {
    console.log('Updating item:', orderId, itemId, status);
    await this.delay(300);
    const order = mockOrders.find(o => o.id === orderId);
    if (!order) throw new Error('Заказ не найден');
    const item = order.items.find(i => i.id === itemId);
    if (!item) throw new Error('Позиция не найдена');
    item.cookingStatus = status;

    // Если все позиции готовы, переводим заказ в статус ready
    if (order.items.every(i => i.cookingStatus === 'ready')) {
      order.status = 'ready';
    } else if (order.status === 'new' && order.items.some(i => i.cookingStatus === 'cooking')) {
      order.status = 'cooking';
    }
    return order;
  }

  /**
   * Обновить статус всего заказа (например, "заказ готов")
   */
  async updateOrderStatus(orderId: number, status: OrderStatus): Promise<Order> {
    await this.delay(300);
    const order = mockOrders.find(o => o.id === orderId);
    if (!order) throw new Error('Заказ не найден');
    order.status = status;
    return order;
  }

  private delay(ms: number): Promise<void> {
    return new Promise(resolve => setTimeout(resolve, ms));
  }
}

export default OrderService.getInstance();
