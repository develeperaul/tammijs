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
          <!-- Основная информация -->
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
            rows="2"
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
          <div v-if="form.items.length > 0" class="bg-grey-2 q-pa-sm rounded-borders q-mb-md">
            <div class="row justify-between">
              <span class="text-weight-bold">Себестоимость:</span>
              <span class="text-weight-bold text-primary">{{ formatMoney(totalCost) }}</span>
            </div>
            <div class="row justify-between text-caption text-grey-7">
              <span>на {{ form.outputWeight }} {{ form.outputUnit }}</span>
            </div>
          </div>

          <div class="q-gutter-md" style="max-height: 400px; overflow-y: auto;">
            <div v-if="form.items.length === 0" class="text-center text-grey-7 q-pa-md">
              Добавьте ингредиенты или полуфабрикаты в состав
            </div>

            <recipe-item-row
              v-for="(item, idx) in form.items"
              :key="idx"
              :item="item"
              :ingredients="ingredientsList"
              :semi-finished="semiFinishedList"
              @update="(updated) => updateItem(idx, updated)"
              @remove="removeItem(idx)"
            />
          </div>

          <q-btn
            flat
            color="primary"
            icon="add"
            label="Добавить ингредиент/полуфабрикат"
            @click="addItem"
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
import { Recipe, CreateRecipeDto, RecipeItem } from 'src/types/recipe.types';
import { Product } from 'src/types/product.types';
import { Ingredient } from 'src/types/ingredient.types';
import { SemiFinished } from 'src/types/semi-finished.types';
import RecipeItemRow from './RecipeIngredientRow.vue';

export default defineComponent({
  name: 'RecipeDialog',

  components: { RecipeItemRow },

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
      type: Array as PropType<Ingredient[]>,
      required: true
    },
    semiFinishedList: {
      type: Array as PropType<SemiFinished[]>,
      required: true
    }
  },

  emits: ['update:modelValue', 'ok', 'hide'],

  setup(props, { emit }) {
    const photoFile = ref<File | null>(null);
    const photoPreview = ref<string | null>(null);
    const isEdit = computed(() => !!props.recipe);

    const unitOptions = [
      { label: 'грамм', value: 'г' },
      { label: 'килограмм', value: 'кг' },
      { label: 'штука', value: 'шт' },
      { label: 'литр', value: 'л' },
      { label: 'миллилитр', value: 'мл' },
      { label: 'порция', value: 'порция' }
    ];

    const form = ref<CreateRecipeDto>({
      productId: 0,
      name: '',
      outputWeight: 0,
      outputUnit: 'г',
      cookingTime: undefined,
      instructions: '',
      items: [],
      photo: ''
    });

    const totalCost = computed(() => {
      return form.value.items.reduce((sum, item) => {
        if (item.itemType === 'ingredient') {
          const ing = props.ingredientsList.find(i => i.id === item.itemId);
          if (!ing) return sum;
          const pricePerBaseUnit = ing.costPrice / ing.baseRatio;
          return sum + (pricePerBaseUnit * item.quantity);
        } else {
          const semi = props.semiFinishedList.find(s => s.id === item.itemId);
          if (!semi) return sum;
          return sum + (semi.costPrice * item.quantity);
        }
      }, 0);
    });

    const canSubmit = computed(() => {
  console.log('=== Проверка canSubmit ===');
  console.log('productId:', form.value.productId);
  console.log('name:', form.value.name);
  console.log('outputWeight:', form.value.outputWeight);
  console.log('outputUnit:', form.value.outputUnit);
  console.log('items length:', form.value.items.length);

  form.value.items.forEach((item, idx) => {
    console.log(`item[${idx}]:`, {
      itemId: item.itemId,
      quantity: item.quantity,
      unit: item.unit
    });
  });

  if (!form.value.productId) return false;
  if (!form.value.name) return false;
  if (!form.value.outputWeight || form.value.outputWeight <= 0) return false;
  if (!form.value.outputUnit) return false;
  if (form.value.items.length === 0) return false;

  const allValid = form.value.items.every(item =>
    item.itemId && item.quantity > 0
  );

  console.log('allValid:', allValid);
  return allValid;
});

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

    watch(() => props.recipe, (val) => {
      if (val) {
        form.value = {
          productId: val.productId,
          name: val.name,
          outputWeight: val.outputWeight,
          outputUnit: val.outputUnit,
          cookingTime: val.cookingTime,
          instructions: val.instructions || '',
          items: val.items.map(item => ({
            itemType: item.itemType,
            itemId: item.itemId,
            quantity: item.quantity,
            unit: item.unit,
            isOptional: item.isOptional
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
          items: [],
          photo: ''
        };
        photoPreview.value = null;
      }
    }, { immediate: true });

    const addItem = () => {
      form.value.items.push({
        itemType: 'ingredient',
        itemId: 0,
        quantity: 1,
        unit: 'г',
        isOptional: false
      });
    };

    const removeItem = (index: number) => {
      form.value.items.splice(index, 1);
    };

    const updateItem = (index: number, updatedItem: RecipeItem) => {
      form.value.items[index] = updatedItem;
    };

    const onSubmit = () => {
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
      totalCost,
      canSubmit,
      photoFile,
      photoPreview,
      onFileSelected,
      addItem,
      removeItem,
      updateItem,
      onSubmit,
      onCancel,
      formatMoney
    };
  }
});
</script>
