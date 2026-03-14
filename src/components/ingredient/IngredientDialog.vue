<template>
  <q-dialog ref="dialog" @hide="onDialogHide">
    <q-card style="min-width: 500px">
      <q-card-section>
        <div class="text-h6">{{ isEdit ? 'Редактировать' : 'Добавить' }} ингредиент</div>
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

          <!-- Единицы измерения -->
          <div class="row q-gutter-sm">
            <q-select
              v-model="form.unit"
              :options="unitOptions"
              label="Ед. хранения *"
              outlined
              dense
              class="col"
              emit-value
              map-options
              :rules="[val => !!val || 'Обязательное поле']"
            />

            <q-select
              v-model="form.baseUnit"
              :options="baseUnitOptions"
              label="Базовая ед. *"
              outlined
              dense
              class="col"
              emit-value
              map-options
            />
          </div>

          <!-- Коэффициент -->
          <q-input
            v-model.number="form.baseRatio"
            label="Коэффициент *"
            outlined
            dense
            type="number"
            step="0.01"
            :rules="[val => val > 0 || 'Введите положительное число']"
            hint="Сколько базовых единиц в 1 ед. хранения"
          >
            <template v-slot:append>
              <span class="text-grey-7">
                1 {{ form.unit }} = {{ form.baseRatio }} {{ form.baseUnit }}
              </span>
            </template>
          </q-input>

          <!-- Цены и остатки -->
          <div class="row q-gutter-sm">
            <q-input
              v-model.number="form.costPrice"
              label="Цена закупа"
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

            <div class="col text-caption text-grey-7 flex items-center">
              ≈ {{ pricePerBaseUnit }} ₽/{{ form.baseUnit }}
            </div>
          </div>

          <div class="row q-gutter-sm">
            <q-input
              v-if="!isEdit"
              v-model.number="form.currentStock"
              label="Начальный остаток"
              outlined
              dense
              type="number"
              class="col"
              step="0.001"
            >
              <template v-slot:append>
                <span class="text-grey-7">{{ form.unit }}</span>
              </template>
            </q-input>

            <q-input
              v-model.number="form.minStock"
              label="Мин. остаток"
              outlined
              dense
              type="number"
              class="col"
              step="0.001"
            >
              <template v-slot:append>
                <span class="text-grey-7">{{ form.unit }}</span>
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
import { Ingredient } from 'src/types/ingredient.types';
import { ProductCategory } from 'src/types/product.types';

export default defineComponent({
  name: 'IngredientDialog',

  props: {
    item: {
      type: Object as () => Ingredient | null,
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
      { label: 'л (литр)', value: 'л' },
      { label: 'уп (упаковка)', value: 'уп' },
      { label: 'кор (коробка)', value: 'кор' }
    ];

    const baseUnitOptions = [
      { label: 'грамм (г)', value: 'г' },
      { label: 'миллилитр (мл)', value: 'мл' },
      { label: 'штука (шт)', value: 'шт' }
    ];

    const form = ref({
      name: '',
      categoryId: null as number | null,
      unit: 'кг',
      baseUnit: 'г',
      baseRatio: 1000,
      costPrice: 0,
      currentStock: 0,
      minStock: 0,
      description: ''
    });

    const pricePerBaseUnit = computed(() => {
      if (!form.value.costPrice || !form.value.baseRatio) return 0;
      return (form.value.costPrice / form.value.baseRatio).toFixed(2);
    });

    const canSubmit = computed(() => {
      return form.value.name && form.value.unit && form.value.baseRatio > 0;
    });

    watch(() => props.item, (val) => {
      if (val) {
        form.value = {
          name: val.name,
          categoryId: val.categoryId,
          unit: val.unit,
          baseUnit: val.baseUnit,
          baseRatio: val.baseRatio,
          costPrice: val.costPrice,
          currentStock: val.currentStock,
          minStock: val.minStock,
          description: val.description || ''
        };
        isEdit.value = true;
      } else {
        form.value = {
          name: '',
          categoryId: null,
          unit: 'кг',
          baseUnit: 'г',
          baseRatio: 1000,
          costPrice: 0,
          currentStock: 0,
          minStock: 0,
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
      baseUnitOptions,
      pricePerBaseUnit,
      canSubmit,
      show,
      hide,
      onSubmit,
      onDialogHide
    };
  }
});
</script>
