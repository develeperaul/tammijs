<template>
  <div>
    <q-input
      v-model="search"
      outlined
      dense
      placeholder="Поиск блюд..."
      class="q-mb-md"
    >
      <template v-slot:append>
        <q-icon name="search" />
      </template>
    </q-input>

    <q-tabs v-model="categoryTab" dense align="justify" class="q-mb-md">
      <q-tab name="all" label="Все" />
      <q-tab v-for="cat in categories" :key="cat.id" :name="cat.id" :label="cat.name" />
    </q-tabs>

    <div class="row q-col-gutter-sm">
      <div
        v-for="product in filteredProducts"
        :key="product.id"
        class="col-6 col-sm-4 col-md-3"
      >
        <q-card class="cursor-pointer" @click="addToCart(product)">
          <q-card-section class="text-center">
            <div class="text-weight-bold">{{ product.name }}</div>
            <div class="text-caption text-grey">{{ product.sellingPrice }} ₽</div>
          </q-card-section>
        </q-card>
      </div>
    </div>
  </div>
</template>

<script lang="ts">
import { defineComponent, PropType, ref, computed } from 'vue';
import { Product } from 'src/types/product.types';
import { ProductCategory } from 'src/types/product.types';

export default defineComponent({
  name: 'MenuPanel',

  props: {
    products: {
      type: Array as PropType<Product[]>,
      required: true
    },
    categories: {
      type: Array as PropType<ProductCategory[]>,
      default: () => []
    }
  },

  emits: ['add-to-cart'],

  setup(props, { emit }) {
    const search = ref('');
    const categoryTab = ref('all');

    const filteredProducts = computed(() => {
      let filtered = props.products;

      if (search.value) {
        const s = search.value.toLowerCase();
        filtered = filtered.filter(p => p.name.toLowerCase().includes(s));
      }

      if (categoryTab.value !== 'all') {
        filtered = filtered.filter(p => p.categoryId === categoryTab.value);
      }

      return filtered;
    });

    const addToCart = (product: Product) => {
      emit('add-to-cart', product);
    };

    return {
      search,
      categoryTab,
      filteredProducts,
      addToCart
    };
  }
});
</script>
