<template>
  <q-page class="q-pa-md">
    <!-- Заголовок -->
    <div class="row q-mb-md items-center">
      <div class="col-6">
        <div class="text-h5">Товары</div>
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
          class="q-mr-sm"
        />
        <q-btn
          color="secondary"
          label="Категории"
          icon="category"
          @click="openCategoryManager"
        />
      </div>
    </div>

    <!-- Вкладки для разделения типов товаров -->
    <q-tabs
      v-model="activeTab"
      dense
      class="text-primary q-mb-md"
      @update:model-value="onTabChange"
    >
      <q-tab name="all" label="Все товары" />
      <q-tab name="produced" label="Готовые блюда" />
      <q-tab name="resale" label="Товары перепродажи" />
    </q-tabs>

    <!-- Таблица товаров -->
    <product-table
      v-model:search="filters.search"
      v-model:category="filters.categoryId"
      v-model:lowStock="filters.lowStock"
      :products="filteredProducts"
      :categories="categories"
      :loading="loading"
      :show-type-filter="false"
      @edit="openEditDialog"
      @delete="confirmDelete"
      @refresh="loadProducts"
      @resetFilters="resetFilters"
    />

    <!-- Диалог добавления/редактирования товара -->
    <product-dialog
      ref="productDialog"
      :product="selectedProduct"
      :categories="categories"
      @ok="saveProduct"
      @hide="selectedProduct = null"
    />

    <!-- Диалог управления категориями -->
    <q-dialog v-model="categoryDialog" maximized>
      <q-card>
        <q-card-section class="row items-center">
          <div class="text-h6">Управление категориями</div>
          <q-space />
          <q-btn flat round icon="close" v-close-popup />
        </q-card-section>

        <q-card-section>
          <div class="row q-mb-md">
            <q-btn
              color="primary"
              label="Добавить категорию"
              icon="add"
              @click="openCreateCategoryDialog"
            />
          </div>

          <q-table
            :rows="categories"
            :columns="categoryColumns"
            row-key="id"
            flat
            bordered
          >
            <template v-slot:body-cell-actions="props">
              <q-td :props="props">
                <q-btn
                  flat
                  round
                  dense
                  icon="edit"
                  @click="openEditCategoryDialog(props.row)"
                />
                <q-btn
                  flat
                  round
                  dense
                  icon="delete"
                  color="negative"
                  @click="confirmDeleteCategory(props.row)"
                />
              </q-td>
            </template>
          </q-table>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Диалог добавления/редактирования категории -->
    <category-dialog
      v-model="categoryFormDialog"
      :category="selectedCategory"
      @ok="saveCategory"
      @hide="selectedCategory = null"
    />
  </q-page>
</template>

<script lang="ts">
import { defineComponent, ref, computed, onMounted, watch } from 'vue';
import { useQuasar } from 'quasar';
import productService from 'src/services/product.service';
import { Product, ProductFilter, ProductCategory } from 'src/types/product.types';
import ProductTable from 'components/products/ProductTable.vue';
import ProductDialog from 'components/products/ProductDialog.vue';
import CategoryDialog from 'components/categories/CategoryDialog.vue';

