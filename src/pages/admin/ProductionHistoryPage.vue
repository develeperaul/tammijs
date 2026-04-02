<template>
  <q-page class="q-pa-md">
    <div class="row q-mb-md items-center">
      <div class="col-6">
        <div class="text-h5">Журнал производства</div>
      </div>
      <div class="col-6 text-right">
        <q-btn flat icon="refresh" @click="loadHistory" :loading="loading" />
      </div>
    </div>

    <!-- Фильтры -->
    <div class="row q-mb-md q-gutter-sm">
      <q-select
        v-model="filter.semiFinishedId"
        :options="semiFinishedList"
        option-label="name"
        option-value="id"
        label="Полуфабрикат"
        outlined
        dense
        clearable
        emit-value
        map-options
        class="col-3"
        use-input
        @filter="filterSemi"
      />
      <q-input
        v-model="filter.dateFrom"
        label="Дата с"
        outlined
        dense
        mask="##.##.####"
        fill-mask
        class="col-2"
      >
        <template v-slot:append>
          <q-icon name="event" class="cursor-pointer">
            <q-popup-proxy>
              <q-date v-model="filter.dateFrom" mask="DD.MM.YYYY" />
            </q-popup-proxy>
          </q-icon>
        </template>
      </q-input>
      <q-input
        v-model="filter.dateTo"
        label="Дата по"
        outlined
        dense
        mask="##.##.####"
        fill-mask
        class="col-2"
      >
        <template v-slot:append>
          <q-icon name="event" class="cursor-pointer">
            <q-popup-proxy>
              <q-date v-model="filter.dateTo" mask="DD.MM.YYYY" />
            </q-popup-proxy>
          </q-icon>
        </template>
      </q-input>
      <q-btn flat icon="search" @click="loadHistory" />
      <q-btn flat icon="clear" @click="resetFilters" />
    </div>

    <!-- Загрузка -->
    <div v-if="loading" class="text-center q-pa-md">
      <q-spinner size="50px" color="primary" />
    </div>

    <!-- Группировка по дням -->
    <div v-else-if="Object.keys(groupedHistory).length === 0" class="text-center q-pa-md text-grey-7">
      Нет записей за выбранный период
    </div>

    <div v-else>
      <div v-for="(group, date) in groupedHistory" :key="date" class="q-mb-md">
        <q-card flat bordered>
          <q-card-section class="bg-grey-2">
            <div class="text-h6">{{ date }}</div>
          </q-card-section>
          <q-card-section>
            <q-list bordered separator>
              <q-item v-for="item in group" :key="item.id">
                <q-item-section>
                  <q-item-label>{{ item.PRODUCT_NAME }}</q-item-label>
                  <q-item-label caption>
                    Количество: {{ item.UF_QUANTITY }} {{ getProductUnit(item.UF_PRODUCT_ID) }} |
                    Себестоимость: {{ formatMoney(item.UF_PRICE) }}
                  </q-item-label>
                </q-item-section>
                <q-item-section side>
                  <q-btn
                    flat
                    round
                    dense
                    icon="edit"
                    color="primary"
                    @click="openEditDialog(item)"
                  >
                    <q-tooltip>Редактировать количество</q-tooltip>
                  </q-btn>
                  <q-btn
                    flat
                    round
                    dense
                    icon="undo"
                    color="warning"
                    @click="confirmRevert(item)"
                  >
                    <q-tooltip>Отменить производство</q-tooltip>
                  </q-btn>
                </q-item-section>
              </q-item>
            </q-list>
          </q-card-section>
        </q-card>
      </div>

      <!-- Пагинация -->
      <div class="row justify-center q-mt-md">
        <q-pagination
          v-model="pagination.page"
          :max="Math.ceil(pagination.rowsNumber / pagination.rowsPerPage)"
          :max-pages="6"
          boundary-numbers
          direction-links
          @update:model-value="loadHistory"
        />
      </div>
    </div>
  </q-page>
</template>

<script lang="ts">
import { defineComponent, ref, onMounted, computed } from 'vue';
import { useQuasar } from 'quasar';
import { api } from 'boot/axios';
import dayjs from 'dayjs';
import semiFinishedService from 'src/services/semi-finished.service';

