<template>
  <q-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    @hide="$emit('hide')"
    persistent
    maximized
  >
    <q-card>
      <q-card-section class="row items-center">
        <div class="text-h6">{{ isEdit ? 'Редактировать' : 'Создать' }} полуфабрикат</div>
        <q-space />
        <q-btn flat round icon="close" @click="onCancel" />
      </q-card-section>

      <q-card-section>
        <q-form @submit="onSubmit" class="q-gutter-md">
          <!-- Основная информация -->
          <div class="row q-gutter-sm">
            <q-input
              v-model="form.name"
              label="Название *"
              outlined
              dense
              class="col-6"
              :rules="[val => !!val || 'Введите название']"
            />
            <q-select
              v-model="form.unit"
              :options="unitOptions"
              label="Единица измерения *"
              outlined
              dense
              class="col-2"
              emit-value
              map-options
              :rules="[val => !!val || 'Выберите единицу']"
            />
            <q-input
              v-model.number="form.sellingPrice"
              label="Цена продажи"
              outlined
              dense
              type="number"
              class="col-2"
              step="0.01"
            />
          </div>

          <!-- Состав полуфабриката -->
          <div class="text-subtitle2 q-mt-md">Состав полуфабриката</div>

          <!-- Предварительный расчёт себестоимости -->
          <div v-if="form.ingredients.length > 0" class="bg-grey-2 q-pa-sm rounded-borders q-mb-md">
            <div class="row justify-between">
              <span class="text-weight-bold">Себестоимость:</span>
              <span class="text-weight-bold text-primary">{{ formatMoney(totalCost) }}</span>
            </div>
            <div class="row justify-between text-caption text-grey-7">
              <span>Цена продажи:</span>
              <span :class="totalCost > form.sellingPrice ? 'text-negative' : 'text-positive'">
                {{ formatMoney(form.sellingPrice) }}
                ({{ profitMargin }}% маржинальность)
              </span>
            </div>
          </div>

          <div class="q-gutter-md" style="max-height: 400px; overflow-y: auto;">
            <div v-if="form.ingredients.length === 0" class="text-center text-grey-7 q-pa-md">
              Добавьте ингредиенты в состав полуфабриката
            </div>

            <semi-ingredient-row
              v-for="(ing, idx) in form.ingredients"
              :key="idx"
              :item="ing"
              :ingredients="ingredientsList"
              :semi-finished="semiFinishedList"
              @update="(updated) => updateIngredient(idx, updated)"
              @remove="removeIngredient(idx)"
            />
          </div>

          <q-btn
            flat
            color="primary"
            icon="add"
            label="Добавить ингредиент"
            @click="addIngredient"
          />
        </q-form>
      </q-card-section>

      <q-card-actions align="right">
        <q-btn flat label="Отмена" color="negative" @click="onCancel" />
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
import { defineComponent, PropType, ref, computed, watch } from 'vue';
import { SemiFinished } from 'src/types/semi-finished.types';
import { Ingredient } from 'src/types/ingredient.types';
import SemiIngredientRow from './SemiIngredientRow.vue';

export default defineComponent({
  name: 'SemiFinishedDialog',

  components: { SemiIngredientRow },

  props: {
    modelValue: { type: Boolean, required: true },
    item: { type: Object as PropType<SemiFinished | null>, default: null },
    ingredientsList: { type: Array as PropType<Ingredient[]>, required: true },
    semiFinishedList: { type: Array as PropType<SemiFinished[]>, default: () => [] }
  },

  emits: ['update:modelValue', 'ok', 'hide'],

  setup(props, { emit }) {
    const isEdit = computed(() => !!props.item);

    const unitOptions = [
      { label: 'кг (килограмм)', value: 'кг' },
      { label: 'шт (штука)', value: 'шт' },
      { label: 'л (литр)', value: 'л' },
      { label: 'мл (миллилитр)', value: 'мл' },
      { label: 'уп (упаковка)', value: 'уп' }
    ];

    const form = ref({
      name: '',
      unit: 'шт',
      sellingPrice: 0,
      ingredients: [] as Array<{
        itemType: 'ingredient' | 'semi-finished';
        itemId: number;
        quantity: number;
        unit: string;
      }>
    });

    const totalCost = computed(() => {
      return form.value.ingredients.reduce((sum, ing) => {
        if (ing.itemType === 'ingredient') {
          const ingredient = props.ingredientsList.find(i => i.id === ing.itemId);
          if (!ingredient) return sum;
          const pricePerBaseUnit = ingredient.costPrice / ingredient.baseRatio;
          return sum + (pricePerBaseUnit * ing.quantity);
        } else {
          const semi = props.semiFinishedList.find(s => s.id === ing.itemId);
          if (!semi) return sum;
          return sum + (semi.costPrice * ing.quantity);
        }
      }, 0);
    });

    const profitMargin = computed(() => {
      if (!form.value.sellingPrice || !totalCost.value) return 0;
      return (((form.value.sellingPrice - totalCost.value) / form.value.sellingPrice) * 100).toFixed(1);
    });

    const canSubmit = computed(() => {
      if (!form.value.name || !form.value.unit) return false;
      if (form.value.ingredients.length === 0) return false;

      return form.value.ingredients.every(ing =>
        ing.itemId && ing.itemId !== 0 && ing.quantity > 0
      );
    });

    const addIngredient = () => {
      form.value.ingredients.push({
        itemType: 'ingredient',
        itemId: 0,
        quantity: 1,
        unit: ''
      });
    };

    const removeIngredient = (index: number) => {
      form.value.ingredients = form.value.ingredients.filter((_, i) => i !== index);
    };

    const updateIngredient = (index: number, updated: any) => {
      const newIngredients = [...form.value.ingredients];
      newIngredients[index] = updated;
      form.value.ingredients = newIngredients;
    };

    // Заполнение при редактировании
    watch(() => props.item, (val) => {
  console.log('Editing item (raw):', JSON.parse(JSON.stringify(val))); // отладка

  if (val) {
    form.value = {
      name: val.name,
      unit: val.unit || 'шт',
      sellingPrice: val.sellingPrice || 0,
      ingredients: (val.ingredients || []).map(ing => ({
        itemType: ing.itemType || 'ingredient',
        // ✅ ключевое исправление: ingredientId → itemId
        itemId: ing.ingredientId || ing.itemId || 0,
        quantity: ing.quantity,
        unit: ing.unit || ''
      }))
    };
    console.log('Form ingredients after mapping:', form.value.ingredients);
  } else {
    form.value = {
      name: '',
      unit: 'шт',
      sellingPrice: 0,
      ingredients: []
    };
  }
}, { immediate: true, deep: true });

    const onSubmit = () => {
      const dataToSend = {
        name: form.value.name,
        unit: form.value.unit,
        sellingPrice: form.value.sellingPrice,
        ingredients: form.value.ingredients
          .filter(ing => ing.itemId && ing.itemId !== 0)
          .map(ing => ({
            ingredientId: ing.itemId,
            quantity: ing.quantity,
            unit: ing.unit,
            itemType: ing.itemType
          }))
      };

      emit('ok', dataToSend);
      emit('update:modelValue', false);
    };

    const onCancel = () => {
      emit('update:modelValue', false);
    };

    const formatMoney = (value: number) => {
      return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        minimumFractionDigits: 2
      }).format(value);
    };

    return {
      isEdit,
      form,
      unitOptions,
      totalCost,
      profitMargin,
      canSubmit,
      addIngredient,
      removeIngredient,
      updateIngredient,
      onSubmit,
      onCancel,
      formatMoney
    };
  }
});
</script>
