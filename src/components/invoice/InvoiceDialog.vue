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
        <div class="text-h6">Накладная</div>
        <q-space />
        <q-btn flat round icon="close" @click="onCancel" />
      </q-card-section>

      <q-card-section>
        <q-form @submit="onSubmit" class="q-gutter-md">
          <!-- Шапка накладной (без изменений) -->
          <div class="row q-gutter-md">
            <q-select
              v-model="supplierId"
              :options="suppliers"
              option-label="name"
              option-value="id"
              label="Поставщик *"
              outlined
              dense
              class="col-3"
              emit-value
              map-options
              use-input
              @filter="filterSuppliers"
              :loading="loadingSuppliers"
              :rules="[val => !!val || 'Выберите поставщика']"
            >
              <template v-slot:no-option>
                <q-item>
                  <q-item-section class="text-grey">
                    <div>Нет поставщиков</div>
                    <q-btn
                      flat
                      color="primary"
                      label="+ Добавить"
                      @click="openSupplierDialog"
                      class="full-width"
                    />
                  </q-item-section>
                </q-item>
              </template>
              <template v-slot:after>
                <q-btn
                  flat
                  round
                  dense
                  icon="add"
                  color="primary"
                  @click="openSupplierDialog"
                />
              </template>
            </q-select>

            <q-input
              v-model="invoiceNumber"
              label="Номер накладной *"
              outlined
              dense
              class="col-2"
              :rules="[val => !!val || 'Введите номер']"
            />

            <q-input
              v-model="invoiceDate"
              label="Дата"
              outlined
              dense
              class="col-2"
              mask="##.##.####"
              fill-mask
              hint="ДД.ММ.ГГГГ"
            />
          </div>

          <q-separator />

          <!-- Список позиций -->
          <div class="text-subtitle2">Позиции накладной</div>

          <div v-if="items.length === 0" class="text-center text-grey-7 q-py-md">
            Добавьте товары в накладную
          </div>

          <div v-else class="q-gutter-md">
            <invoice-item-row
              v-for="(item, idx) in items"
              :key="item.id"
              :item-id="item.itemId"
              :item-type="item.itemType"
              :quantity="item.quantity"
              :price="item.price"
              :unit-type="item.unitType"
              :items="purchasableItems"
              @update="(data) => updateItem(idx, data)"
              @remove="removeItem(idx)"
            />
          </div>

          <q-btn
            flat
            color="primary"
            icon="add"
            label="Добавить товар"
            @click="addItem"
          />

          <q-separator />

          <!-- Итог -->
          <div class="row justify-end">
            <div class="text-h6">
              Итого: {{ formatMoney(totalAmount) }}
            </div>
          </div>
        </q-form>
      </q-card-section>

      <q-card-actions align="right">
        <q-btn flat label="Отмена" color="negative" @click="onCancel" />
        <q-btn
          flat
          label="Сохранить накладную"
          color="positive"
          :disable="!canSubmit"
          @click="onSubmit"
        />
      </q-card-actions>
    </q-card>

    <!-- Диалог добавления поставщика -->
    <supplier-dialog
      v-model="supplierDialog"
      @ok="onSupplierCreated"
    />
  </q-dialog>
</template>

<script lang="ts">
import { defineComponent, ref, computed, onMounted } from 'vue';
import { useQuasar } from 'quasar';
import { PurchasableItem } from 'src/types/purchasable.types';
import { Supplier } from 'src/types/supplier.types';
import ingredientService from 'src/services/ingredient.service';
import productService from 'src/services/product.service';
import supplierService from 'src/services/supplier.service';
import SupplierDialog from 'components/suppliers/SupplierDialog.vue';
import InvoiceItemRow from './InvoiceProductRow.vue';

interface InvoiceItemLocal {
  id: number;
  itemId: number;
  itemType: 'ingredient' | 'resale';
  quantity: number;
  price: number;
  unitType: 'unit' | 'baseUnit';
}

