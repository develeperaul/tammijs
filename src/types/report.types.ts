export interface SalesReportItem {
  date: string;               // дата в формате YYYY-MM-DD
  ordersCount: number;
  totalSales: number;
  itemsSold: number;
}

export interface TopProduct {
  productId: number;
  productName: string;
  quantity: number;
  total: number;
}

export interface FoodCostItem {
  productId: number;
  productName: string;
  soldQuantity: number;
  revenue: number;
  costPrice: number;          // себестоимость единицы
  totalCost: number;          // costPrice * soldQuantity
  foodCostPercent: number;    // totalCost / revenue * 100
}

export interface MovementReportItem {
  date: string;
  productId: number;
  productName: string;
  type: 'income' | 'outcome' | 'write-off';
  quantity: number;
  documentType: string;
  comment?: string;
}

export interface ReportPeriod {
  startDate: string;          // YYYY-MM-DD
  endDate: string;            // YYYY-MM-DD
}
