import axios, { AxiosInstance, AxiosRequestConfig, AxiosResponse, AxiosError } from 'axios';
import { ApiResponse, ApiError } from 'src/types/api.types';

class ApiService {
  private api: AxiosInstance;
  private static instance: ApiService;

  private constructor() {
    this.api = axios.create({
      baseURL: process.env.API_URL || '/api',
      headers: {
        'X-API-Key': process.env.API_KEY || 'ваш-секретный-ключ',
        'Content-Type': 'application/json',
      },
      timeout: 30000,
    });

    this.setupInterceptors();
  }

  public static getInstance(): ApiService {
    if (!ApiService.instance) {
      ApiService.instance = new ApiService();
    }
    return ApiService.instance;
  }

  private setupInterceptors(): void {
    // Request interceptor
    this.api.interceptors.request.use(
      (config) => {
        // Можно добавить токен авторизации
        const token = localStorage.getItem('token');
        if (token) {
          config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
      },
      (error) => Promise.reject(error)
    );

    // Response interceptor
    this.api.interceptors.response.use(
      (response: AxiosResponse) => response.data,
      (error: AxiosError) => {
        if (error.response) {
          const apiError: ApiError = {
            error: true,
            message: (error.response.data as any)?.message || 'Ошибка сервера',
            code: error.response.status,
          };
          return Promise.reject(apiError);
        } else if (error.request) {
          return Promise.reject({
            error: true,
            message: 'Нет ответа от сервера',
          } as ApiError);
        } else {
          return Promise.reject({
            error: true,
            message: error.message,
          } as ApiError);
        }
      }
    );
  }

  public async get<T = any>(path: string, params?: Record<string, any>): Promise<ApiResponse<T>> {
    return this.api.get('/index.php', {
      params: { path, ...params },
    });
  }

  public async post<T = any>(path: string, data?: any, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
    return this.api.post('/index.php', data, {
      params: { path },
      ...config,
    });
  }

  public async put<T = any>(path: string, data?: any): Promise<ApiResponse<T>> {
    return this.api.put('/index.php', data, {
      params: { path },
    });
  }

  public async delete<T = any>(path: string): Promise<ApiResponse<T>> {
    return this.api.delete('/index.php', {
      params: { path },
    });
  }

  public async upload<T = any>(path: string, file: File, additionalData?: Record<string, any>): Promise<ApiResponse<T>> {
    const formData = new FormData();
    formData.append('photo', file);

    if (additionalData) {
      Object.entries(additionalData).forEach(([key, value]) => {
        formData.append(key, String(value));
      });
    }

    return this.api.post('/index.php', formData, {
      params: { path },
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
  }
}

export default ApiService.getInstance();
