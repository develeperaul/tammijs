<template>
  <q-dialog ref="dialog" @hide="onDialogHide" maximized>
    <q-card>
      <q-card-section class="row items-center">
        <div class="text-h6">Приход по накладной</div>
        <q-space />
        <q-btn flat round icon="close" v-close-popup />
      </q-card-section>

      <q-card-section>
        <!-- Шапка накладной -->
        <div class="row q-gutter-md q-mb-md">
          <q-input
            v-model="form.supplier"
            label="Поставщик"
            outlined
            dense
            class="col"
          />
          <q-input
            v-model="form.number"
            label="Номер накладной *"
            outlined
            dense
            class="col"
            :rules="[val => !!val || 'Введите номер']"
          />
          <q-input
            v-model="form.date"
            label="Дата"
            outlined
            dense
            class="col"
            mask="##.##.####"
            fill-mask
            hint="ДД.ММ.ГГГГ"
          />
        </div>

        <!-- Кнопка добавления позиции -->
        <div class="row q-mb-md">
          <q-btn
            color="primary"
            icon="add"
            label="Добавить товар"
            @click="addItem"
          />
        </div>

        <!-- Таблица позиций -->
        <q-table
          :rows="form.items"
          :columns="columns"
          row-key="id"
          flat
          bordered
          hide-pagination
        >
          <!-- Выбор товара -->
          <template v-slot:body-cell-product="props">
            <q-td :props="props">
              <q-select
                :model-value="getProductById(props.row.productId)"
                :options="products"
                option-label="name"
                label="Товар"
                outlined
                dense
                options-dense
                @update:model-value="(val) => onProductSelect(props.row, val)"
              >
                <template v-slot:option="scope">
                  <q-item v-bind="scope.itemProps">
                    <q-item-section>
                      <q-item-label>{{ scope.opt.name }}</q-item-label>
                      <q-item-label caption>
                        Остаток: {{ scope.opt.currentStock }} {{ scope.opt.unit }}
                        <br>
                        <span class="text-grey-7">
                          1 {{ scope.opt.unit }} = {{ scope.opt.baseRatio }} {{ scope.opt.baseUnit }}
                        </span>
                      </q-item-label>
                    </q-item-section>
                  </q-item>
                </template>
              </q-select>
            </q-td>
          </template>

          <!-- Количество с выбором единицы -->
          <template v-slot:body-cell-quantity="props">
            <q-td :props="props">
              <div class="row items-center no-wrap">
                <q-input
                  v-model.number="props.row.quantity"
                  type="number"
                  dense
                  style="width: 80px"
                  step="0.001"
                  :rules="[val => val > 0 || '>0']"
                />
                <q-select
                  v-model="props.row.unitType"
                  :options="unitTypeOptions"
                  dense
                  options-dense
                  emit-value
                  map-options
                  style="min-width: 60px; margin-left: 4px;"
                  @update:model-value="(val) => onUnitTypeChange(props.row, val)"
                />
              </div>
            </q-td>
          </template>

          <!-- Цена (за выбранную единицу) -->
          <template v-slot:body-cell-price="props">
            <q-td :props="props">
              <div class="row items-center">
                <q-input
                  v-model.number="props.row.price"
                  type="number"
                  dense
                  style="width: 100px"
                  step="0.01"
                />
                <span class="q-ml-xs">₽/{{ getPriceUnitLabel(props.row) }}</span>
              </div>
            </q-td>
          </template>

          <!-- Сумма -->
          <template v-slot:body-cell-total="props">
            <q-td :props="props">
              {{ formatMoney((props.row.price || 0) * (props.row.quantity || 0)) }}
            </q-td>
          </template>

          <!-- Действия -->
          <template v-slot:body-cell-actions="props">
            <q-td :props="props">
              <q-btn
                flat
                dense
                icon="delete"
                color="negative"
                @click="removeItem(props.row)"
              />
            </q-td>
          </template>
        </q-table>

        <!-- Итого -->
        <div class="row justify-end q-mt-md">
          <div class="text-h6">
            Итого: {{ formatMoney(totalAmount) }}
          </div>
        </div>
      </q-card-section>

      <q-card-actions align="right">
        <q-btn flat label="Отмена" color="negative" v-close-popup />
        <q-btn
          flat
          label="Сохранить накладную"
          color="positive"
          :disable="!canSubmit"
          @click="onSubmit"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script lang="ts">
import { defineComponent, ref, computed } from 'vue';
import { Product } from 'src/types/product.types';

interface InvoiceItem {
  id: number;
  productId: number | null;
  quantity: number;
  price: number;
  unitType: 'unit' | 'baseUnit'; // в каких единицах введено количество
}

