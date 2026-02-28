<template>
  <q-page class="q-pa-md">
    <!-- Заголовок -->
    <div class="row q-mb-md items-center">
      <div class="col-6">
        <div class="text-h5">Остатки товаров</div>
        <div class="text-caption text-grey-7">
          Всего позиций: {{ items.length }}
          <q-badge
            v-if="lowStockCount > 0"
            :label="`${lowStockCount} критических`"
            color="warning"
            class="q-ml-sm"
          />
        </div>
      </div>
      <div class="col-6 text-right">
        <q-btn
          color="primary"
          label="Приход"
          icon="add"
          @click="openGlobalIncome"
          class="q-mr-sm"
        />
        <q-btn
          color="negative"
          label="Списание"
          icon="remove"
          @click="openGlobalWriteOff"
        />
      </div>
    </div>

    <!-- Таблица остатков -->
    <stock-table
      v-model:search="filters.search"
      v-model:type="filters.type"
      v-model:category="filters.categoryId"
      v-model:lowStock="filters.lowStock"
      :items="filteredItems"
      :categories="categories"
      :loading="loading"
      @refresh="loadStock"
      @resetFilters="resetFilters"
      @income="openIncomeDialog"
      @write-off="openWriteOffDialog"
      @history="openHistoryDialog"
    />

    <!-- Диалоги -->
     <income-dialog
      v-model="incomeDialog"
      :product="selectedProduct"
      @ok="onIncome"
      @hide="selectedProduct = null"
    />

    <write-off-dialog
      v-model="writeOffDialog"
      :product="selectedProduct"
      :products="items"
      @ok="onWriteOff"
      @hide="selectedProduct = null"
    />

    <history-dialog
      v-model="historyDialog"
      :product="selectedProduct"
      :movements="productHistory"
      @hide="selectedProduct = null; productHistory = []"
    />
  </q-page>
</template>

<script lang="ts">
import { defineComponent, ref, computed, onMounted } from 'vue';
import { useQuasar } from 'quasar';
import stockService from 'src/services/stock.service';
import productService from 'src/services/product.service';
import { Product } from 'src/types/product.types';
import { ProductCategory } from 'src/types/product.types';
import { StockMovement } from 'src/types/stokc.types';
import StockTable from 'components/stock/StockTable.vue';
import IncomeDialog from 'components/stock/IncomeDialog.vue';
import WriteOffDialog from 'components/stock/WriteOffDialog.vue';
import HistoryDialog from 'components/stock/HistoryDialog.vue'; // создадим далее

export default defineComponent({
  name: 'StockPage',

  components: {
    StockTable,
    IncomeDialog,
    WriteOffDialog,
    HistoryDialog
  },

  setup() {
    const $q = useQuasar();
    const items = ref<Product[]>([]);
    const categories = ref<ProductCategory[]>([]);
    const loading = ref(false);
    const selectedProduct = ref<Product | null>(null);
    const productHistory = ref<StockMovement[]>([]);

    // Диалоги
    const incomeDialog = ref(false);
    const writeOffDialog = ref(false);
    const historyDialog = ref(false);

    // Фильтры
    const filters = ref({
      search: '',
      type: null as string | null,
      categoryId: null as number | null,
      lowStock: false
    });

    // Отфильтрованные товары
    const filteredItems = computed(() => {
      let filtered = items.value;

      if (filters.value.search) {
        const s = filters.value.search.toLowerCase();
        filtered = filtered.filter(p => p.name.toLowerCase().includes(s));
      }
      if (filters.value.type) {
        filtered = filtered.filter(p => p.type === filters.value.type);
      }
      if (filters.value.categoryId) {
        filtered = filtered.filter(p => p.categoryId === filters.value.categoryId);
      }
      if (filters.value.lowStock) {
        filtered = filtered.filter(p => p.currentStock <= p.minStock);
      }
      return filtered;
    });

    const lowStockCount = computed(() => {
      return items.value.filter(p => p.currentStock <= p.minStock).length;
    });

    const loadStock = async () => {
      loading.value = true;
      try {
        items.value = await stockService.getCurrentStock();
      } catch (error) {
        $q.notify({ type: 'negative', message: 'Ошибка загрузки остатков' });
      } finally {
        loading.value = false;
      }
    };

    const loadCategories = async () => {
      try {
        categories.value = await productService.getCategories();
      } catch (error) {
        console.error('Ошибка загрузки категорий:', error);
      }
    };

    const resetFilters = () => {
      filters.value = {
        search: '',
        type: null,
        categoryId: null,
        lowStock: false
      };
    };

    // Приход (для конкретного товара)
    const openIncomeDialog = (product: Product) => {
      selectedProduct.value = product;
      incomeDialog.value = true;
    };

    // Глобальный приход (без выбора товара)
    const openGlobalIncome = () => {
      selectedProduct.value = null;
      incomeDialog.value = true;
    };

    const onIncome = async (data: any) => {
      try {
        const result = await stockService.addMovement({
          productId: data.productId,
          type: 'income',
          quantity: data.quantity,
          price: data.price,
          documentType: data.documentType,
          comment: data.comment
        });
        $q.notify({ type: 'positive', message: 'Приход добавлен' });
        await loadStock(); // обновляем список
      } catch (error: any) {
        $q.notify({ type: 'negative', message: error.message || 'Ошибка' });
      }
    };

    // Списание
    const openWriteOffDialog = (product: Product) => {
      selectedProduct.value = product;
      writeOffDialog.value = true;
    };

    const openGlobalWriteOff = () => {
      selectedProduct.value = null;
      writeOffDialog.value = true;
    };

    const onWriteOff = async (data: { items: Array<{ productId: number; quantity: number }>; reason: string }) => {
      try {
        const result = await stockService.writeOff({
          items: data.items,
          reason: data.reason
        });
        $q.notify({ type: 'positive', message: 'Списание выполнено' });
        await loadStock();
      } catch (error: any) {
        $q.notify({ type: 'negative', message: error.message || 'Ошибка' });
      }
    };

    // История
    const openHistoryDialog = async (product: Product) => {
      selectedProduct.value = product;
      try {
        productHistory.value = await stockService.getProductHistory(product.id);
        historyDialog.value = true;
      } catch (error) {
        $q.notify({ type: 'negative', message: 'Ошибка загрузки истории' });
      }
    };

    onMounted(() => {
      loadStock();
      loadCategories();
    });

    return {
      items,
      categories,
      loading,
      filters,
      filteredItems,
      lowStockCount,
      incomeDialog,
      writeOffDialog,
      historyDialog,
      selectedProduct,
      productHistory,
      loadStock,
      resetFilters,
      openIncomeDialog,
      openGlobalIncome,
      onIncome,
      openWriteOffDialog,
      openGlobalWriteOff,
      onWriteOff,
      openHistoryDialog
    };
  }
});
</script>
