import { api } from 'boot/axios';
import { ProductCategory } from 'src/types/product.types';

class CategoryService {
  private static instance: CategoryService;

  private constructor() {}

  public static getInstance(): CategoryService {
    if (!CategoryService.instance) {
      CategoryService.instance = new CategoryService();
    }
    return CategoryService.instance;
  }

  async getAll(): Promise<ProductCategory[]> {
    const response = await api.get('/index.php', {
      params: { action: 'categories.get' }
    });
    return response.data;
  }

  async create(data: { name: string; sort?: number }): Promise<{ id: number }> {
    const response = await api.post('/index.php', data, {
      params: { action: 'category.create' }
    });
    return response.data;
  }

  async update(id: number, data: { name?: string; sort?: number }): Promise<boolean> {
    const response = await api.post('/index.php', data, {
      params: { action: 'category.update', id }
    });
    return response.data.success;
  }

  async delete(id: number): Promise<boolean> {
    const response = await api.delete('/index.php', {
      params: { action: 'category.delete', id }
    });
    return response.data.success;
  }
}

export default CategoryService.getInstance();
