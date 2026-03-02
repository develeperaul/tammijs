<template>
  <q-table
    :rows="recipes"
    :columns="columns"
    :loading="loading"
    row-key="id"
    flat
    bordered
  >
    <!-- Себестоимость -->
    <template v-slot:body-cell-cost="props">
      <q-td :props="props">
        <div>{{ formatMoney(calculateRecipeCost(props.row)) }}</div>
        <div class="text-caption text-grey-7">
          за 1 {{ props.row.outputUnit }}
        </div>
      </q-td>
    </template>

    <!-- Ингредиенты (кратко) -->
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

    <!-- Действия -->
    <template v-slot:body-cell-actions="props">
      <q-td :props="props">
        <q-btn
          flat
          round
          dense
          icon="edit"
          size="sm"
          @click="$emit('edit', props.row)"
        >
          <q-tooltip>Редактировать</q-tooltip>
        </q-btn>
        <q-btn
          flat
          round
          dense
          icon="delete"
          size="sm"
          color="negative"
          @click="$emit('delete', props.row)"
        >
          <q-tooltip>Удалить</q-tooltip>
        </q-btn>
      </q-td>
    </template>
  </q-table>
</template>

<script lang="ts">
import { defineComponent, PropType } from 'vue';
import { Recipe } from 'src/types/recipe.types';
import { useQuasar } from 'quasar';

export default defineComponent({
  name: 'RecipeTable',

  props: {
    recipes: {
      type: Array as PropType<Recipe[]>,
      required: true
    },
    loading: {
      type: Boolean,
      default: false
    }
  },

  emits: ['edit', 'delete'],

  setup() {
    const $q = useQuasar();

    const columns = [
      { name: 'name', label: 'Название', field: 'name', align: 'left', sortable: true },
      { name: 'productName', label: 'Блюдо', field: 'productName', align: 'left' },
      { name: 'output', label: 'Выход', field: 'outputWeight', align: 'center' },
      { name: 'cookingTime', label: 'Время', field: 'cookingTime', align: 'center' },
      { name: 'cost', label: 'Себестоимость', field: 'cost', align: 'right' },
      { name: 'ingredients', label: 'Состав', field: 'ingredients', align: 'center' },
      { name: 'actions', label: 'Действия', align: 'center' }
    ];

    const calculateRecipeCost = (recipe: Recipe): number => {
      if (!recipe.ingredients) return 0;

      return recipe.ingredients.reduce((total, ing) => {
        return total + ((ing.cost || 0) * ing.quantity);
      }, 0);
    };

    const formatMoney = (value: number): string => {
      return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        minimumFractionDigits: 2
      }).format(value);
    };

    const showIngredients = (recipe: Recipe) => {
      const ingredientsList = recipe.ingredients?.map(ing =>
        `• ${ing.ingredientName}: ${ing.quantity} ${ing.unit}${ing.isOptional ? ' (опционально)' : ''}`
      ).join('<br>') || 'Нет ингредиентов';

      $q.dialog({
        title: `Состав: ${recipe.name}`,
        message: ingredientsList,
        html: true,
        ok: 'Закрыть'
      });
    };

    return {
      columns,
      calculateRecipeCost,
      formatMoney,
      showIngredients
    };
  }
});
</script>
