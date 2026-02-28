<template>
  <q-page class="q-pa-md">
    <!-- Заголовок и фильтр -->
    <div class="row q-mb-md items-center">
      <div class="col-6">
        <div class="text-h5">Кухня</div>
        <div class="text-caption text-grey-7">
          Отображено заказов: {{ filteredOrders.length }} из {{ orders.length }}
        </div>
      </div>
      <div class="col-6 text-right">
        <!-- Фильтр по статусам -->
        <q-select
          v-model="filterStatus"
          :options="filterOptions"
          label="Фильтр по статусу"
          dense
          outlined
          emit-value
          map-options
          class="inline-block q-mr-sm"
          style="min-width: 150px"
          clearable
          @clear="filterStatus = 'all'"
        />
        <q-btn flat icon="refresh" @click="loadOrders" :loading="loading">
          <q-tooltip>Обновить</q-tooltip>
        </q-btn>
      </div>
    </div>

    <!-- Индикатор загрузки -->
    <div v-if="loading" class="text-center q-pa-md">
      <q-spinner size="50px" color="primary" />
    </div>

    <!-- Нет заказов -->
    <div v-else-if="filteredOrders.length === 0" class="text-center q-pa-md text-grey-7">
      Нет заказов, соответствующих фильтру
    </div>

    <!-- Список карточек заказов -->
    <div v-else class="row q-col-gutter-md">
      <div
        v-for="order in filteredOrders"
        :key="order.id"
        class="col-12 col-md-6 col-xl-4"
      >
        <kitchen-order-card
          :order="order"
          @update-item="updateItemStatus"
          @update-order-status="updateOrderStatus"
          @ready="markOrderReady"
        />
      </div>
    </div>

    <!-- Звуковое оповещение о новом заказе -->
    <audio ref="newOrderSound" src="/sounds/new-order.mp3" preload="auto"></audio>
  </q-page>
</template>

<script lang="ts">
import { defineComponent, ref, computed, onMounted, onUnmounted } from 'vue';
import { useQuasar } from 'quasar';
import orderService from 'src/services/order.service';
import { Order, OrderStatus } from 'src/types/order.types';
import KitchenOrderCard from 'components/kitchen/KitchenOrderCard.vue';

export default defineComponent({
  name: 'KitchenPage',
  components: { KitchenOrderCard },

  setup() {
    const $q = useQuasar();
    const orders = ref<Order[]>([]);
    const loading = ref(false);
    const newOrderSound = ref<HTMLAudioElement | null>(null);
    const filterStatus = ref<string>('all'); // 'all', 'preorder', 'new', 'produced'

    // Варианты фильтра
    const filterOptions = [
      { label: 'Все', value: 'all' },
      { label: 'Предзаказ', value: 'preorder' },
      { label: 'Новые', value: 'new' },
      { label: 'Выполненные', value: 'produced' }
    ];

    let previousOrdersCount = 0;
    let interval: number;

    // Загрузка заказов
    const loadOrders = async () => {
      loading.value = true;
      try {
        // Предполагаем, что getKitchenOrders возвращает все заказы, кроме удалённых/отменённых
        const data = await orderService.getKitchenOrders();
        if (data.length > previousOrdersCount) {
          playNewOrderSound();
        }
        previousOrdersCount = data.length;
        orders.value = data;
      } catch (error) {
        $q.notify({ type: 'negative', message: 'Ошибка загрузки заказов' });
      } finally {
        loading.value = false;
      }
    };

    // Воспроизведение звука нового заказа
    const playNewOrderSound = () => {
      if (newOrderSound.value) {
        newOrderSound.value.play().catch(() => {});
      }
    };

    // Фильтрация заказов по статусу
    const filteredOrders = computed(() => {
      if (filterStatus.value === 'all') return orders.value;
      return orders.value.filter(order => order.status === filterStatus.value);
    });

    // Обновление статуса позиции
    const updateItemStatus = async (
      orderId: number,
      itemId: number,
      status: 'pending' | 'cooking' | 'ready'
    ) => {
      try {
        const updatedOrder = await orderService.updateOrderItemStatus(orderId, itemId, status);
        const index = orders.value.findIndex((o) => o.id === orderId);
        if (index !== -1) {
          orders.value[index] = updatedOrder;
        }
      } catch (error) {
        $q.notify({ type: 'negative', message: 'Ошибка обновления статуса позиции' });
      }
    };

    // Обновление общего статуса заказа
    const updateOrderStatus = async (orderId: number, status: OrderStatus) => {
      try {
        const updatedOrder = await orderService.updateOrderStatus(orderId, status);
        const index = orders.value.findIndex((o) => o.id === orderId);
        if (index !== -1) {
          orders.value[index] = updatedOrder;
        }
      } catch (error) {
        $q.notify({ type: 'negative', message: 'Ошибка обновления статуса заказа' });
      }
    };

    // Отметить заказ как готовый (кнопка "Заказ готов")
    const markOrderReady = async (orderId: number) => {
      try {
        const updatedOrder = await orderService.updateOrderStatus(orderId, 'produced');
        const index = orders.value.findIndex((o) => o.id === orderId);
        if (index !== -1) {
          orders.value[index] = updatedOrder;
          // Не удаляем из списка, остаётся доступным по фильтру "Выполненные"
        }
      } catch (error) {
        $q.notify({ type: 'negative', message: 'Ошибка обновления статуса' });
      }
    };

    onMounted(() => {
      loadOrders();
      interval = window.setInterval(loadOrders, 30000);
    });

    onUnmounted(() => {
      clearInterval(interval);
    });

    return {
      orders,
      filteredOrders,
      loading,
      filterStatus,
      filterOptions,
      newOrderSound,
      loadOrders,
      updateItemStatus,
      updateOrderStatus,
      markOrderReady,
    };
  },
});
</script>
