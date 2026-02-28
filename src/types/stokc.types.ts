export type MovementType = 'income' | 'outcome' | 'write-off' | 'move';
export type DocumentType = 'manual' | 'invoice' | 'sale' | 'inventory';

export interface StockMovement {
  id: number;
  productId: number;
  productName?: string;
  type: MovementType;
  quantity: number;
  price?: number;
  documentType: DocumentType;
  documentId?: number;
  comment?: string;
  createdBy?: number;
  createdAt: string;
}

export interface CreateMovementDto {
  productId: number;
  type: MovementType;
  quantity: number;
  price?: number;
  documentType: DocumentType;
  documentId?: number;
  comment?: string;
}

export interface WriteOffItemDto {
  productId: number;
  quantity: number;
  reason?: string;
}

export interface WriteOffDto {
  items: WriteOffItemDto[];
  reason: string;
}

export interface StockHistoryFilter {
  productId?: number;
  type?: MovementType;
  dateFrom?: string;
  dateTo?: string;
  limit?: number;
  offset?: number;
}
