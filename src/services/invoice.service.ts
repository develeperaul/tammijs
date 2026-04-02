import { api } from 'boot/axios';
import { Invoice, CreateInvoiceDto } from 'src/types/invoice.types';

class InvoiceService {
  private static instance: InvoiceService;

  private constructor() {}

  public static getInstance(): InvoiceService {
    if (!InvoiceService.instance) {
      InvoiceService.instance = new InvoiceService();
    }
    return InvoiceService.instance;
  }

  async getInvoices(): Promise<Invoice[]> {
    const response = await api.get('/index.php', {
      params: { action: 'invoices.get' }
    });
    return response.data;
  }

  async createInvoice(data: CreateInvoiceDto): Promise<{ id: number }> {
    const response = await api.post('/index.php', data, {
      params: { action: 'invoice.create' }
    });
    return response.data;
  }

  async confirmInvoice(id: number): Promise<boolean> {
    const response = await api.post('/index.php', { id }, {
      params: { action: 'invoice.confirm' }
    });
    return response.data.success;
  }
}

export default InvoiceService.getInstance();