export default defineComponent({
  name: 'ProductionHistoryPage',

  setup() {
    const $q = useQuasar();
    const history = ref<any[]>([]);
    const loading = ref(false);
    const semiFinishedList = ref<any[]>([]);
    const filteredSemiOptions = ref<any[]>([]);

    const filter = ref({
      semiFinishedId: null as number | null,
      dateFrom: '',
      dateTo: ''
    });

    const pagination = ref({
      sortBy: 'createdAt',
      descending: true,
      page: 1,
      rowsPerPage: 20,
      rowsNumber: 0
    });

    const columns = [
      { name: 'createdAt', label: 'Дата', field: 'UF_CREATED_AT', align: 'center', sortable: true },
      { name: 'productName', label: 'Полуфабрикат', field: 'PRODUCT_NAME', align: 'left' },
      { name: 'quantity', label: 'Количество', field: 'UF_QUANTITY', align: 'center' },
      { name: 'costPrice', label: 'Себестоимость', field: 'UF_PRICE', align: 'right' },
      { name: 'comment', label: 'Комментарий', field: 'UF_COMMENT', align: 'left' },
      { name: 'actions', label: 'Действия', align: 'center' }
    ];

    // Группировка по датам
    const groupedHistory = computed(() => {
      const groups: Record<string, any[]> = {};

      history.value.forEach(item => {
        const date = dayjs(item.UF_CREATED_AT).format('DD.MM.YYYY');
        if (!groups[date]) groups[date] = [];
        groups[date].push(item);
      });
      return groups;
    });

    const loadSemiFinished = async () => {
      semiFinishedList.value = (await semiFinishedService.getAll()).data;
      filteredSemiOptions.value = semiFinishedList.value;
    };

    const filterSemi = (val: string, update: any) => {
      update(() => {
        const needle = val.toLowerCase();
        filteredSemiOptions.value = semiFinishedList.value.filter(
          (item: any) => item.name.toLowerCase().includes(needle)
        );
      });
    };

    const loadHistory = async () => {
      loading.value = true;
      try {
        const params: any = {
          action: 'production.history',
          limit: pagination.value.rowsPerPage,
          offset: (pagination.value.page - 1) * pagination.value.rowsPerPage
        };
        if (filter.value.semiFinishedId) params.semiFinishedId = filter.value.semiFinishedId;
        if (filter.value.dateFrom) {
          const [d, m, y] = filter.value.dateFrom.split('.');
          params.dateFrom = `${y}-${m}-${d}`;
        }
        if (filter.value.dateTo) {
          const [d, m, y] = filter.value.dateTo.split('.');
          params.dateTo = `${y}-${m}-${d}`;
        }

        const response = await api.get('/index.php', { params });
        history.value = response.data.data.data;
        pagination.value.rowsNumber = response.data.total;
      } catch (error) {
        $q.notify({ type: 'negative', message: 'Ошибка загрузки истории' });
      } finally {
        loading.value = false;
      }
    };

    const onRequest = (props: any) => {
      pagination.value.page = props.pagination.page;
      pagination.value.rowsPerPage = props.pagination.rowsPerPage;
      loadHistory();
    };

    const resetFilters = () => {
      filter.value = { semiFinishedId: null, dateFrom: '', dateTo: '' };
      pagination.value.page = 1;
      loadHistory();
    };

    const getProductUnit = (productId: number) => {
      const product = semiFinishedList.value.find((p: any) => p.id === productId);
      return product?.unit || '';
    };

    const formatDate = (date: string) => dayjs(date).format('DD.MM.YYYY HH:mm');
    const formatMoney = (val: number) =>
      new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB' }).format(val);

    // Редактирование количества
    const openEditDialog = (item: any) => {
      $q.dialog({
        title: 'Редактирование производства',
        message: `Изменить количество для "${item.PRODUCT_NAME}"?`,
        prompt: {
          model: item.UF_QUANTITY,
          type: 'number',
          label: 'Новое количество',
          attributes: {
            step: 0.001,
            min: 0.001
          }
        },
        cancel: true,
        persistent: true
      }).onOk(async (newQuantity) => {
        if (newQuantity === item.UF_QUANTITY) {
          $q.notify({ type: 'info', message: 'Количество не изменилось' });
          return;
        }

        try {
          await api.post('/index.php', {
            movementId: item.ID,
            newQuantity: newQuantity
          }, {
            params: { action: 'production.update' }
          });
          $q.notify({ type: 'positive', message: 'Количество обновлено' });
          loadHistory();
        } catch (error: any) {
          $q.notify({ type: 'negative', message: error.message || 'Ошибка при обновлении' });
        }
      });
    };

    // Отмена производства (сторно)
    const confirmRevert = (movement: any) => {
      $q.dialog({
        title: 'Подтверждение',
        message: `Отменить производство "${movement.PRODUCT_NAME}" в количестве ${movement.UF_QUANTITY}?`,
        cancel: true,
        persistent: true
      }).onOk(async () => {
        try {
          await api.post('/index.php', {
            movementId: movement.ID
          }, {
            params: { action: 'production.revert' }
          });
          $q.notify({ type: 'positive', message: 'Производство отменено' });
          loadHistory();
        } catch (error: any) {
          $q.notify({ type: 'negative', message: error.message || 'Ошибка при отмене' });
        }
      });
    };

    onMounted(() => {
      loadSemiFinished();
      loadHistory();
    });

    return {
      history,
      loading,
      semiFinishedList,
      filteredSemiOptions,
      filter,
      pagination,
      columns,
      groupedHistory,
      filterSemi,
      loadHistory,
      onRequest,
      resetFilters,
      getProductUnit,
      formatDate,
      formatMoney,
      openEditDialog,
      confirmRevert
    };
  }
});
</script>