export default defineComponent({
  name: 'ProductsPage',

  components: {
    ProductTable,
    ProductDialog,
    CategoryDialog
  },

  setup() {
    const $q = useQuasar();

    // Состояние
    const products = ref<Product[]>([]);
    const categories = ref<ProductCategory[]>([]);
    const loading = ref(false);
    const selectedProduct = ref<Product | null>(null);
    const productDialog = ref<any>(null);
    const activeTab = ref<string>('all');

    // Состояние для категорий
    const categoryDialog = ref(false);
    const categoryFormDialog = ref(false);
    const selectedCategory = ref<ProductCategory | null>(null);

    // Фильтры
    const filters = ref<ProductFilter>({
      search: '',
      categoryId: undefined,
      lowStock: false
    });

    const categoryColumns = [
      { name: 'name', label: 'Название', field: 'name', align: 'left', sortable: true },
      { name: 'sortOrder', label: 'Сортировка', field: 'sortOrder', align: 'center', sortable: true },
      { name: 'actions', label: 'Действия', align: 'center' }
    ];

    // Загрузка товаров с учётом выбранной вкладки
    const loadProducts = async () => {
      loading.value = true;
      try {
        const type = activeTab.value === 'all' ? 'all' : activeTab.value as 'produced' | 'resale';
        products.value = (await productService.getProducts(type, filters.value)).data;
      } catch (error) {
        $q.notify({ type: 'negative', message: 'Ошибка загрузки товаров' });
      } finally {
        loading.value = false;
      }
    };

    // Загрузка категорий
    const loadCategories = async () => {
      try {
        categories.value = (await productService.getCategories()).data;
      } catch (error) {
        console.error('Ошибка загрузки категорий:', error);
      }
    };

    // Отфильтрованные товары (дополнительная фильтрация на фронте)
    const filteredProducts = computed(() => {
      let filtered = products.value;

      if (filters.value.search) {
        const search = filters.value.search.toLowerCase();
        filtered = filtered.filter(p =>
          p.name.toLowerCase().includes(search) ||
          p.code?.toLowerCase().includes(search)
        );
      }

      if (filters.value.categoryId) {
        filtered = filtered.filter(p => p.categoryId === filters.value.categoryId);
      }

      if (filters.value.lowStock) {
        filtered = filtered.filter(p =>
          p.currentStock !== undefined && p.minStock !== undefined &&
          p.currentStock <= p.minStock
        );
      }

      return filtered;
    });

    // Количество критических остатков
    const lowStockCount = computed(() => {
      return products.value.filter(p =>
        p.currentStock !== undefined && p.minStock !== undefined &&
        p.currentStock <= p.minStock
      ).length;
    });

    // Смена вкладки
    const onTabChange = () => {
      filters.value.categoryId = undefined;
      loadProducts();
    };

    // Сброс фильтров
    const resetFilters = () => {
      filters.value = {
        search: '',
        categoryId: undefined,
        lowStock: false
      };
    };

    // Открыть диалог создания товара
    const openCreateDialog = () => {
      selectedProduct.value = null;
      setTimeout(() => {
        productDialog.value?.show();
      }, 100);
    };

    // Открыть диалог редактирования товара
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
          await productService.updateProduct(selectedProduct.value.id, formData);
          $q.notify({ type: 'positive', message: 'Товар обновлен' });
        } else {
          await productService.createProduct(formData);
          $q.notify({ type: 'positive', message: 'Товар добавлен' });
        }
        await loadProducts();
      } catch (error) {
        $q.notify({ type: 'negative', message: 'Ошибка сохранения' });
      }
    };

    // Подтверждение удаления товара
    const confirmDelete = (product: Product) => {
      $q.dialog({
        title: 'Подтверждение',
        message: `Удалить товар "${product.name}"?`,
        cancel: true
      }).onOk(async () => {
        try {
          await productService.deleteProduct(product.id);
          await loadProducts();
          $q.notify({ type: 'positive', message: 'Товар удален' });
        } catch (error) {
          $q.notify({ type: 'negative', message: 'Ошибка удаления' });
        }
      });
    };

    // Управление категориями
    const openCategoryManager = () => {
      categoryDialog.value = true;
    };

    const openCreateCategoryDialog = () => {
      selectedCategory.value = null;
      categoryFormDialog.value = true;
    };

    const openEditCategoryDialog = (category: ProductCategory) => {
      selectedCategory.value = category;
      categoryFormDialog.value = true;
    };

    const saveCategory = async (formData: any) => {
      try {
        if (selectedCategory.value) {
          await productService.updateCategory(selectedCategory.value.id, formData);
          $q.notify({ type: 'positive', message: 'Категория обновлена' });
        } else {
          await productService.createCategory(formData);
          $q.notify({ type: 'positive', message: 'Категория добавлена' });
        }
        await loadCategories();
        categoryFormDialog.value = false;
      } catch (error) {
        $q.notify({ type: 'negative', message: 'Ошибка сохранения категории' });
      }
    };

    const confirmDeleteCategory = (category: ProductCategory) => {
      $q.dialog({
        title: 'Подтверждение',
        message: `Удалить категорию "${category.name}"?`,
        cancel: true
      }).onOk(async () => {
        try {
          await productService.deleteCategory(category.id);
          await loadCategories();
          $q.notify({ type: 'positive', message: 'Категория удалена' });
        } catch (error) {
          $q.notify({ type: 'negative', message: 'Ошибка удаления категории' });
        }
      });
    };

    onMounted(() => {
      loadProducts();
      loadCategories();
    });

    return {
      products,
      categories,
      loading,
      selectedProduct,
      productDialog,
      filters,
      filteredProducts,
      lowStockCount,
      activeTab,
      categoryDialog,
      categoryFormDialog,
      selectedCategory,
      categoryColumns,
      onTabChange,
      loadProducts,
      openCreateDialog,
      openEditDialog,
      saveProduct,
      confirmDelete,
      resetFilters,
      openCategoryManager,
      openCreateCategoryDialog,
      openEditCategoryDialog,
      saveCategory,
      confirmDeleteCategory
    };
  }
});
</script>
