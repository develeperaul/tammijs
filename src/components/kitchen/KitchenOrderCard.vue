<template>
  <q-card flat bordered class="kitchen-order-card">
    <q-card-section class="bg-primary text-white">
      <div class="row items-center">
        <div class="text-h6">Заказ #{{ order.number }}</div>
        <q-space />
        <!-- Выпадающий список статусов заказа -->
        <q-select
          v-model="localStatus"
          :options="statusOptions"
          dense
          outlined
          dark
          bg-color="white"
          color="primary"
          style="min-width: 150px"
          @update:model-value="$emit('update-order-status', order.id, $event)"
        />
      </div>
      <div class="row q-mt-sm">
        <div class="col-6">
          <div>Стол: {{ order.tableNumber || '—' }}</div>
          <div>Тип: {{ order.type === 'dine-in' ? 'На месте' : order.type === 'takeaway' ? 'С собой' : 'Доставка' }}</div>
        </div>
        <div class="col-6 text-right">
          <div>Время: {{ formatTime(order.createdAt) }}</div>
          <div>Прошло: {{ timeSince(order.createdAt) }}</div>
        </div>
      </div>
    </q-card-section>

    <q-card-section>
      <q-list bordered separator>
        <kitchen-order-item
          v-for="item in order.items"
          :key="item.id"
          :item="item"
          @update-status="(status) => $emit('update-item', order.id, item.id, status)"
        />
      </q-list>
    </q-card-section>

    <q-card-actions align="right">
      <q-btn
        flat
        label="Заказ готов"
        color="positive"
        :disable="!allItemsReady"
        @click="$emit('ready', order.id)"
      />
    </q-card-actions>
  </q-card>
</template>

<script lang="ts">
import { defineComponent, PropType, computed, ref } from 'vue';
import { Order, OrderStatus } from 'src/types/order.types';
import { date } from 'quasar';
import KitchenOrderItem from './KitchenOrderItem.vue';

export default defineComponent({
  name: 'KitchenOrderCard',
  components: { KitchenOrderItem },
  props: {
    order: {
      type: Object as PropType<Order>,
      required: true
    }
  },
  emits: ['update-item', 'update-order-status', 'ready'],
  setup(props, { emit }) {
    const localStatus = ref(props.order.status);

    const statusOptions = [
      { label: 'Предзаказ', value: 'preorder' },
      { label: 'Новый', value: 'new' },
      { label: 'Собран', value: 'collected' },
      { label: 'В доставке', value: 'in_delivery' },
      { label: 'В производстве', value: 'in_production' },
      { label: 'Произведен', value: 'produced' }
    ];

    const allItemsReady = computed(() => {
      return props.order.items.every(item => item.cookingStatus === 'ready');
    });

    const formatTime = (value: string) => date.formatDate(value, 'HH:mm');
    const timeSince = (value: string) => {
      const minutes = date.getDateDiff(new Date(), new Date(value), 'minutes');
      if (minutes < 60) return `${minutes} мин`;
      return `${Math.floor(minutes / 60)} ч ${minutes % 60} мин`;
    };

    return {
      localStatus,
      statusOptions,
      allItemsReady,
      formatTime,
      timeSince
    };
  }
});
</script>
