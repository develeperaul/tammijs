import { api } from 'boot/axios';
import { ApiResponse } from 'src/types/api.types';

export class BaseService {
  protected async get<T>(path: string, params?: Record<string, any>): Promise<T> {
    const response = await api.get<ApiResponse<T>>('/index.php', {
      params: { path, ...params }
    });
    return (response as any).data;
  }

  protected async post<T>(path: string, data?: any): Promise<T> {
    const response = await api.post<ApiResponse<T>>('/index.php', data, {
      params: { path }
    });
    return (response as any).data;
  }

  protected async put<T>(path: string, data?: any): Promise<T> {
    const response = await api.put<ApiResponse<T>>('/index.php', data, {
      params: { path }
    });
    return (response as any).data;
  }

  protected async delete<T>(path: string): Promise<T> {
    const response = await api.delete<ApiResponse<T>>('/index.php', {
      params: { path }
    });
    return (response as any).data;
  }
}
