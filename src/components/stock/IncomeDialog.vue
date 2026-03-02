<template>
  <q-dialog ref="dialog" @hide="onDialogHide">
    <q-card style="min-width: 500px">
      <q-card-section>
        <div class="text-h6">Приход товара</div>
      </q-card-section>

      <q-card-section v-if="product">
        <div class="text-subtitle2">{{ product.name }}</div>
        <div class="text-caption">
          Текущий остаток:
          <strong>{{ product.currentStock }} {{ product.unit }}</strong>
          <span class="text-grey-7 q-ml-sm">
            ({{ (product.currentStock * (product.baseRatio || 1)).toFixed(0) }} {{ product.baseUnit }})
          </span>
        </div>
      </q-card-section>

      <q-card-section>
        <q-form @submit="onSubmit" class="q-gutter-md">
          <!-- Выбор товара (если не передан конкретный) -->
          <q-select
            v-if="!product"
            v-model="selectedProductId"
            :options="products"
            option-label="name"
            option-value="id"
            label="Выберите товар *"
            outlined
            dense
            emit-value
            map-options
            :rules="[val => !!val || 'Выберите товар']"
            @update:model-value="onProductSelect"
          >
            <template v-slot:option="scope">
              <q-item v-bind="scope.itemProps">
                <q-item-section>
                  <q-item-label>{{ scope.opt.name }}</q-item-label>
                  <q-item-label caption>
                    Текущий остаток: {{ scope.opt.currentStock }} {{ scope.opt.unit }}
                    ({{ (scope.opt.currentStock * (scope.opt.baseRatio || 1)).toFixed(0) }} {{ scope.opt.baseUnit }})
                  </q-item-label>
                </q-item-section>
              </q-item>
            </template>
          </q-select>

          <!-- Информация о выбранном товаре -->
          <div v-if="selectedProductInfo" class="text-caption bg-grey-2 q-pa-sm rounded-borders">
            <div class="row items-center">
              <q-icon name="info" size="sm" color="primary" class="q-mr-sm" />
              <span>
                1 {{ selectedProductInfo.unit }} = {{ selectedProductInfo.baseRatio }} {{ selectedProductInfo.baseUnit }}
              </span>
            </div>
          </div>

          <!-- Выбор единицы измерения для ввода -->
          <div class="row items-center q-gutter-sm">
            <q-option-group
              v-model="selectedUnitType"
              :options="unitTypeOptions"
              color="primary"
              inline
              dense
            />
          </div>

          <!-- Ввод количества с выбранной единицей -->
          <div class="row q-gutter-sm items-end">
            <q-input
              v-model.number="form.quantity"
              :label="`Количество *`"
              type="number"
              outlined
              dense
              class="col"
              step="0.001"
              :rules="[val => val > 0 || 'Введите положительное число']"
            >
              <template v-slot:append>
                <span class="text-grey-7">{{ selectedUnitLabel }}</span>
              </template>
            </q-input>

            <!-- Пересчёт в другую единицу -->
            <div v-if="selectedProductInfo && form.quantity" class="col text-caption text-grey-7">
              ≈ {{ convertedQuantity }} {{ otherUnitLabel }}
            </div>
          </div>

          <!-- Цена закупки (с выбором единицы) -->
          <div class="row q-gutter-sm items-end">
            <q-input
              v-model.number="form.price"
              :label="`Цена закупки`"
              type="number"
              outlined
              dense
              class="col"
              step="0.01"
            >
              <template v-slot:append>
                <span class="text-grey-7">₽ / {{ selectedPriceUnitLabel }}</span>
              </template>
            </q-input>

            <!-- Пересчёт цены в другую единицу -->
            <div v-if="selectedProductInfo && form.price" class="col text-caption text-grey-7">
              ≈ {{ convertedPrice }} ₽ / {{ otherPriceUnitLabel }}
            </div>
          </div>

          <!-- Тип документа -->
          <q-select
            v-model="form.documentType"
            :options="documentOptions"
            label="Тип документа"
            outlined
            dense
            emit-value
            map-options
          />

          <!-- Номер накладной (только для invoice) -->
          <q-input
            v-if="form.documentType === 'invoice'"
            v-model="form.documentNumber"
            label="Номер накладной *"
            outlined
            dense
            :rules="[val => form.documentType !== 'invoice' || !!val || 'Введите номер']"
          />

          <!-- Комментарий -->
          <q-input
            v-model="form.comment"
            label="Комментарий"
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
import { Product } from 'src/types/product.types';

