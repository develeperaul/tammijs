<template>
  <q-card class="product-card cursor-pointer" @click="$emit('add', product)">
    <q-card-section class="text-center q-pa-sm">
      <div class="text-h6">{{ product.name }}</div>
      <div class="text-caption text-grey-7">{{ formatPrice(product.sellingPrice) }}</div>
    </q-card-section>
  </q-card>
</template>

<script lang="ts">
import { defineComponent, PropType } from 'vue';
import { Product } from 'src/types/product.types';

export default defineComponent({
  name: 'ProductCard',

  props: {
    product: { type: Object as PropType<Product>, required: true }
  },

  emits: ['add'],

  setup() {
    const formatPrice = (value: number | undefined) => {
      if (!value && value !== 0) return 'Цена не указана';
      return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        minimumFractionDigits: 0
      }).format(value);
    };

    return { formatPrice };
  }
});
</script>