export default defineComponent({
  name: 'InvoiceDialog',

  props: {
    products: {
      type: Array as () => Product[],
      required: true
    }
  },

  emits: ['ok', 'hide'],

  setup(props, { emit }) {
    const dialog = ref<any>(null);
    let nextId = 1;

    const form = ref({
      supplier: '',
      number: '',
      date: new Date().toLocaleDateString('ru-RU'),
      items: [] as InvoiceItem[]
    });

    const unitTypeOptions = [
      { label: 'ед.хр', value: 'unit' },
      { label: 'баз.ед', value: 'baseUnit' }
    ];

    const columns = [
      { name: 'product', label: 'Товар', align: 'left' },
      { name: 'quantity', label: 'Кол-во', align: 'center' },
      { name: 'price', label: 'Цена', align: 'right' },
      { name: 'total', label: 'Сумма', align: 'right' },
      { name: 'actions', label: '', align: 'center' }
    ];

    const totalAmount = computed(() => {
      return form.value.items.reduce((sum, item) => {
        return sum + (Number(item.price) || 0) * (Number(item.quantity) || 0);
      }, 0);
    });

    const canSubmit = computed(() => {
      if (!form.value.number) return false;
      if (form.value.items.length === 0) return false;
      return form.value.items.every(item =>
        item.productId && Number(item.quantity) > 0
      );
    });

    const getProductById = (id: number | null) => {
      if (!id) return null;
      return props.products.find(p => p.id === id) || null;
    };

    const getProductRatio = (productId: number | null): number => {
      const product = getProductById(productId);
      return product?.baseRatio || 1;
    };

    const getProductUnit = (productId: number | null): string => {
      const product = getProductById(productId);
      return product?.unit || '';
    };

    const getProductBaseUnit = (productId: number | null): string => {
      const product = getProductById(productId);
      return product?.baseUnit || '';
    };

    const getPriceUnitLabel = (item: InvoiceItem): string => {
      const product = getProductById(item.productId);
      if (!product) return '';
      return item.unitType === 'unit' ? product.unit : product.baseUnit;
    };

    const show = () => {
      dialog.value?.show();
    };

    const hide = () => {
      dialog.value?.hide();
    };

    const addItem = () => {
      form.value.items.push({
        id: nextId++,
        productId: null,
        quantity: 1,
        price: 0,
        unitType: 'baseUnit' // по умолчанию базовые единицы
      });
    };

    const removeItem = (item: InvoiceItem) => {
      const index = form.value.items.findIndex(i => i.id === item.id);
      if (index !== -1) {
        form.value.items.splice(index, 1);
      }
    };

    const onProductSelect = (item: InvoiceItem, selectedProduct: Product | null) => {
      if (!selectedProduct) {
        item.productId = null;
        item.price = 0;
        return;
      }

      item.productId = selectedProduct.id;
      // Подставляем цену за базовую единицу по умолчанию
      item.price = (selectedProduct.costPrice || 0) / (selectedProduct.baseRatio || 1);
      item.unitType = 'baseUnit';
    };

    const onUnitTypeChange = (item: InvoiceItem, newUnitType: 'unit' | 'baseUnit') => {
      if (!item.productId) return;

      const ratio = getProductRatio(item.productId);
      const product = getProductById(item.productId);
      if (!product) return;

      // Пересчитываем цену при смене единицы
      if (newUnitType === 'unit' && item.unitType === 'baseUnit') {
        // Было в базовых единицах, стало в единицах хранения
        item.price = item.price * ratio;
        item.quantity = item.quantity / ratio;
      } else if (newUnitType === 'baseUnit' && item.unitType === 'unit') {
        // Было в единицах хранения, стало в базовых единицах
        item.price = item.price / ratio;
        item.quantity = item.quantity * ratio;
      }

      item.unitType = newUnitType;
    };

    const onSubmit = () => {
      // Проверяем все позиции
      const invalidItems = form.value.items.filter(
        item => !item.productId || !item.quantity || Number(item.quantity) <= 0
      );

      if (invalidItems.length > 0) {
        console.error('Есть незаполненные позиции:', invalidItems);
        return;
      }

      // Преобразуем все позиции в единицы хранения для отправки на бэкенд
      const items = form.value.items.map(item => {
        const ratio = getProductRatio(item.productId);

        let quantityInStorage: number;
        let priceInStorage: number;

        if (item.unitType === 'unit') {
          // Уже в единицах хранения
          quantityInStorage = item.quantity;
          priceInStorage = item.price;
        } else {
          // Из базовых единиц в единицы хранения
          quantityInStorage = item.quantity / ratio;
          priceInStorage = item.price * ratio;
        }

        return {
          productId: Number(item.productId),
          quantity: quantityInStorage,
          price: priceInStorage
        };
      });

      const invoiceData = {
        supplier: form.value.supplier || '',
        number: form.value.number,
        date: form.value.date,
        items
      };

      console.log('✅ Отправка валидных данных:', invoiceData);
      emit('ok', invoiceData);
      hide();
    };

    const onDialogHide = () => {
      form.value = {
        supplier: '',
        number: '',
        date: new Date().toLocaleDateString('ru-RU'),
        items: []
      };
      nextId = 1;
      emit('hide');
    };

    const formatMoney = (value: number) => {
      return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
        minimumFractionDigits: 0
      }).format(value);
    };

    return {
      dialog,
      form,
      columns,
      unitTypeOptions,
      totalAmount,
      canSubmit,
      getProductById,
      getProductRatio,
      getProductUnit,
      getProductBaseUnit,
      getPriceUnitLabel,
      show,
      hide,
      addItem,
      removeItem,
      onProductSelect,
      onUnitTypeChange,
      onSubmit,
      onDialogHide,
      formatMoney
    };
  }
});
</script>
