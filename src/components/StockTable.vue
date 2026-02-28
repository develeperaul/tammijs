<template>
  <div>
    <q-table
      :rows="items"
      :columns="columns"
      :loading="loading"
      :filter="filter"
      row-key="id"
      flat
      bordered
    >
      <!-- Кастомный рендер для остатков -->
      <template v-slot:body-cell-stock="props">
        <q-td :props="props">
          <div class="row items-center">
            <q-badge :color="getStockColor(props.row)" class="q-mr-sm">
              {{ props.row.currentStock }} {{ props.row.unit }}
            </q-badge>
            <q-chip
              v-if="props.row.currentStock <= props.row.minStock"
              dense
              size="sm"
              color="orange"
              text-color="white"
            >
              {{ getStockStatus(props.row) }}
            </q-chip>
          </div>
        </q-td>
      </template>

      <!-- Кастомный рендер для действий -->
      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <q-btn
            flat
            round
            dense
            icon="edit"
            size="sm"
            @click="$emit('edit', props.row)"
          >
            <q-tooltip>Редактировать</q-tooltip>
          </q-btn>
          <q-btn
            flat
            round
            dense
            icon="add_shopping_cart"
            size="sm"
            color="positive"
            @click="$emit('income', props.row)"
          >
            <q-tooltip>Оприходовать</q-tooltip>
          </q-btn>
          <q-btn
            flat
            round
            dense
            icon="remove_shopping_cart"
            size="sm"
            color="negative"
            @click="$emit('write-off', props.row)"
          >
            <q-tooltip>Списать</q-tooltip>
          </q-btn>
        </q-td>
      </template>

      <!-- Состояние пустого списка -->
      <template v-slot:no-data>
        <div class="text-center q-pa-md">
          <q-icon name="inbox" size="48px" color="grey-5" />
          <div class="text-grey-7">Нет данных</div>
        </div>
      </template>
    </q-table>
  </div>
</template>

<script lang="ts">
import { defineComponent, PropType } from 'vue';
import { Product } from 'src/types/product.types';

export default defineComponent({
  name: 'StockTable',

  props: {
    items: {
      type: Array as PropType<Product[]>,
      required: true,
    },
    loading: {
      type: Boolean,
      default: false,
    },
    filter: {
      type: String,
      default: '',
    },
  },

  emits: ['edit', 'income', 'write-off'],

  setup() {
    const columns = [
      {
        name: 'name',
        label: 'Товар',
        field: 'name',
        align: 'left' as const,
        sortable: true,
      },
      {
        name: 'type',
        label: 'Тип',
        field: 'type',
        align: 'center' as const,
        format: (val: string) => {
          const types: Record<string, string> = {
            ingredient: 'Ингредиент',
            finished: 'Готовый',
            'semi-finished': 'Полуфабрикат',
          };
          return types[val] || val;
        },
      },
      {
        name: 'stock',
        label: 'Остаток',
        field: 'currentStock',
        align: 'center' as const,
        sortable: true,
      },
      {
        name: 'unit',
        label: 'Ед. изм.',
        field: 'unit',
        align: 'center' as const,
      },
      {
        name: 'minStock',
        label: 'Мин. остаток',
        field: 'minStock',
        align: 'center' as const,
      },
      {
        name: 'sellingPrice',
        label: 'Цена',
        field: 'sellingPrice',
        align: 'right' as const,
        format: (val: number) => val ? `${val} ₽` : '-',
      },
      {
        name: 'actions',
        label: 'Действия',
        field: 'actions',
        align: 'center' as const,
      },
    ];

    const getStockColor = (item: Product): string => {
      if (item.currentStock <= 0) return 'negative';
      if (item.currentStock <= item.minStock) return 'warning';
      return 'positive';
    };

    const getStockStatus = (item: Product): string => {
      if (item.currentStock <= 0) return 'Отсутствует';
      if (item.currentStock <= item.minStock) return 'Критический';
      if (item.currentStock <= item.minStock * 2) return 'Мало';
      return 'Норма';
    };

    return {
      columns,
      getStockColor,
      getStockStatus,
    };
  },
});
</script>
