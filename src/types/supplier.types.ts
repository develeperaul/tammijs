export interface Supplier {
  id: number;
  name: string;           // Название поставщика
  phone?: string;         // Телефон
  email?: string;         // Email
  address?: string;       // Адрес
  inn?: string;           // ИНН
  kpp?: string;           // КПП
  comment?: string;       // Комментарий
  createdAt?: string;
}

export interface CreateSupplierDto {
  name: string;
  phone?: string;
  email?: string;
  address?: string;
  inn?: string;
  kpp?: string;
  comment?: string;
}
