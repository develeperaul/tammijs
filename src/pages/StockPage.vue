<template>
  <q-page class="q-pa-md">
    <!-- Заголовок -->
    <div class="row q-mb-md items-center">
      <div class="col-6">
        <div class="text-h5">Остатки товаров</div>
        <div class="text-caption text-grey-7">
          Всего позиций: {{ totalItems }}
          <q-badge
            v-if="lowStockItems.length"
            :label="`${lowStockItems.length} критических`"
            color="warning"
            class="q-ml-sm"
          />
        </div>
      </div>
      <div class="col-6 text-right">
        <q-btn
          color="primary"
          label="Оприходовать"
          icon="add"
          @click="openInvoiceDialog"
          class="q-mr-sm"
        />
        <q-btn
          color="secondary"
          label="Приход по фото"
          icon="photo_camera"
          @click="openAIDialog"
        />
      </div>
    </div>

    <!-- Фильтры -->
    <div class="row q-mb-md q-gutter-sm">
      <q-input
        v-model="filter.search"
        outlined
        dense
        placeholder="Поиск товара"
        class="col-4"
        @update:model-value="debouncedSearch"
      >
        <template v-slot:append>
          <q-icon name="search" />
        </template>
      </q-input>

      <q-select
        v-model="filter.type"
        :options="typeOptions"
        outlined
        dense
        placeholder="Тип"
        clearable
        class="col-2"
        @update:model-value="fetchStock"
      />

      <q-select
        v-model="filter.categoryId"
        :options="categoryOptions"
        outlined
        dense
        placeholder="Категория"
        clearable
        class="col-2"
        @update:model-value="fetchStock"
      />

      <q-checkbox
        v-model="filter.lowStock"
        label="Только критические"
        @update:model-value="fetchStock"
      />

      <q-btn
        flat
        dense
        icon="refresh"
        @click="resetFilter"
      >
        <q-tooltip>Сбросить фильтры</q-tooltip>
      </q-btn>
    </div>

    <!-- Таблица остатков -->
    <stock-table
      :items="items"
      :loading="loading"
      :filter="filter.search"
      @edit="openEditDialog"
      @income="openIncomeDialog"
      @write-off="openWriteOffDialog"
    />

    <!-- Диалог оприходования -->
    <q-dialog v-model="invoiceDialog">
      <invoice-dialog
        @saved="onInvoiceSaved"
      />
    </q-dialog>

    <!-- AI диалог -->
    <q-dialog v-model="aiDialog">
      <ai-dialog
        @processed="onAIProcessed"
      />
    </q-dialog>

    <!-- Диалог списания -->
    <q-dialog v-model="writeOffDialog">
      <write-off-dialog
        :product="selectedProduct"
        @completed="onWriteOffCompleted"
      />
    </q-dialog>
  </q-page>
</template>

<script lang="ts">
import { defineComponent, ref, onMounted } from 'vue';
import { useQuasar } from 'quasar';
import { useStock } from 'src/composables/useStock';
import StockTable from 'components/StockTable.vue';
import InvoiceDialog from 'components/InvoiceDialog.vue';
import AIDialog from 'components/AIDialog.vue';
import WriteOffDialog from 'components/WriteOffDialog.vue';
import { Product } from 'src/types/product.types';
import { debounce } from 'lodash';

export default defineComponent({
  name: 'StockPage',

  components: {
    StockTable,
    InvoiceDialog,
    AIDialog,
    WriteOffDialog,
  },

  setup() {
    const $q = useQuasar();
    const { items, loading, filter, fetchStock, resetFilter, totalItems, lowStockItems } = useStock();

    const invoiceDialog = ref(false);
    const aiDialog = ref(false);
    const writeOffDialog = ref(false);
    const selectedProduct = ref<Product | null>(null);

    const typeOptions = [
      { label: 'Ингредиент', value: 'ingredient' },
      { label: 'Готовый', value: 'finished' },
      { label: 'Полуфабрикат', value: 'semi-finished' },
    ];

    const categoryOptions = ref([]); // Загрузить из API

    // Debounced поиск
    const debouncedSearch = debounce(() => {
      fetchStock();
    }, 500);

    const openInvoiceDialog = () => {
      invoiceDialog.value = true;
    };

    const openAIDialog = () => {
      aiDialog.value = true;
    };

    const openWriteOffDialog = (product: Product) => {
      selectedProduct.value = product;
      writeOffDialog.value = true;
    };

    const openEditDialog = (product: Product) => {
      // Открыть диалог редактирования
    };

    const onInvoiceSaved = () => {
      invoiceDialog.value = false;
      fetchStock();
      $q.notify({
        type: 'positive',
        message: 'Накладная сохранена',
      });
    };

    const onAIProcessed = () => {
      aiDialog.value = false;
      fetchStock();
    };

    const onWriteOffCompleted = () => {
      writeOffDialog.value = false;
      selectedProduct.value = null;
      fetchStock();
    };

    onMounted(() => {
      fetchStock();
    });

    return {
      // State
      items,
      loading,
      filter,
      invoiceDialog,
      aiDialog,
      writeOffDialog,
      selectedProduct,
      typeOptions,
      categoryOptions,
      totalItems,
      lowStockItems,

      // Methods
      fetchStock,
      resetFilter,
      debouncedSearch,
      openInvoiceDialog,
      openAIDialog,
      openWriteOffDialog,
      openEditDialog,
      onInvoiceSaved,
      onAIProcessed,
      onWriteOffCompleted,
    };
  },
});
</script>
