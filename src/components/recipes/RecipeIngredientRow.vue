<template>
  <div class="row items-center q-gutter-sm">
    <q-select
      :model-value="localIngredient.ingredientId"
      :options="ingredientsOptions"
      option-label="name"
      option-value="id"
      label="Ингредиент"
      outlined
      dense
      style="min-width: 220px"
      emit-value
      map-options
      @update:model-value="(val) => updateIngredient({ ingredientId: val })"
    >
      <template v-slot:option="scope">
        <q-item v-bind="scope.itemProps">
          <q-item-section>
            <q-item-label>{{ scope.opt.name }}</q-item-label>
            <q-item-label caption>
              {{ scope.opt.unit }} |
              база: 1 {{ scope.opt.unit }} = {{ scope.opt.baseRatio }} {{ scope.opt.baseUnit }}
            </q-item-label>
          </q-item-section>
        </q-item>
      </template>
    </q-select>

    <q-input
      :model-value="localIngredient.quantity"
      type="number"
      :label="`Кол-во (в ${selectedBaseUnit})`"
      outlined
      dense
      style="width: 130px"
      step="0.01"
      @update:model-value="(val) => updateIngredient({ quantity: val })"
    />

    <!-- Единица измерения - теперь строка (только для отображения) -->
    <q-input
      :model-value="localIngredient.unit || selectedBaseUnit"
      label="Ед."
      outlined
      dense
      style="width: 70px"
      readonly
      disable
    />

    <!-- Информация о пересчёте в единицы хранения -->
    <div v-if="selectedProduct" class="text-caption text-grey-7" style="min-width: 100px">
      ≈ {{ convertedToStorage }} {{ selectedProduct.unit }}
    </div>

    <q-checkbox
      :model-value="localIngredient.isOptional"
      label="Необяз."
      @update:model-value="(val) => updateIngredient({ isOptional: val })"
    />

    <q-btn flat dense icon="close" color="negative" @click="$emit('remove')" />
  </div>
</template>

<script lang="ts">
import { defineComponent, PropType, ref, watch, computed } from 'vue';
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
    },
    index: {
      type: Number,
      required: true
    }
  },

  emits: ['update:ingredient', 'remove'],

  setup(props, { emit }) {
    const localIngredient = ref({ ...props.ingredient });

    watch(() => props.ingredient, (val) => {
      localIngredient.value = { ...val };
    }, { deep: true });

    const updateIngredient = (changes: Partial<RecipeIngredient>) => {
      const updated = { ...localIngredient.value, ...changes };

      // Если изменился ingredientId, обновляем unit
      if (changes.ingredientId) {
        const product = props.ingredientsList.find(p => p.id === changes.ingredientId);
        if (product) {
          updated.unit = product.baseUnit || 'г';
        }
      }

      emit('update:ingredient', props.index, updated);
    };

    const selectedProduct = computed(() => {
      if (!localIngredient.value.ingredientId) return null;
      return props.ingredientsList.find(p => p.id === localIngredient.value.ingredientId);
    });

    const selectedBaseUnit = computed(() => {
      return selectedProduct.value?.baseUnit || 'г';
    });

    const convertedToStorage = computed(() => {
      if (!selectedProduct.value || !localIngredient.value.quantity) return '0';
      const ratio = selectedProduct.value.baseRatio || 1;
      return (localIngredient.value.quantity / ratio).toFixed(3);
    });

    const ingredientsOptions = props.ingredientsList.filter(p => p.type === 'ingredient');

    return {
      localIngredient,
      ingredientsOptions,
      selectedProduct,
      selectedBaseUnit,
      convertedToStorage,
      updateIngredient
    };
  }
});
</script>
