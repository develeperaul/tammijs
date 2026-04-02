import { api } from 'boot/axios';
import { ApiResponse } from 'src/types/api.types';
import { Order, CreateOrderDto, OrderStatus } from 'src/types/order.types';

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
   * Создать заказ (реальный API)
   */
  async createOrder(data: CreateOrderDto): Promise<{ orderId: number; itemIds: number[] }> {
    const response = await api.post('/index.php', data, {
      params: { action: 'order.create' }
    });
    return response.data;
  }

  /**
   * Получить заказы с фильтрацией
   */
  async getOrders(status?: OrderStatus | OrderStatus[]): Promise<ApiResponse<Order[]>> {
    const params: any = { action: 'orders.get' };
    if (status) {
      params.status = Array.isArray(status) ? status.join(',') : status;
    }
    const response = await api.get('/index.php', { params });
    return response.data;
  }

  /**
   * Получить активные заказы для кухни
   */
  async getActiveOrders(): Promise<ApiResponse<Order[]>> {
    return this.getOrders(['new', 'cooking', 'ready']);
  }

  /**
   * Обновить статус заказа
   */
  async updateOrderStatus(orderId: number, status: OrderStatus): Promise<boolean> {
    const response = await api.post('/index.php', { orderId, status }, {
      params: { action: 'order.update.status' }
    });
    return response.data.success;
  }

  /**
   * Обновить статус приготовления позиции
   */
  async updateOrderItemStatus(orderId: number, itemId: number, status: 'pending' | 'cooking' | 'ready'): Promise<boolean> {
    const response = await api.post('/index.php', { orderId, itemId, status }, {
      params: { action: 'order.item.update.status' }
    });
    return response.data.success;
  }
}

export default OrderService.getInstance();
