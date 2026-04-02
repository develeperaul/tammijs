<template>
  <q-page class="q-pa-md">
    <div class="row q-mb-md items-center">
      <div class="col-6">
        <div class="text-h5">Полуфабрикаты</div>
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
      :items="filteredItems"
      :columns="columns"
      :loading="loading"
      entity-name="полуфабрикатов"
      @refresh="loadItems"
      @resetFilters="resetFilters"
      @edit="openEditDialog"
      @delete="confirmDelete"
    >
      <template v-slot:body-cell-cost="props">
        <q-td :props="props">
          <div>{{ formatMoney(props.row.costPrice) }}</div>
          <div class="text-caption text-grey-7">
            из {{ props.row.ingredients?.length || 0 }} ингр.
          </div>
        </q-td>
      </template>

      <template v-slot:body-cell-price="props">
        <q-td :props="props">
          {{ formatMoney(props.row.sellingPrice) }}
        </q-td>
      </template>
    </base-table>

    <semi-finished-dialog
      v-model="dialog"
      :item="selectedItem"
      :ingredients-list="ingredientsList"
      @ok="saveItem"
      @hide="selectedItem = null"
    />
  </q-page>
</template>

<script lang="ts">
import { defineComponent, ref, computed, onMounted } from 'vue';
import { useQuasar } from 'quasar';
import semiFinishedService from 'src/services/semi-finished.service';
import ingredientService from 'src/services/ingredient.service';
import BaseTable from 'components/common/BaseTable.vue';
import SemiFinishedDialog from 'components/semi-finished/SemiFinishedDialog.vue';

export default defineComponent({
  name: 'SemiFinishedPage',

  components: { BaseTable, SemiFinishedDialog },

  setup() {
    const $q = useQuasar();
    const items = ref([]);
    const ingredientsList = ref([]);
    const loading = ref(false);
    const dialog = ref(false);
    const selectedItem = ref(null);

    const filters = ref({
      search: ''
    });

    const columns = [
      { name: 'name', label: 'Название', field: 'name', align: 'left', sortable: true },
      { name: 'unit', label: 'Ед.', field: 'unit', align: 'center' },
      { name: 'cost', label: 'Себестоимость', field: 'costPrice', align: 'right' },
      { name: 'price', label: 'Цена', field: 'sellingPrice', align: 'right' },
      { name: 'actions', label: 'Действия', align: 'center' }
    ];

    const filteredItems = computed(() => {
      let filtered = items.value;
      if (filters.value.search) {
        const s = filters.value.search.toLowerCase();
        filtered = filtered.filter((p: any) => p.name.toLowerCase().includes(s));
      }
      return filtered;
    });

    const loadItems = async () => {
      loading.value = true;
      try {
        items.value = (await semiFinishedService.getAll()).data;
      } catch (error) {
        $q.notify({ type: 'negative', message: 'Ошибка загрузки' });
      } finally {
        loading.value = false;
      }
    };

    const loadIngredients = async () => {
      try {
        ingredientsList.value = (await ingredientService.getAll()).data;
      } catch (error) {
        $q.notify({ type: 'negative', message: 'Ошибка загрузки ингредиентов' });
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
          await semiFinishedService.update(selectedItem.value.id, formData);
          $q.notify({ type: 'positive', message: 'Полуфабрикат обновлён' });
        } else {
          await semiFinishedService.create(formData);
          $q.notify({ type: 'positive', message: 'Полуфабрикат добавлен' });
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
          await semiFinishedService.delete(item.id);
          await loadItems();
          $q.notify({ type: 'positive', message: 'Удалено' });
        } catch (error) {
          $q.notify({ type: 'negative', message: 'Ошибка удаления' });
        }
      });
    };

    const resetFilters = () => {
      filters.value = { search: '' };
    };

    const formatMoney = (value: number) => {
      return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        minimumFractionDigits: 0
      }).format(value);
    };

    onMounted(() => {
      loadItems();
      loadIngredients();
    });

    return {
      items,
      ingredientsList,
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
      resetFilters,
      formatMoney
    };
  }
});
</script>
