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
        <div class="text-h6">{{ isEdit ? 'Редактировать' : 'Создать' }} рецепт</div>
        <q-space />
        <q-btn flat round icon="close" @click="onCancel" />
      </q-card-section>

      <q-card-section>
        <q-form @submit="onSubmit" class="q-gutter-md">
          <div class="row q-gutter-sm">
            <q-select
              v-model="form.productId"
              :options="finishedProducts"
              option-label="name"
              option-value="id"
              label="Готовое блюдо *"
              outlined
              dense
              class="col-4"
              emit-value
              map-options
              :rules="[val => !!val || 'Выберите блюдо']"
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
              v-model.number="form.outputWeight"
              label="Выход блюда *"
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
            <q-input
              v-model.number="form.cookingTime"
              label="Время приготовления (мин)"
              type="number"
              outlined
              dense
              class="col-2"
            />
          </div>

          <q-input
            v-model="form.instructions"
            label="Инструкция"
            outlined
            dense
            type="textarea"
            rows="3"
          />

          <!-- Фото -->
          <div class="q-mb-md">
            <div class="text-subtitle2">Фото блюда</div>
            <q-file
              v-model="photoFile"
              label="Выберите изображение"
              accept=".jpg,.jpeg,.png"
              outlined
              dense
              @update:model-value="onFileSelected"
            >
              <template v-slot:prepend>
                <q-icon name="image" />
              </template>
            </q-file>
            <div v-if="photoPreview" class="q-mt-sm">
              <img :src="photoPreview" style="max-width: 200px; max-height: 150px;" />
            </div>
          </div>

          <!-- Состав рецепта -->
          <div class="text-subtitle2">Состав рецепта</div>

          <!-- Предварительный расчёт себестоимости -->
          <div v-if="form.ingredients.length > 0" class="bg-grey-2 q-pa-sm rounded-borders q-mb-md">
            <div class="row justify-between">
              <span class="text-weight-bold">Предварительная себестоимость:</span>
              <span class="text-weight-bold text-primary">{{ formatMoney(calculateTotalCost) }}</span>
            </div>
            <div class="text-caption text-grey-7">
              за 1 порцию ({{ form.outputWeight }} {{ form.outputUnit }})
            </div>
          </div>

          <div class="q-gutter-md" style="max-height: 400px; overflow-y: auto;">
            <recipe-ingredient-row
              v-for="(ing, idx) in form.ingredients"
              :key="idx"
              :ingredient="ing"
              :ingredients-list="ingredientsList"
              :index="idx"
              @update:ingredient="updateIngredient"
              @remove="removeIngredient"
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
import { Recipe, CreateRecipeDto, RecipeIngredient } from 'src/types/recipe.types';
import { Product } from 'src/types/product.types';
import RecipeIngredientRow from './RecipeIngredientRow.vue';

export default defineComponent({
  name: 'RecipeDialog',

  components: { RecipeIngredientRow },

  props: {
    modelValue: {
      type: Boolean,
      required: true
    },
    recipe: {
      type: Object as PropType<Recipe | null>,
      default: null
    },
    finishedProducts: {
      type: Array as PropType<Product[]>,
      default: () => []
    },
    ingredientsList: {
      type: Array as PropType<Product[]>,
      default: () => []
    }
  },

  emits: ['update:modelValue', 'ok', 'hide'],

  setup(props, { emit }) {
    const photoFile = ref<File | null>(null);
    const photoPreview = ref<string | null>(props.recipe?.photo || null);

    const onFileSelected = (file: File | null) => {
      if (!file) {
        photoPreview.value = null;
        return;
      }
      const reader = new FileReader();
      reader.onload = (e) => {
        photoPreview.value = e.target?.result as string;
      };
      reader.readAsDataURL(file);
    };

    const isEdit = computed(() => !!props.recipe);

    const form = ref<CreateRecipeDto>({
      productId: 0,
      name: '',
      outputWeight: 0,
      outputUnit: 'г',
      cookingTime: undefined,
      instructions: '',
      ingredients: [],
      photo: ''
    });

    const unitOptions = [
      { label: 'грамм', value: 'г' },
      { label: 'килограмм', value: 'кг' },
      { label: 'штука', value: 'шт' },
      { label: 'литр', value: 'л' },
      { label: 'миллилитр', value: 'мл' },
      { label: 'порция', value: 'порция' }
    ];

    const calculateTotalCost = computed(() => {
      if (!form.value.ingredients.length) return 0;

      return form.value.ingredients.reduce((total, ing) => {
        if (!ing.ingredientId) return total;

        const product = props.ingredientsList.find(p => p.id === ing.ingredientId);
        if (!product) return total;

        // Себестоимость за базовую единицу * количество
        const costPerBaseUnit = product.costPrice / (product.baseRatio || 1);
        return total + (costPerBaseUnit * ing.quantity);
      }, 0);
    });

    const canSubmit = computed(() => {
      // Проверяем основные поля
      if (!form.value.productId) return false;
      if (!form.value.name) return false;
      if (!form.value.outputWeight || form.value.outputWeight <= 0) return false;
      if (!form.value.outputUnit) return false;

      // Проверяем ингредиенты
      if (form.value.ingredients.length === 0) return false;

      return form.value.ingredients.every(ing =>
        ing.ingredientId && ing.quantity > 0 && ing.unit
      );
    });

    // Заполнение формы при редактировании
    watch(() => props.recipe, (val) => {
      if (val) {
        form.value = {
          productId: val.productId,
          name: val.name,
          outputWeight: val.outputWeight,
          outputUnit: val.outputUnit,
          cookingTime: val.cookingTime,
          instructions: val.instructions || '',
          ingredients: val.ingredients.map(ing => ({
            ingredientId: ing.ingredientId,
            quantity: ing.quantity,
            unit: ing.unit,
            isOptional: ing.isOptional
          })),
          photo: val.photo || ''
        };
        photoPreview.value = val.photo || null;
      } else {
        form.value = {
          productId: 0,
          name: '',
          outputWeight: 0,
          outputUnit: 'г',
          cookingTime: undefined,
          instructions: '',
          ingredients: [],
          photo: ''
        };
        photoPreview.value = null;
      }
    }, { immediate: true });

    const addIngredient = () => {
      form.value.ingredients.push({
        ingredientId: 0,
        quantity: 1,
        unit: 'г',
        isOptional: false
      });
    };

    const removeIngredient = (index: number) => {
      form.value.ingredients.splice(index, 1);
    };

    const updateIngredient = (index: number, updatedIng: Partial<RecipeIngredient>) => {
      // Обновляем ингредиент
      form.value.ingredients[index] = {
        ...form.value.ingredients[index],
        ...updatedIng
      };

      // Если изменился ingredientId, обновляем единицу измерения по умолчанию
      if (updatedIng.ingredientId) {
        const product = props.ingredientsList.find(p => p.id === updatedIng.ingredientId);
        if (product) {
          // Устанавливаем базовую единицу товара как единицу по умолчанию
          form.value.ingredients[index].unit = product.baseUnit || 'г';
        }
      }
    };

    const onSubmit = () => {
      if (!canSubmit.value) return;
      emit('ok', form.value);
      emit('update:modelValue', false);
    };

    const onCancel = () => {
      emit('update:modelValue', false);
    };

    const formatMoney = (value: number): string => {
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
      calculateTotalCost,
      canSubmit,
      addIngredient,
      removeIngredient,
      updateIngredient,
      onSubmit,
      onCancel,
      photoFile,
      photoPreview,
      onFileSelected,
      formatMoney
    };
  }
});
</script>
