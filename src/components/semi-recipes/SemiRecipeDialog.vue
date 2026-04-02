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
        <div class="text-h6">{{ isEdit ? 'Редактировать' : 'Создать' }} рецепт полуфабриката</div>
        <q-space />
        <q-btn flat round icon="close" @click="onCancel" />
      </q-card-section>

      <q-card-section>
        <q-form @submit="onSubmit" class="q-gutter-md">
          <!-- Выбор полуфабриката -->
          <div class="row q-gutter-sm">
            <q-select
              v-model="form.semiFinishedId"
              :options="semiFinishedList"
              option-label="name"
              option-value="id"
              label="Полуфабрикат *"
              outlined
              dense
              class="col-4"
              emit-value
              map-options
              :rules="[val => !!val || 'Выберите полуфабрикат']"
            />
            <q-input
              v-model="form.name"
              label="Название рецепта *"
              outlined
              dense
              class="col-4"
              :rules="[val => !!val || 'Введите название']"
            />
          </div>

          <div class="row q-gutter-sm">
            <q-input
              v-model.number="form.outputQuantity"
              label="Выход *"
              type="number"
              outlined
              dense
              class="col-2"
              :rules="[val => val > 0 || 'Введите положительное число']"
            />
            <q-select
              v-model="form.outputUnit"
              :options="unitOptions"
              label="Ед. измерения *"
              outlined
              dense
              class="col-2"
              emit-value
              map-options
            />
          </div>

          <q-input
            v-model="form.instructions"
            label="Инструкция"
            outlined
            dense
            type="textarea"
            rows="2"
          />

          <!-- Состав -->
          <div class="text-subtitle2 q-mt-md">Состав</div>

          <div v-if="form.ingredients.length > 0" class="bg-grey-2 q-pa-sm rounded-borders q-mb-md">
            <div class="row justify-between">
              <span class="text-weight-bold">Себестоимость:</span>
              <span class="text-weight-bold text-primary">{{ formatMoney(totalCost) }}</span>
            </div>
            <div class="text-caption text-grey-7">
              за {{ form.outputQuantity }} {{ form.outputUnit }}
            </div>
          </div>

          <div class="q-gutter-md" style="max-height: 400px; overflow-y: auto;">
            <div v-if="form.ingredients.length === 0" class="text-center text-grey-7 q-pa-md">
              Добавьте ингредиенты в состав
            </div>

            <semi-recipe-ingredient-row
              v-for="(ing, idx) in form.ingredients"
              :key="idx"
              :ingredient="ing"
              :ingredients="ingredientsList"
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
import { CreateSemiRecipeDto } from 'src/types/semi-recipe.types';
import SemiRecipeIngredientRow from './SemiRecipeIngredientRow.vue';

export default defineComponent({
  name: 'SemiRecipeDialog',

  components: { SemiRecipeIngredientRow },

  props: {
    modelValue: { type: Boolean, required: true },
    recipe: { type: Object as PropType<any>, default: null },
    semiFinishedList: { type: Array as PropType<SemiFinished[]>, required: true },
    ingredientsList: { type: Array as PropType<Ingredient[]>, required: true }
  },

  emits: ['update:modelValue', 'ok', 'hide'],

  setup(props, { emit }) {
    const isEdit = computed(() => !!props.recipe);

    const unitOptions = [
      { label: 'кг', value: 'кг' }, { label: 'шт', value: 'шт' },
      { label: 'л', value: 'л' }, { label: 'мл', value: 'мл' },
      { label: 'г', value: 'г' }, { label: 'уп', value: 'уп' }
    ];

    const form = ref<CreateSemiRecipeDto>({
      semiFinishedId: 0,
      name: '',
      outputQuantity: 0,
      outputUnit: 'г',
      instructions: '',
      ingredients: []
    });

    const totalCost = computed(() => {
      return form.value.ingredients.reduce((sum, ing) => {
        const ingredient = props.ingredientsList.find(i => i.id === ing.ingredientId);
        if (!ingredient) return sum;
        const pricePerBaseUnit = ingredient.costPrice / ingredient.baseRatio;
        return sum + (pricePerBaseUnit * ing.quantity);
      }, 0);
    });

    const canSubmit = computed(() => {
      if (!form.value.semiFinishedId) return false;
      if (!form.value.name) return false;
      if (!form.value.outputQuantity || form.value.outputQuantity <= 0) return false;
      if (!form.value.outputUnit) return false;
      if (form.value.ingredients.length === 0) return false;
      return form.value.ingredients.every(ing => ing.ingredientId && ing.quantity > 0);
    });

    watch(() => props.recipe, (val) => {
      if (val) {
        form.value = {
          semiFinishedId: val.semiFinishedId,
          name: val.name,
          outputQuantity: val.outputQuantity,
          outputUnit: val.outputUnit,
          instructions: val.instructions || '',
          ingredients: val.ingredients.map((ing: any) => ({
            ingredientId: ing.ingredientId,
            quantity: ing.quantity
          }))
        };
      } else {
        form.value = {
          semiFinishedId: 0,
          name: '',
          outputQuantity: 0,
          outputUnit: 'г',
          instructions: '',
          ingredients: []
        };
      }
    }, { immediate: true });

    const addIngredient = () => {
      form.value.ingredients.push({ ingredientId: 0, quantity: 1 });
    };

    const removeIngredient = (index: number) => {
      form.value.ingredients.splice(index, 1);
    };

    const updateIngredient = (index: number, updated: any) => {
      form.value.ingredients[index] = updated;
    };

    const onSubmit = () => {
      emit('ok', form.value);
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
