<template>
  <q-page class="q-pa-md">
    <!-- Заголовок -->
    <div class="row q-mb-md items-center">
      <div class="col-6">
        <div class="text-h5">Поставщики</div>
        <div class="text-caption text-grey-7">
          Всего поставщиков: {{ suppliers.length }}
        </div>
      </div>
      <div class="col-6 text-right">
        <q-btn
          color="primary"
          label="Добавить поставщика"
          icon="add"
          @click="openCreateDialog"
        />
      </div>
    </div>

    <!-- Таблица поставщиков -->
    <q-table
      :rows="suppliers"
      :columns="columns"
      :loading="loading"
      row-key="id"
      flat
      bordered
    >
      <!-- Действия -->
      <template v-slot:body-cell-actions="props">
        <q-td :props="props">
          <q-btn
            flat
            round
            dense
            icon="edit"
            @click="openEditDialog(props.row)"
          >
            <q-tooltip>Редактировать</q-tooltip>
          </q-btn>
          <q-btn
            flat
            round
            dense
            icon="delete"
            color="negative"
            @click="confirmDelete(props.row)"
          >
            <q-tooltip>Удалить</q-tooltip>
          </q-btn>
          <q-btn
            flat
            round
            dense
            icon="history"
            color="info"
            @click="openPriceHistory(props.row)"
          >
            <q-tooltip>История цен</q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </q-table>

    <!-- Диалог добавления/редактирования -->
    <supplier-dialog
      v-model="dialog"
      :supplier="selectedSupplier"
      @ok="saveSupplier"
      @hide="selectedSupplier = null"
    />

    <!-- Диалог истории цен (пока заглушка) -->
    <q-dialog v-model="historyDialog">
      <q-card style="min-width: 80vw; max-width: 90vw;">
        <q-card-section>
          <div class="text-h6">История цен: {{ selectedSupplier?.name }}</div>
        </q-card-section>
        <q-card-section>
          <div class="text-center text-grey-7 q-pa-md">
            <price-history-chart
              v-model="historyDialog"
              :supplier="selectedSupplier"
            />
          </div>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Закрыть" color="primary" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script lang="ts">
import { defineComponent, ref, onMounted } from 'vue';
import { useQuasar } from 'quasar';
import supplierService from 'src/services/supplier.service';
import { Supplier } from 'src/types/supplier.types';
import SupplierDialog from 'components/suppliers/SupplierDialog.vue';
import PriceHistoryChart from 'components/suppliers/PriceHistoryChart.vue';

export default defineComponent({
  name: 'SuppliersPage',

  components: { SupplierDialog,PriceHistoryChart },

  setup() {
    const $q = useQuasar();
    const suppliers = ref<Supplier[]>([]);
    const loading = ref(false);
    const dialog = ref(false);
    const historyDialog = ref(false);
    const selectedSupplier = ref<Supplier | null>(null);

    const columns = [
      { name: 'name', label: 'Название', field: 'name', align: 'left', sortable: true },
      { name: 'phone', label: 'Телефон', field: 'phone', align: 'left' },
      { name: 'email', label: 'Email', field: 'email', align: 'left' },
      { name: 'inn', label: 'ИНН', field: 'inn', align: 'center' },
      { name: 'kpp', label: 'КПП', field: 'kpp', align: 'center' },
      { name: 'actions', label: 'Действия', align: 'center' }
    ];

    const loadSuppliers = async () => {
      loading.value = true;
      try {
        suppliers.value = (await supplierService.getSuppliers()).data;
      } catch (error) {
        $q.notify({ type: 'negative', message: 'Ошибка загрузки поставщиков' });
      } finally {
        loading.value = false;
      }
    };

    const openCreateDialog = () => {
      selectedSupplier.value = null;
      dialog.value = true;
    };

    const openEditDialog = (supplier: Supplier) => {
      selectedSupplier.value = supplier;
      dialog.value = true;
    };

    const saveSupplier = async (formData: any) => {
      try {
        if (selectedSupplier.value) {
          await supplierService.updateSupplier(selectedSupplier.value.id, formData);
          $q.notify({ type: 'positive', message: 'Поставщик обновлён' });
        } else {
          await supplierService.createSupplier(formData);
          $q.notify({ type: 'positive', message: 'Поставщик добавлен' });
        }
        await loadSuppliers();
        dialog.value = false;
      } catch (error) {
        $q.notify({ type: 'negative', message: 'Ошибка сохранения' });
      }
    };

    const confirmDelete = (supplier: Supplier) => {
      $q.dialog({
        title: 'Подтверждение',
        message: `Удалить поставщика "${supplier.name}"?`,
        cancel: true,
        persistent: true
      }).onOk(async () => {
        try {
          await supplierService.deleteSupplier(supplier.id);
          await loadSuppliers();
          $q.notify({ type: 'positive', message: 'Поставщик удалён' });
        } catch (error) {
          $q.notify({ type: 'negative', message: 'Ошибка удаления' });
        }
      });
    };

    const openPriceHistory = (supplier: Supplier) => {
      selectedSupplier.value = supplier;
      historyDialog.value = true;
    };

    onMounted(() => {
      loadSuppliers();
    });

    return {
      suppliers,
      loading,
      columns,
      dialog,
      historyDialog,
      selectedSupplier,
      openCreateDialog,
      openEditDialog,
      saveSupplier,
      confirmDelete,
      openPriceHistory
    };
  }
});
</script>
