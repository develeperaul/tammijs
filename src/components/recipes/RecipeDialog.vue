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
          <div class="text-subtitle2">Состав рецепта</div>

          <div class="q-gutter-md">
            <recipe-ingredient-row
              v-for="(ing, idx) in form.ingredients"
              :key="idx"
              :ingredient="ing"
              :ingredients-list="ingredientsList"
              @update:ingredient="updateIngredient(idx, $event)"
              @remove="removeIngredient(idx)"
            />

            <q-btn
              flat
              color="primary"
              icon="add"
              label="Добавить ингредиент"
              @click="addIngredient"
            />
          </div>
        </q-form>
      </q-card-section>

      <q-card-actions align="right">
        <q-btn flat label="Отмена" color="negative" @click="onCancel" />
        <q-btn flat label="Сохранить" color="positive" @click="onSubmit" />
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

    const unitOptions = ['г', 'кг', 'шт', 'л', 'мл', 'порция'].map(u => ({ label: u, value: u }));

    // Заполнение формы при редактировании
    watch(() => props.recipe, (val) => {
      if (val) {
        form.value = {
          productId: val.productId,
          name: val.name,
          outputWeight: val.outputWeight,
          outputUnit: val.outputUnit,
          cookingTime: val.cookingTime,
          instructions: val.instructions,
          ingredients: val.ingredients.map(ing => ({
            ingredientId: ing.ingredientId,
            quantity: ing.quantity,
            unit: ing.unit,
            isOptional: ing.isOptional
          }))
        };
      } else {
        form.value = {
          productId: 0,
          name: '',
          outputWeight: 0,
          outputUnit: 'г',
          cookingTime: undefined,
          instructions: '',
          ingredients: []
        };
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
      form.value.ingredients[index] = {
        ...form.value.ingredients[index],
        ...updatedIng
      };
    };

    const onSubmit = () => {
      emit('ok', form.value);
      emit('update:modelValue', false);
    };

    const onCancel = () => {
      emit('update:modelValue', false);
    };

    return {
      isEdit,
      form,
      unitOptions,
      addIngredient,
      removeIngredient,
      updateIngredient,
      onSubmit,
      onCancel,
      photoFile,
      photoPreview,
      onFileSelected
    };
  }
});
</script>
