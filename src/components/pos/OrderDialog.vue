<template>
  <q-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    @hide="$emit('hide')"
    persistent
  >
    <q-card style="min-width: 400px">
      <q-card-section>
        <div class="text-h6">Оформление заказа</div>
      </q-card-section>

      <q-card-section>
        <q-form @submit="onSubmit" class="q-gutter-md">
          <q-select
            v-model="orderType"
            :options="typeOptions"
            label="Тип заказа *"
            outlined
            dense
            emit-value
            map-options
            :rules="[val => !!val || 'Выберите тип']"
          />

          <q-input
            v-if="orderType === 'dine-in'"
            v-model.number="tableNumber"
            label="Номер стола"
            type="number"
            outlined
            dense
          />

          <q-select
            v-model="paymentMethod"
            :options="paymentOptions"
            label="Способ оплаты"
            outlined
            dense
            emit-value
            map-options
            clearable
          />

          <q-input
            v-model="comment"
            label="Комментарий к заказу"
            outlined
            dense
            type="textarea"
            rows="2"
          />

          <div class="text-subtitle2">Состав заказа:</div>
          <q-list bordered separator dense>
            <q-item v-for="item in items" :key="item.productId">
              <q-item-section>
                {{ item.name }} × {{ item.quantity }}
              </q-item-section>
              <q-item-section side>
                {{ formatPrice(item.price * item.quantity) }}
              </q-item-section>
            </q-item>
            <q-item class="text-weight-bold">
              <q-item-section>Итого:</q-item-section>
              <q-item-section side>{{ formatPrice(total) }}</q-item-section>
            </q-item>
          </q-list>
        </q-form>
      </q-card-section>

      <q-card-actions align="right">
        <q-btn flat label="Отмена" color="negative" v-close-popup />
        <q-btn flat label="Создать заказ" color="positive" @click="onSubmit" />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script lang="ts">
import { defineComponent, PropType, ref, computed } from 'vue';
import { CartItem, OrderType, PaymentMethod } from 'src/types/order.types';

export default defineComponent({
  name: 'OrderDialog',

  props: {
    modelValue: { type: Boolean, required: true },
    items: { type: Array as PropType<CartItem[]>, required: true }
  },

  emits: ['update:modelValue', 'ok', 'hide'],

  setup(props, { emit }) {
    const orderType = ref<OrderType>('dine-in');
    const tableNumber = ref<number>();
    const paymentMethod = ref<PaymentMethod>();
    const comment = ref('');

    const typeOptions = [
      { label: 'На месте', value: 'dine-in' },
      { label: 'С собой', value: 'takeaway' },
      { label: 'Доставка', value: 'delivery' }
    ];

    const paymentOptions = [
      { label: 'Наличные', value: 'cash' },
      { label: 'Карта', value: 'card' }
    ];

    const total = computed(() => {
      return props.items.reduce((sum, item) => sum + item.price * item.quantity, 0);
    });

    const onSubmit = () => {
      emit('ok', {
        type: orderType.value,
        tableNumber: orderType.value === 'dine-in' ? tableNumber.value : undefined,
        paymentMethod: paymentMethod.value,
        comment: comment.value,
        items: props.items.map(i => ({
          productId: i.productId,
          quantity: i.quantity,
          comment: i.comment
        }))
      });
      emit('update:modelValue', false);
    };

    const formatPrice = (value: number) => {
      return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        minimumFractionDigits: 0
      }).format(value);
    };

    return {
      orderType,
      tableNumber,
      paymentMethod,
      comment,
      typeOptions,
      paymentOptions,
      total,
      onSubmit,
      formatPrice
    };
  }
});
</script>
