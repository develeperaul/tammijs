import { StockMovement, CreateMovementDto, WriteOffDto, StockHistoryFilter } from 'src/types/stock.types';
import { Product } from 'src/types/product.types';

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
   * Получить текущие остатки (список продуктов с актуальными остатками)
   */
  async getCurrentStock(): Promise<Product[]> {
    await this.delay(400);
    // Просто возвращаем копию продуктов, так как мы будем обновлять остатки через движения
    return JSON.parse(JSON.stringify(mockProducts));
  }

  /**
   * Добавить движение (приход/расход) и обновить остаток продукта
   */
  async addMovement(data: CreateMovementDto): Promise<{ movementId: number; newStock: number }> {
    await this.delay(600);

    const product = mockProducts.find(p => p.id === data.productId);
    if (!product) throw new Error('Товар не найден');

    // Рассчитываем новый остаток
    let newStock = product.currentStock;
    if (data.type === 'income') {
      newStock += data.quantity;
    } else if (data.type === 'outcome' || data.type === 'write-off') {
      if (product.currentStock < data.quantity) {
        throw new Error('Недостаточно товара на складе');
      }
      newStock -= data.quantity;
    } // для 'move' пока не реализуем

    // Создаём запись движения
    const movement: StockMovement = {
      id: mockMovements.length + 1,
      productId: data.productId,
      productName: product.name,
      type: data.type,
      quantity: data.quantity,
      price: data.price,
      documentType: data.documentType,
      documentId: data.documentId,
      comment: data.comment,
      createdBy: 1, // заглушка
      createdAt: new Date().toISOString()
    };
    mockMovements.push(movement);

    // Обновляем остаток продукта
    product.currentStock = newStock;

    return { movementId: movement.id, newStock };
  }

  /**
   * Массовое списание
   */
  async writeOff(data: WriteOffDto): Promise<{
    writeOffId: string;
    items: Array<{ productId: number; movementId: number; newStock: number }>;
  }> {
    await this.delay(800);

    const results = [];
    for (const item of data.items) {
      const product = mockProducts.find(p => p.id === item.productId);
      if (!product) throw new Error(`Товар с id ${item.productId} не найден`);
      if (product.currentStock < item.quantity) {
        throw new Error(`Недостаточно товара "${product.name}" на складе`);
      }

      const movement: StockMovement = {
        id: mockMovements.length + 1,
        productId: item.productId,
        productName: product.name,
        type: 'write-off',
        quantity: item.quantity,
        documentType: 'manual',
        comment: data.reason + (item.reason ? ` (${item.reason})` : ''),
        createdBy: 1,
        createdAt: new Date().toISOString()
      };
      mockMovements.push(movement);

      product.currentStock -= item.quantity;

      results.push({
        productId: item.productId,
        movementId: movement.id,
        newStock: product.currentStock
      });
    }

    return {
      writeOffId: `wo-${Date.now()}`,
      items: results
    };
  }

  /**
   * Получить историю движений с фильтрацией
   */
  async getHistory(filter?: StockHistoryFilter): Promise<{
    data: StockMovement[];
    total: number;
    limit: number;
    offset: number;
  }> {
    await this.delay(500);

    let filtered = [...mockMovements];

    if (filter?.productId) {
      filtered = filtered.filter(m => m.productId === filter.productId);
    }
    if (filter?.type) {
      filtered = filtered.filter(m => m.type === filter.type);
    }
    if (filter?.dateFrom) {
      const from = new Date(filter.dateFrom).getTime();
      filtered = filtered.filter(m => new Date(m.createdAt).getTime() >= from);
    }
    if (filter?.dateTo) {
      const to = new Date(filter.dateTo).getTime();
      filtered = filtered.filter(m => new Date(m.createdAt).getTime() <= to);
    }

    const limit = filter?.limit || 50;
    const offset = filter?.offset || 0;
    const paginated = filtered.slice(offset, offset + limit);

    return {
      data: paginated,
      total: filtered.length,
      limit,
      offset
    };
  }

  /**
   * Получить историю по конкретному товару
   */
  async getProductHistory(productId: number, limit = 20): Promise<StockMovement[]> {
    const result = await this.getHistory({ productId, limit });
    return result.data;
  }

  private delay(ms: number): Promise<void> {
    return new Promise(resolve => setTimeout(resolve, ms));
  }
}

export default StockService.getInstance();
