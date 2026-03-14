<template>
  <q-dialog v-model="showDialog" maximized @hide="onHide">
    <q-card>
      <q-card-section class="row items-center q-col-gutter-md">
        <div class="text-h6">История цен: {{ supplier?.name }}</div>
        <q-space />

        <!-- Выбор товара -->
        <q-select
          v-model="selectedProduct"
          :options="products"
          option-label="name"
          option-value="id"
          label="Выберите товар"
          outlined
          dense
          style="min-width: 250px"
          emit-value
          map-options
          :loading="loadingProducts"
          @update:model-value="loadHistory"
        />

        <q-btn flat round icon="close" @click="showDialog = false" />
      </q-card-section>

      <!-- Вкладки для выбора периода -->
      <q-card-section class="q-pt-none">
        <div class="row items-center q-gutter-md">
          <q-tabs
            v-model="period"
            dense
            class="text-primary"
            @update:model-value="onPeriodChange"
          >
            <q-tab name="week" label="Неделя" />
            <q-tab name="month" label="Месяц" />
            <q-tab name="year" label="Год" />
            <q-tab name="custom" label="Свой период" />
            <q-tab name="all" label="Всё время" />
          </q-tabs>

          <!-- Календарь для выбора периода (показывается только при custom) -->
          <div v-if="period === 'custom'" class="row items-center q-gutter-sm">
            <q-input
              v-model="customStartDate"
              label="С"
              outlined
              dense
              mask="##.##.####"
              fill-mask
              hint="ДД.ММ.ГГГГ"
              style="width: 150px"
            >
              <template v-slot:append>
                <q-icon name="event" class="cursor-pointer">
                  <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                    <q-date v-model="customStartDate" mask="DD.MM.YYYY" />
                  </q-popup-proxy>
                </q-icon>
              </template>
            </q-input>

            <q-input
              v-model="customEndDate"
              label="По"
              outlined
              dense
              mask="##.##.####"
              fill-mask
              hint="ДД.ММ.ГГГГ"
              style="width: 150px"
            >
              <template v-slot:append>
                <q-icon name="event" class="cursor-pointer">
                  <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                    <q-date v-model="customEndDate" mask="DD.MM.YYYY" />
                  </q-popup-proxy>
                </q-icon>
              </template>
            </q-input>

            <q-btn
              flat
              color="primary"
              icon="search"
              label="Применить"
              :disable="!customStartDate || !customEndDate"
              @click="loadCustomHistory"
            />
          </div>
        </div>
      </q-card-section>

      <!-- Статистика -->
      <q-card-section v-if="stats" class="q-pt-none">
        <div class="row q-gutter-md">
          <q-card flat bordered class="col">
            <q-card-section class="text-center">
              <div class="text-caption text-grey-7">Минимум</div>
              <div class="text-h6 text-positive">{{ formatMoney(stats.min) }}</div>
            </q-card-section>
          </q-card>
          <q-card flat bordered class="col">
            <q-card-section class="text-center">
              <div class="text-caption text-grey-7">Максимум</div>
              <div class="text-h6 text-negative">{{ formatMoney(stats.max) }}</div>
            </q-card-section>
          </q-card>
          <q-card flat bordered class="col">
            <q-card-section class="text-center">
              <div class="text-caption text-grey-7">Среднее</div>
              <div class="text-h6 text-primary">{{ formatMoney(stats.avg) }}</div>
            </q-card-section>
          </q-card>
          <q-card flat bordered class="col">
            <q-card-section class="text-center">
              <div class="text-caption text-grey-7">Тренд</div>
              <div class="text-h6" :class="stats.trend >= 0 ? 'text-positive' : 'text-negative'">
                {{ stats.trend >= 0 ? '+' : '' }}{{ formatMoney(stats.trend) }}
                ({{ stats.trendPercent > 0 ? '+' : '' }}{{ stats.trendPercent }}%)
              </div>
            </q-card-section>
          </q-card>
        </div>
      </q-card-section>

      <!-- График и таблица -->
      <q-card-section>
        <div v-if="loading" class="text-center q-pa-md">
          <q-spinner size="50px" color="primary" />
        </div>

        <div v-else-if="!selectedProduct" class="text-center q-pa-md text-grey-7">
          Выберите товар для просмотра истории цен
        </div>

        <div v-else-if="historyData.length === 0" class="text-center q-pa-md text-grey-7">
          Нет данных о ценах на этот товар от данного поставщика
        </div>

        <div v-else>
          <!-- График -->
          <q-card flat bordered class="q-mb-md">
            <q-card-section>
              <div ref="chartContainer" style="height: 400px; width: 100%;"></div>
            </q-card-section>
          </q-card>

          <!-- Таблица -->
          <q-table
            :rows="sortedHistory"
            :columns="columns"
            row-key="date"
            flat
            bordered
            :rows-per-page-options="[10, 25, 50, 100]"
          >
            <template v-slot:body-cell-date="props">
              <q-td :props="props">
                {{ formatDate(props.value) }}
              </q-td>
            </template>

            <template v-slot:body-cell-price="props">
              <q-td :props="props">
                {{ formatMoney(props.value) }}
              </q-td>
            </template>
          </q-table>
        </div>
      </q-card-section>
    </q-card>
  </q-dialog>
