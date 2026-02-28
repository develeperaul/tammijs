import dayjs from 'dayjs';
import { SalesReportItem, TopProduct, FoodCostItem, MovementReportItem, ReportPeriod } from 'src/types/report.types';

// Мок-данные продаж (последние 7 дней)
const generateMockSales = (days: number): SalesReportItem[] => {
  const result: SalesReportItem[] = [];
  for (let i = days - 1; i >= 0; i--) {
    const date = dayjs().subtract(i, 'day').format('YYYY-MM-DD');
    result.push({
      date,
      ordersCount: Math.floor(Math.random() * 10) + 5,
      totalSales: Math.floor(Math.random() * 20000) + 5000,
      itemsSold: Math.floor(Math.random() * 30) + 10,
    });
  }
  return result;
};

// Мок-данные топ продуктов
const mockTopProducts: TopProduct[] = [
  { productId: 101, productName: 'Ролл Калифорния', quantity: 42, total: 14700 },
  { productId: 102, productName: 'Ролл Филадельфия', quantity: 38, total: 15960 },
  { productId: 103, productName: 'Сет "Самурай"', quantity: 12, total: 14400 },
  { productId: 104, productName: 'Кола 0.5л', quantity: 85, total: 6800 },
  { productId: 105, productName: 'Сендвич с курицей', quantity: 27, total: 4860 },
];

// Мок-данные фудкоста
const mockFoodCost: FoodCostItem[] = [
  { productId: 101, productName: 'Ролл Калифорния', soldQuantity: 42, revenue: 14700, costPrice: 150, totalCost: 6300, foodCostPercent: 42.86 },
  { productId: 102, productName: 'Ролл Филадельфия', soldQuantity: 38, revenue: 15960, costPrice: 180, totalCost: 6840, foodCostPercent: 42.86 },
  { productId: 103, productName: 'Сет "Самурай"', soldQuantity: 12, revenue: 14400, costPrice: 600, totalCost: 7200, foodCostPercent: 50.00 },
  { productId: 104, productName: 'Кола 0.5л', soldQuantity: 85, revenue: 6800, costPrice: 45, totalCost: 3825, foodCostPercent: 56.25 },
  { productId: 105, productName: 'Сендвич с курицей', soldQuantity: 27, revenue: 4860, costPrice: 90, totalCost: 2430, foodCostPercent: 50.00 },
];

// Мок-данные движений
const mockMovements: MovementReportItem[] = [
  { date: dayjs().subtract(1, 'day').format('YYYY-MM-DD HH:mm'), productId: 1, productName: 'Рис круглозерный', type: 'income', quantity: 10, documentType: 'Накладная', comment: 'Поставка' },
  { date: dayjs().subtract(1, 'day').format('YYYY-MM-DD HH:mm'), productId: 3, productName: 'Лосось', type: 'income', quantity: 5, documentType: 'Накладная', comment: 'Поставка' },
  { date: dayjs().subtract(1, 'day').format('YYYY-MM-DD HH:mm'), productId: 101, productName: 'Ролл Калифорния', type: 'outcome', quantity: 10, documentType: 'Продажа', comment: 'Заказ #101' },
  { date: dayjs().subtract(2, 'day').format('YYYY-MM-DD HH:mm'), productId: 1, productName: 'Рис круглозерный', type: 'write-off', quantity: 0.5, documentType: 'Списание', comment: 'Порча' },
];

class ReportService {
  private static instance: ReportService;

  private constructor() {}

  public static getInstance(): ReportService {
    if (!ReportService.instance) {
      ReportService.instance = new ReportService();
    }
    return ReportService.instance;
  }

  /**
   * Получить продажи за период
   */
  async getSalesReport(period: ReportPeriod): Promise<SalesReportItem[]> {
    await this.delay(500);
    // В реальном API здесь будет фильтрация по периоду
    return generateMockSales(7);
  }

  /**
   * Получить топ продуктов за период
   */
  async getTopProducts(period: ReportPeriod): Promise<TopProduct[]> {
    await this.delay(400);
    return mockTopProducts;
  }

  /**
   * Получить фудкост за период
   */
  async getFoodCost(period: ReportPeriod): Promise<FoodCostItem[]> {
    await this.delay(500);
    return mockFoodCost;
  }

  /**
   * Получить движения товаров за период
   */
  async getMovements(period: ReportPeriod): Promise<MovementReportItem[]> {
    await this.delay(600);
    // Фильтруем по дате (упрощённо)
    return mockMovements.filter(m =>
      dayjs(m.date).isBetween(dayjs(period.startDate), dayjs(period.endDate), 'day', '[]')
    );
  }

  private delay(ms: number): Promise<void> {
    return new Promise(resolve => setTimeout(resolve, ms));
  }
}

export default ReportService.getInstance();
