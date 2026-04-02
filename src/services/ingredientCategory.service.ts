import { api } from 'boot/axios';
import { ApiResponse } from 'src/types/api.types';
import { ProductCategory } from 'src/types/product.types';

class IngredientCategoryService {
  private static instance: IngredientCategoryService;

  private constructor() {}

  public static getInstance(): IngredientCategoryService {
    if (!IngredientCategoryService.instance) {
      IngredientCategoryService.instance = new IngredientCategoryService();
    }
    return IngredientCategoryService.instance;
  }

  async getAll(): Promise<ApiResponse<ProductCategory[]>> {
    const response = await api.get('/index.php', {
      params: { action: 'ingredient.categories.get' }
    });
    return response.data;
  }

  async create(data: { name: string; sort?: number }): Promise<{ id: number }> {
    const response = await api.post('/index.php', data, {
      params: { action: 'ingredient.category.create' }
    });
    return response.data;
  }

  async update(id: number, data: { name?: string; sort?: number }): Promise<boolean> {
    const response = await api.post('/index.php', data, {
      params: { action: 'ingredient.category.update', id }
    });
    return response.data.success;
  }

  async delete(id: number): Promise<boolean> {
    const response = await api.delete('/index.php', {
      params: { action: 'ingredient.category.delete', id }
    });
    return response.data.success;
  }
}

export default IngredientCategoryService.getInstance();
