<template>
  <q-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    @hide="$emit('hide')"
    maximized
  >
    <q-card>
      <q-card-section class="row items-center">
        <div class="text-h6">История движений: {{ product?.name }}</div>
        <q-space />
        <q-btn flat round icon="close" @click="onCancel" />
      </q-card-section>

      <q-card-section>
        <div v-if="!movements || movements.length === 0" class="text-center q-pa-md text-grey-7">
          Нет движений по данному товару
        </div>
        <q-table
          v-else
          :rows="movements"
          :columns="columns"
          row-key="id"
          flat
          bordered
        >
          <template v-slot:body-cell-type="props">
            <q-td :props="props">
              <q-badge :color="getTypeColor(props.value)">
                {{ getTypeLabel(props.value) }}
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

          <template v-slot:body-cell-createdAt="props">
            <q-td :props="props">
              {{ formatDate(props.value) }}
            </q-td>
          </template>
        </q-table>
      </q-card-section>
    </q-card>
  </q-dialog>
</template>

<script lang="ts">
import { defineComponent, PropType } from 'vue';
import { StockMovement } from 'src/types/stock.types';
import { Product } from 'src/types/product.types';
import { date } from 'quasar';

export default defineComponent({
  name: 'HistoryDialog',

  props: {
    modelValue: {
      type: Boolean,
      required: true
    },
    product: {
      type: Object as PropType<Product | null>,
      default: null
    },
    movements: {
      type: Array as PropType<StockMovement[]>,
      default: () => []
    }
  },

  emits: ['update:modelValue', 'hide'],

  setup(props, { emit }) {
    const columns = [
      { name: 'type', label: 'Тип', field: 'type', align: 'center' },
      { name: 'quantity', label: 'Количество', field: 'quantity', align: 'right' },
      { name: 'comment', label: 'Комментарий', field: 'comment', align: 'left' },
      { name: 'createdAt', label: 'Дата', field: 'createdAt', align: 'center' }
    ];

    const getTypeColor = (type: string = ''): string => {
      const colors: Record<string, string> = {
        income: 'positive',
        outcome: 'info',
        'write-off': 'negative',
        move: 'warning'
      };
      return colors[type] || 'grey';
    };

    const getTypeLabel = (type: string = ''): string => {
      const labels: Record<string, string> = {
        income: 'Приход',
        outcome: 'Расход',
        'write-off': 'Списание',
        move: 'Перемещение'
      };
      return labels[type] || type;
    };

    const getQuantityClass = (movement: StockMovement): string => {
      if (!movement) return '';
      return movement.type === 'income' ? 'text-positive' : 'text-negative';
    };

    const formatDate = (value: string = ''): string => {
      if (!value) return '';
      return date.formatDate(value, 'DD.MM.YYYY HH:mm');
    };

    const onCancel = () => {
      emit('update:modelValue', false);
    };

    return {
      columns,
      getTypeColor,
      getTypeLabel,
      getQuantityClass,
      formatDate,
      onCancel
    };
  }
});
</script>
