<template>
  <q-dialog ref="dialog" @hide="onDialogHide">
    <q-card style="min-width: 500px; max-width: 700px; width: 100%;">
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

          <!-- Тип товара (производимый/перепродажа) -->
          <q-select
            v-model="form.type"
            :options="typeOptions"
            label="Тип товара *"
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
            emit-value
            map-options
          />

          <!-- Цена продажи -->
          <q-input
            v-model.number="form.sellingPrice"
            label="Цена продажи"
            outlined
            dense
            type="number"
            step="0.01"
            hint="Цена для клиента"
          />

          <!-- Остатки (только при создании) -->
          <q-input
            v-if="!isEdit"
            v-model.number="form.currentStock"
            label="Начальный остаток"
            outlined
            dense
            type="number"
            step="0.001"
          />

          <!-- Минимальный остаток -->
          <q-input
            v-model.number="form.minStock"
            label="Минимальный остаток"
            outlined
            dense
            type="number"
            step="0.001"
            hint="При каком остатке считать критическим"
          />

          <!-- Описание -->
          <q-input
            v-model="form.description"
            label="Описание"
            outlined
            dense
            type="textarea"
            rows="2"
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
      { label: '🏭 Производимый товар', value: 'produced' },
      { label: '🛒 Товар перепродажи', value: 'resale' }
    ];

    const form = ref<CreateProductDto>({
      name: '',
      type: 'produced',
      sellingPrice: 0,
      currentStock: 0,
      minStock: 0,
      categoryId: undefined,
      description: ''
    });

    // Заполнение формы при редактировании
    watch(() => props.product, (newVal) => {
      if (newVal) {
        form.value = {
          name: newVal.name,
          type: newVal.type || 'produced',
          sellingPrice: newVal.sellingPrice || 0,
          currentStock: newVal.currentStock || 0,
          minStock: newVal.minStock || 0,
          categoryId: newVal.categoryId,
          description: newVal.description || ''
        };
        isEdit.value = true;
      } else {
        form.value = {
          name: '',
          type: 'produced',
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
      const dataToSend = {
        name: form.value.name,
        type: form.value.type,
        sellingPrice: form.value.sellingPrice,
        currentStock: form.value.currentStock,
        minStock: form.value.minStock,
        categoryId: form.value.categoryId,
        description: form.value.description || ''
      };

      console.log('Отправляемые данные:', dataToSend);
      emit('ok', dataToSend);
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
