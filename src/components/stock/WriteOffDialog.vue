<template>
  <q-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    @hide="$emit('hide')"
  >
    <q-card style="min-width: 500px">
      <q-card-section>
        <div class="text-h6">Списание товаров</div>
      </q-card-section>

      <q-card-section>
        <q-form @submit="onSubmit" class="q-gutter-md">
          <!-- Режим: конкретный товар -->
          <div v-if="product">
            <div class="row items-center q-mb-sm">
              <q-badge color="primary" class="q-mr-sm">Товар:</q-badge>
              <span class="text-subtitle1">{{ product.name }}</span>
            </div>
            <div class="row items-center q-gutter-md">
              <div class="text-caption">
                Текущий остаток:
                <strong>{{ product.currentStock }} {{ product.unit }}</strong>
                <br>
                <span class="text-grey-7">
                  ({{ (product.currentStock * (product.baseRatio || 1)).toFixed(0) }} {{ product.baseUnit }})
                </span>
              </div>
              <q-input
                v-model.number="singleQuantity"
                type="number"
                :label="`Количество (в ${product.baseUnit}) *`"
                dense
                outlined
                style="width: 180px"
                step="0.1"
                :rules="[
                  val => val > 0 || 'Введите положительное число',
                  val => {
                    const maxBase = product.currentStock * (product.baseRatio || 1);
                    return val <= maxBase || `Не больше ${maxBase} ${product.baseUnit}`;
                  }
                ]"
              />
            </div>
          </div>

          <!-- Режим: массовое списание -->
          <div v-else>
            <q-select
              v-model="selectedProduct"
              :options="products"
              option-label="name"
              option-value="id"
              label="Выберите товар для добавления"
              outlined
              dense
              clearable
              @update:model-value="addItem"
            >
              <template v-slot:option="scope">
                <q-item v-bind="scope.itemProps">
                  <q-item-section>
                    <q-item-label>{{ scope.opt.name }}</q-item-label>
                    <q-item-label caption>
                      Остаток: {{ scope.opt.currentStock }} {{ scope.opt.unit }}
                      ({{ (scope.opt.currentStock * (scope.opt.baseRatio || 1)).toFixed(0) }} {{ scope.opt.baseUnit }})
                    </q-item-label>
                  </q-item-section>
                </q-item>
              </template>
            </q-select>

            <!-- Список товаров к списанию -->
            <q-list bordered separator class="q-mt-md">
              <q-item v-for="(item, index) in items" :key="index">
                <q-item-section>
                  <q-item-label>{{ getProductName(item.productId) }}</q-item-label>
                  <q-item-label caption>
                    Остаток: {{ getProductStock(item.productId) }} {{ getProductUnit(item.productId) }}
                    ({{ getProductBaseStock(item.productId) }} {{ getProductBaseUnit(item.productId) }})
                  </q-item-label>
                </q-item-section>
                <q-item-section side class="row items-center">
                  <q-input
                    v-model.number="item.quantity"
                    type="number"
                    dense
                    style="width: 100px"
                    step="0.1"
                    :label="`в ${getProductBaseUnit(item.productId)}`"
                    :rules="[val => val > 0 && val <= getProductBaseStock(item.productId)]"
                  />
                  <q-btn flat dense icon="close" @click="removeItem(index)" />
                </q-item-section>
              </q-item>
            </q-list>
          </div>

          <!-- Причина списания -->
          <q-input
            v-model="reason"
            label="Причина списания *"
            outlined
            dense
            :rules="[val => !!val || 'Укажите причину']"
          />
        </q-form>
      </q-card-section>

      <q-card-actions align="right">
        <q-btn flat label="Отмена" color="negative" @click="onCancel" />
        <q-btn
          flat
          label="Списать"
          color="negative"
          :disable="!canSubmit"
          @click="onSubmit"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script lang="ts">
import { defineComponent, PropType, ref, computed, watch } from 'vue';
import { Product } from 'src/types/product.types';

export default defineComponent({
  name: 'WriteOffDialog',

  props: {
    modelValue: {
      type: Boolean,
      required: true
    },
    product: {
      type: Object as PropType<Product | null>,
      default: null
    },
    products: {
      type: Array as PropType<Product[]>,
      default: () => []
    }
  },

  emits: ['update:modelValue', 'ok', 'hide'],

  setup(props, { emit }) {
    const items = ref<Array<{ productId: number; quantity: number }>>([]);
    const reason = ref('');
    const selectedProduct = ref<Product | null>(null);
    const singleQuantity = ref(1);

    // Инициализация при открытии
    watch(() => props.modelValue, (newVal) => {
      if (newVal) {
        if (props.product) {
          items.value = [];
          singleQuantity.value = 1;
        } else {
          items.value = [];
          singleQuantity.value = 1;
        }
        reason.value = '';
      }
    });

    const getProduct = (id: number): Product | undefined => {
      return props.products.find(p => p.id === id) || props.product || undefined;
    };

    const getProductName = (id: number): string => {
      const p = getProduct(id);
      return p ? p.name : 'Неизвестно';
    };

    const getProductStock = (id: number): number => {
      const p = getProduct(id);
      return p ? p.currentStock : 0;
    };

    const getProductUnit = (id: number): string => {
      const p = getProduct(id);
      return p ? p.unit : '';
    };

    const getProductBaseUnit = (id: number): string => {
      const p = getProduct(id);
      return p ? p.baseUnit : '';
    };

    const getProductBaseStock = (id: number): number => {
      const p = getProduct(id);
      return p ? p.currentStock * (p.baseRatio || 1) : 0;
    };

    const getProductRatio = (id: number): number => {
      const p = getProduct(id);
      return p ? p.baseRatio || 1 : 1;
    };

    const addItem = (product: Product | null) => {
      if (product && !items.value.some(i => i.productId === product.id)) {
        items.value.push({
          productId: product.id,
          quantity: 1
        });
      }
      selectedProduct.value = null;
    };

    const removeItem = (index: number) => {
      items.value.splice(index, 1);
    };

    const canSubmit = computed(() => {
      if (!reason.value) return false;

      if (props.product) {
        // Режим одного товара
        const maxBase = props.product.currentStock * (props.product.baseRatio || 1);
        return singleQuantity.value > 0 && singleQuantity.value <= maxBase;
      } else {
        // Режим множественного выбора
        return items.value.length > 0 && items.value.every(item => {
          const maxBase = getProductBaseStock(item.productId);
          return item.quantity > 0 && item.quantity <= maxBase;
        });
      }
    });

    const onSubmit = () => {
      if (!canSubmit.value) return;

      let payloadItems: Array<{ productId: number; quantity: number }>;

      if (props.product) {
        // Режим одного товара - пересчитываем из базовых единиц в единицы хранения
        const ratio = props.product.baseRatio || 1;
        payloadItems = [{
          productId: props.product.id,
          quantity: singleQuantity.value / ratio
        }];
      } else {
        // Режим множественного выбора - пересчитываем для каждого товара
        payloadItems = items.value.map(item => ({
          productId: item.productId,
          quantity: item.quantity / getProductRatio(item.productId)
        }));
      }

      emit('ok', {
        items: payloadItems,
        reason: reason.value
      });
      emit('update:modelValue', false);
    };

    const onCancel = () => {
      emit('update:modelValue', false);
    };

    return {
      items,
      reason,
      selectedProduct,
      singleQuantity,
      getProductName,
      getProductStock,
      getProductUnit,
      getProductBaseUnit,
      getProductBaseStock,
      getProductRatio,
      addItem,
      removeItem,
      canSubmit,
      onSubmit,
      onCancel
    };
  }
});
</script>
