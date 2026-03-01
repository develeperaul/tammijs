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
                      </q-item-label>
                    </q-item-section>
                  </q-item>
                </template>
              </q-select>
            </q-td>
          </template>

          <!-- Количество -->
          <template v-slot:body-cell-quantity="props">
            <q-td :props="props">
              <q-input
                v-model.number="props.row.quantity"
                type="number"
                dense
                style="width: 100px"
                :rules="[val => val > 0 || '>0']"
              />
            </q-td>
          </template>

          <!-- Цена -->
          <template v-slot:body-cell-price="props">
            <q-td :props="props">
              <q-input
                v-model.number="props.row.price"
                type="number"
                dense
                style="width: 120px"
                step="0.01"
              />
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

    // Вспомогательная функция для получения продукта по ID
    const getProductById = (id: number | null) => {
      if (!id) return null;
      return props.products.find(p => p.id === id) || null;
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
        price: 0
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
      item.price = selectedProduct.costPrice || 0;
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

      // Формируем данные для отправки
      const items = form.value.items.map(item => ({
        productId: Number(item.productId),
        quantity: Number(item.quantity),
        price: Number(item.price || 0)
      }));

      // Финальная проверка на NaN
      if (items.some(item => isNaN(item.productId) || isNaN(item.quantity) || isNaN(item.price))) {
        console.error('Обнаружен NaN в данных');
        return;
      }

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
      totalAmount,
      canSubmit,
      getProductById,
      show,
      hide,
      addItem,
      removeItem,
      onProductSelect,
      onSubmit,
      onDialogHide,
      formatMoney
    };
  }
});
</script>
