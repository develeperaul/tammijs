<template>
  <q-page class="q-pa-md">
    <div class="text-h4 text-center q-mb-md">Тест API</div>

    <!-- Кнопки -->
    <div class="row q-mb-md justify-center q-gutter-md">
      <q-btn
        label="Загрузить товары"
        color="primary"
        @click="loadProducts"
        :loading="loading"
      />

      <q-btn
        label="Загрузить остатки"
        color="secondary"
        @click="loadStock"
        :loading="stockLoading"
      />
    </div>

    <!-- Статус -->
    <div v-if="status" class="text-center q-mb-md">
      <q-badge :color="statusColor">{{ status }}</q-badge>
    </div>

    <!-- Результат товары -->
    <q-card v-if="products.length" class="q-mb-md">
      <q-card-section>
        <div class="text-h6">Товары ({{ products.length }})</div>
      </q-card-section>

      <q-card-section>
        <q-list bordered separator>
          <q-item v-for="product in products" :key="product.id">
            <q-item-section>
              <q-item-label>{{ product.name }}</q-item-label>
              <q-item-label caption>
                Тип: {{ product.type }} |
                Остаток: {{ product.currentStock }} {{ product.unit }}
              </q-item-label>
            </q-item-section>
          </q-item>
        </q-list>
      </q-card-section>
    </q-card>

    <!-- Результат остатки -->
    <q-card v-if="stock.length">
      <q-card-section>
        <div class="text-h6">Остатки ({{ stock.length }})</div>
      </q-card-section>

      <q-card-section>
        <q-list bordered separator>
          <q-item v-for="item in stock" :key="item.id">
            <q-item-section>
              <q-item-label>{{ item.name }}</q-item-label>
              <q-item-label caption>
                Остаток: {{ item.currentStock }} {{ item.unit }}
                <q-badge
                  :color="item.currentStock <= item.minStock ? 'red' : 'green'"
                  class="q-ml-sm"
                >
                  {{ item.currentStock <= item.minStock ? 'Критично' : 'Норма' }}
                </q-badge>
              </q-item-label>
            </q-item-section>
          </q-item>
        </q-list>
      </q-card-section>
    </q-card>
  </q-page>
</template>

<script lang="ts">
import { defineComponent, ref } from 'vue';
import { productService, Product } from 'src/services/api';

export default defineComponent({
  name: 'ApiTestPage',

  setup() {
    const products = ref<Product[]>([]);
    const stock = ref<Product[]>([]);
    const loading = ref(false);
    const stockLoading = ref(false);
    const status = ref('');
    const statusColor = ref('grey');

    const loadProducts = async () => {
      loading.value = true;
      status.value = 'Загрузка товаров...';
      statusColor.value = 'info';

      try {
        const data = await productService.getAll();
        products.value = data;
        status.value = `Загружено ${data.length} товаров`;
        statusColor.value = 'positive';
      } catch (error: any) {
        status.value = `Ошибка: ${error.message}`;
        statusColor.value = 'negative';
        console.error(error);
      } finally {
        loading.value = false;
      }
    };

    const loadStock = async () => {
      stockLoading.value = true;
      status.value = 'Загрузка остатков...';
      statusColor.value = 'info';

      try {
        const data = await productService.getStock();
        stock.value = data;
        status.value = `Загружено ${data.length} позиций`;
        statusColor.value = 'positive';
      } catch (error: any) {
        status.value = `Ошибка: ${error.message}`;
        statusColor.value = 'negative';
        console.error(error);
      } finally {
        stockLoading.value = false;
      }
    };

    return {
      products,
      stock,
      loading,
      stockLoading,
      status,
      statusColor,
      loadProducts,
      loadStock,
    };
  },
});
</script>
