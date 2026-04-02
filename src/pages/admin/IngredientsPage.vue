<template>
  <q-page class="q-pa-md">
    <div class="row q-mb-md items-center">
      <div class="col-6">
        <div class="text-h5">Ингредиенты</div>
        <div class="text-caption text-grey-7">
          Всего: {{ items.length }}
        </div>
      </div>
      <div class="col-6 text-right">
        <q-btn
          color="primary"
          label="Добавить"
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

    <base-table
      v-model:search="filters.search"
      v-model:category="filters.categoryId"
      :items="filteredItems"
      :columns="columns"
      :loading="loading"
      entity-name="ингредиентов"
      :categories="categories"
      @refresh="loadItems"
      @resetFilters="resetFilters"
      @edit="openEditDialog"
      @delete="confirmDelete"
    >
      <template v-slot:body-cell-unit="props">
        <q-td :props="props">
          <div>{{ props.row.unit }}</div>
          <div class="text-caption text-grey-7">
            база: {{ props.row.baseUnit }} (1:{{ props.row.baseRatio }})
          </div>
        </q-td>
      </template>
    </base-table>

    <ingredient-dialog
      v-model="dialog"
      :item="selectedItem"
      :categories="categories"
      @ok="saveItem"
      @hide="selectedItem = null"
    />

    <!-- Диалог управления категориями ингредиентов -->
    <q-dialog v-model="categoryDialog" maximized>
      <q-card>
        <q-card-section class="row items-center">
          <div class="text-h6">Категории ингредиентов</div>
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
            :rows="categoryItems"
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
import { defineComponent, ref, computed, onMounted } from 'vue';
import { useQuasar } from 'quasar';
import ingredientService from 'src/services/ingredient.service';
import ingredientCategoryService from 'src/services/ingredientCategory.service';
import BaseTable from 'components/common/BaseTable.vue';
import IngredientDialog from 'components/ingredient/IngredientDialog.vue';
import CategoryDialog from 'components/categories/CategoryDialog.vue'; // переиспользуем

export default defineComponent({
  name: 'IngredientsPage',

  components: { BaseTable, IngredientDialog, CategoryDialog },

  setup() {
    const $q = useQuasar();
    const items = ref([]);
    const categories = ref([]); // категории для фильтра
    const loading = ref(false);
    const dialog = ref(false);
    const selectedItem = ref(null);

    // Для управления категориями
    const categoryDialog = ref(false);
    const categoryFormDialog = ref(false);
    const selectedCategory = ref(null);
    const categoryItems = ref([]); // список категорий

    const filters = ref({
      search: '',
      categoryId: null
    });

    const columns = [
      { name: 'name', label: 'Название', field: 'name', align: 'left', sortable: true },
      { name: 'unit', label: 'Единицы', field: 'unit', align: 'left' },
      { name: 'costPrice', label: 'Цена закупа', field: 'costPrice', align: 'right' },
      { name: 'currentStock', label: 'Остаток', field: 'currentStock', align: 'center' },
      { name: 'minStock', label: 'Мин.', field: 'minStock', align: 'center' },
      { name: 'actions', label: 'Действия', align: 'center' }
    ];

    const categoryColumns = [
      { name: 'name', label: 'Название', field: 'name', align: 'left', sortable: true },
      { name: 'sortOrder', label: 'Сортировка', field: 'sortOrder', align: 'center' },
      { name: 'actions', label: 'Действия', align: 'center' }
    ];

    const filteredItems = computed(() => {
      let filtered = items.value;

      if (filters.value.search) {
        const s = filters.value.search.toLowerCase();
        filtered = filtered.filter((p: any) => p.name.toLowerCase().includes(s));
      }

      if (filters.value.categoryId) {
        filtered = filtered.filter((p: any) => p.categoryId === filters.value.categoryId);
      }

      return filtered;
    });

    const loadItems = async () => {
      loading.value = true;
      try {
        items.value = (await ingredientService.getAll()).data;
      } catch (error) {
        $q.notify({ type: 'negative', message: 'Ошибка загрузки' });
      } finally {
        loading.value = false;
      }
    };

    const loadCategories = async () => {
      try {
        categories.value = (await ingredientCategoryService.getAll()).data;
      } catch (error) {
        console.error('Ошибка загрузки категорий:', error);
      }
    };

    const loadCategoryItems = async () => {
      try {
        categoryItems.value = (await ingredientCategoryService.getAll()).data;
      } catch (error) {
        $q.notify({ type: 'negative', message: 'Ошибка загрузки категорий' });
      }
    };

    const openCreateDialog = () => {
      selectedItem.value = null;
      dialog.value = true;
    };

    const openEditDialog = (item: any) => {
      selectedItem.value = item;
      dialog.value = true;
    };

    const saveItem = async (formData: any) => {
      try {
        if (selectedItem.value) {
          await ingredientService.update(selectedItem.value.id, formData);
          $q.notify({ type: 'positive', message: 'Ингредиент обновлён' });
        } else {
          await ingredientService.create(formData);
          $q.notify({ type: 'positive', message: 'Ингредиент добавлен' });
        }
        await loadItems();
        dialog.value = false;
      } catch (error) {
        $q.notify({ type: 'negative', message: 'Ошибка сохранения' });
      }
    };

    const confirmDelete = (item: any) => {
      $q.dialog({
        title: 'Подтверждение',
        message: `Удалить "${item.name}"?`,
        cancel: true
      }).onOk(async () => {
        try {
          await ingredientService.delete(item.id);
          await loadItems();
          $q.notify({ type: 'positive', message: 'Удалено' });
        } catch (error) {
          $q.notify({ type: 'negative', message: 'Ошибка удаления' });
        }
      });
    };

    const resetFilters = () => {
      filters.value = { search: '', categoryId: null };
    };

    // Управление категориями
    const openCategoryManager = () => {
      loadCategoryItems();
      categoryDialog.value = true;
    };

    const openCreateCategoryDialog = () => {
      selectedCategory.value = null;
      categoryFormDialog.value = true;
    };

    const openEditCategoryDialog = (cat: any) => {
      selectedCategory.value = cat;
      categoryFormDialog.value = true;
    };

    const saveCategory = async (formData: any) => {
      try {
        if (selectedCategory.value) {
          await ingredientCategoryService.update(selectedCategory.value.id, formData);
          $q.notify({ type: 'positive', message: 'Категория обновлена' });
        } else {
          await ingredientCategoryService.create(formData);
          $q.notify({ type: 'positive', message: 'Категория добавлена' });
        }
        await loadCategoryItems();
        await loadCategories(); // обновить и фильтр
        categoryFormDialog.value = false;
      } catch (error) {
        $q.notify({ type: 'negative', message: 'Ошибка сохранения' });
      }
    };

    const confirmDeleteCategory = (cat: any) => {
      $q.dialog({
        title: 'Подтверждение',
        message: `Удалить категорию "${cat.name}"?`,
        cancel: true
      }).onOk(async () => {
        try {
          await ingredientCategoryService.delete(cat.id);
          await loadCategoryItems();
          await loadCategories();
          $q.notify({ type: 'positive', message: 'Категория удалена' });
        } catch (error) {
          $q.notify({ type: 'negative', message: error.message || 'Ошибка удаления' });
        }
      });
    };

    onMounted(() => {
      loadItems();
      loadCategories();
    });

    return {
      items,
      categories,
      loading,
      dialog,
      selectedItem,
      filters,
      filteredItems,
      columns,
      categoryDialog,
      categoryFormDialog,
      selectedCategory,
      categoryItems,
      categoryColumns,
      openCreateDialog,
      openEditDialog,
      saveItem,
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
