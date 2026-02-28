<template>
  <q-page class="row q-pa-md">
    <!-- Левая колонка: меню -->
    <div class="col-8 q-pr-md">
      <menu-panel
        :products="menu"
        :categories="categories"
        @add-to-cart="addToCart"
      />
    </div>

    <!-- Правая колонка: корзина -->
    <div class="col-4">
      <cart-panel
        :items="cart"
        @update:items="cart = $event"
        @checkout="openOrderDialog"
      />
    </div>

    <!-- Диалог оформления заказа -->
    <order-dialog
      v-model="orderDialog"
      :items="cart"
      @ok="createOrder"
    />
  </q-page>
</template>

<script lang="ts">
import { defineComponent, ref, onMounted } from 'vue';
import { useQuasar } from 'quasar';
import orderService from 'src/services/order.service';
import productService from 'src/services/product.service';
import recipeService from 'src/services/recipe.service';
import { Product } from 'src/types/product.types';
import { ProductCategory } from 'src/types/product.types';
import MenuPanel from 'components/orders/MenuPanel.vue';
import CartPanel, { CartItem } from 'components/orders/CartPanel.vue';
import OrderDialog from 'components/orders/OrderDialog.vue';

export default defineComponent({
  name: 'SalePage',

  components: {
    MenuPanel,
    CartPanel,
    OrderDialog
  },

  setup() {
    const $q = useQuasar();
    const menu = ref<Product[]>([]);
    const categories = ref<ProductCategory[]>([]);
    const cart = ref<CartItem[]>([]);
    const orderDialog = ref(false);

    const loadData = async () => {
      try {
        menu.value = await orderService.getMenu();
        categories.value = await productService.getCategories();
      } catch (error) {
        $q.notify({ type: 'negative', message: 'Ошибка загрузки меню' });
      }
    };

    const addToCart = (product: Product) => {
  const existing = cart.value.find(item => item.productId === product.id);
  if (existing) {
    existing.quantity += 1;
    cart.value = [...cart.value];
  } else {
    cart.value.push({
      productId: product.id,
      name: product.name,
      originalPrice: product.sellingPrice,
      currentPrice: product.sellingPrice,
      quantity: 1,
      discountPercent: undefined
    });
  }
};

    const openOrderDialog = () => {
      if (cart.value.length === 0) {
        $q.notify({ type: 'warning', message: 'Корзина пуста' });
        return;
      }
      orderDialog.value = true;
    };

    const createOrder = async (orderData: any) => {
      try {
        await orderService.createOrder(orderData);
        $q.notify({ type: 'positive', message: 'Заказ создан' });
        cart.value = []; // очищаем корзину
        orderDialog.value = false;

        for (const item of data.items) {
          const product = menu.find(p => p.id === item.productId);
          if (product && product.type === 'finished') {
            const recipe = await recipeService.getRecipeByProductId(product.id);
            if (recipe) {
              await recipeService.consumeIngredients(recipe.id, item.quantity);
            } else {
              // Если рецепта нет – можно либо игнорировать, либо предупредить
              console.warn(`Рецепт для товара ${product.name} не найден, списание не выполнено`);
            }
          }
        }
      } catch (error: any) {
        $q.notify({ type: 'negative', message: error.message || 'Ошибка' });
      }
    };

    onMounted(() => {
      loadData();
    });

    return {
      menu,
      categories,
      cart,
      orderDialog,
      addToCart,
      openOrderDialog,
      createOrder
    };
  }
});
</script>
