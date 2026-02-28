<template>
  <div class="row items-center q-gutter-sm">
    <q-select
      v-model="localIngredient.ingredientId"
      :options="ingredientsOptions"
      option-label="name"
      option-value="id"
      label="Ингредиент"
      outlined
      dense
      style="min-width: 200px"
      @update:model-value="$emit('update:ingredient', localIngredient)"
    />
    <q-input
      v-model.number="localIngredient.quantity"
      type="number"
      label="Кол-во"
      outlined
      dense
      style="width: 100px"
      step="0.01"
      @update:model-value="$emit('update:ingredient', localIngredient)"
    />
    <q-select
      v-model="localIngredient.unit"
      :options="unitOptions"
      label="Ед."
      outlined
      dense
      style="width: 80px"
      @update:model-value="$emit('update:ingredient', localIngredient)"
    />
    <q-checkbox
      v-model="localIngredient.isOptional"
      label="Необяз."
      @update:model-value="$emit('update:ingredient', localIngredient)"
    />
    <q-btn flat dense icon="close" color="negative" @click="$emit('remove')" />
  </div>
</template>

<script lang="ts">
import { defineComponent, PropType, ref, watch } from 'vue';
import { RecipeIngredient } from 'src/types/recipe.types';
import { Product } from 'src/types/product.types';

export default defineComponent({
  name: 'RecipeIngredientRow',

  props: {
    ingredient: {
      type: Object as PropType<Partial<RecipeIngredient>>,
      required: true
    },
    ingredientsList: {
      type: Array as PropType<Product[]>,
      default: () => []
    }
  },

  emits: ['update:ingredient', 'remove'],

  setup(props, { emit }) {
    const localIngredient = ref({ ...props.ingredient });

    watch(() => props.ingredient, (val) => {
      localIngredient.value = { ...val };
    }, { deep: true });

    const ingredientsOptions = props.ingredientsList.filter(p => p.type === 'ingredient');

    const unitOptions = ['кг', 'г', 'шт', 'л', 'мл', 'уп'].map(u => ({ label: u, value: u }));

    return {
      localIngredient,
      ingredientsOptions,
      unitOptions
    };
  }
});
</script>
