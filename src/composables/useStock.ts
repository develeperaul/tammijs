import { ref, reactive, computed } from 'vue';
import { useQuasar } from 'quasar';
import stockService from 'src/services/stock.service';
import { Product } from 'src/types/product.types';
import { CreateMovementDto, WriteOffDto, StockMovement } from "src/types/stokc.types";
import { ApiError } from 'src/types/api.types';

export function useStock() {
  const $q = useQuasar();

  const items = ref<Product[]>([]);
  const loading = ref<boolean>(false);
  const error = ref<string | null>(null);

  const filter = reactive({
    categoryId: null as number | null,
    type: null as string | null,
    lowStock: false,
    search: '',
  });

  const lowStockItems = computed(() =>
    items.value.filter(item => item.currentStock <= item.minStock)
  );

  const totalItems = computed(() => items.value.length);

  /**
   * Загрузка остатков
   */
  const fetchStock = async (params?: Record<string, any>): Promise<void> => {
    loading.value = true;
    error.value = null;

    try {
      items.value = await stockService.getCurrentStock({
        ...filter,
        ...params,
      });
    } catch (err) {
      const apiError = err as ApiError;
      error.value = apiError.message;
      $q.notify({
        type: 'negative',
        message: error.value,
      });
    } finally {
      loading.value = false;
    }
  };

  /**
   * Добавление прихода
   */
  const addIncome = async (
    productId: number,
    quantity: number,
    comment?: string
  ): Promise<boolean> => {
    loading.value = true;

    try {
      const data: CreateMovementDto = {
        productId,
        type: 'income',
        quantity,
        documentType: 'manual',
        comment,
      };

      const result = await stockService.addMovement(data);

      $q.notify({
        type: 'positive',
        message: 'Приход успешно добавлен',
      });

      // Обновляем остаток в списке
      const product = items.value.find(p => p.id === productId);
      if (product) {
        product.currentStock = result.newStock;
      }

      return true;
    } catch (err) {
      const apiError = err as ApiError;
      $q.notify({
        type: 'negative',
        message: apiError.message,
      });
      return false;
    } finally {
      loading.value = false;
    }
  };

  /**
   * Списание товара
   */
  const writeOff = async (items: Array<{ productId: number; quantity: number }>, reason: string): Promise<boolean> => {
    loading.value = true;

    try {
      const data: WriteOffDto = {
        items,
        reason,
      };

      const result = await stockService.writeOff(data);

      $q.notify({
        type: 'positive',
        message: 'Списание выполнено успешно',
      });

      // Обновляем остатки
      for (const item of result.items) {
        const product = items.value.find(p => p.id === item.productId);
        if (product) {
          product.currentStock = item.newStock;
        }
      }

      return true;
    } catch (err) {
      const apiError = err as ApiError;
      $q.notify({
        type: 'negative',
        message: apiError.message,
      });
      return false;
    } finally {
      loading.value = false;
    }
  };

  /**
   * Получение цвета для остатка
   */
  const getStockColor = (item: Product): string => {
    if (item.currentStock <= 0) return 'red-10';
    if (item.currentStock <= item.minStock) return 'orange';
    return 'green';
  };

  /**
   * Получение статуса остатка
   */
  const getStockStatus = (item: Product): string => {
    if (item.currentStock <= 0) return 'Отсутствует';
    if (item.currentStock <= item.minStock) return 'Критический';
    if (item.currentStock <= item.minStock * 2) return 'Мало';
    return 'Норма';
  };

  /**
   * Сброс фильтров
   */
  const resetFilter = (): void => {
    filter.categoryId = null;
    filter.type = null;
    filter.lowStock = false;
    filter.search = '';
  };

  return {
    // State
    items,
    loading,
    error,
    filter,

    // Computed
    lowStockItems,
    totalItems,

    // Methods
    fetchStock,
    addIncome,
    writeOff,
    getStockColor,
    getStockStatus,
    resetFilter,
  };
}
