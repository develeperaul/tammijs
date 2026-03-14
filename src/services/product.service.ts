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
   * Получить продукты с фильтрацией по типу
   * @param type - 'all' | 'produced' | 'resale' (по умолчанию 'all')
   */
  async getProducts(type: 'all' | 'produced' | 'resale' = 'all', filter?: ProductFilter): Promise< ApiResponse<Product[]>> {
    const params: Record<string, any> = {
      action: 'products.get',
      type
    };

    if (filter?.categoryId) {
      params.category = filter.categoryId;
    }

    const response = await api.get('/index.php', { params });
    let products = response.data;

    // Дополнительная фильтрация на фронте если нужно
    if (filter?.search) {
      const search = filter.search.toLowerCase();
      products = products.filter((p: Product) =>
        p.name.toLowerCase().includes(search) ||
        p.code?.toLowerCase().includes(search)
      );
    }

    if (filter?.lowStock) {
      products = products.filter((p: Product) =>
        p.currentStock !== undefined && p.minStock !== undefined &&
        p.currentStock <= p.minStock
      );
    }

    return products;
  }

  /**
   * Получить готовые блюда
   */
  async getProducedProducts(filter?: ProductFilter): Promise< ApiResponse<Product[]>> {
    return this.getProducts('produced', filter);
  }

  /**
   * Получить товары для перепродажи
   */
  async getResaleProducts(filter?: ProductFilter): Promise< ApiResponse<Product[]>> {
    return this.getProducts('resale', filter);
  }

  /**
   * Создать товар
   */
  async createProduct(data: CreateProductDto): Promise<{ id: number }> {
    const response = await api.post('/index.php', data, {
      params: { action: 'product.create' }
    });
    return response.data;
  }

  /**
   * Обновить товар
   */
  async updateProduct(id: number, data: Partial<CreateProductDto>): Promise<boolean> {
    const response = await api.post('/index.php', data, {
      params: { action: 'product.update', id }
    });
    return response.data.success;
  }

  /**
   * Удалить товар
   */
  async deleteProduct(id: number): Promise<boolean> {
    const response = await api.delete('/index.php', {
      params: { action: 'product.delete', id }
    });
    return response.data.success;
  }

  /**
   * Получить категории
   */
  async getCategories(): Promise<ApiResponse<ProductCategory[]>> {
    const response = await api.get('/index.php', {
      params: { action: 'categories.get' }
    });
    return response.data;
  }

  /**
   * Создать категорию
   */
  async createCategory(data: { name: string; sort?: number }): Promise<{ id: number }> {
    const response = await api.post('/index.php', data, {
      params: { action: 'category.create' }
    });
    return response.data;
  }

  /**
   * Обновить категорию
   */
  async updateCategory(id: number, data: { name?: string; sort?: number }): Promise<boolean> {
    const response = await api.post('/index.php', data, {
      params: { action: 'category.update', id }
    });
    return response.data.success;
  }

  /**
   * Удалить категорию
   */
  async deleteCategory(id: number): Promise<boolean> {
    const response = await api.delete('/index.php', {
      params: { action: 'category.delete', id }
    });
    return response.data.success;
  }
}

export default ProductService.getInstance();
