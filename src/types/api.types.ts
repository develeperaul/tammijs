export interface ApiResponse<T = any> {
  success: boolean;
  data: T;
  error?: string;
  message?: string;
  total?: number;
}

export interface ApiError {
  error: boolean;
  message: string;
  code?: number;
}

export interface PaginatedResponse<T> {
  success: boolean;
  data: T[];
  total: number;
  limit: number;
  offset: number;
}
