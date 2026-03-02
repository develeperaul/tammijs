<template>
  <div>
    <div class="row q-mb-md items-center">
      <div class="col-6">
        <div class="text-h6">Сравнение цен поставщиков</div>
      </div>
      <div class="col-6">
        <q-select
          v-model="selectedProduct"
          :options="products"
          option-label="name"
          option-value="id"
          label="Выберите товар"
          outlined
          dense
          emit-value
          map-options
          @update:model-value="loadComparisonData"
        />
      </div>
    </div>

    <div v-if="loading" class="text-center q-pa-md">
      <q-spinner size="50px" color="primary" />
    </div>

    <div v-else-if="chartData.length === 0" class="text-center q-pa-md text-grey-7">
      Нет данных для отображения
    </div>

    <div v-else>
      <!-- График цен -->
      <q-card flat bordered class="q-mb-md">
        <q-card-section>
          <div ref="chartContainer" style="height: 400px; width: 100%;"></div>
        </q-card-section>
      </q-card>

      <!-- Таблица сравнения -->
      <q-table
        :rows="tableData"
        :columns="columns"
        row-key="supplierId"
        flat
        bordered
      />
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent, ref, onMounted } from 'vue';
import * as echarts from 'echarts';
import supplierService from 'src/services/supplier.service';
import productService from 'src/services/product.service';
import { Product } from 'src/types/product.types';

export default defineComponent({
  name: 'PriceComparisonChart',

  props: {
    products: {
      type: Array as () => Product[],
      required: true
    }
  },

  setup() {
    const selectedProduct = ref<number | null>(null);
    const loading = ref(false);
    const chartData = ref<any[]>([]);
    const tableData = ref<any[]>([]);
    const chartContainer = ref<HTMLElement | null>(null);
    let chart: echarts.ECharts | null = null;

    const columns = [
      { name: 'supplier', label: 'Поставщик', field: 'supplierName' },
      { name: 'price', label: 'Цена', field: 'price', align: 'right' },
      { name: 'date', label: 'Последняя поставка', field: 'lastDate', align: 'center' },
      { name: 'trend', label: 'Динамика', field: 'trend', align: 'center' }
    ];

    const loadComparisonData = async () => {
      if (!selectedProduct.value) return;

      loading.value = true;
      try {
        const data = await supplierService.comparePrices(selectedProduct.value);
        chartData.value = data;

        // Подготовка данных для графика
        const series: any[] = [];
        const xAxisData: string[] = [];

        data.forEach((supplier: any) => {
          series.push({
            name: supplier.supplierName,
            type: 'line',
            data: supplier.prices.map((p: any) => p.price)
          });

          if (xAxisData.length === 0) {
            xAxisData.push(...supplier.prices.map((p: any) => p.date));
          }
        });

        // Подготовка данных для таблицы
        tableData.value = data.map((s: any) => ({
          supplierId: s.supplierId,
          supplierName: s.supplierName,
          price: s.latestPrice,
          lastDate: s.lastDate,
          trend: s.trend === 'up' ? '📈' : s.trend === 'down' ? '📉' : '➡️'
        }));

        // Отрисовка графика
        if (chartContainer.value) {
          if (!chart) {
            chart = echarts.init(chartContainer.value);
          }

          chart.setOption({
            title: {
              text: 'Сравнение цен поставщиков',
              left: 'center'
            },
            tooltip: {
              trigger: 'axis'
            },
            legend: {
              data: series.map(s => s.name),
              bottom: 0
            },
            grid: {
              left: '3%',
              right: '4%',
              bottom: '10%',
              containLabel: true
            },
            xAxis: {
              type: 'category',
              boundaryGap: false,
              data: xAxisData
            },
            yAxis: {
              type: 'value',
              name: 'Цена (₽)'
            },
            series: series
          });
        }
      } catch (error) {
        console.error('Ошибка загрузки данных для сравнения:', error);
      } finally {
        loading.value = false;
      }
    };

    onMounted(() => {
      // Инициализация
    });

    return {
      selectedProduct,
      loading,
      chartData,
      tableData,
      columns,
      chartContainer,
      loadComparisonData
    };
  }
});
</script>
