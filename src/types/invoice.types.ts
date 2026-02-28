export type InvoiceStatus = 'draft' | 'confirmed' | 'cancelled';

export interface Invoice {
  id: number;
  supplier: string;
  number: string;
  date: string;
  totalAmount: number;
  status: InvoiceStatus;
  photoId?: number;
  photoUrl?: string;
  aiProcessed: boolean;
  createdBy: number;
  createdAt: string;
  items: InvoiceItem[];
}

export interface InvoiceItem {
  id: number;
  invoiceId: number;
  productId: number;
  productName?: string;
  quantity: number;
  price: number;
  amount: number;
}

export interface CreateInvoiceDto {
  supplier: string;
  number: string;
  date: string;
  items: {
    productId: number;
    quantity: number;
    price: number;
  }[];
}

export interface AIRecognizedItem {
  recognizedName: string;
  quantity: number;
  price: number;
  unit: string;
  confidence: number;
  matches?: ProductMatch[];
}

export interface ProductMatch {
  productId: number;
  productName: string;
  similarity: number;
}

export interface AIRecognizeResponse {
  success: boolean;
  photoId: number;
  photoUrl: string;
  recognized: AIRecognizedItem[];
  totalAmount: number;
}
