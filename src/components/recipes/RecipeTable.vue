<template>
  <q-table
    :rows="recipes"
    :columns="columns"
    :loading="loading"
    row-key="id"
    flat
    bordered
  >
    <template v-slot:body-cell-actions="props">
      <q-td :props="props">
        <q-btn flat round dense icon="edit" size="sm" @click="$emit('edit', props.row)">
          <q-tooltip>Редактировать</q-tooltip>
        </q-btn>
        <q-btn flat round dense icon="delete" size="sm" color="negative" @click="$emit('delete', props.row)">
          <q-tooltip>Удалить</q-tooltip>
        </q-btn>
      </q-td>
    </template>
  </q-table>
</template>

<script lang="ts">
import { defineComponent, PropType } from 'vue';
import { Recipe } from 'src/types/recipe.types';

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
    const columns = [
      { name: 'id', label: 'ID', field: 'id', align: 'left', sortable: true },
      { name: 'name', label: 'Название', field: 'name', align: 'left', sortable: true },
      { name: 'productName', label: 'Блюдо', field: 'productName', align: 'left' },
      { name: 'outputWeight', label: 'Выход', field: 'outputWeight', align: 'center' },
      { name: 'outputUnit', label: 'Ед.', field: 'outputUnit', align: 'center' },
      { name: 'cookingTime', label: 'Время (мин)', field: 'cookingTime', align: 'center' },
      { name: 'actions', label: 'Действия', align: 'center' }
    ];

    return { columns };
  }
});
</script>
