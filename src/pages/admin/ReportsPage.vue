<template>
  <q-page class="q-pa-md">
    <div class="text-h5 q-mb-md">Отчёты</div>

    <!-- Выбор периода -->
    <date-range-picker
      v-model:start="period.startDate"
      v-model:end="period.endDate"
      class="q-mb-md"
    />

    <!-- Вкладки -->
    <q-tabs v-model="tab" dense class="text-primary q-mb-md">
      <q-tab name="sales" label="Продажи" />
      <q-tab name="foodcost" label="Фудкост" />
      <q-tab name="movements" label="Движения товаров" />
    </q-tabs>

    <!-- Кнопка обновления -->
    <div class="text-right q-mb-md">
      <q-btn flat icon="refresh" @click="loadReports" :loading="loading" />
    </div>

    <!-- Содержимое вкладок -->
    <q-tab-panels v-model="tab" animated>
      <!-- Продажи -->
      <q-tab-panel name="sales">
        <!-- Таблица продаж по дням -->
        <q-table
          :rows="salesData"
          :columns="salesColumns"
          row-key="date"
          flat
          bordered
        >
          <template v-slot:body-cell-totalSales="props">
            <q-td :props="props">
              {{ formatMoney(props.value) }}
            </q-td>
          </template>
        </q-table>

        <!-- Топ продуктов -->
        <div class="text-h6 q-mt-lg">Топ продуктов</div>
        <q-table
          :rows="topProducts"
          :columns="topColumns"
          row-key="productId"
          flat
          bordered
          class="q-mt-md"
        >
          <template v-slot:body-cell-total="props">
            <q-td :props="props">
              {{ formatMoney(props.value) }}
            </q-td>
          </template>
        </q-table>
      </q-tab-panel>

      <!-- Фудкост -->
      <q-tab-panel name="foodcost">
        <q-table
          :rows="foodCostData"
          :columns="foodCostColumns"
          row-key="productId"
          flat
          bordered
        >
          <template v-slot:body-cell-revenue="props">
            <q-td :props="props">
              {{ formatMoney(props.value) }}
            </q-td>
          </template>
          <template v-slot:body-cell-totalCost="props">
            <q-td :props="props">
              {{ formatMoney(props.value) }}
            </q-td>
          </template>
          <template v-slot:body-cell-foodCostPercent="props">
            <q-td :props="props">
              {{ props.value.toFixed(2) }}%
            </q-td>
          </template>
        </q-table>
        <div class="text-h6 q-mt-md">
          Общий фудкост: {{ formatMoney(totalCost) }} / {{ formatMoney(totalRevenue) }} =
          {{ overallFoodCostPercent.toFixed(2) }}%
        </div>
      </q-tab-panel>

      <!-- Движения товаров -->
      <q-tab-panel name="movements">
        <q-table
          :rows="movementsData"
          :columns="movementsColumns"
          row-key="id"
          flat
          bordered
        >
          <template v-slot:body-cell-type="props">
            <q-td :props="props">
              <q-badge :color="getMovementColor(props.value)">
                {{ getMovementLabel(props.value) }}
              </q-badge>
            </q-td>
          </template>
          <template v-slot:body-cell-quantity="props">
            <q-td :props="props">
              <span :class="getQuantityClass(props.row)">
                {{ props.row.type === 'income' ? '+' : '-' }} {{ props.row.quantity }}
              </span>
            </q-td>
          </template>
        </q-table>
      </q-tab-panel>
    </q-tab-panels>
  </q-page>
</template>

<script lang="ts">
import { defineComponent, ref, computed, onMounted } from 'vue';
import dayjs from 'dayjs';
import reportService from 'src/services/report.service';
import { ReportPeriod, SalesReportItem, TopProduct, FoodCostItem, MovementReportItem } from 'src/types/report.types';
import DateRangePicker from 'components/reports/DateRangePicker.vue';

