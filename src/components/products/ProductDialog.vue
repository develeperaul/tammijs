<template>
  <q-dialog ref="dialog" @hide="onDialogHide">
    <q-card style="min-width: 400px; max-width: 600px; width: 100%;">
      <q-card-section>
        <div class="text-h6">{{ isEdit ? 'Редактировать' : 'Добавить' }} товар</div>
      </q-card-section>

      <q-card-section>
        <q-form @submit="onSubmit" class="q-gutter-md">
          <!-- Название -->
          <q-input
            v-model="form.name"
            label="Название *"
            outlined
            dense
            :rules="[val => !!val || 'Обязательное поле']"
          />

          <!-- Тип -->
          <q-select
            v-model="form.type"
            :options="typeOptions"
            label="Тип *"
            outlined
            dense
            emit-value
            map-options
            :rules="[val => !!val || 'Выберите тип']"
          />

          <!-- Категория -->
          <q-select
            v-model="form.categoryId"
            :options="categories"
            option-label="name"
            option-value="id"
            label="Категория"
            outlined
            dense
            clearable
          />

          <div class="row q-gutter-sm">
            <!-- Единица измерения -->
            <q-input
              v-model="form.unit"
              label="Ед. изм. *"
              outlined
              dense
              class="col"
              :rules="[val => !!val || 'Обязательное поле']"
            />

            <!-- Цена продажи -->
            <q-input
              v-model.number="form.sellingPrice"
              label="Цена продажи"
              outlined
              dense
              type="number"
              class="col"
            />
          </div>

          <div class="row q-gutter-sm">
            <!-- Себестоимость -->
            <q-input
              v-model.number="form.costPrice"
              label="Себестоимость"
              outlined
              dense
              type="number"
              class="col"
            />

            <!-- Мин. остаток -->
            <q-input
              v-model.number="form.minStock"
              label="Мин. остаток"
              outlined
              dense
              type="number"
              class="col"
            />
          </div>

          <!-- Текущий остаток (только при создании) -->
          <q-input
            v-if="!isEdit"
            v-model.number="form.currentStock"
            label="Начальный остаток"
            outlined
            dense
            type="number"
          />

          <!-- Описание -->
          <q-input
            v-model="form.description"
            label="Описание"
            outlined
            dense
            type="textarea"
            rows="3"
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
import { defineComponent, ref, watch } from 'vue';
import { Product, CreateProductDto, ProductCategory } from 'src/types/product.types';

export default defineComponent({
  name: 'ProductDialog',

  props: {
    product: {
      type: Object as () => Product | null,
      default: null
    },
    categories: {
      type: Array as () => ProductCategory[],
      default: () => []
    }
  },

  emits: ['ok', 'hide'],

  setup(props, { emit }) {
    const dialog = ref<any>(null);
    const isEdit = ref(!!props.product);

    const typeOptions = [
      { label: 'Ингредиент', value: 'ingredient' },
      { label: 'Готовый', value: 'finished' },
      { label: 'Полуфабрикат', value: 'semi-finished' }
    ];

    const form = ref<CreateProductDto>({
      name: '',
      type: 'ingredient',
      unit: 'шт',
      costPrice: 0,
      sellingPrice: 0,
      currentStock: 0,
      minStock: 0,
      categoryId: undefined,
      description: ''
    });

    // Заполняем форму при редактировании
    watch(() => props.product, (newVal) => {
      if (newVal) {
        form.value = {
          name: newVal.name,
          type: newVal.type,
          unit: newVal.unit,
          costPrice: newVal.costPrice,
          sellingPrice: newVal.sellingPrice,
          currentStock: newVal.currentStock,
          minStock: newVal.minStock,
          categoryId: newVal.categoryId,
          description: newVal.description || ''
        };
        isEdit.value = true;
      } else {
        form.value = {
          name: '',
          type: 'ingredient',
          unit: 'шт',
          costPrice: 0,
          sellingPrice: 0,
          currentStock: 0,
          minStock: 0,
          categoryId: undefined,
          description: ''
        };
        isEdit.value = false;
      }
    }, { immediate: true });

    const show = () => {
      dialog.value?.show();
    };

    const hide = () => {
      dialog.value?.hide();
    };

    const onSubmit = () => {
      emit('ok', form.value);
      hide();
    };

    const onDialogHide = () => {
      emit('hide');
    };

    return {
      dialog,
      form,
      isEdit,
      typeOptions,
      show,
      hide,
      onSubmit,
      onDialogHide
    };
  }
});
</script>
