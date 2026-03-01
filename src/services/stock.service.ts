import { api } from 'boot/axios';

import { StockMovement, CreateMovementDto, WriteOffDto, StockHistoryFilter } from 'src/types/stock.types';
import { Product } from 'src/types/product.types';
import { ApiResponse } from 'src/types/api.types';

// Мок-данные продуктов (возьмём из product.service, но чтобы не было циклических зависимостей, скопируем часть)
const mockProducts: Product[] = [
  {
    id: 1,
    name: 'Рис круглозерный',
    code: 'rice',
    active: true,
    type: 'ingredient',
    unit: 'кг',
    costPrice: 80,
    sellingPrice: 120,
    currentStock: 15.5,
    minStock: 5,
    categoryId: 1,
    description: 'Рис для суши'
  },
  {
    id: 2,
    name: 'Нори листы',
    code: 'nori',
    active: true,
    type: 'ingredient',
    unit: 'уп',
    costPrice: 180,
    sellingPrice: 250,
    currentStock: 50,
    minStock: 10,
    categoryId: 1
  },
  {
    id: 3,
    name: 'Лосось слабой соли',
    code: 'salmon',
    active: true,
    type: 'ingredient',
    unit: 'кг',
    costPrice: 750,
    sellingPrice: 950,
    currentStock: 3.2,
    minStock: 2,
    categoryId: 2
  },
  {
    id: 4,
    name: 'Кола 0.5л',
    code: 'cola',
    active: true,
    type: 'finished',
    unit: 'шт',
    costPrice: 45,
    sellingPrice: 80,
    currentStock: 48,
    minStock: 20,
    categoryId: 3
  }
];

// Мок-данные движений
let mockMovements: StockMovement[] = [
  {
    id: 1,
    productId: 1,
    productName: 'Рис круглозерный',
    type: 'income',
    quantity: 10,
    price: 80,
    documentType: 'invoice',
    documentId: 101,
    comment: 'Приход по накладной №123',
    createdBy: 1,
    createdAt: '2025-02-20T10:30:00Z'
  },
  {
    id: 2,
    productId: 1,
    productName: 'Рис круглозерный',
    type: 'outcome',
    quantity: 2.5,
    documentType: 'sale',
    comment: 'Продажа роллов',
    createdBy: 2,
    createdAt: '2025-02-21T14:20:00Z'
  },
  {
    id: 3,
    productId: 3,
    productName: 'Лосось слабой соли',
    type: 'write-off',
    quantity: 0.3,
    documentType: 'manual',
    comment: 'Испорчен при разморозке',
    createdBy: 1,
    createdAt: '2025-02-22T09:15:00Z'
  }
];


class StockService {
  private static instance: StockService;

  private constructor() {}

  public static getInstance(): StockService {
    if (!StockService.instance) {
      StockService.instance = new StockService();
    }
    return StockService.instance;
  }

  /**
   * Получить текущие остатки (список товаров с актуальными остатками)
   */
  async getCurrentStock(filter?: {
    categoryId?: number;
    type?: string;
    lowStock?: boolean;
  }): Promise< ApiResponse<Product[]>> {
    const params: Record<string, any> = { action: 'stock.get' };
    if (filter?.categoryId) params.categoryId = filter.categoryId;
    if (filter?.type) params.type = filter.type;
    if (filter?.lowStock) params.lowStock = filter.lowStock ? '1' : '0';

    const response = await api.get('/index.php', { params });
    return response.data; // предполагаем, что API возвращает массив товаров
  }

  /**
   * Добавить движение (приход/расход) и обновить остаток продукта
   */
  // async addMovement(data: CreateMovementDto): Promise<{ movementId: number; newStock: number }> {
  //   const response = await api.post('/index.php', data, {
  //     params: { action: 'stock.movement.add' }
  //   });
  //   return response.data;
  // }
  async addMovement(data: {
    productId: number;
    type: string;
    quantity: number;
    price?: number;
    documentType?: string;
    documentId?: number;
    comment?: string;
  }): Promise<{ movementId: number; newStock: number }> {
    console.log('Sending to API:', data); // для отладки

    const response = await api.post('/index.php', data, {
      params: { action: 'stock.movement.add' }
    });

    return response.data;
  }


  /**
   * Массовое списание
   */
  async writeOff(data: WriteOffDto): Promise<{
    writeOffId: string;
    items: Array<{ productId: number; movementId: number; newStock: number }>;
  }> {
    const results = [];

    for (const item of data.items) {
      const movementData = {
        productId: item.productId,
        quantity: item.quantity,
        type: 'write-off',
        comment: data.reason,
        documentType: 'manual', // XML_ID manual
        documentId: 0
      };

      try {
        const result = await this.addMovement(movementData);
        results.push({
          productId: item.productId,
          movementId: result.movementId,
          newStock: result.newStock
        });
      } catch (error) {
        console.error('Ошибка списания товара', item.productId, error);
        throw error;
      }
    }

    return {
      writeOffId: `wo-${Date.now()}`,
      items: results
    };
  }

  /**
   * Получить историю движений с фильтрацией
   */
  async getHistory(filter?: StockHistoryFilter): Promise<ApiResponse<{
    data: StockMovement[];
    total: number;
    limit: number;
    offset: number;
  }>> {
    const params: Record<string, any> = { action: 'stock.history.get' };
    if (filter?.productId) params.productId = filter.productId;
    if (filter?.type) params.type = filter.type;
    if (filter?.dateFrom) params.dateFrom = filter.dateFrom;
    if (filter?.dateTo) params.dateTo = filter.dateTo;
    if (filter?.limit) params.limit = filter.limit;
    if (filter?.offset) params.offset = filter.offset;

    const response = await api.get('/index.php', { params });
    return response.data.data;
  }

  /**
   * Получить историю по конкретному товару
   */
  async getProductHistory(productId: number, limit = 20): Promise< StockMovement[]> {
    const result = await this.getHistory({ productId, limit });
    return result.data;
  }
}

export default StockService.getInstance();