export default defineComponent({
  name: 'ReportsPage',
  components: { DateRangePicker },

  setup() {
    const loading = ref(false);
    const tab = ref('sales');

    // Период по умолчанию: последние 7 дней
    const period = ref<ReportPeriod>({
      startDate: dayjs().subtract(6, 'day').format('YYYY-MM-DD'),
      endDate: dayjs().format('YYYY-MM-DD')
    });

    const salesData = ref<SalesReportItem[]>([]);
    const topProducts = ref<TopProduct[]>([]);
    const foodCostData = ref<FoodCostItem[]>([]);
    const movementsData = ref<MovementReportItem[]>([]);

    const salesColumns = [
      { name: 'date', label: 'Дата', field: 'date', align: 'left' },
      { name: 'ordersCount', label: 'Заказов', field: 'ordersCount', align: 'center' },
      { name: 'itemsSold', label: 'Товаров', field: 'itemsSold', align: 'center' },
      { name: 'totalSales', label: 'Выручка', field: 'totalSales', align: 'right' }
    ];

    const topColumns = [
      { name: 'productName', label: 'Товар', field: 'productName', align: 'left' },
      { name: 'quantity', label: 'Кол-во', field: 'quantity', align: 'center' },
      { name: 'total', label: 'Сумма', field: 'total', align: 'right' }
    ];

    const foodCostColumns = [
      { name: 'productName', label: 'Товар', field: 'productName', align: 'left' },
      { name: 'soldQuantity', label: 'Продано', field: 'soldQuantity', align: 'center' },
      { name: 'revenue', label: 'Выручка', field: 'revenue', align: 'right' },
      { name: 'totalCost', label: 'Себестоимость', field: 'totalCost', align: 'right' },
      { name: 'foodCostPercent', label: '%', field: 'foodCostPercent', align: 'center' }
    ];

    const movementsColumns = [
      { name: 'date', label: 'Дата', field: 'date', align: 'left' },
      { name: 'productName', label: 'Товар', field: 'productName', align: 'left' },
      { name: 'type', label: 'Тип', field: 'type', align: 'center' },
      { name: 'quantity', label: 'Кол-во', field: 'quantity', align: 'right' },
      { name: 'documentType', label: 'Документ', field: 'documentType', align: 'center' },
      { name: 'comment', label: 'Комментарий', field: 'comment', align: 'left' }
    ];

    const totalRevenue = computed(() =>
      foodCostData.value.reduce((sum, item) => sum + item.revenue, 0)
    );

    const totalCost = computed(() =>
      foodCostData.value.reduce((sum, item) => sum + item.totalCost, 0)
    );

    const overallFoodCostPercent = computed(() =>
      totalRevenue.value ? (totalCost.value / totalRevenue.value) * 100 : 0
    );

    const loadReports = async () => {
      loading.value = true;
      try {
        const [sales, top, food, movements] = await Promise.all([
          reportService.getSalesReport(period.value),
          reportService.getTopProducts(period.value),
          reportService.getFoodCost(period.value),
          reportService.getMovements(period.value)
        ]);
        salesData.value = sales;
        topProducts.value = top;
        foodCostData.value = food;
        movementsData.value = movements;
      } catch (error) {
        console.error('Ошибка загрузки отчетов', error);
      } finally {
        loading.value = false;
      }
    };

    const formatMoney = (value: number): string => {
      return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        minimumFractionDigits: 0
      }).format(value);
    };

    const getMovementColor = (type: string): string => {
      const colors: Record<string, string> = {
        income: 'positive',
        outcome: 'info',
        'write-off': 'negative'
      };
      return colors[type] || 'grey';
    };

    const getMovementLabel = (type: string): string => {
      const labels: Record<string, string> = {
        income: 'Приход',
        outcome: 'Расход',
        'write-off': 'Списание'
      };
      return labels[type] || type;
    };

    const getQuantityClass = (movement: MovementReportItem): string => {
      return movement.type === 'income' ? 'text-positive' : 'text-negative';
    };

    onMounted(() => {
      loadReports();
    });

    return {
      loading,
      tab,
      period,
      salesData,
      topProducts,
      foodCostData,
      movementsData,
      salesColumns,
      topColumns,
      foodCostColumns,
      movementsColumns,
      totalRevenue,
      totalCost,
      overallFoodCostPercent,
      loadReports,
      formatMoney,
      getMovementColor,
      getMovementLabel,
      getQuantityClass
    };
  }
});
</script>
