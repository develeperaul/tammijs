import axios from 'axios';

// Типы данных
export interface Product {
  id: number;
  name: string;
  type: 'ingredient' | 'finished' | 'semi-finished';
  unit: string;
  currentStock: number;
  minStock: number;
  sellingPrice: number;
}

// Мок-данные для теста (пока бэкенд не готов)
export const mockProducts: Product[] = [
  {
    id: 1,
    name: 'Рис круглозерный',
    type: 'ingredient',
    unit: 'кг',
    currentStock: 15.5,
    minStock: 5,
    sellingPrice: 120
  },
  {
    id: 2,
    name: 'Нори листы',
    type: 'ingredient',
    unit: 'уп',
    currentStock: 50,
    minStock: 10,
    sellingPrice: 250
  },
  {
    id: 3,
    name: 'Лосось слабой соли',
    type: 'ingredient',
    unit: 'кг',
    currentStock: 3.2,
    minStock: 2,
    sellingPrice: 950
  },
  {
    id: 4,
    name: 'Кола 0.5л',
    type: 'finished',
    unit: 'шт',
    currentStock: 48,
    minStock: 20,
    sellingPrice: 80
  }
];

// Создаем экземпляр axios
const api = axios.create({
  baseURL: '/api',
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    'X-API-Key': 'test-key-123'
  }
});

// Сервис для товаров
export const productService = {
  // Получить все товары
  async getAll(): Promise<Product[]> {
    try {
      // Пока используем мок-данные
      return new Promise(resolve => {
        setTimeout(() => resolve(mockProducts), 500);
      });

      // Когда бэкенд будет готов, раскомментируйте:
      // const response = await api.get('/index.php?path=products');
      // return response.data?.data || [];
    } catch (error) {
      console.error('Ошибка загрузки товаров:', error);
      return [];
    }
  },

  // Получить остатки
  async getStock(): Promise<Product[]> {
    try {
      // Пока используем мок-данные
      return new Promise(resolve => {
        setTimeout(() => resolve(mockProducts), 500);
      });

      // Когда бэкенд будет готов, раскомментируйте:
      // const response = await api.get('/index.php?path=stock');
      // return response.data?.data || [];
    } catch (error) {
      console.error('Ошибка загрузки остатков:', error);
      return [];
    }
  }
};
