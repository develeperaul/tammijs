<template>
  <div>
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
        :model-value="type"
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
        :model-value="category"
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
        :model-value="lowStock"
        label="Только критические"
        @update:model-value="$emit('update:lowStock', $event)"
      />

      <q-btn flat icon="clear_all" color="primary" @click="$emit('resetFilters')">
        <q-tooltip>Сбросить фильтры</q-tooltip>
      </q-btn>

      <q-btn flat icon="refresh" @click="$emit('refresh')">
        <q-tooltip>Обновить список</q-tooltip>
      </q-btn>
    </div>

    <q-table
      :rows="products"
      :columns="columns"
      :loading="loading"
      row-key="id"
      flat
      bordered
    >
      <template v-slot:body-cell-type="props">
        <q-td :props="props">
          <q-chip :color="getTypeColor(props.value)" text-color="white" dense size="sm">
            {{ getTypeLabel(props.value) }}
          </q-chip>
        </q-td>
      </template>

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

      <template v-slot:body-cell-price="props">
        <q-td :props="props">
          {{ formatMoney(props.row.sellingPrice) }}
        </q-td>
      </template>

      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <q-btn flat round dense icon="edit" size="sm" @click="$emit('edit', props.row)">
            <q-tooltip>Редактировать</q-tooltip>
          </q-btn>
          <q-btn flat round dense icon="delete" size="sm" color="negative" @click="$emit('delete', props.row)">
            <q-tooltip>Удалить</q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </q-table>
  </div>
</template>

<script lang="ts">
import { defineComponent, PropType } from 'vue';
import { Product, ProductCategory } from 'src/types/product.types';

export default defineComponent({
  name: 'ProductTable',

  props: {
    products: { type: Array as PropType<Product[]>, required: true },
    categories: { type: Array as PropType<ProductCategory[]>, default: () => [] },
    loading: { type: Boolean, default: false },
    search: { type: String, default: '' },
    type: { type: String, default: null },        // переименовано
    category: { type: Number, default: null },     // переименовано
    lowStock: { type: Boolean, default: false }    // переименовано
  },

  emits: [
    'update:search',
    'update:type',
    'update:category',
    'update:lowStock',
    'resetFilters',
    'edit',
    'delete',
    'refresh'
  ],

  setup(props) {
    const typeOptions = [
      { label: 'Ингредиент', value: 'ingredient' },
      { label: 'Готовый', value: 'finished' },
      { label: 'Полуфабрикат', value: 'semi-finished' }
    ];

    const columns = [
      { name: 'name', label: 'Название', field: 'name', align: 'left', sortable: true },
      { name: 'type', label: 'Тип', field: 'type', align: 'center' },
      { name: 'stock', label: 'Остаток', field: 'currentStock', align: 'center', sortable: true },
      { name: 'minStock', label: 'Мин.', field: 'minStock', align: 'center' },
      { name: 'price', label: 'Цена', field: 'sellingPrice', align: 'right', sortable: true },
      { name: 'actions', label: 'Действия', field: 'actions', align: 'center' }
    ];

    const getTypeColor = (type: string): string => {
      const colors: Record<string, string> = { ingredient: 'blue', finished: 'green', 'semi-finished': 'orange' };
      return colors[type] || 'grey';
    };

    const getTypeLabel = (type: string): string => {
      const labels: Record<string, string> = { ingredient: 'Ингредиент', finished: 'Готовый', 'semi-finished': 'Полуфабрикат' };
      return labels[type] || type;
    };

    const getStockColor = (product: Product): string => {
      if (product.currentStock <= 0) return 'negative';
      if (product.currentStock <= product.minStock) return 'warning';
      return 'positive';
    };

    const formatMoney = (value: number): string => {
      return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', minimumFractionDigits: 0 }).format(value);
    };

    return { typeOptions, columns, getTypeColor, getTypeLabel, getStockColor, formatMoney };
  }
});
</script>
