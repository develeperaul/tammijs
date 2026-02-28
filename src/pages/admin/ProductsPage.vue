<template>
  <q-page class="q-pa-md">
    <!-- Заголовок -->
    <div class="row q-mb-md items-center">
      <div class="col-6">
        <div class="text-h5">Товары и ингредиенты</div>
        <div class="text-caption text-grey-7">
          Всего позиций: {{ products.length }}
          <q-badge
            v-if="lowStockCount > 0"
            :label="`${lowStockCount} критических`"
            color="warning"
            class="q-ml-sm"
          />
        </div>
      </div>
      <div class="col-6 text-right">
        <q-btn
          color="primary"
          label="Добавить товар"
          icon="add"
          @click="openCreateDialog"
        />
      </div>
    </div>

    <!-- Таблица товаров -->
     <product-table
      v-model:search="filters.search"
      v-model:type="filters.type"
      v-model:category="filters.categoryId"
      v-model:lowStock="filters.lowStock"
      :products="filteredProducts"
      :categories="categories"
      :loading="loading"
      @edit="openEditDialog"
      @delete="confirmDelete"
      @refresh="loadProducts"
      @resetFilters="resetFilters"
    />

    <!-- Диалог добавления/редактирования -->
    <product-dialog
      ref="productDialog"
      :product="selectedProduct"
      :categories="categories"
      @ok="saveProduct"
      @hide="selectedProduct = null"
    />
  </q-page>
</template>

<script lang="ts">
import { defineComponent, ref, computed, onMounted } from 'vue';
import { useQuasar } from 'quasar';
import productService from 'src/services/product.service';
import { Product, ProductFilter, ProductCategory } from 'src/types/product.types';
import ProductTable from 'components/products/ProductTable.vue';
import ProductDialog from 'components/products/ProductDialog.vue';

export default defineComponent({
  name: 'ProductsPage',

  components: {
    ProductTable,
    ProductDialog
  },

  setup() {
    const $q = useQuasar();

    // Состояние
    const products = ref<Product[]>([]);
    const categories = ref<ProductCategory[]>([]);
    const loading = ref(false);
    const selectedProduct = ref<Product | null>(null);
    const productDialog = ref<any>(null);

    // Фильтры
    const filters = ref<ProductFilter>({
      search: '',
      type: undefined,
      categoryId: undefined,
      lowStock: false
    });

    // Отфильтрованные товары
    const filteredProducts = computed(() => {
      let filtered = products.value;

      if (filters.value.search) {
        const search = filters.value.search.toLowerCase();
        filtered = filtered.filter(p =>
          p.name.toLowerCase().includes(search) ||
          p.code.toLowerCase().includes(search)
        );
      }

      if (filters.value.type) {
        filtered = filtered.filter(p => p.type === filters.value.type);
      }

      if (filters.value.categoryId) {
        filtered = filtered.filter(p => p.categoryId === filters.value.categoryId);
      }

      if (filters.value.lowStock) {
        filtered = filtered.filter(p => p.currentStock <= p.minStock);
      }

      return filtered;
    });

    // Количество критических остатков
    const lowStockCount = computed(() => {
      return products.value.filter(p => p.currentStock <= p.minStock).length;
    });

    // Загрузка товаров
    const loadProducts = async () => {
      loading.value = true;
      try {
        products.value = (await productService.getProducts()).data;


      } catch (error) {
        $q.notify({
          type: 'negative',
          message: 'Ошибка загрузки товаров'
        });
      } finally {

        loading.value = false;
      }
    };

    // Загрузка категорий
    const loadCategories = async () => {
      try {
        categories.value = await productService.getCategories();
      } catch (error) {
        console.error('Ошибка загрузки категорий:', error);
      }
    };

    // Открыть диалог создания
    const openCreateDialog = () => {
      selectedProduct.value = null;
      setTimeout(() => {
        productDialog.value?.show();
      }, 100);
    };

    // Открыть диалог редактирования
    const openEditDialog = (product: Product) => {
      selectedProduct.value = product;
      setTimeout(() => {
        productDialog.value?.show();
      }, 100);
    };

    // Сохранить товар
    const saveProduct = async (formData: any) => {
      try {
        if (selectedProduct.value) {
          // Обновление
          await productService.updateProduct(selectedProduct.value.id, formData);
          $q.notify({
            type: 'positive',
            message: 'Товар обновлен'
          });
        } else {
          // Создание
          await productService.createProduct(formData);
          $q.notify({
            type: 'positive',
            message: 'Товар добавлен'
          });
        }
        await loadProducts();
      } catch (error) {
        $q.notify({
          type: 'negative',
          message: 'Ошибка сохранения'
        });
      }
    };

    // Подтверждение удаления
    const confirmDelete = (product: Product) => {
      $q.dialog({
        title: 'Подтверждение',
        message: `Удалить товар "${product.name}"?`,
        cancel: true,
        persistent: true
      }).onOk(async () => {
        try {
          await productService.deleteProduct(product.id);
          await loadProducts();
          $q.notify({
            type: 'positive',
            message: 'Товар удален'
          });
        } catch (error) {
          $q.notify({
            type: 'negative',
            message: 'Ошибка удаления'
          });
        }
      });
    };

    onMounted(() => {
      loadProducts();
      loadCategories();
    });
    const resetFilters = () => {
      filters.value = {
        search: '',
        type: undefined,
        categoryId: undefined,
        lowStock: false
      };
    };
    return {
      products,
      categories,
      loading,
      selectedProduct,
      productDialog,
      filters,
      filteredProducts,
      lowStockCount,
      loadProducts,
      openCreateDialog,
      openEditDialog,
      saveProduct,
      confirmDelete,
      resetFilters
    };
  }
});
</script>
