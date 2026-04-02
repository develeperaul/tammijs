<template>
  <q-page class="row q-pa-md">
    <!-- Левая колонка: меню -->
    <div class="col-12 col-md-8 q-pr-md">
      <div class="text-h5 q-mb-md">Меню</div>

      <!-- Поиск -->
      <q-input
        v-model="search"
        outlined
        dense
        placeholder="Поиск блюд..."
        class="q-mb-md"
        clearable
      >
        <template v-slot:append>
          <q-icon name="search" />
        </template>
      </q-input>

      <!-- Режим: показываем категории (с фильтрацией по поиску) -->
      <div v-if="showCategories" class="row q-col-gutter-sm q-mb-md">
        <!-- Кнопка "Все блюда" (показываем всегда, даже если есть поиск) -->
        <div
          v-if="filteredCategories.length > 0 || !search"
          class="col-6 col-sm-4 col-md-3"
        >
          <q-card
            class="category-card cursor-pointer"
            :class="{ active: selectedCategory === 'all' }"
            @click="showAllProducts"
          >
            <q-card-section class="text-center q-pa-md">
              <q-icon name="restaurant_menu" size="32px" class="q-mb-sm" />
              <div class="text-weight-bold">Все блюда</div>
              <div class="text-caption text-grey-7">{{ products.length }}</div>
            </q-card-section>
          </q-card>
        </div>

        <!-- Категории (фильтрованные по поиску) -->
        <div
          v-for="cat in filteredCategories"
          :key="cat.id"
          class="col-6 col-sm-4 col-md-3"
        >
          <q-card
            class="category-card cursor-pointer"
            @click="selectCategory(cat.id)"
          >
            <q-card-section class="text-center q-pa-md">
              <q-icon name="category" size="32px" class="q-mb-sm" />
              <div class="text-weight-bold">{{ cat.name }}</div>
              <div class="text-caption text-grey-7">{{ getCategoryCount(cat.id) }}</div>
            </q-card-section>
          </q-card>
        </div>

        <!-- Сообщение, если по запросу ничего не найдено -->
        <div v-if="search && filteredCategories.length === 0" class="col-12 text-center text-grey-7 q-py-md">
          По запросу "{{ search }}" ничего не найдено
        </div>
      </div>

      <!-- Режим: показываем все товары -->
      <div v-if="selectedCategory === 'all'">
        <!-- Кнопка назад -->
        <div class="row q-mb-md">
          <div class="col-6 col-sm-4 col-md-3">
            <q-card
              class="back-card cursor-pointer"
              @click="goBack"
            >
              <q-card-section class="text-center q-pa-md">
                <q-icon name="arrow_back" size="32px" class="q-mb-sm" />
                <div class="text-weight-bold">Назад</div>
                <div class="text-caption text-grey-7">к категориям</div>
              </q-card-section>
            </q-card>
          </div>
        </div>

        <!-- Все товары с поиском -->
        <div v-if="loading" class="text-center q-pa-md">
          <q-spinner size="40px" color="primary" />
        </div>

        <div v-else-if="filteredAllProducts.length === 0" class="text-center text-grey-7 q-pa-md">
          Ничего не найдено
        </div>

        <div v-else class="row q-col-gutter-sm">
          <div
            v-for="product in filteredAllProducts"
            :key="product.id"
            class="col-6 col-sm-4 col-md-3"
          >
            <product-card :product="product" @add="addToCart" />
          </div>
        </div>
      </div>

      <!-- Режим: показываем товары выбранной категории -->
      <div v-else-if="typeof selectedCategory === 'number'">
        <!-- Кнопка назад -->
        <div class="row q-mb-md">
          <div class="col-6 col-sm-4 col-md-3">
            <q-card
              class="back-card cursor-pointer"
              @click="goBack"
            >
              <q-card-section class="text-center q-pa-md">
                <q-icon name="arrow_back" size="32px" class="q-mb-sm" />
                <div class="text-weight-bold">Назад</div>
                <div class="text-caption text-grey-7">к категориям</div>
              </q-card-section>
            </q-card>
          </div>
        </div>

        <!-- Товары категории с поиском -->
        <div v-if="loading" class="text-center q-pa-md">
          <q-spinner size="40px" color="primary" />
        </div>

        <div v-else-if="filteredCategoryProducts.length === 0" class="text-center text-grey-7 q-pa-md">
          В этой категории пока нет блюд
        </div>

        <div v-else class="row q-col-gutter-sm">
          <div
            v-for="product in filteredCategoryProducts"
            :key="product.id"
            class="col-6 col-sm-4 col-md-3"
          >
            <product-card :product="product" @add="addToCart" />
          </div>
        </div>
      </div>
    </div>

    <!-- Правая колонка: корзина (только на десктопе) -->
    <div class="col-4 q-pl-md gt-sm">
      <cart-panel
        :items="cart"
        @update:items="cart = $event"
        @quick-order="quickOrder"
      />
    </div>

    <!-- Мобильная корзина (попап) -->
    <div class="fixed-bottom q-pa-md lt-md" style="pointer-events: none;">
      <div
        class="mobile-cart-bar"
        :class="{ 'has-items': cart.length > 0 }"
        @click="openMobileCart"
      >
        <div class="row items-center justify-between q-pa-sm">
          <div class="row items-center">
            <q-icon name="shopping_cart" size="24px" />
            <span class="q-ml-sm">{{ cart.length }} товаров</span>
          </div>
          <div class="text-weight-bold">
            {{ formatPrice(totalAmount) }}
          </div>
          <q-btn
            flat
            dense
            icon="keyboard_arrow_up"
            :disable="cart.length === 0"
          />
        </div>
      </div>
    </div>

    <!-- Модальное окно корзины на мобильных -->
    <q-dialog
      v-model="mobileCartDialog"
      full-width
      position="bottom"
      class="lt-md"
    >
      <q-card style="border-radius: 16px 16px 0 0;">
        <q-card-section class="row items-center">
          <div class="text-h6">Корзина</div>
          <q-space />
          <q-btn flat round icon="close" v-close-popup />
        </q-card-section>

        <q-card-section class="q-pa-none">
          <cart-panel
            :items="cart"
            @update:items="cart = $event"
            @quick-order="quickOrder"
          />
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script lang="ts">
import { defineComponent, ref, computed, onMounted } from 'vue';
import { useQuasar } from 'quasar';
import productService from 'src/services/product.service';
import orderService from 'src/services/order.service';
import { Product } from 'src/types/product.types';
import { ProductCategory } from 'src/types/product.types';
import { CartItem } from 'src/types/order.types';
import ProductCard from 'components/pos/ProductCard.vue';
import CartPanel from 'components/pos/CartPanel.vue';

