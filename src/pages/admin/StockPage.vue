<template>
  <q-page class="q-pa-md">
    <!-- Заголовок -->
    <div class="row q-mb-md items-center">
      <div class="col-6">
        <div class="text-h5">Остатки товаров</div>
        <div class="text-caption text-grey-7">
          Всего позиций: {{ allStockItems.length }}
          <q-badge
            v-if="lowStockCount > 0"
            :label="`${lowStockCount} критических`"
            color="warning"
            class="q-ml-sm"
          />
        </div>
      </div>
      <div class="col-6 text-right">
        <!-- <q-btn
          color="secondary"
          label="Приход по фото"
          icon="photo_camera"
          @click="openAIDialog"
          class="q-mr-sm"
        /> -->
        <!-- <q-btn
          color="primary"
          label="Приход"
          icon="add"
          @click="openGlobalIncome"
          class="q-mr-sm"
        /> -->
        <q-btn
          color="primary"
          label="Накладная"
          icon="assignment"
          @click="openInvoiceDialog"
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
      @refresh="loadAll"
      @resetFilters="resetFilters"
      @income="openIncomeDialog"
      @write-off="openWriteOffDialog"
      @history="openHistoryDialog"
    />

    <!-- Диалоги -->
    <income-dialog
      v-model="incomeDialog"
      :product="selectedProduct"
      :products="allStockItems"
      @ok="onIncome"
      @hide="selectedProduct = null"
    />

    <!-- Новый диалог накладной (только ингредиенты) -->
    <invoice-dialog
      v-model="invoiceDialog"
      @ok="onInvoiceSave"
      @hide="invoiceDialog = false"
    />

    <write-off-dialog
      v-model="writeOffDialog"
      :product="selectedProduct"
      :products="allStockItems"
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
import ingredientService from 'src/services/ingredient.service';
import { Product } from 'src/types/product.types';
import { Ingredient } from 'src/types/ingredient.types';
import { ProductCategory } from 'src/types/product.types';
import { StockMovement } from 'src/types/stock.types';
import StockTable from 'components/stock/StockTable.vue';
import IncomeDialog from 'components/stock/IncomeDialog.vue';
import InvoiceDialog from 'components/invoice/InvoiceDialog.vue';
import WriteOffDialog from 'components/stock/WriteOffDialog.vue';
import HistoryDialog from 'components/stock/HistoryDialog.vue';

