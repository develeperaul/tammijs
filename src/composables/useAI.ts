import { ref } from 'vue';
import { useQuasar } from 'quasar';
import aiService from 'src/services/ai.service';
import stockService from 'src/services/stock.service';
import { AIRecognizedItem, AIRecognizeResponse, CreateInvoiceDto } from 'src/types/invoice.types';
import { ApiError } from 'src/types/api.types';

export function useAI() {
  const $q = useQuasar();

  const loading = ref<boolean>(false);
  const recognizedItems = ref<AIRecognizedItem[]>([]);
  const photoUrl = ref<string | null>(null);
  const photoId = ref<number | null>(null);
  const totalAmount = ref<number>(0);

  /**
   * Распознавание накладной по фото
   */
  const recognizeInvoice = async (file: File): Promise<boolean> => {
    loading.value = true;

    try {
      const result = await aiService.recognizeInvoice(file);

      recognizedItems.value = result.recognized;
      photoUrl.value = result.photoUrl;
      photoId.value = result.photoId;
      totalAmount.value = result.totalAmount;

      $q.notify({
        type: 'positive',
        message: 'Накладная успешно распознана',
      });

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
   * Подтверждение распознанных товаров и создание накладной
   */
  const confirmInvoice = async (
    supplier: string,
    number: string,
    selectedItems: Array<{
      recognizedName: string;
      productId: number;
      quantity: number;
      price: number;
    }>
  ): Promise<boolean> => {
    if (!photoId.value) {
      $q.notify({
        type: 'warning',
        message: 'Нет фото накладной',
      });
      return false;
    }

    loading.value = true;

    try {
      const invoiceData: CreateInvoiceDto & { photoId: number } = {
        supplier,
        number,
        date: new Date().toISOString().split('T')[0],
        photoId: photoId.value,
        items: selectedItems.map(item => ({
          productId: item.productId,
          quantity: item.quantity,
          price: item.price,
        })),
      };

      const result = await aiService.createInvoiceFromAI(invoiceData);

      $q.notify({
        type: 'positive',
        message: `Накладная №${number} создана`,
      });

      // Очищаем данные
      reset();

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
   * Сброс данных
   */
  const reset = (): void => {
    recognizedItems.value = [];
    photoUrl.value = null;
    photoId.value = null;
    totalAmount.value = 0;
  };

  return {
    // State
    loading,
    recognizedItems,
    photoUrl,
    photoId,
    totalAmount,

    // Methods
    recognizeInvoice,
    confirmInvoice,
    reset,
  };
}