export default defineComponent({
  name: 'SalePage',

  components: { ProductCard, CartPanel },

  setup() {
    const $q = useQuasar();
    const products = ref<Product[]>([]);
    const categories = ref<ProductCategory[]>([]);
    const cart = ref<CartItem[]>([]);
    const search = ref('');
    const selectedCategory = ref<string | number>('categories');
    const loading = ref(false);
    const mobileCartDialog = ref(false);

    const showCategories = computed(() => selectedCategory.value === 'categories');

    const totalAmount = computed(() => {
      return cart.value.reduce((sum, item) => sum + item.price * item.quantity, 0);
    });

    // Фильтрация категорий по поиску (ищем в названиях категорий)
    const filteredCategories = computed(() => {
      if (!search.value) return categories.value;
      const s = search.value.toLowerCase();
      return categories.value.filter(cat =>
        cat.name.toLowerCase().includes(s)
      );
    });

    const getCategoryCount = (categoryId: number) => {
      return products.value.filter(p => p.categoryId === categoryId).length;
    };

    // Все товары с поиском
    const filteredAllProducts = computed(() => {
      let filtered = products.value;
      if (search.value) {
        const s = search.value.toLowerCase();
        filtered = filtered.filter(p => p.name.toLowerCase().includes(s));
      }
      return filtered;
    });

    // Товары выбранной категории с поиском
    const filteredCategoryProducts = computed(() => {
      if (typeof selectedCategory.value !== 'number') return [];
      let filtered = products.value.filter(p => p.categoryId === selectedCategory.value);
      if (search.value) {
        const s = search.value.toLowerCase();
        filtered = filtered.filter(p => p.name.toLowerCase().includes(s));
      }
      return filtered;
    });

    const loadCategories = async () => {
      try {
        const response = await productService.getCategories();
        categories.value = response.data || response;
      } catch (error) {
        console.error('Ошибка загрузки категорий:', error);
      }
    };

    const loadProducts = async () => {
      loading.value = true;
      try {
        const response = await productService.getProducts();
        products.value = response.data
      } catch (error) {
        $q.notify({ type: 'negative', message: 'Ошибка загрузки меню' });
      } finally {
        loading.value = false;
      }
    };

    const showAllProducts = () => {
      selectedCategory.value = 'all';
      search.value = '';
    };

    const selectCategory = (categoryId: number) => {
      selectedCategory.value = categoryId;
      search.value = '';
    };

    const goBack = () => {
      selectedCategory.value = 'categories';
      search.value = '';
    };

    const addToCart = (product: Product) => {
      const existing = cart.value.find(item => item.productId === product.id);
      if (existing) {
        existing.quantity++;
        cart.value = [...cart.value];
      } else {
        cart.value.push({
          productId: product.id,
          name: product.name,
          price: product.sellingPrice,
          quantity: 1
        });
      }
    };

    const openMobileCart = () => {
      if (cart.value.length > 0) {
        mobileCartDialog.value = true;
      }
    };

    const quickOrder = async (orderData: any) => {
      const orderPayload = {
        type: 'dine-in',
        tableNumber: 1,
        paymentMethod: orderData.paymentMethod,
        discount: orderData.discount,
        items: orderData.items.map(item => ({
          productId: item.productId,
          quantity: item.quantity,
          price: item.price
        }))
      };

      try {
        await orderService.createOrder(orderPayload);
        $q.notify({ type: 'positive', message: 'Заказ создан' });
        cart.value = [];
        mobileCartDialog.value = false;
      } catch (error: any) {
        $q.notify({ type: 'negative', message: error.message || 'Ошибка создания заказа' });
      }
    };

    const formatPrice = (value: number) => {
      return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        minimumFractionDigits: 0
      }).format(value);
    };

    onMounted(() => {
      loadProducts();
      loadCategories();
    });

    return {
      products,
      categories,
      cart,
      search,
      selectedCategory,
      showCategories,
      loading,
      mobileCartDialog,
      totalAmount,
      filteredCategories,
      filteredAllProducts,
      filteredCategoryProducts,
      getCategoryCount,
      showAllProducts,
      selectCategory,
      goBack,
      addToCart,
      openMobileCart,
      quickOrder,
      formatPrice
    };
  }
});
</script>