</template>

<script lang="ts">
import { defineComponent, ref, watch, computed, onMounted, onBeforeUnmount } from 'vue';
import * as echarts from 'echarts';
import dayjs from 'dayjs';
import supplierService from 'src/services/supplier.service';
import productService from 'src/services/product.service';
import { Supplier } from 'src/types/supplier.types';
import { Product } from 'src/types/product.types';

export default defineComponent({
  name: 'PriceHistoryChart',

  props: {
    modelValue: {
      type: Boolean,
      required: true
    },
    supplier: {
      type: Object as () => Supplier | null,
      default: null
    }
  },

  emits: ['update:modelValue'],

  setup(props, { emit }) {
    const showDialog = ref(props.modelValue);
    const products = ref<Product[]>([]);
    const loadingProducts = ref(false);
    const selectedProduct = ref<number | null>(null);
    const loading = ref(false);
    const historyData = ref<any[]>([]);
    const chartContainer = ref<HTMLElement | null>(null);
    const period = ref<'week' | 'month' | 'year' | 'custom' | 'all'>('month');
    const customStartDate = ref<string>('');
    const customEndDate = ref<string>('');
    const stats = ref<any>(null);
    let chart: echarts.ECharts | null = null;

    const selectedProductInfo = computed(() => {
      if (!selectedProduct.value) return null;
      return products.value.find(p => p.id === selectedProduct.value);
    });

    const columns = [
      { name: 'date', label: 'Дата', field: 'date', align: 'center', sortable: true },
      { name: 'price', label: 'Цена', field: 'price', align: 'right', sortable: true },
      { name: 'quantity', label: 'Количество', field: 'quantity', align: 'center', sortable: true },
      { name: 'documentId', label: 'Накладная', field: 'documentId', align: 'center' },
      { name: 'comment', label: 'Комментарий', field: 'comment', align: 'left' }
    ];

    // Сортировка от новых к старым для таблицы
    const sortedHistory = computed(() => {
      return [...historyData.value].sort((a, b) =>
        dayjs(b.date).unix() - dayjs(a.date).unix()
      );
    });

    const loadProducts = async () => {
      if (!props.supplier) return;

      loadingProducts.value = true;
      try {
        const all = (await productService.getProducts()).data;
        products.value = all.filter((p: Product) => p.type === 'ingredient');
      } catch (error) {
        console.error('Ошибка загрузки товаров:', error);
      } finally {
        loadingProducts.value = false;
      }
    };

    const loadHistory = async (customStart?: string, customEnd?: string) => {
      if (!selectedProduct.value || !props.supplier) return;

      loading.value = true;
      try {
        const data = await supplierService.getPriceHistory(
          props.supplier.id,
          selectedProduct.value,
          period.value,
          customStart,
          customEnd
        );
        historyData.value = data.history || [];
        stats.value = data.stats || null;

        setTimeout(() => renderChart(), 100);
      } catch (error) {
        console.error('Ошибка загрузки истории:', error);
      } finally {
        loading.value = false;
      }
    };

    const onPeriodChange = () => {
      if (period.value === 'custom') {
        // При выборе custom ничего не загружаем, ждём нажатия кнопки
        return;
      }
      loadHistory();
    };

    const loadCustomHistory = () => {
      if (!customStartDate.value || !customEndDate.value) return;

      // Преобразуем даты из ДД.ММ.ГГГГ в ГГГГ-ММ-ДД для бэкенда
      const [startDay, startMonth, startYear] = customStartDate.value.split('.');
      const [endDay, endMonth, endYear] = customEndDate.value.split('.');

      const startDate = `${startYear}-${startMonth}-${startDay}`;
      const endDate = `${endYear}-${endMonth}-${endDay}`;

      loadHistory(startDate, endDate);
    };

    const renderChart = () => {
      if (!chartContainer.value || historyData.value.length === 0) return;

      if (!chart) {
        chart = echarts.init(chartContainer.value);
      }

      // Данные для графика (в хронологическом порядке)
      const chartData = [...historyData.value].sort((a, b) =>
        dayjs(a.date).unix() - dayjs(b.date).unix()
      );

      const dates = chartData.map((item: any) =>
        dayjs(item.date).format('DD-MM-YYYY')
      );
      const prices = chartData.map((item: any) => item.price);

      chart.setOption({
        title: {
          text: `Цены на ${selectedProductInfo.value?.name} от ${props.supplier?.name}`,
          left: 'center',
          subtext: getPeriodSubtext()
        },
        tooltip: {
          trigger: 'axis',
          formatter: function(params: any) {
            const data = chartData[params[0].dataIndex];
            return `
              <b>${dayjs(data.date).format('DD.MM.YYYY')}</b><br/>
              Цена: ${formatMoney(data.price)}<br/>
              Количество: ${data.quantity} ${selectedProductInfo.value?.unit}<br/>
              Накладная: №${data.documentId || '—'}<br/>
              ${data.comment ? `Комментарий: ${data.comment}` : ''}
            `;
          }
        },
        grid: {
          left: '3%',
          right: '4%',
          bottom: '3%',
          containLabel: true
        },
        xAxis: {
          type: 'category',
          data: dates,
          axisLabel: {
            rotate: 45,
            formatter: (value: string) => value
          }
        },
        yAxis: {
          type: 'value',
          name: 'Цена (₽)'
        },
        series: [
          {
            name: 'Цена',
            type: 'line',
            data: prices,
            smooth: true,
            markPoint: {
              data: [
                { type: 'max', name: 'Максимум' },
                { type: 'min', name: 'Минимум' }
              ]
            },
            markLine: {
              data: [{ type: 'average', name: 'Среднее' }]
            }
          }
        ]
      });
    };

    const getPeriodSubtext = () => {
      if (period.value === 'custom' && customStartDate.value && customEndDate.value) {
        return `Период: ${customStartDate.value} — ${customEndDate.value}`;
      }

      const labels: Record<string, string> = {
        week: 'последние 7 дней',
        month: 'последние 30 дней',
        year: 'последние 365 дней',
        all: 'всё время'
      };
      return labels[period.value] || period.value;
    };

    const onHide = () => {
      selectedProduct.value = null;
      historyData.value = [];
      period.value = 'month';
      customStartDate.value = '';
      customEndDate.value = '';
      stats.value = null;
    };

    const formatDate = (date: string) => {
      return dayjs(date).format('DD.MM.YYYY HH:mm');
    };

    const formatMoney = (value: number) => {
      return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        minimumFractionDigits: 2
      }).format(value);
    };

    watch(() => props.modelValue, async (val) => {
      showDialog.value = val;
      if (val && props.supplier) {
        await loadProducts();
      }
    }, { immediate: true });

    watch(showDialog, (val) => {
      emit('update:modelValue', val);
    });

    onMounted(() => {
      window.addEventListener('resize', () => chart?.resize());
    });

    onBeforeUnmount(() => {
      window.removeEventListener('resize', () => chart?.resize());
      chart?.dispose();
    });

    return {
      showDialog,
      products,
      loadingProducts,
      selectedProduct,
      selectedProductInfo,
      loading,
      historyData,
      stats,
      columns,
      sortedHistory,
      chartContainer,
      period,
      customStartDate,
      customEndDate,
      onPeriodChange,
      loadCustomHistory,
      onHide,
      formatDate,
      formatMoney
    };
  }
});
</script>
