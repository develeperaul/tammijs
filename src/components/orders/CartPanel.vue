<template>
  <q-card flat bordered>
    <q-card-section>
      <div class="text-h6">Корзина</div>
    </q-card-section>

    <q-card-section v-if="items.length === 0" class="text-center text-grey-7">
      Корзина пуста
    </q-card-section>

    <q-card-section v-else>
      <q-list bordered separator>
        <q-item v-for="(item, index) in items" :key="index">
          <q-item-section>
            <q-item-label>{{ item.name }}</q-item-label>
            <q-item-label caption>
              <div class="row items-center q-gutter-xs">
                <!-- Редактирование цены -->
                <q-input
                  v-model.number="item.currentPrice"
                  type="number"
                  dense
                  outlined
                  style="width: 90px"
                  label="Цена"
                  @update:model-value="updateItem(index, { currentPrice: $event })"
                />
                <span class="q-mx-xs">×</span>
                <q-input
                  v-model.number="item.quantity"
                  type="number"
                  dense
                  outlined
                  style="width: 70px"
                  label="Кол-во"
                  min="1"
                  @update:model-value="updateItem(index, { quantity: $event })"
                />
                <!-- Скидка в процентах -->
                <q-input
                  v-model.number="item.discountPercent"
                  type="number"
                  dense
                  outlined
                  style="width: 80px"
                  label="Скидка %"
                  min="0"
                  max="100"
                  @update:model-value="applyPercentDiscount(index, $event)"
                />
                <q-btn
                  flat
                  dense
                  round
                  icon="delete"
                  color="negative"
                  size="sm"
                  @click="removeItem(index)"
                />
              </div>
            </q-item-label>
          </q-item-section>
          <q-item-section side>
            <div class="text-weight-bold">{{ item.currentPrice * item.quantity }} ₽</div>
            <div v-if="item.currentPrice !== item.originalPrice" class="text-caption text-grey">
              Было {{ item.originalPrice }} ₽
            </div>
          </q-item-section>
        </q-item>
      </q-list>
    </q-card-section>

    <q-card-section v-if="items.length > 0">
      <div class="row justify-between text-weight-bold">
        <span>Подытог:</span>
        <span>{{ subtotal }} ₽</span>
      </div>
    </q-card-section>

    <q-card-actions align="right">
      <q-btn
        flat
        label="Оформить заказ"
        color="positive"
        :disable="items.length === 0"
        @click="$emit('checkout')"
      />
    </q-card-actions>
  </q-card>
</template>

<script lang="ts">
import { defineComponent, PropType, computed } from 'vue';
import { CartItem } from 'src/types/cart.types';

export default defineComponent({
  name: 'CartPanel',

  props: {
    items: {
      type: Array as PropType<CartItem[]>,
      required: true
    }
  },

  emits: ['update:items', 'checkout'],

  setup(props, { emit }) {
    const subtotal = computed(() => {
      return props.items.reduce((sum, item) => sum + item.currentPrice * item.quantity, 0);
    });

    const updateItem = (index: number, changes: Partial<CartItem>) => {
      const updated = [...props.items];
      updated[index] = { ...updated[index], ...changes };
      emit('update:items', updated);
    };

    const applyPercentDiscount = (index: number, percent: number | undefined) => {
      if (percent === undefined || percent < 0 || percent > 100) return;
      const item = props.items[index];
      const newPrice = item.originalPrice * (1 - percent / 100);
      updateItem(index, { discountPercent: percent, currentPrice: Math.round(newPrice * 100) / 100 });
    };

    const removeItem = (index: number) => {
      const updated = props.items.filter((_, i) => i !== index);
      emit('update:items', updated);
    };

    return {
      subtotal,
      updateItem,
      applyPercentDiscount,
      removeItem
    };
  }
});
</script>
