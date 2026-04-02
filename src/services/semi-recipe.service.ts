import { api } from 'boot/axios';
import { ApiResponse } from 'src/types/api.types';
import { SemiRecipe, CreateSemiRecipeDto } from 'src/types/semi-recipe.types';

class SemiRecipeService {
  private static instance: SemiRecipeService;

  private constructor() {}

  public static getInstance(): SemiRecipeService {
    if (!SemiRecipeService.instance) {
      SemiRecipeService.instance = new SemiRecipeService();
    }
    return SemiRecipeService.instance;
  }

  async getAll(): Promise<ApiResponse<SemiRecipe[]>> {
    const response = await api.get('/index.php', {
      params: { action: 'semi.recipes.get' }
    });
    return response.data;
  }

  async getBySemiFinishedId(semiFinishedId: number): Promise<ApiResponse<SemiRecipe | null>> {
    const response = await api.get('/index.php', {
      params: { action: 'semi.recipes.get', semiFinishedId }
    });
    return response.data[0] || null;
  }

  async create(data: CreateSemiRecipeDto): Promise<{ id: number }> {
    const response = await api.post('/index.php', data, {
      params: { action: 'semi.recipe.create' }
    });
    return response.data;
  }

  async update(id: number, data: Partial<CreateSemiRecipeDto>): Promise<boolean> {
    const response = await api.post('/index.php', data, {
      params: { action: 'semi.recipe.update', id }
    });
    return response.data.success;
  }

  async delete(id: number): Promise<boolean> {
    const response = await api.delete('/index.php', {
      params: { action: 'semi.recipe.delete', id }
    });
    return response.data.success;
  }
}

export default SemiRecipeService.getInstance();
