<template>
  <div class="row items-center q-gutter-sm">
    <!-- Тип элемента -->
    <q-select
      :model-value="item.itemType"
      :options="itemTypeOptions"
      label="Тип"
      outlined
      dense
      style="min-width: 120px"
      emit-value
      map-options
      @update:model-value="updateType"
    />

    <!-- Выбор элемента -->
    <q-select
      :model-value="item.itemId"
      :options="availableItems"
      option-label="name"
      option-value="id"
      :label="item.itemType === 'ingredient' ? 'Ингредиент' : 'Полуфабрикат'"
      outlined
      dense
      style="min-width: 220px"
      emit-value
      map-options
      @update:model-value="updateItemId"
    >
      <template v-slot:option="scope">
        <q-item v-bind="scope.itemProps">
          <q-item-section>
            <q-item-label>{{ scope.opt.name }}</q-item-label>
            <q-item-label caption>
              {{ getItemDetails(scope.opt) }}
            </q-item-label>
          </q-item-section>
        </q-item>
      </template>
    </q-select>

    <!-- Количество -->
    <q-input
      :model-value="item.quantity"
      type="number"
      :label="`Кол-во (в ${selectedUnit})`"
      outlined
      dense
      style="width: 130px"
      step="0.01"
      @update:model-value="updateQuantity"
    />

    <!-- Единица измерения (только для отображения) -->
    <q-input
      :model-value="selectedUnit"
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

    <q-btn flat dense icon="close" color="negative" @click="$emit('remove')" />
  </div>
</template>

<script lang="ts">
import { defineComponent, PropType, computed } from 'vue';
import { Ingredient } from 'src/types/ingredient.types';
import { SemiFinished } from 'src/types/semi-finished.types';

export default defineComponent({
  name: 'SemiIngredientRow',

  props: {
    item: {
      type: Object as PropType<{
        itemType: 'ingredient' | 'semi-finished';
        itemId: number;
        quantity: number;
        unit: string;
      }>,
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
    const itemTypeOptions = [
      { label: 'Ингредиент', value: 'ingredient' },
      { label: 'Полуфабрикат', value: 'semi-finished' }
    ];

    const availableItems = computed(() => {
      if (props.item.itemType === 'ingredient') {
        return props.ingredients;
      } else {
        return props.semiFinished;
      }
    });

    const selectedItem = computed(() => {
      if (!props.item.itemId || !props.item.itemType) return null;

      if (props.item.itemType === 'ingredient') {
        return props.ingredients.find(i => i.id === props.item.itemId);
      } else {
        return props.semiFinished.find(s => s.id === props.item.itemId);
      }
    });

    const selectedUnit = computed(() => {
      if (!selectedItem.value) return '';

      if (props.item.itemType === 'ingredient') {
        return (selectedItem.value as Ingredient).baseUnit || 'г';
      } else {
        return (selectedItem.value as SemiFinished).unit || 'шт';
      }
    });

    const itemCost = computed(() => {
      if (!selectedItem.value || !props.item.quantity) return 0;

      if (props.item.itemType === 'ingredient') {
        const ing = selectedItem.value as Ingredient;
        const pricePerBaseUnit = ing.costPrice / ing.baseRatio;
        return (pricePerBaseUnit * props.item.quantity).toFixed(2);
      } else {
        const semi = selectedItem.value as SemiFinished;
        return (semi.costPrice * props.item.quantity).toFixed(2);
      }
    });

    const getItemDetails = (item: any) => {
      if (!item) return '';

      if (props.item.itemType === 'ingredient') {
        return `${item.costPrice} ₽/${item.unit} | база: ${item.baseUnit}`;
      } else {
        return `себ.: ${item.costPrice} ₽/${item.unit}`;
      }
    };

    const updateType = (val: string) => {
      emit('update', {
        ...props.item,
        itemType: val,
        itemId: 0,
        quantity: 1,
        unit: ''
      });
    };

    const updateItemId = (val: number) => {
      if (val && val !== 0) {
        const selected = availableItems.value.find(i => i.id === val);
        if (selected) {
          let newUnit = '';
          if (props.item.itemType === 'ingredient') {
            newUnit = (selected as Ingredient).baseUnit;
          } else {
            newUnit = (selected as SemiFinished).unit;
          }
          emit('update', {
            ...props.item,
            itemId: val,
            unit: newUnit
          });
        }
      } else {
        emit('update', {
          ...props.item,
          itemId: 0,
          unit: ''
        });
      }
    };

    const updateQuantity = (val: number) => {
      emit('update', {
        ...props.item,
        quantity: val
      });
    };

    return {
      itemTypeOptions,
      availableItems,
      selectedUnit,
      itemCost,
      getItemDetails,
      updateType,
      updateItemId,
      updateQuantity
    };
  }
});
</script>
