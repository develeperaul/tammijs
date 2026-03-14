<template>
  <div class="row items-center q-gutter-sm">
    <q-select
      v-model="localIngredient.ingredientId"
      :options="ingredients"
      option-label="name"
      option-value="id"
      label="Ингредиент"
      outlined
      dense
      style="min-width: 220px"
      emit-value
      map-options
      @update:model-value="updateIngredient"
    >
      <template v-slot:option="scope">
        <q-item v-bind="scope.itemProps">
          <q-item-section>
            <q-item-label>{{ scope.opt.name }}</q-item-label>
            <q-item-label caption>
              {{ scope.opt.unit }} |
              цена: {{ formatMoney(scope.opt.costPrice / scope.opt.baseRatio) }}/{{ scope.opt.baseUnit }}
            </q-item-label>
          </q-item-section>
        </q-item>
      </template>
    </q-select>

    <q-input
      v-model.number="localIngredient.quantity"
      type="number"
      :label="`Кол-во (в ${selectedBaseUnit})`"
      outlined
      dense
      style="width: 130px"
      step="0.01"
      @update:model-value="updateIngredient"
    />

    <q-input
      v-model="selectedBaseUnit"
      label="Ед."
      outlined
      dense
      style="width: 70px"
      readonly
      disable
    />

    <div class="text-caption text-grey-7" style="min-width: 100px">
      ≈ {{ ingredientCost }} ₽
    </div>

    <q-btn flat dense icon="close" color="negative" @click="$emit('remove')" />
  </div>
</template>

<script lang="ts">
import { defineComponent, PropType, ref, computed } from 'vue';
import { Ingredient } from 'src/types/ingredient.types';

export default defineComponent({
  name: 'SemiIngredientRow',

  props: {
    ingredient: {
      type: Object as PropType<{
        ingredientId: number;
        quantity: number;
      }>,
      required: true
    },
    ingredients: {
      type: Array as PropType<Ingredient[]>,
      required: true
    }
  },

  emits: ['update', 'remove'],

  setup(props, { emit }) {
    const localIngredient = ref({ ...props.ingredient });

    const selectedIngredient = computed(() => {
      return props.ingredients.find(i => i.id === localIngredient.value.ingredientId);
    });

    const selectedBaseUnit = computed(() => {
      return selectedIngredient.value?.baseUnit || 'г';
    });

    const ingredientCost = computed(() => {
      if (!selectedIngredient.value || !localIngredient.value.quantity) return 0;
      const pricePerBaseUnit = selectedIngredient.value.costPrice / selectedIngredient.value.baseRatio;
      return (pricePerBaseUnit * localIngredient.value.quantity).toFixed(2);
    });

    const updateIngredient = () => {
      emit('update', localIngredient.value);
    };

    const formatMoney = (value: number) => {
      return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        minimumFractionDigits: 2
      }).format(value);
    };

    return {
      localIngredient,
      selectedIngredient,
      selectedBaseUnit,
      ingredientCost,
      updateIngredient,
      formatMoney
    };
  }
});
</script>
