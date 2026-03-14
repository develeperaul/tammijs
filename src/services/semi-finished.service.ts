import { api } from 'boot/axios';
import { ApiResponse } from 'src/types/api.types';
import { SemiFinished, CreateSemiFinishedDto } from 'src/types/semi-finished.types';

class SemiFinishedService {
  private static instance: SemiFinishedService;

  private constructor() {}

  public static getInstance(): SemiFinishedService {
    if (!SemiFinishedService.instance) {
      SemiFinishedService.instance = new SemiFinishedService();
    }
    return SemiFinishedService.instance;
  }

  async getAll(): Promise<ApiResponse<SemiFinished[]>> {
    const response = await api.get('/index.php', {
      params: { action: 'semi.get' }
    });
    return response.data;
  }

  async getById(id: number): Promise<SemiFinished | null> {
    const response = await api.get('/index.php', {
      params: { action: 'semi.get', id }
    });
    return response.data;
  }

  async create(data: CreateSemiFinishedDto): Promise<{ id: number }> {
    const response = await api.post('/index.php', data, {
      params: { action: 'semi.create' }
    });
    return response.data;
  }

  async update(id: number, data: Partial<CreateSemiFinishedDto>): Promise<boolean> {
    const response = await api.post('/index.php', data, {
      params: { action: 'semi.update', id }
    });
    return response.data.success;
  }

  async delete(id: number): Promise<boolean> {
    const response = await api.delete('/index.php', {
      params: { action: 'semi.delete', id }
    });
    return response.data.success;
  }
}

export default SemiFinishedService.getInstance();