<style scoped>
.category-card {
  transition: all 0.2s ease;
  border: 1px solid #e0e0e0;
  height: 100%;
  display: flex;
  flex-direction: column;
}

.category-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.category-card.active {
  border-color: #1976d2;
  background-color: rgba(25, 118, 210, 0.05);
}

.category-card.active .text-weight-bold {
  color: #1976d2;
}

/* Стили для кнопки "Назад" */
.back-card {
  transition: all 0.2s ease;
  border: 1px solid #4caf50;
  background-color: rgba(76, 175, 80, 0.1);
  height: 100%;
  display: flex;
  flex-direction: column;
}

.back-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(76, 175, 80, 0.2);
  background-color: rgba(76, 175, 80, 0.2);
}

.back-card .text-weight-bold {
  color: #4caf50;
}

/* Мобильная корзина */
.mobile-cart-bar {
  position: fixed;
  bottom: 16px;
  left: 16px;
  right: 16px;
  background-color: #fff;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  cursor: pointer;
  transition: all 0.2s ease;
  pointer-events: auto;
  border: 1px solid #e0e0e0;
}

.mobile-cart-bar.has-items {
  background-color: #1976d2;
  color: white;
}

.mobile-cart-bar.has-items .q-icon {
  color: white;
}

.mobile-cart-bar.has-items .q-btn {
  color: white;
}
</style>