export default defineComponent({
  name: 'IncomeDialog',

  props: {
    product: {
      type: Object as () => Product | null,
      default: null
    },
    products: {
      type: Array as () => Product[],
      default: () => []
    }
  },

  emits: ['ok', 'hide'],

  setup(props, { emit }) {
    const dialog = ref<any>(null);
    const selectedProductId = ref<number | null>(props.product?.id || null);
    const selectedUnitType = ref<'unit' | 'baseUnit'>('baseUnit'); // по умолчанию baseUnit

    const form = ref({
      quantity: 1,
      price: 0,
      comment: '',
      documentType: 'manual',
      documentNumber: ''
    });

    const unitTypeOptions = [
      { label: 'Ввести в единицах хранения', value: 'unit' },
      { label: 'Ввести в базовых единицах', value: 'baseUnit' }
    ];

    const selectedProductInfo = computed(() => {
      if (props.product) return props.product;
      if (selectedProductId.value) {
        return props.products.find(p => p.id === selectedProductId.value);
      }
      return null;
    });

    const ratio = computed(() => selectedProductInfo.value?.baseRatio || 1);

    // Метки для отображения
    const selectedUnitLabel = computed(() => {
      if (!selectedProductInfo.value) return '';
      return selectedUnitType.value === 'unit'
        ? selectedProductInfo.value.unit
        : selectedProductInfo.value.baseUnit;
    });

    const otherUnitLabel = computed(() => {
      if (!selectedProductInfo.value) return '';
      return selectedUnitType.value === 'unit'
        ? selectedProductInfo.value.baseUnit
        : selectedProductInfo.value.unit;
    });

    const selectedPriceUnitLabel = computed(() => {
      if (!selectedProductInfo.value) return '';
      return selectedUnitType.value === 'unit'
        ? selectedProductInfo.value.unit
        : selectedProductInfo.value.baseUnit;
    });

    const otherPriceUnitLabel = computed(() => {
      if (!selectedProductInfo.value) return '';
      return selectedUnitType.value === 'unit'
        ? selectedProductInfo.value.baseUnit
        : selectedProductInfo.value.unit;
    });

    // Пересчёт количества
    const convertedQuantity = computed(() => {
      if (!selectedProductInfo.value || !form.value.quantity) return 0;

      if (selectedUnitType.value === 'unit') {
        // Из unit в baseUnit
        return (form.value.quantity * ratio.value).toFixed(1);
      } else {
        // Из baseUnit в unit
        return (form.value.quantity / ratio.value).toFixed(3);
      }
    });

    // Пересчёт цены
    const convertedPrice = computed(() => {
      if (!selectedProductInfo.value || !form.value.price) return 0;

      if (selectedUnitType.value === 'unit') {
        // Цена за unit -> цена за baseUnit
        return (form.value.price / ratio.value).toFixed(2);
      } else {
        // Цена за baseUnit -> цена за unit
        return (form.value.price * ratio.value).toFixed(2);
      }
    });

    const documentOptions = [
      { label: '📝 Ручной ввод', value: 'manual' },
      { label: '📄 Накладная', value: 'invoice' }
    ];

    const canSubmit = computed(() => {
      if (form.value.quantity <= 0) return false;
      if (!props.product && !selectedProductId.value) return false;
      if (form.value.documentType === 'invoice' && !form.value.documentNumber) return false;
      return true;
    });

    const show = () => {
      dialog.value?.show();
    };

    const hide = () => {
      dialog.value?.hide();
    };

    const onProductSelect = () => {
      // Сбрасываем форму при выборе нового товара
      form.value.quantity = 1;
      form.value.price = 0;
    };

    const onSubmit = () => {
      const productInfo = selectedProductInfo.value;
      if (!productInfo) return;

      let quantityInStorageUnits: number;
      let priceInStorageUnits: number;

      if (selectedUnitType.value === 'unit') {
        // Уже в единицах хранения
        quantityInStorageUnits = form.value.quantity;
        priceInStorageUnits = form.value.price;
      } else {
        // Конвертируем из базовых единиц
        quantityInStorageUnits = form.value.quantity / ratio.value;
        priceInStorageUnits = form.value.price * ratio.value;
      }

      emit('ok', {
        productId: productInfo.id,
        quantity: quantityInStorageUnits,
        price: priceInStorageUnits,
        documentType: form.value.documentType,
        documentId: form.value.documentType === 'invoice' ? parseInt(form.value.documentNumber) || 0 : 0,
        comment: form.value.comment
      });
      hide();
    };

    const onDialogHide = () => {
      form.value = {
        quantity: 1,
        price: 0,
        comment: '',
        documentType: 'manual',
        documentNumber: ''
      };
      selectedProductId.value = props.product?.id || null;
      selectedUnitType.value = 'baseUnit';
      emit('hide');
    };

    watch(() => props.product, (newVal) => {
      selectedProductId.value = newVal?.id || null;
    });

    return {
      dialog,
      selectedProductId,
      selectedProductInfo,
      selectedUnitType,
      unitTypeOptions,
      form,
      selectedUnitLabel,
      otherUnitLabel,
      selectedPriceUnitLabel,
      otherPriceUnitLabel,
      convertedQuantity,
      convertedPrice,
      documentOptions,
      canSubmit,
      show,
      hide,
      onProductSelect,
      onSubmit,
      onDialogHide
    };
  }
});
</script>