export default defineComponent({
  name: 'StockPage',

  components: {
    StockTable,
    IncomeDialog,
    InvoiceDialog,
    WriteOffDialog,
    HistoryDialog
  },

  setup() {
    const $q = useQuasar();

    // Ингредиенты (сырьё)
    const ingredients = ref<Ingredient[]>([]);
    // Товары перепродажи (кола, чипсы) — имеют остатки
    const resaleProducts = ref<Product[]>([]);
    const categories = ref<ProductCategory[]>([]);
    const loading = ref(false);
    const selectedProduct = ref<Product | Ingredient | null>(null);
    const productHistory = ref<StockMovement[]>([]);

    // Диалоги
    const incomeDialog = ref(false);
    const invoiceDialog = ref(false);
    const writeOffDialog = ref(false);
    const historyDialog = ref(false);

    // Фильтры
    const filters = ref({
      search: '',
      type: null as string | null,
      categoryId: null as number | null,
      lowStock: false
    });

    // Объединяем только то, что имеет остатки
    const allStockItems = computed(() => {
      return [...ingredients.value, ...resaleProducts.value];
    });

    // Отфильтрованные товары для таблицы
    const filteredItems = computed(() => {
      let filtered = allStockItems.value;

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
      return allStockItems.value.filter(p => p.currentStock <= p.minStock).length;
    });

    // Загрузка ингредиентов
    const loadIngredients = async () => {
      try {
        const response = await ingredientService.getAll();
        ingredients.value = response.data || [];
      } catch (error) {
        $q.notify({ type: 'negative', message: 'Ошибка загрузки ингредиентов' });
      }
    };

    // Загрузка товаров перепродажи (только тип resale)
    const loadResaleProducts = async () => {
      try {
        const response = await productService.getProducts();
        resaleProducts.value = response.data.filter(p => p.type === 'resale');
      } catch (error) {
        console.error('Ошибка загрузки товаров перепродажи:', error);
      }
    };

    const loadCategories = async () => {
      try {
        categories.value = await productService.getCategories();
      } catch (error) {
        console.error('Ошибка загрузки категорий:', error);
      }
    };

    const loadAll = async () => {
      loading.value = true;
      try {
        await Promise.all([loadIngredients(), loadResaleProducts()]);
      } catch (error) {
        $q.notify({ type: 'negative', message: 'Ошибка загрузки данных' });
      } finally {
        loading.value = false;
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

    // Приход
    const openIncomeDialog = (product: Product | Ingredient) => {
      selectedProduct.value = product;
      incomeDialog.value = true;
    };

    const openGlobalIncome = () => {
      selectedProduct.value = null;
      incomeDialog.value = true;
    };

    const onIncome = async (data: any) => {
      try {
        await stockService.addMovement({
          productId: data.productId,
          type: 'income',
          quantity: data.quantity,
          price: data.price,
          documentType: data.documentType,
          comment: data.comment
        });
        $q.notify({ type: 'positive', message: 'Приход добавлен' });
        await loadAll();
      } catch (error: any) {
        $q.notify({ type: 'negative', message: error.message || 'Ошибка' });
      }
    };

    // Накладная
    const openInvoiceDialog = () => {
      invoiceDialog.value = true;
    };

    const onInvoiceSave = async (invoiceData: any) => {
      loading.value = true;
      try {
        for (const item of invoiceData.items) {
          await stockService.addMovement({
            productId: item.productId,
            type: 'income',
            quantity: item.quantity,
            price: item.price,
            documentType: 'invoice',
            documentId: parseInt(invoiceData.number) || 0,
            comment: `Накладная №${invoiceData.number} от ${invoiceData.date}`,
            supplierId: invoiceData.supplierId
          });
        }
        $q.notify({ type: 'positive', message: 'Накладная проведена' });
        await loadAll();
        invoiceDialog.value = false;
      } catch (error: any) {
        $q.notify({ type: 'negative', message: error.message || 'Ошибка' });
      } finally {
        loading.value = false;
      }
    };

    // Списание
    const openWriteOffDialog = (product: Product | Ingredient) => {
      selectedProduct.value = product;
      writeOffDialog.value = true;
    };

    const openGlobalWriteOff = () => {
      selectedProduct.value = null;
      writeOffDialog.value = true;
    };

    const onWriteOff = async (data: { items: Array<{ productId: number; quantity: number }>; reason: string }) => {
      try {
        await stockService.writeOff({
          items: data.items,
          reason: data.reason
        });
        $q.notify({ type: 'positive', message: 'Списание выполнено' });
        await loadAll();
      } catch (error: any) {
        $q.notify({ type: 'negative', message: error.message || 'Ошибка' });
      }
    };

    // История
    const openHistoryDialog = async (product: Product | Ingredient) => {
      selectedProduct.value = product;
      try {
        productHistory.value = await stockService.getProductHistory(product.id);
        historyDialog.value = true;
      } catch (error) {
        $q.notify({ type: 'negative', message: 'Ошибка загрузки истории' });
      }
    };

    onMounted(() => {
      loadAll();
      loadCategories();
    });

    return {
      ingredients,
      resaleProducts,
      categories,
      loading,
      filters,
      filteredItems,
      allStockItems,
      lowStockCount,
      incomeDialog,
      invoiceDialog,
      writeOffDialog,
      historyDialog,
      selectedProduct,
      productHistory,
      loadAll,
      resetFilters,
      openIncomeDialog,
      openGlobalIncome,
      onIncome,
      openInvoiceDialog,
      onInvoiceSave,
      openWriteOffDialog,
      openGlobalWriteOff,
      onWriteOff,
      openHistoryDialog
    };
  }
});
</script>
