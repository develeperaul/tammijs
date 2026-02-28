<template>
  <q-item>
    <q-item-section>
      <q-item-label>{{ item.productName }}</q-item-label>
      <q-item-label caption>
        Кол-во: {{ item.quantity }}
        <span v-if="item.comment"> | Комментарий: {{ item.comment }}</span>
      </q-item-label>
    </q-item-section>

    <q-item-section side>
      <q-checkbox
        :model-value="isReady"
        label="Готово"
        @update:model-value="$emit('update-status', $event ? 'ready' : 'pending')"
      />
    </q-item-section>
  </q-item>
</template>

<script lang="ts">
import { defineComponent, PropType, computed } from 'vue';
import { OrderItem } from 'src/types/order.types';

export default defineComponent({
  name: 'KitchenOrderItem',
  props: {
    item: {
      type: Object as PropType<OrderItem>,
      required: true
    }
  },
  emits: ['update-status'],
  setup(props) {
    const isReady = computed(() => props.item.cookingStatus === 'ready');
    return { isReady };
  }
});
</script>
