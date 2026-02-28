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
          <!-- Режим: конкретный товар (отображается сразу) -->
          <div v-if="product">
            <div class="row items-center q-mb-sm">
              <q-badge color="primary" class="q-mr-sm">Товар:</q-badge>
              <span class="text-subtitle1">{{ product.name }}</span>
            </div>
            <div class="row items-center q-gutter-md">
              <div class="text-caption">
                Текущий остаток: <strong>{{ product.currentStock }} {{ product.unit }}</strong>
              </div>
              <q-input
                v-model.number="singleQuantity"
                type="number"
                label="Количество *"
                dense
                outlined
                style="width: 150px"
                :rules="[
                  val => val > 0 || 'Введите положительное число',
                  val => val <= product.currentStock || 'Не больше остатка'
                ]"
              />
            </div>
          </div>

          <!-- Режим: массовое списание (без конкретного товара) -->
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
            />

            <!-- Список товаров к списанию -->
            <q-list bordered separator class="q-mt-md">
              <q-item v-for="(item, index) in items" :key="index">
                <q-item-section>
                  <q-item-label>{{ getProductName(item.productId) }}</q-item-label>
                  <q-item-label caption>Остаток: {{ getProductStock(item.productId) }}</q-item-label>
                </q-item-section>
                <q-item-section side class="row items-center">
                  <q-input
                    v-model.number="item.quantity"
                    type="number"
                    dense
                    style="width: 80px"
                    :rules="[val => val > 0 && val <= getProductStock(item.productId)]"
                  />
                  <q-btn flat dense icon="close" @click="removeItem(index)" />
                </q-item-section>
              </q-item>
            </q-list>
          </div>

          <!-- Общее поле причины -->
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
    const singleQuantity = ref(1); // для режима конкретного товара

    // Инициализация при открытии
    watch(() => props.modelValue, (newVal) => {
      if (newVal) {
        if (props.product) {
          // Режим одного товара
          items.value = []; // очищаем (не будем использовать items для этого режима)
          singleQuantity.value = 1;
        } else {
          // Режим множественного выбора
          items.value = [];
          singleQuantity.value = 1; // не используется, но сбросим
        }
        reason.value = '';
      }
    });

    const getProductName = (id: number): string => {
      const p = props.products.find(p => p.id === id) || props.product;
      return p ? p.name : 'Неизвестно';
    };

    const getProductStock = (id: number): number => {
      const p = props.products.find(p => p.id === id) || props.product;
      return p ? p.currentStock : 0;
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
        return (
          singleQuantity.value > 0 &&
          singleQuantity.value <= (props.product?.currentStock || 0)
        );
      } else {
        // Режим множественного выбора
        return items.value.length > 0 && items.value.every(item => item.quantity > 0);
      }
    });

    const onSubmit = () => {
      if (!canSubmit.value) return;

      let payloadItems: Array<{ productId: number; quantity: number }>;
      if (props.product) {
        // Режим одного товара
        payloadItems = [{
          productId: props.product.id,
          quantity: singleQuantity.value
        }];
      } else {
        payloadItems = items.value;
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
      addItem,
      removeItem,
      canSubmit,
      onSubmit,
      onCancel
    };
  }
});
</script>
