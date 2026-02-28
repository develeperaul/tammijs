<template>
  <q-dialog ref="dialog" @hide="onDialogHide">
    <q-card style="min-width: 400px">
      <q-card-section>
        <div class="text-h6">Приход товара</div>
      </q-card-section>

      <q-card-section v-if="product">
        <div class="text-subtitle2">{{ product.name }}</div>
        <div class="text-caption">Текущий остаток: {{ product.currentStock }} {{ product.unit }}</div>
      </q-card-section>

      <q-card-section>
        <q-form @submit="onSubmit" class="q-gutter-md">
          <q-input
            v-model.number="form.quantity"
            label="Количество *"
            type="number"
            outlined
            dense
            :rules="[val => val > 0 || 'Введите положительное число']"
          />

          <q-input
            v-model.number="form.price"
            label="Цена закупки"
            type="number"
            outlined
            dense
          />

          <q-input
            v-model="form.comment"
            label="Комментарий"
            outlined
            dense
          />

          <q-select
            v-model="form.documentType"
            :options="documentOptions"
            label="Тип документа"
            outlined
            dense
            emit-value
            map-options
          />

          <q-input
            v-if="form.documentType === 'invoice'"
            v-model="form.documentNumber"
            label="Номер накладной"
            outlined
            dense
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
import { Product } from 'src/types/product.types';

export default defineComponent({
  name: 'IncomeDialog',

  props: {
    product: {
      type: Object as () => Product | null,
      default: null
    }
  },

  emits: ['ok', 'hide'],

  setup(props, { emit }) {
    const dialog = ref<any>(null);
    const form = ref({
      quantity: 1,
      price: 0,
      comment: '',
      documentType: 'manual',
      documentNumber: ''
    });

    const documentOptions = [
      { label: 'Ручной ввод', value: 'manual' },
      { label: 'Накладная', value: 'invoice' }
    ];

    const show = () => {
      dialog.value?.show();
    };

    const hide = () => {
      dialog.value?.hide();
    };

    const onSubmit = () => {
      emit('ok', {
        ...form.value,
        productId: props.product?.id
      });
      hide();
    };

    const onDialogHide = () => {
      emit('hide');
    };

    // Сброс формы при открытии
    watch(() => props.product, () => {
      if (props.product) {
        form.value = {
          quantity: 1,
          price: 0,
          comment: '',
          documentType: 'manual',
          documentNumber: ''
        };
      }
    });

    return {
      dialog,
      form,
      documentOptions,
      show,
      hide,
      onSubmit,
      onDialogHide
    };
  }
});
</script>
