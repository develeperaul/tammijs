<template>
  <q-card flat bordered class="cart-panel">
    <q-card-section class="bg-grey-2">
      <div class="text-h6">Корзина</div>
    </q-card-section>

    <q-card-section v-if="items.length === 0" class="text-center text-grey-7">
      Корзина пуста
    </q-card-section>

    <q-card-section v-else class="q-pa-sm">
      <q-list bordered separator>
        <q-item v-for="(item, idx) in items" :key="idx">
          <q-item-section>
            <q-item-label>{{ item.name }}</q-item-label>
            <q-item-label caption>
              {{ formatPrice(item.price) }} × {{ item.quantity }}
            </q-item-label>
          </q-item-section>
          <q-item-section side>
            <div class="row items-center">
              <q-btn flat dense round icon="remove" size="sm" @click="updateQuantity(idx, item.quantity - 1)" />
              <span class="q-mx-sm">{{ item.quantity }}</span>
              <q-btn flat dense round icon="add" size="sm" @click="updateQuantity(idx, item.quantity + 1)" />
              <q-btn flat dense round icon="delete" color="negative" size="sm" @click="removeItem(idx)" />
            </div>
          </q-item-section>
        </q-item>
      </q-list>
    </q-card-section>

    <q-card-section v-if="items.length > 0" class="bg-grey-2">
      <!-- Подытог -->
      <div class="row justify-between">
        <span>Подытог:</span>
        <span>{{ formatPrice(subtotal) }}</span>
      </div>

      <!-- Скидка с выбором типа (две большие кнопки) -->
      <div class="row justify-center q-gutter-sm q-mt-md">
        <q-btn
          :color="discountType === 'percent' ? 'primary' : 'grey-4'"
          :text-color="discountType === 'percent' ? 'white' : 'black'"
          label="Процент %"
          class="col"
          @click="discountType = 'percent'"
        />
        <q-btn
          :color="discountType === 'amount' ? 'primary' : 'grey-4'"
          :text-color="discountType === 'amount' ? 'white' : 'black'"
          label="Рубли ₽"
          class="col"
          @click="discountType = 'amount'"
        />
      </div>

      <!-- Поле ввода скидки -->
      <div class="row justify-between items-center q-mt-sm">
        <span>Скидка:</span>
        <q-input
          v-model.number="discountValue"
          type="number"
          dense
          outlined
          style="width: 120px"
          :placeholder="discountType === 'percent' ? '0-100' : '0'"
          :min="0"
          :max="discountType === 'percent' ? 100 : undefined"
          step="1"
        />
        <q-btn
          flat
          dense
          icon="clear"
          size="sm"
          @click="discountValue = 0"
        />
      </div>

      <!-- Быстрый выбор процентов (только когда выбран процент) -->
      <div v-if="discountType === 'percent'" class="row justify-center q-gutter-sm q-mt-sm">
        <q-btn
          v-for="percent in [5, 10, 15, 20, 25, 30, 40, 50]"
          :key="percent"
          flat
          dense
          outline
          :label="`${percent}%`"
          @click="discountValue = percent"
        />
      </div>

      <!-- Сумма скидки (для информации) -->
      <div v-if="discountAmount > 0" class="row justify-between text-positive q-mt-sm">
        <span>Сумма скидки:</span>
        <span>- {{ formatPrice(discountAmount) }}</span>
      </div>

      <!-- Итого -->
      <div class="row justify-between text-weight-bold q-mt-sm">
        <span>Итого:</span>
        <span>{{ formatPrice(total) }}</span>
      </div>

      <!-- Способ оплаты -->
      <div class="row justify-center q-gutter-sm q-mt-md">
        <q-btn
          :color="paymentMethod === 'cash' ? 'primary' : 'grey-4'"
          :text-color="paymentMethod === 'cash' ? 'white' : 'black'"
          label="Наличные"
          class="col"
          @click="paymentMethod = 'cash'"
        />
        <q-btn
          :color="paymentMethod === 'card' ? 'primary' : 'grey-4'"
          :text-color="paymentMethod === 'card' ? 'white' : 'black'"
          label="Безналичные"
          class="col"
          @click="paymentMethod = 'card'"
        />
      </div>

      <!-- Быстрые суммы (только для наличных) -->
      <div v-if="paymentMethod === 'cash'" class="row justify-between q-mt-md q-gutter-sm">
        <q-btn
          v-for="amount in quickAmounts"
          :key="amount"
          flat
          dense
          :label="`${amount} ₽`"
          @click="setQuickPayment(amount)"
        />
      </div>
    </q-card-section>

    <q-card-actions align="right" class="q-pa-md q-gutter-sm">
      <q-btn
        flat
        label="Очистить"
        color="negative"
        @click="clearCart"
      />
      <q-btn
        flat
        label="Быстрый заказ"
        color="positive"
        :disable="items.length === 0 || !paymentMethod"
        @click="quickOrder"
      />
    </q-card-actions>
  </q-card>
