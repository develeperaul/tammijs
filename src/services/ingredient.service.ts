import { api } from 'boot/axios';
import { ApiResponse } from 'src/types/api.types';
import { Ingredient, CreateIngredientDto } from 'src/types/ingredient.types';

class IngredientService {
  private static instance: IngredientService;

  private constructor() {}

  public static getInstance(): IngredientService {
    if (!IngredientService.instance) {
      IngredientService.instance = new IngredientService();
    }
    return IngredientService.instance;
  }

  async getAll(): Promise<ApiResponse<Ingredient[]>> {
    const response = await api.get('/index.php', {
      params: { action: 'ingredients.get' }
    });
    return response.data;
  }

  async create(data: CreateIngredientDto): Promise<{ id: number }> {
    const response = await api.post('/index.php', data, {
      params: { action: 'ingredient.create' }
    });
    return response.data;
  }

  async update(id: number, data: Partial<CreateIngredientDto>): Promise<boolean> {
    const response = await api.post('/index.php', data, {
      params: { action: 'ingredient.update', id }
    });
    return response.data.success;
  }

  async delete(id: number): Promise<boolean> {
    const response = await api.delete('/index.php', {
      params: { action: 'ingredient.delete', id }
    });
    return response.data.success;
  }
}

export default IngredientService.getInstance();
