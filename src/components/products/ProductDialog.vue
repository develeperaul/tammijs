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
            @update:model-value="onTypeChange"
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

          <!-- Единицы измерения с пояснениями -->
          <div class="row q-gutter-sm">
            <!-- Единица хранения (для склада) -->
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
            >
              <template v-slot:append>
                <q-icon name="info">
                  <q-tooltip>
                    {{ unitTooltip }}
                  </q-tooltip>
                </q-icon>
              </template>
            </q-select>

            <!-- Базовая единица (для рецептов) -->
            <q-select
              v-model="form.baseUnit"
              :options="baseUnitOptions"
              label="Базовая ед. *"
              outlined
              dense
              class="col"
              emit-value
              map-options
              :rules="[val => !!val || 'Обязательное поле']"
            >
              <template v-slot:append>
                <q-icon name="info">
                  <q-tooltip>
                    {{ baseUnitTooltip }}
                  </q-tooltip>
                </q-icon>
              </template>
            </q-select>
          </div>

          <!-- Коэффициент пересчёта с пояснением -->
          <q-input
            v-model.number="form.baseRatio"
            label="Коэффициент *"
            outlined
            dense
            type="number"
            step="0.01"
            :rules="[val => val > 0 || 'Введите положительное число']"
          >
            <template v-slot:append>
              <q-icon name="info">
                <q-tooltip>
                  {{ ratioTooltip }}
                </q-tooltip>
              </q-icon>
            </template>
          </q-input>

          <!-- Пример пересчёта -->
          <div v-if="form.unit && form.baseUnit && form.baseRatio" class="text-caption bg-grey-2 q-pa-sm rounded-borders">
            <div class="text-weight-bold">Пример:</div>
            <div>1 {{ form.unit }} = {{ form.baseRatio }} {{ form.baseUnit }}</div>
            <div v-if="form.costPrice">Цена за 1 {{ form.baseUnit }}: {{ (form.costPrice / form.baseRatio).toFixed(2) }} ₽</div>
          </div>

          <!-- Цена закупа (для всех) -->
          <q-input
            v-model.number="form.costPrice"
            label="Цена закупа"
            outlined
            dense
            type="number"
            step="0.01"
          >
            <template v-slot:append>
              <span class="text-grey-7">за 1 {{ form.unit }}</span>
            </template>
          </q-input>

          <!-- Цена продажи (только для готовых блюд) -->
          <q-input
            v-if="form.type === 'готовое'"
            v-model.number="form.sellingPrice"
            label="Цена продажи"
            outlined
            dense
            type="number"
            step="0.01"
          >
            <template v-slot:append>
              <span class="text-grey-7">за порцию</span>
            </template>
          </q-input>

          <!-- Остатки -->
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
        <q-btn flat label="Сохранить" color="positive" @click="onSubmit" />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script lang="ts">
import { defineComponent, ref, watch, computed } from 'vue';
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

    // Опции для выпадающих списков
    const typeOptions = [
      { label: '🧂 Ингредиент', value: 'ингредиент' },
      { label: '🍱 Готовое блюдо', value: 'готовое' },
      { label: '🥘 Полуфабрикат', value: 'полуфабрикат' }
    ];

    const unitOptions = [
      { label: '📦 кг (килограмм)', value: 'кг' },
      { label: '📦 шт (штука)', value: 'шт' },
      { label: '📦 л (литр)', value: 'л' },
      { label: '📦 уп (упаковка)', value: 'уп' },
      { label: '📦 кор (коробка)', value: 'кор' }
    ];

    const baseUnitOptions = [
      { label: '⚖️ г (грамм)', value: 'г' },
      { label: '🥤 мл (миллилитр)', value: 'мл' },
      { label: '🔢 шт (штука)', value: 'шт' }
    ];

    // Вычисляемые подсказки
    const unitTooltip = computed(() => {
      if (form.value.type === 'ингредиент') {
        return 'Как товар хранится на складе: мешки, коробки, бутылки';
      }
      return 'Единица, в которой учитывается остаток на складе';
    });

    const baseUnitTooltip = computed(() => {
      if (form.value.type === 'ингредиент') {
        return 'В каких единицах указываете ингредиент в рецептах (обычно граммы)';
      }
      return 'Базовая единица для расчётов';
    });

    const ratioTooltip = computed(() => {
      if (form.value.unit && form.value.baseUnit) {
        return `Сколько ${form.value.baseUnit} в 1 ${form.value.unit}`;
      }
      return 'Коэффициент пересчёта единиц';
    });

    const form = ref<CreateProductDto>({
      name: '',
      type: 'ингредиент',
      unit: 'кг',
      baseUnit: 'г',
      baseRatio: 1000,
      costPrice: 0,
      sellingPrice: 0,
      currentStock: 0,
      minStock: 0,
      categoryId: undefined,
      description: ''
    });

    // Обработчик смены типа
    const onTypeChange = (type: string) => {
      if (type === 'ингредиент') {
        form.value.sellingPrice = 0;
        // Для ингредиентов по умолчанию ставим разумные значения
        if (form.value.unit === 'кг') {
          form.value.baseUnit = 'г';
          form.value.baseRatio = 1000;
        } else if (form.value.unit === 'л') {
          form.value.baseUnit = 'мл';
          form.value.baseRatio = 1000;
        } else {
          form.value.baseUnit = 'шт';
          form.value.baseRatio = 1;
        }
      } else if (type === 'готовое') {
        // Для готовых блюд базовая единица обычно совпадает с единицей порции
        form.value.baseUnit = 'шт';
        form.value.baseRatio = 1;
      }
    };

    // Обработчик смены единицы хранения
    watch(() => form.value.unit, (newUnit) => {
      if (form.value.type === 'ингредиент') {
        if (newUnit === 'кг') {
          form.value.baseUnit = 'г';
          form.value.baseRatio = 1000;
        } else if (newUnit === 'л') {
          form.value.baseUnit = 'мл';
          form.value.baseRatio = 1000;
        } else if (newUnit === 'шт' || newUnit === 'уп' || newUnit === 'кор') {
          form.value.baseUnit = 'шт';
          form.value.baseRatio = 1;
        }
      }
    });

    // Заполнение формы при редактировании
    watch(() => props.product, (newVal) => {
      if (newVal) {
        form.value = {
          name: newVal.name,
          type: newVal.type === 'ingredient' ? 'ингредиент' :
                newVal.type === 'finished' ? 'готовое' : 'полуфабрикат',
          unit: newVal.unitLabel || newVal.unit || 'кг',
          baseUnit: newVal.baseUnitLabel || newVal.baseUnit || 'г',
          baseRatio: newVal.baseRatio || 1000,
          costPrice: newVal.costPrice || 0,
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
          type: 'ингредиент',
          unit: 'кг',
          baseUnit: 'г',
          baseRatio: 1000,
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
    const dataToSend = {
      name: form.value.name,
      type: form.value.type,              // уже 'ингредиент', 'готовое' или 'полуфабрикат'
      unit: form.value.unit,               // уже 'кг', 'шт', 'л' и т.д.
      baseUnit: form.value.baseUnit,       // уже 'г', 'мл', 'шт'
      baseRatio: form.value.baseRatio,
      costPrice: form.value.costPrice,
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
      unitOptions,
      baseUnitOptions,
      unitTooltip,
      baseUnitTooltip,
      ratioTooltip,
      onTypeChange,
      show,
      hide,
      onSubmit,
      onDialogHide
    };
  }
});
</script>
