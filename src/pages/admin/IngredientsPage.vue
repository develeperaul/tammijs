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
        <q-btn color="primary" label="Добавить" icon="add" @click="openCreateDialog" />
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
  </q-page>
</template>

<script lang="ts">
import { defineComponent, ref, computed, onMounted } from 'vue';
import { useQuasar } from 'quasar';
import ingredientService from 'src/services/ingredient.service';
import productService from 'src/services/product.service';
import BaseTable from 'components/common/BaseTable.vue';
import IngredientDialog from 'components/ingredient/IngredientDialog.vue';

export default defineComponent({
  name: 'IngredientsPage',

  components: { BaseTable, IngredientDialog },

  setup() {
    const $q = useQuasar();
    const items = ref([]);
    const categories = ref([]);
    const loading = ref(false);
    const dialog = ref(false);
    const selectedItem = ref(null);

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
        categories.value = (await productService.getCategories()).data;
      } catch (error) {
        console.error('Ошибка загрузки категорий:', error);
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
      loadItems,
      openCreateDialog,
      openEditDialog,
      saveItem,
      confirmDelete,
      resetFilters
    };
  }
});
</script>
