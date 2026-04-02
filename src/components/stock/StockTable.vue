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
      <!-- Тип товара -->
      <template v-slot:body-cell-type="props">
        <q-td :props="props">
          <q-chip :color="getTypeColor(props.value)" text-color="white" dense size="sm">
            {{ getTypeLabel(props.value) }}
          </q-chip>
        </q-td>
      </template>

      <!-- Остаток (с базовыми единицами для ингредиентов) -->
      <template v-slot:body-cell-stock="props">
        <q-td :props="props">
          <div class="row items-center">
            <q-badge :color="getStockColor(props.row)" class="q-mr-sm">
              {{ props.row.currentStock }} {{ props.row.unit || '' }}
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
          <div v-if="props.row.baseUnit" class="text-caption text-grey-7">
            {{ (props.row.currentStock * (props.row.baseRatio || 1)).toFixed(0) }} {{ props.row.baseUnit }}
          </div>
        </q-td>
      </template>

      <!-- Себестоимость -->
      <template v-slot:body-cell-cost="props">
        <q-td :props="props">
          <div v-if="props.row.type === 'ingredient'">
            {{ formatMoney(props.row.costPrice) }} / {{ props.row.unit }}
            <div class="text-caption text-grey-7">
              {{ formatMoney(props.row.costPrice / (props.row.baseRatio || 1)) }} / {{ props.row.baseUnit }}
            </div>
          </div>
          <div v-else-if="props.row.type === 'resale'">
            {{ formatMoney(props.row.costPrice) }} / {{ props.row.unit }}
          </div>
          <div v-else-if="props.row.type === 'semi-finished'">
            {{ formatMoney(props.row.costPrice) }} / {{ props.row.unit }}
          </div>
          <div v-else class="text-grey-5">
            из рецепта
          </div>
        </q-td>
      </template>

      <!-- Цена продажи -->
      <template v-slot:body-cell-price="props">
        <q-td :props="props">
          <div v-if="props.row.type === 'finished' || props.row.type === 'resale' || props.row.type === 'semi-finished'">
            {{ formatMoney(props.row.sellingPrice) }}
          </div>
          <div v-else class="text-grey-5">
            —
          </div>
        </q-td>
      </template>

      <!-- Минимальный остаток -->
      <template v-slot:body-cell-minStock="props">
        <q-td :props="props">
          <div>{{ props.row.minStock }} {{ props.row.unit || '' }}</div>
          <div v-if="props.row.baseUnit" class="text-caption text-grey-7">
            {{ (props.row.minStock * (props.row.baseRatio || 1)).toFixed(0) }} {{ props.row.baseUnit }}
          </div>
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
import { Ingredient } from 'src/types/ingredient.types';
import { ProductCategory } from 'src/types/product.types';

// Объединяем типы для совместимости
type StockItem = (Product | Ingredient) & {
  type: string;
  unit?: string;
  baseUnit?: string;
  baseRatio?: number;
  costPrice?: number;
  sellingPrice?: number;
  currentStock: number;
  minStock: number;
};

export default defineComponent({
  name: 'StockTable',

  props: {
    items: { type: Array as PropType<StockItem[]>, required: true },
    categories: { type: Array as PropType<ProductCategory[]>, default: () => [] },
    loading: { type: Boolean, default: false },
    search: { type: String, default: '' },
    typeFilter: { type: String, default: null },
    categoryFilter: { type: Number, default: null },
    lowStockFilter: { type: Boolean, default: false }
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
      { label: 'Полуфабрикат', value: 'semi-finished' },
      { label: 'Товар перепродажи', value: 'resale' }
    ];

    const columns = [
      { name: 'name', label: 'Товар', field: 'name', align: 'left', sortable: true },
      { name: 'type', label: 'Тип', field: 'type', align: 'center' },
      { name: 'stock', label: 'Остаток', field: 'currentStock', align: 'left', sortable: true },
      { name: 'cost', label: 'Себестоимость', field: 'costPrice', align: 'right', sortable: true },
      { name: 'price', label: 'Цена продажи', field: 'sellingPrice', align: 'right', sortable: true },
      { name: 'minStock', label: 'Мин. остаток', field: 'minStock', align: 'left' },
      { name: 'actions', label: 'Действия', align: 'center' }
    ];

    const getTypeColor = (type: string): string => {
      const colors: Record<string, string> = {
        ingredient: 'blue',
        finished: 'green',
        'semi-finished': 'orange',
        resale: 'purple'
      };
      return colors[type] || 'grey';
    };

    const getTypeLabel = (type: string): string => {
      const labels: Record<string, string> = {
        ingredient: 'Ингредиент',
        finished: 'Готовый',
        'semi-finished': 'Полуфабрикат',
        resale: 'Перепродажа'
      };
      return labels[type] || type;
    };

    const getStockColor = (item: StockItem): string => {
      if (item.currentStock <= 0) return 'negative';
      if (item.currentStock <= item.minStock) return 'warning';
      return 'positive';
    };

    const formatMoney = (value: number): string => {
      if (value === undefined || value === null) return '—';
      return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        minimumFractionDigits: 2
      }).format(value);
    };

    return {
      typeOptions,
      columns,
      getTypeColor,
      getTypeLabel,
      getStockColor,
      formatMoney
    };
  }
});
</script>
