<template>
  <div class="row items-center q-gutter-sm">
    <!-- Тип элемента (ингредиент/полуфабрикат) -->
    <q-select
      v-model="localItem.itemType"
      :options="itemTypeOptions"
      label="Тип"
      outlined
      dense
      style="min-width: 120px"
      emit-value
      map-options
      @update:model-value="onTypeChange"
    />

    <!-- Выбор элемента -->
    <q-select
      v-model="localItem.itemId"
      :options="availableItems"
      :option-label="getItemLabel"
      option-value="id"
      :label="localItem.itemType === 'ingredient' ? 'Ингредиент' : 'Полуфабрикат'"
      outlined
      dense
      style="min-width: 220px"
      emit-value
      map-options
      @update:model-value="onItemSelect"
    >
      <template v-slot:option="scope">
        <q-item v-bind="scope.itemProps">
          <q-item-section>
            <q-item-label>{{ getItemLabel(scope.opt) }}</q-item-label>
            <q-item-label caption>
              {{ getItemDetails(scope.opt) }}
            </q-item-label>
          </q-item-section>
        </q-item>
      </template>
    </q-select>

    <!-- Количество -->
    <q-input
      v-model.number="localItem.quantity"
      type="number"
      :label="`Кол-во (в ${selectedBaseUnit})`"
      outlined
      dense
      style="width: 130px"
      step="0.01"
      @update:model-value="$emit('update', localItem)"
    />

    <!-- Единица измерения (только для отображения) -->
    <q-input
      v-model="selectedBaseUnit"
      label="Ед."
      outlined
      dense
      style="width: 70px"
      readonly
      disable
    />

    <!-- Информация о стоимости -->
    <div class="text-caption text-grey-7" style="min-width: 100px">
      ≈ {{ itemCost }} ₽
    </div>

    <q-checkbox
      v-model="localItem.isOptional"
      label="Необяз."
      @update:model-value="$emit('update', localItem)"
    />

    <q-btn flat dense icon="close" color="negative" @click="$emit('remove')" />
  </div>
</template>

<script lang="ts">
import { defineComponent, PropType, ref, computed, watch } from 'vue';
import { RecipeItem, RecipeItemType } from 'src/types/recipe.types';
import { Ingredient } from 'src/types/ingredient.types';
import { SemiFinished } from 'src/types/semi-finished.types';

export default defineComponent({
  name: 'RecipeItemRow',

  props: {
    item: {
      type: Object as PropType<Partial<RecipeItem>>,
      required: true
    },
    ingredients: {
      type: Array as PropType<Ingredient[]>,
      required: true
    },
    semiFinished: {
      type: Array as PropType<SemiFinished[]>,
      required: true
    }
  },

  emits: ['update', 'remove'],

  setup(props, { emit }) {
    const localItem = ref({ ...props.item });

    const itemTypeOptions = [
      { label: 'Ингредиент', value: 'ingredient' },
      { label: 'Полуфабрикат', value: 'semi-finished' }
    ];

    const availableItems = computed(() => {
      if (localItem.value.itemType === 'ingredient') {
        return props.ingredients;
      } else {
        return props.semiFinished;
      }
    });

    const selectedItem = computed(() => {
      if (!localItem.value.itemId || !localItem.value.itemType) return null;

      if (localItem.value.itemType === 'ingredient') {
        return props.ingredients.find(i => i.id === localItem.value.itemId);
      } else {
        return props.semiFinished.find(s => s.id === localItem.value.itemId);
      }
    });

    const selectedBaseUnit = computed(() => {
      if (localItem.value.itemType === 'ingredient') {
        return (selectedItem.value as Ingredient)?.baseUnit || 'г';
      } else {
        return (selectedItem.value as SemiFinished)?.unit || 'шт';
      }
    });

    const itemCost = computed(() => {
      if (!selectedItem.value || !localItem.value.quantity) return 0;

      if (localItem.value.itemType === 'ingredient') {
        const ing = selectedItem.value as Ingredient;
        const pricePerBaseUnit = ing.costPrice / ing.baseRatio;
        return (pricePerBaseUnit * localItem.value.quantity).toFixed(2);
      } else {
        const semi = selectedItem.value as SemiFinished;
        const pricePerUnit = semi.costPrice;
        return (pricePerUnit * localItem.value.quantity).toFixed(2);
      }
    });

    const getItemLabel = (item: any) => {
      return item?.name || '';
    };

    const getItemDetails = (item: any) => {
      if (!item) return '';

      if (localItem.value.itemType === 'ingredient') {
        return `${item.costPrice} ₽/${item.unit} | база: ${item.baseUnit}`;
      } else {
        return `себ.: ${item.costPrice} ₽/${item.unit}`;
      }
    };

    const onTypeChange = () => {
      localItem.value.itemId = 0;
      localItem.value.quantity = 1;
      emit('update', localItem.value);
    };

    const onItemSelect = () => {
      if (localItem.value.itemType === 'ingredient' && selectedItem.value) {
        localItem.value.unit = (selectedItem.value as Ingredient).baseUnit;
      } else if (selectedItem.value) {
        localItem.value.unit = (selectedItem.value as SemiFinished).unit;
      }
      emit('update', localItem.value);
    };

    watch(() => props.item, (val) => {
      localItem.value = { ...val };
    }, { deep: true });

    watch(localItem, () => {
      emit('update', localItem.value);
    }, { deep: true });

    return {
      localItem,
      itemTypeOptions,
      availableItems,
      selectedBaseUnit,
      itemCost,
      getItemLabel,
      getItemDetails,
      onTypeChange,
      onItemSelect
    };
  }
});
</script>
