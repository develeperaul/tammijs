<template>
  <div>
    <!-- Фильтры и поиск -->
    <div class="row q-mb-md q-gutter-sm items-center">
      <q-input
        :model-value="search"
        outlined
        dense
        placeholder="Поиск товара"
        class="col-4"
        @update:model-value="$emit('update:search', $event)"
      >
        <template v-slot:append>
          <q-icon name="search" />
        </template>
      </q-input>

      <q-select
        :model-value="typeFilter"
        :options="typeOptions"
        outlined
        dense
        placeholder="Тип"
        clearable
        class="col-2"
        @update:model-value="$emit('update:type', $event?.value)"
        emit-value
        map-options
      />

      <q-select
        :model-value="categoryFilter"
        :options="categories"
        option-label="name"
        option-value="id"
        outlined
        dense
        placeholder="Категория"
        clearable
        class="col-2"
        @update:model-value="$emit('update:category', $event?.id)"
        emit-value
        map-options
      />

      <q-checkbox
        :model-value="lowStockFilter"
        label="Только критические"
        @update:model-value="$emit('update:lowStock', $event)"
      />

      <q-btn flat icon="clear_all" color="primary" @click="$emit('resetFilters')">
        <q-tooltip>Сбросить фильтры</q-tooltip>
      </q-btn>
      <q-btn flat icon="refresh" @click="$emit('refresh')">
        <q-tooltip>Обновить</q-tooltip>
      </q-btn>
    </div>

    <!-- Таблица остатков -->
    <q-table
      :rows="items"
      :columns="columns"
      :loading="loading"
      row-key="id"
      flat
      bordered
    >
      <!-- Индикатор остатка -->
      <template v-slot:body-cell-stock="props">
        <q-td :props="props">
          <div class="row items-center">
            <q-badge :color="getStockColor(props.row)" class="q-mr-sm">
              {{ props.row.currentStock }} {{ props.row.unit }}
            </q-badge>
            <q-icon
              v-if="props.row.currentStock <= props.row.minStock"
              name="warning"
              color="orange"
              size="sm"
            >
              <q-tooltip>Критический остаток</q-tooltip>
            </q-icon>
          </div>
        </q-td>
      </template>

      <!-- Цена -->
      <template v-slot:body-cell-price="props">
        <q-td :props="props">
          {{ formatMoney(props.row.sellingPrice) }}
        </q-td>
      </template>

      <!-- Действия -->
      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <q-btn
            flat
            round
            dense
            icon="add_shopping_cart"
            color="positive"
            @click="$emit('income', props.row)"
          >
            <q-tooltip>Приход</q-tooltip>
          </q-btn>
          <q-btn
            flat
            round
            dense
            icon="remove_shopping_cart"
            color="negative"
            @click="$emit('write-off', props.row)"
          >
            <q-tooltip>Списание</q-tooltip>
          </q-btn>
          <q-btn
            flat
            round
            dense
            icon="history"
            color="info"
            @click="$emit('history', props.row)"
          >
            <q-tooltip>История</q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </q-table>
  </div>
</template>

<script lang="ts">
import { defineComponent, PropType } from 'vue';
import { Product } from 'src/types/product.types';
import { ProductCategory } from 'src/types/product.types';

export default defineComponent({
  name: 'StockTable',

  props: {
    items: {
      type: Array as PropType<Product[]>,
      required: true
    },
    categories: {
      type: Array as PropType<ProductCategory[]>,
      default: () => []
    },
    loading: {
      type: Boolean,
      default: false
    },
    search: {
      type: String,
      default: ''
    },
    typeFilter: {
      type: String,
      default: null
    },
    categoryFilter: {
      type: Number,
      default: null
    },
    lowStockFilter: {
      type: Boolean,
      default: false
    }
  },

  emits: [
    'update:search',
    'update:type',
    'update:category',
    'update:lowStock',
    'resetFilters',
    'refresh',
    'income',
    'write-off',
    'history'
  ],

  setup(props) {
    const typeOptions = [
      { label: 'Ингредиент', value: 'ingredient' },
      { label: 'Готовый', value: 'finished' },
      { label: 'Полуфабрикат', value: 'semi-finished' }
    ];

    const columns = [
      { name: 'name', label: 'Товар', field: 'name', align: 'left', sortable: true },
      { name: 'type', label: 'Тип', field: 'type', align: 'center' },
      { name: 'stock', label: 'Остаток', field: 'currentStock', align: 'center', sortable: true },
      { name: 'unit', label: 'Ед.', field: 'unit', align: 'center' },
      { name: 'minStock', label: 'Мин.', field: 'minStock', align: 'center' },
      { name: 'price', label: 'Цена', field: 'sellingPrice', align: 'right' },
      { name: 'actions', label: 'Действия', align: 'center' }
    ];

    const getStockColor = (product: Product): string => {
      if (product.currentStock <= 0) return 'negative';
      if (product.currentStock <= product.minStock) return 'warning';
      return 'positive';
    };

    const formatMoney = (value: number): string => {
      return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        minimumFractionDigits: 0
      }).format(value);
    };

    return {
      typeOptions,
      columns,
      getStockColor,
      formatMoney
    };
  }
});
</script>
