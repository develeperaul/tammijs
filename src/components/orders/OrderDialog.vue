<template>
  <q-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    @hide="$emit('hide')"
  >
    <q-card style="min-width: 500px">
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

          <!-- Общая скидка -->
          <div class="row q-gutter-sm">
            <q-select
              v-model="totalDiscountType"
              :options="discountTypeOptions"
              label="Тип скидки"
              outlined
              dense
              emit-value
              map-options
              class="col-5"
              clearable
            />
            <q-input
              v-if="totalDiscountType"
              v-model.number="totalDiscountValue"
              :label="totalDiscountType === 'percent' ? 'Процент' : 'Сумма'"
              type="number"
              outlined
              dense
              class="col-4"
              :min="0"
              :max="totalDiscountType === 'percent' ? 100 : undefined"
            />
          </div>

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
                <div v-if="item.currentPrice !== item.originalPrice" class="text-caption text-grey">
                  Цена со скидкой: {{ item.currentPrice }} ₽ (было {{ item.originalPrice }} ₽)
                </div>
                <div v-if="item.discountPercent" class="text-caption text-positive">
                  Скидка {{ item.discountPercent }}%
                </div>
              </q-item-section>
              <q-item-section side>
                {{ item.currentPrice * item.quantity }} ₽
              </q-item-section>
            </q-item>
            <q-item class="text-weight-bold">
              <q-item-section>Подытог:</q-item-section>
              <q-item-section side>{{ subtotal }} ₽</q-item-section>
            </q-item>
            <q-item v-if="totalDiscountValue" class="text-weight-bold text-positive">
              <q-item-section>Скидка:</q-item-section>
              <q-item-section side>- {{ discountAmount }} ₽</q-item-section>
            </q-item>
            <q-item class="text-weight-bold text-primary">
              <q-item-section>ИТОГО:</q-item-section>
              <q-item-section side>{{ total }} ₽</q-item-section>
            </q-item>
          </q-list>
        </q-form>
      </q-card-section>

      <q-card-actions align="right">
        <q-btn flat label="Отмена" color="negative" @click="onCancel" />
        <q-btn flat label="Создать заказ" color="positive" @click="onSubmit" />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script lang="ts">
import { defineComponent, PropType, ref, computed } from 'vue';
import { CartItem } from 'src/types/cart.types';
import { DiscountType } from 'src/types/order.types';

export default defineComponent({
  name: 'OrderDialog',

  props: {
    modelValue: {
      type: Boolean,
      required: true
    },
    items: {
      type: Array as PropType<CartItem[]>,
      required: true
    }
  },

  emits: ['update:modelValue', 'ok', 'hide'],

  setup(props, { emit }) {
    const orderType = ref<'dine-in' | 'takeaway' | 'delivery'>('dine-in');
    const tableNumber = ref<number>();
    const paymentMethod = ref<string>();
    const comment = ref('');
    const totalDiscountType = ref<DiscountType>();
    const totalDiscountValue = ref<number>();

    const typeOptions = [
      { label: 'На месте', value: 'dine-in' },
      { label: 'С собой', value: 'takeaway' },
      { label: 'Доставка', value: 'delivery' }
    ];

    const discountTypeOptions = [
      { label: 'Процент', value: 'percent' },
      { label: 'Сумма', value: 'amount' }
    ];

    const paymentOptions = [
      { label: 'Наличные', value: 'cash' },
      { label: 'Карта', value: 'card' }
    ];

    const subtotal = computed(() => {
      return props.items.reduce((sum, item) => sum + item.currentPrice * item.quantity, 0);
    });

    const discountAmount = computed(() => {
      if (!totalDiscountType.value || !totalDiscountValue.value) return 0;
      if (totalDiscountType.value === 'percent') {
        return (subtotal.value * totalDiscountValue.value) / 100;
      } else {
        return totalDiscountValue.value;
      }
    });

    const total = computed(() => {
      return subtotal.value - discountAmount.value;
    });

    const onSubmit = () => {
      emit('ok', {
        type: orderType.value,
        tableNumber: orderType.value === 'dine-in' ? tableNumber.value : undefined,
        paymentMethod: paymentMethod.value,
        totalDiscountType: totalDiscountType.value,
        totalDiscountValue: totalDiscountValue.value,
        comment: comment.value,
        items: props.items.map(i => ({
          productId: i.productId,
          quantity: i.quantity,
          price: i.currentPrice,
          discountPercent: i.discountPercent,
          comment: i.comment
        }))
      });
      emit('update:modelValue', false);
    };

    const onCancel = () => {
      emit('update:modelValue', false);
    };

    return {
      orderType,
      tableNumber,
      paymentMethod,
      comment,
      totalDiscountType,
      totalDiscountValue,
      typeOptions,
      discountTypeOptions,
      paymentOptions,
      subtotal,
      discountAmount,
      total,
      onSubmit,
      onCancel
    };
  }
});
</script>
