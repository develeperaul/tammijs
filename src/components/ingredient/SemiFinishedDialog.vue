<template>
  <q-dialog ref="dialog" @hide="onDialogHide">
    <q-card style="min-width: 500px">
      <q-card-section>
        <div class="text-h6">{{ isEdit ? 'Редактировать' : 'Добавить' }} полуфабрикат</div>
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

          <!-- Единица измерения -->
          <q-select
            v-model="form.unit"
            :options="unitOptions"
            label="Единица измерения *"
            outlined
            dense
            emit-value
            map-options
            :rules="[val => !!val || 'Обязательное поле']"
          />

          <!-- Цены -->
          <div class="row q-gutter-sm">
            <q-input
              v-model.number="form.costPrice"
              label="Себестоимость"
              outlined
              dense
              type="number"
              class="col"
              step="0.01"
            >
              <template v-slot:append>
                <span class="text-grey-7">₽/{{ form.unit }}</span>
              </template>
            </q-input>

            <q-input
              v-model.number="form.sellingPrice"
              label="Цена продажи"
              outlined
              dense
              type="number"
              class="col"
              step="0.01"
            >
              <template v-slot:append>
                <span class="text-grey-7">₽/{{ form.unit }}</span>
              </template>
            </q-input>
          </div>

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
        <q-btn
          flat
          label="Сохранить"
          color="positive"
          @click="onSubmit"
          :disable="!canSubmit"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script lang="ts">
import { defineComponent, ref, computed, watch } from 'vue';
import { SemiFinished } from 'src/types/semi-finished.types';
import { ProductCategory } from 'src/types/product.types';

export default defineComponent({
  name: 'SemiFinishedDialog',

  props: {
    item: {
      type: Object as () => SemiFinished | null,
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
    const isEdit = ref(!!props.item);

    const unitOptions = [
      { label: 'кг (килограмм)', value: 'кг' },
      { label: 'шт (штука)', value: 'шт' },
      { label: 'уп (упаковка)', value: 'уп' }
    ];

    const form = ref({
      name: '',
      categoryId: null as number | null,
      unit: 'шт',
      costPrice: 0,
      sellingPrice: 0,
      description: ''
    });

    const canSubmit = computed(() => {
      return form.value.name && form.value.unit;
    });

    watch(() => props.item, (val) => {
      if (val) {
        form.value = {
          name: val.name,
          categoryId: val.categoryId,
          unit: val.unit,
          costPrice: val.costPrice,
          sellingPrice: val.sellingPrice,
          description: val.description || ''
        };
        isEdit.value = true;
      } else {
        form.value = {
          name: '',
          categoryId: null,
          unit: 'шт',
          costPrice: 0,
          sellingPrice: 0,
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
      unitOptions,
      canSubmit,
      show,
      hide,
      onSubmit,
      onDialogHide
    };
  }
});
</script>