</template>

<script lang="ts">
import { defineComponent, PropType, computed, ref } from 'vue';
import { CartItem } from 'src/types/order.types';

export default defineComponent({
  name: 'CartPanel',

  props: {
    items: { type: Array as PropType<CartItem[]>, required: true }
  },

  emits: ['update:items', 'quick-order'],

  setup(props, { emit }) {
    const discountType = ref<'percent' | 'amount'>('percent');
    const discountValue = ref<number>(0);
    const paymentMethod = ref<'cash' | 'card'>('cash');
    const quickAmounts = [100, 200, 500, 1000, 2000, 5000];

    const subtotal = computed(() => {
      return props.items.reduce((sum, item) => sum + item.price * item.quantity, 0);
    });

    const discountAmount = computed(() => {
      if (discountValue.value <= 0) return 0;
      if (discountType.value === 'percent') {
        const amount = (subtotal.value * discountValue.value) / 100;
        return Math.min(amount, subtotal.value);
      } else {
        return Math.min(discountValue.value, subtotal.value);
      }
    });

    const total = computed(() => {
      return Math.max(0, subtotal.value - discountAmount.value);
    });

    const updateQuantity = (index: number, newQuantity: number) => {
      if (newQuantity <= 0) {
        removeItem(index);
        return;
      }
      const updated = [...props.items];
      updated[index] = { ...updated[index], quantity: newQuantity };
      emit('update:items', updated);
    };

    const removeItem = (index: number) => {
      const updated = props.items.filter((_, i) => i !== index);
      emit('update:items', updated);
    };

    const clearCart = () => {
      emit('update:items', []);
      discountValue.value = 0;
    };

    const setQuickPayment = (amount: number) => {
      if (amount >= subtotal.value) {
        discountType.value = 'amount';
        discountValue.value = 0;
      } else {
        discountType.value = 'amount';
        discountValue.value = subtotal.value - amount;
      }
    };

    const quickOrder = () => {
      emit('quick-order', {
        items: props.items,
        total: total.value,
        subtotal: subtotal.value,
        discount: discountAmount.value,
        discountType: discountType.value,
        discountValue: discountValue.value,
        paymentMethod: paymentMethod.value
      });
    };

    const formatPrice = (value: number) => {
      return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        minimumFractionDigits: 0
      }).format(value);
    };

    return {
      discountType,
      discountValue,
      paymentMethod,
      quickAmounts,
      subtotal,
      discountAmount,
      total,
      updateQuantity,
      removeItem,
      clearCart,
      setQuickPayment,
      quickOrder,
      formatPrice
    };
  }
});
</script>

<style scoped>
.cart-panel {
  height: 100%;
  display: flex;
  flex-direction: column;
}
.cart-panel :deep(.q-card__section) {
  flex-shrink: 0;
}
.cart-panel .q-list {
  flex: 1;
  overflow-y: auto;
  max-height: 400px;
}
</style>
