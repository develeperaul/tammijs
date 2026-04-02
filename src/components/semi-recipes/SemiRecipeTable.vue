<template>
  <q-table
    :rows="recipes"
    :columns="columns"
    :loading="loading"
    row-key="id"
    flat
    bordered
  >
    <!-- Ингредиенты -->
    <template v-slot:body-cell-ingredients="props">
      <q-td :props="props">
        <div>{{ props.row.ingredients?.length || 0 }} ингр.</div>
        <q-btn
          flat
          dense
          icon="visibility"
          size="sm"
          @click="showIngredients(props.row)"
        >
          <q-tooltip>Показать состав</q-tooltip>
        </q-btn>
      </q-td>
    </template>

    <!-- Себестоимость -->
    <template v-slot:body-cell-cost="props">
      <q-td :props="props">
        {{ formatMoney(calculateCost(props.row)) }}
      </q-td>
    </template>

    <!-- Действия -->
    <template v-slot:body-cell-actions="props">
      <q-td :props="props">
        <q-btn flat round dense icon="edit" @click="$emit('edit', props.row)">
          <q-tooltip>Редактировать</q-tooltip>
        </q-btn>
        <q-btn flat round dense icon="delete" color="negative" @click="$emit('delete', props.row)">
          <q-tooltip>Удалить</q-tooltip>
        </q-btn>
      </q-td>
    </template>
  </q-table>
</template>

<script lang="ts">
import { defineComponent, PropType } from 'vue';
import { SemiRecipe } from 'src/types/semi-recipe.types';
import { useQuasar } from 'quasar';

export default defineComponent({
  name: 'SemiRecipeTable',

  props: {
    recipes: { type: Array as PropType<SemiRecipe[]>, required: true },
    loading: { type: Boolean, default: false }
  },

  emits: ['edit', 'delete'],

  setup() {
    const $q = useQuasar();

    const columns = [
      { name: 'name', label: 'Название', field: 'name', align: 'left', sortable: true },
      { name: 'semiFinishedName', label: 'Полуфабрикат', field: 'semiFinishedName', align: 'left' },
      { name: 'output', label: 'Выход', field: 'outputQuantity', align: 'center' },
      { name: 'outputUnit', label: 'Ед.', field: 'outputUnit', align: 'center' },
      { name: 'cost', label: 'Себестоимость', field: 'cost', align: 'right' },
      { name: 'ingredients', label: 'Состав', field: 'ingredients', align: 'center' },
      { name: 'actions', label: 'Действия', align: 'center' }
    ];

    const calculateCost = (recipe: SemiRecipe): number => {
      if (!recipe.ingredients) return 0;
      return recipe.ingredients.reduce((total, ing) => total + (ing.cost || 0) * ing.quantity, 0);
    };

    const formatMoney = (value: number): string => {
      return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        minimumFractionDigits: 2
      }).format(value);
    };

    const showIngredients = (recipe: SemiRecipe) => {
      const list = recipe.ingredients?.map(ing =>
        `• ${ing.ingredientName}: ${ing.quantity} ${ing.unit}`
      ).join('<br>') || 'Нет ингредиентов';

      $q.dialog({
        title: `Состав: ${recipe.name}`,
        message: list,
        html: true,
        ok: 'Закрыть'
      });
    };

    return { columns, calculateCost, formatMoney, showIngredients };
  }
});
</script>