export default defineComponent({
  name: 'InvoiceDialog',

  components: { InvoiceItemRow, SupplierDialog },

  props: {
    modelValue: { type: Boolean, required: true }
  },

  emits: ['update:modelValue', 'ok', 'hide'],

  setup(props, { emit }) {
    const $q = useQuasar();
    let nextId = 1;

    // Состояние
    const purchasableItems = ref<PurchasableItem[]>([]);
    const suppliers = ref<Supplier[]>([]);
    const filteredSuppliers = ref<Supplier[]>([]);
    const loadingSuppliers = ref(false);
    const supplierDialog = ref(false);

    // Форма
    const supplierId = ref<number | null>(null);
    const invoiceNumber = ref('');
    const invoiceDate = ref(new Date().toLocaleDateString('ru-RU'));
    const items = ref<InvoiceItemLocal[]>([]);

    const totalAmount = computed(() => {
      return items.value.reduce((sum, item) => {
        return sum + item.price * item.quantity;
      }, 0);
    });

    const canSubmit = computed(() => {
      if (!supplierId.value) return false;
      if (!invoiceNumber.value) return false;
      if (items.value.length === 0) return false;
      return items.value.every(item =>
        item.itemId && item.quantity > 0 && item.price >= 0
      );
    });

    // Загрузка ингредиентов и товаров перепродажи
    const loadPurchasableItems = async () => {
      try {
        const [ingredientsRes, productsRes] = await Promise.all([
          ingredientService.getAll(),
          productService.getProducts()
        ]);

        const ingredients = (ingredientsRes.data || []).map(ing => ({
          ...ing,
          itemType: 'ingredient' as const,
          displayName: ing.name,
          unit: ing.unit,
          baseUnit: ing.baseUnit,
          baseRatio: ing.baseRatio,
          costPrice: ing.costPrice
        }));

        const resaleProducts = (productsRes.data || [])
          .filter(p => p.type === 'resale')
          .map(p => ({
            ...p,
            itemType: 'resale' as const,
            displayName: p.name,
            unit: p.unit,
            baseUnit: undefined,
            baseRatio: undefined,
            costPrice: p.costPrice
          }));

        purchasableItems.value = [...ingredients, ...resaleProducts];
      } catch (error) {
        $q.notify({ type: 'negative', message: 'Ошибка загрузки товаров' });
      }
    };

    const loadSuppliers = async () => {
      loadingSuppliers.value = true;
      try {
        suppliers.value = (await supplierService.getSuppliers()).data;
        filteredSuppliers.value = [...suppliers.value];
      } catch (error) {
        console.log(error);

        $q.notify({ type: 'negative', message: 'Ошибка загрузки поставщиков' });
      } finally {
        loadingSuppliers.value = false;
      }
    };

    const filterSuppliers = (val: string, update: any) => {
      update(() => {
        const needle = val.toLowerCase();
        filteredSuppliers.value = suppliers.value.filter(
          s => s.name.toLowerCase().includes(needle)
        );
      });
    };

    // Управление позициями
    const addItem = () => {
      items.value.push({
        id: nextId++,
        itemId: 0,
        itemType: 'ingredient',
        quantity: 1,
        price: 0,
        unitType: 'baseUnit'
      });
    };

    const updateItem = (index: number, data: any) => {
      items.value[index] = {
        ...items.value[index],
        itemId: data.itemId,
        itemType: data.itemType,
        quantity: data.quantity,
        price: data.price,
        unitType: data.unitType
      };
    };

    const removeItem = (index: number) => {
      items.value.splice(index, 1);
    };

    // Поставщики
    const openSupplierDialog = () => {
      supplierDialog.value = true;
    };

    const onSupplierCreated = async (supplierData: any) => {
      await loadSuppliers();
      supplierId.value = supplierData.id;
      supplierDialog.value = false;
    };

    // Отправка
    const onSubmit = () => {
      const invoiceData = {
        supplierId: supplierId.value,
        number: invoiceNumber.value,
        date: invoiceDate.value,
        items: items.value.map(item => ({
          productId: item.itemId,
          quantity: item.quantity,
          price: item.price
        }))
      };

      emit('ok', invoiceData);
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

    onMounted(() => {
      loadPurchasableItems();
      loadSuppliers();
    });

    return {
      purchasableItems,
      suppliers,
      filteredSuppliers,
      loadingSuppliers,
      supplierDialog,
      supplierId,
      invoiceNumber,
      invoiceDate,
      items,
      totalAmount,
      canSubmit,
      filterSuppliers,
      addItem,
      updateItem,
      removeItem,
      openSupplierDialog,
      onSupplierCreated,
      onSubmit,
      onCancel,
      formatMoney
    };
  }
});
</script>
