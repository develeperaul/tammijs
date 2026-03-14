<template>
  <div>
    <div class="row q-mb-md q-gutter-sm items-center">
      <q-input
        v-model="search"
        outlined
        dense
        :placeholder="`Поиск ${entityName}...`"
        class="col-4"
        @update:model-value="$emit('update:search', $event)"
      >
        <template v-slot:append>
          <q-icon name="search" />
        </template>
      </q-input>

      <q-select
        v-if="categories"
        v-model="category"
        :options="categories"
        option-label="name"
        option-value="id"
        label="Категория"
        outlined
        dense
        clearable
        class="col-2"
        emit-value
        map-options
        @update:model-value="$emit('update:category', $event)"
      />

      <q-btn flat icon="clear_all" color="primary" @click="$emit('resetFilters')">
        <q-tooltip>Сбросить фильтры</q-tooltip>
      </q-btn>

      <q-btn flat icon="refresh" @click="$emit('refresh')">
        <q-tooltip>Обновить</q-tooltip>
      </q-btn>
    </div>

    <q-table
      :rows="items"
      :columns="columns"
      :loading="loading"
      row-key="id"
      flat
      bordered
    >
      <template v-for="slot in slots" #[slot]="props" :key="slot">
        <slot :name="slot" v-bind="props" />
      </template>

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
  </div>
</template>

<script lang="ts">
import { defineComponent, ref } from 'vue';

export default defineComponent({
  name: 'BaseTable',

  props: {
    items: { type: Array, required: true },
    columns: { type: Array, required: true },
    loading: { type: Boolean, default: false },
    entityName: { type: String, default: 'элементов' },
    categories: { type: Array, default: null },
    slots: { type: Array, default: () => [] }
  },

  emits: [
    'update:search',
    'update:category',
    'resetFilters',
    'refresh',
    'edit',
    'delete'
  ],

  setup() {
    const search = ref('');
    const category = ref(null);

    return { search, category };
  }
});
</script>
