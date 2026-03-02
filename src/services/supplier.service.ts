import { api } from 'boot/axios';
import { ApiResponse } from 'src/types/api.types';
import { Supplier, CreateSupplierDto } from 'src/types/supplier.types';

class SupplierService {
  private static instance: SupplierService;

  private constructor() {}

  public static getInstance(): SupplierService {
    if (!SupplierService.instance) {
      SupplierService.instance = new SupplierService();
    }
    return SupplierService.instance;
  }

  /**
   * Получить всех поставщиков
   */
  async getSuppliers(): Promise< ApiResponse<Supplier[]>> {
    const response = await api.get('/index.php', {
      params: { action: 'suppliers.get' }
    });
    return response.data;
  }

  /**
   * Создать нового поставщика
   */
  async createSupplier(data: CreateSupplierDto): Promise<{ id: number }> {
    const response = await api.post('/index.php', data, {
      params: { action: 'supplier.create' }
    });
    return response.data;
  }

  /**
   * Обновить поставщика
   */
  async updateSupplier(id: number, data: Partial<CreateSupplierDto>): Promise<boolean> {
    const response = await api.post('/index.php', data, {
      params: { action: 'supplier.update', id }
    });
    return response.data.success;
  }

  /**
   * Удалить поставщика
   */
  async deleteSupplier(id: number): Promise<boolean> {
    const response = await api.delete('/index.php', {
      params: { action: 'supplier.delete', id }
    });
    return response.data.success;
  }

  /**
   * Получить историю цен поставщика по товару
   */
  async getPriceHistory(supplierId: number, productId: number): Promise<any[]> {
    const response = await api.get('/index.php', {
      params: { action: 'supplier.price.history', supplierId, productId }
    });
    return response.data;
  }

  /**
   * Сравнить цены поставщиков на товар
   */
  async comparePrices(productId: number): Promise<any[]> {
    const response = await api.get('/index.php', {
      params: { action: 'supplier.compare.prices', productId }
    });
    return response.data;
  }
}

export default SupplierService.getInstance();
