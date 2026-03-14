<template>
  <q-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    @hide="$emit('hide')"
    persistent
  >
    <q-card style="min-width: 400px">
      <q-card-section>
        <div class="text-h6">{{ isEdit ? 'Редактировать' : 'Добавить' }} категорию</div>
      </q-card-section>

      <q-card-section>
        <q-form @submit="onSubmit" class="q-gutter-md">
          <q-input
            v-model="form.name"
            label="Название категории *"
            outlined
            dense
            :rules="[val => !!val || 'Введите название']"
            autofocus
          />

          <q-input
            v-model="form.sort"
            label="Сортировка"
            outlined
            dense
            type="number"
            :rules="[val => val > 0 || 'Введите положительное число']"
          />
        </q-form>
      </q-card-section>

      <q-card-actions align="right">
        <q-btn flat label="Отмена" color="negative" v-close-popup />
        <q-btn flat label="Сохранить" color="positive" @click="onSubmit" />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script lang="ts">
import { defineComponent, ref, computed, watch } from 'vue';
import { ProductCategory } from 'src/types/product.types';

export default defineComponent({
  name: 'CategoryDialog',

  props: {
    modelValue: { type: Boolean, required: true },
    category: { type: Object as () => ProductCategory | null, default: null }
  },

  emits: ['update:modelValue', 'ok', 'hide'],

  setup(props, { emit }) {
    const isEdit = computed(() => !!props.category);

    const form = ref({
      name: '',
      sort: 500
    });

    watch(() => props.category, (val) => {
      if (val) {
        form.value = {
          name: val.name,
          sort: val.sortOrder || 500
        };
      } else {
        form.value = {
          name: '',
          sort: 500
        };
      }
    }, { immediate: true });

    const onSubmit = () => {
      if (!form.value.name) return;
      emit('ok', form.value);
      emit('update:modelValue', false);
    };

    return {
      isEdit,
      form,
      onSubmit
    };
  }
});
</script>
