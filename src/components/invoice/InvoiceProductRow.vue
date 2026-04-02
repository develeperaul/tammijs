<template>
  <div class="row items-center q-gutter-sm q-mb-sm">
    <!-- Выбор товара с поиском -->
    <q-select
      v-model="localItemId"
      :options="filteredItems"
      option-label="displayName"
      option-value="id"
      label="Товар *"
      outlined
      dense
      class="col-3"
      emit-value
      map-options
      use-input
      @filter="filterItems"
      @update:model-value="onItemChange"
    >
      <template v-slot:option="scope">
        <q-item v-bind="scope.itemProps">
          <q-item-section>
            <q-item-label>{{ scope.opt.displayName }}</q-item-label>
            <q-item-label caption>
              <span v-if="scope.opt.itemType === 'ingredient'">
                {{ scope.opt.unit }} | база: {{ scope.opt.baseUnit }} (1:{{ scope.opt.baseRatio }})
                | цена: {{ formatMoney(scope.opt.costPrice / scope.opt.baseRatio) }}/{{ scope.opt.baseUnit }}
              </span>
              <span v-else>
                {{ scope.opt.unit }} | цена: {{ formatMoney(scope.opt.costPrice) }}/{{ scope.opt.unit }}
              </span>
            </q-item-label>
          </q-item-section>
        </q-item>
      </template>
    </q-select>

    <!-- Количество с выбором единицы (только для ингредиентов) -->
    <div class="row items-center col-3">
      <q-input
        v-model.number="localQuantity"
        type="number"
        label="Количество"
        outlined
        dense
        class="col"
        step="0.001"
        :rules="[val => val > 0 || '>0']"
        @update:model-value="update"
      />
      <q-select
        v-if="isIngredientMode"
        v-model="localUnitType"
        :options="unitTypeOptions"
        dense
        options-dense
        emit-value
        map-options
        style="min-width: 70px; margin-left: 4px;"
        @update:model-value="onUnitTypeChange"
      />
      <div v-else class="q-ml-sm text-grey-7" style="min-width: 70px;">
        {{ currentUnitLabel }}
      </div>
    </div>

    <!-- Цена -->
    <q-input
      v-model.number="localPrice"
      type="number"
      label="Цена"
      outlined
      dense
      class="col-2"
      step="0.01"
      @update:model-value="update"
    >
      <template v-slot:append>
        <span class="text-grey-7">₽/{{ currentPriceUnit }}</span>
      </template>
    </q-input>

    <!-- Сумма -->
    <div class="col-2 text-right text-weight-bold">
      {{ formatMoney(localQuantity * localPrice) }}
    </div>

    <!-- Удалить -->
    <q-btn flat dense icon="close" color="negative" @click="$emit('remove')" />
  </div>
</template>

<script lang="ts">
import { defineComponent, PropType, ref, computed, watch } from 'vue';
import { PurchasableItem, isIngredient, isResale } from 'src/types/purchasable.types';

export default defineComponent({
  name: 'InvoiceItemRow',

  props: {
    itemId: { type: Number, default: 0 },
    itemType: { type: String as PropType<'ingredient' | 'resale'>, default: 'ingredient' },
    quantity: { type: Number, default: 1 },
    price: { type: Number, default: 0 },
    unitType: { type: String, default: 'baseUnit' },
    items: { type: Array as PropType<PurchasableItem[]>, required: true }
  },

  emits: ['update', 'remove'],

  setup(props, { emit }) {
    const localItemId = ref(props.itemId);
    const localQuantity = ref(props.quantity);
    const localPrice = ref(props.price);
    const localUnitType = ref<'unit' | 'baseUnit'>(props.unitType as any);

    const filteredItems = ref<PurchasableItem[]>([]);

    const unitTypeOptions = [
      { label: 'ед.хр', value: 'unit' },
      { label: 'баз.ед', value: 'baseUnit' }
    ];

    const selectedItem = computed(() => {
      return props.items.find(i => i.id === localItemId.value && i.itemType === props.itemType);
    });

    const isIngredientMode = computed(() => selectedItem.value?.itemType === 'ingredient');
    const currentUnitLabel = computed(() => {
      if (!selectedItem.value) return '';
      if (isIngredientMode.value && localUnitType.value === 'unit') {
        return selectedItem.value.unit;
      } else if (isIngredientMode.value && localUnitType.value === 'baseUnit') {
        return (selectedItem.value as any).baseUnit;
      } else {
        return selectedItem.value.unit;
      }
    });

    const currentPriceUnit = computed(() => {
      if (!selectedItem.value) return '';
      if (isIngredientMode.value) {
        return localUnitType.value === 'unit' ? selectedItem.value.unit : (selectedItem.value as any).baseUnit;
      } else {
        return selectedItem.value.unit;
      }
    });

    const ratio = computed(() => (selectedItem.value as any)?.baseRatio || 1);

    const filterItems = (val: string, update: any) => {
      update(() => {
        const needle = val.toLowerCase();
        filteredItems.value = props.items.filter(
          item => item.displayName.toLowerCase().includes(needle)
        );
      });
    };

    const onItemChange = (id: number) => {
      const item = props.items.find(i => i.id === id);
      if (item) {
        if (isIngredient(item)) {
          localPrice.value = item.costPrice / item.baseRatio;
          localUnitType.value = 'baseUnit';
        } else {
          localPrice.value = item.costPrice;
          localUnitType.value = 'unit';
        }
        localQuantity.value = 1;
        update();
      }
    };

    const onUnitTypeChange = (newType: string) => {
      if (!selectedItem.value || !isIngredientMode.value) return;

      if (newType === 'unit' && localUnitType.value === 'baseUnit') {
        localPrice.value = localPrice.value * ratio.value;
        localQuantity.value = localQuantity.value / ratio.value;
      } else if (newType === 'baseUnit' && localUnitType.value === 'unit') {
        localPrice.value = localPrice.value / ratio.value;
        localQuantity.value = localQuantity.value * ratio.value;
      }
      localUnitType.value = newType as any;
      update();
    };

    const update = () => {
      emit('update', {
        itemId: localItemId.value,
        itemType: selectedItem.value?.itemType,
        quantity: localQuantity.value,
        price: localPrice.value,
        unitType: localUnitType.value
      });
    };

    const formatMoney = (value: number) => {
      return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        minimumFractionDigits: 2
      }).format(value);
    };

    // Инициализация фильтрованного списка
    watch(() => props.items, () => {
      filteredItems.value = [...props.items];
    }, { immediate: true });

    watch(() => props.itemId, (val) => { localItemId.value = val; });
    watch(() => props.quantity, (val) => { localQuantity.value = val; });
    watch(() => props.price, (val) => { localPrice.value = val; });

    return {
      localItemId,
      localQuantity,
      localPrice,
      localUnitType,
      filteredItems,
      unitTypeOptions,
      selectedItem,
      isIngredientMode,
      currentUnitLabel,
      currentPriceUnit,
      filterItems,
      onItemChange,
      onUnitTypeChange,
      update,
      formatMoney
    };
  }
});
</script>
