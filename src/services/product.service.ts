// src/services/product.service.ts
import { api } from 'boot/axios';
import { ApiResponse } from 'src/types/api.types';
import { Product, ProductFilter, CreateProductDto, ProductCategory } from 'src/types/product.types';

class ProductService {
  private static instance: ProductService;

  private constructor() {}

  public static getInstance(): ProductService {
    if (!ProductService.instance) {
      ProductService.instance = new ProductService();
    }
    return ProductService.instance;
  }

  /**
   * Получить список товаров
   */
  async getProducts(filter?: ProductFilter): Promise<ApiResponse<Product[]>> {
    const params: Record<string, any> = { action: 'products.get' };

    if (filter?.search) params.search = filter.search;
    if (filter?.type) params.type = filter.type;
    if (filter?.categoryId) params.categoryId = filter.categoryId;
    if (filter?.lowStock) params.lowStock = filter.lowStock ? '1' : '0';

    const response = await api.get('/index.php', { params });
    // response уже содержит { success: true, data: [...] } после интерцептора
    return response.data; // массив товаров
  }

  /**
   * Получить товар по ID
   */
  async getProductById(id: number): Promise<Product | null> {
    const params = { action: 'products.get', id: id.toString() };
    const response = await api.get('/index.php', { params });
    // Предполагаем, что бэкенд вернёт массив из одного элемента или объект?
    // В текущей реализации getProducts возвращает массив. Удобнее было бы сделать отдельный метод product.get,
    // но пока можем найти в массиве. Альтернативно: бэкенд может поддерживать product.get с id.
    // Пока так:
    const products = response.data as Product[];
    return products.find(p => p.id === id) || null;
  }

  /**
   * Создать товар
   * Для этого метода на бэкенде должен быть реализован action 'product.create'
   */
  async createProduct(data: CreateProductDto): Promise<Product> {
    const response = await api.post('/index.php', data, {
      params: { action: 'product.create' }
    });
    // Предполагаем, что бэкенд возвращает созданный товар в response.data
    return response.data;
  }

  /**
   * Обновить товар
   * action 'product.update'
   */
  async updateProduct(id: number, data: Partial<CreateProductDto>): Promise<Product> {
    const response = await api.put('/index.php', data, {
      params: { action: 'product.update', id: id.toString() }
    });
    return response.data;
  }

  /**
   * Удалить товар
   * action 'product.delete'
   */
  async deleteProduct(id: number): Promise<boolean> {
    await api.delete('/index.php', {
      params: { action: 'product.delete', id: id.toString() }
    });
    return true; // если дошли до этой строки, значит исключение не было выброшено
  }

  /**
   * Получить категории
   * Предполагается, что на бэкенде есть action 'categories.get'
   */
  async getCategories(): Promise<ProductCategory[]> {
    const params = { action: 'categories.get' };
    const response = await api.get('/index.php', { params });
    return response.data;
  }
}

export default ProductService.getInstance();
