import apiService from './api.service';
import { AIRecognizeResponse, CreateInvoiceDto } from 'src/types/invoice.types';

class AIService {
  private static instance: AIService;

  private constructor() {}

  public static getInstance(): AIService {
    if (!AIService.instance) {
      AIService.instance = new AIService();
    }
    return AIService.instance;
  }

  /**
   * Распознать накладную по фото
   */
  async recognizeInvoice(photoFile: File): Promise<AIRecognizeResponse> {
    const response = await apiService.upload<AIRecognizeResponse>(
      'ai/recognize-invoice',
      photoFile
    );

    if (!response.data) {
      throw new Error('Failed to recognize invoice');
    }

    return response.data;
  }

  /**
   * Создать накладную из распознанных данных
   */
  async createInvoiceFromAI(data: CreateInvoiceDto & { photoId: number }): Promise<{
    invoiceId: number;
    movements: Array<{
      productId: number;
      movementId: number;
    }>;
  }> {
    const response = await apiService.post('invoices/from-ai', data);

    if (!response.data) {
      throw new Error('Failed to create invoice');
    }

    return response.data;
  }

  /**
   * Сопоставить распознанный товар с существующим в базе
   */
  async matchProduct(recognizedName: string): Promise<Array<{
    productId: number;
    productName: string;
    similarity: number;
  }>> {
    const response = await apiService.get('ai/match-product', { name: recognizedName });
    return response.data || [];
  }
}

export default AIService.getInstance();
