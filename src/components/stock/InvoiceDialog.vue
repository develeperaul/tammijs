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
          <!-- Выбор поставщика с возможностью добавления нового -->
          <div class="col row items-center q-gutter-sm">
            <q-select
              v-model="form.supplierId"
              :options="filteredSuppliers"
              option-label="name"
              option-value="id"
              label="Поставщик *"
              outlined
              dense
              class="col"
              emit-value
              map-options
              clearable
              use-input
              :loading="loadingSuppliers"
              @filter="filterSuppliers"
              @update:model-value="onSupplierSelect"
            >
              <template v-slot:no-option>
                <q-item>
                  <q-item-section class="text-grey">
                    <div v-if="loadingSuppliers" class="text-center">
                      <q-spinner size="sm" /> Загрузка...
                    </div>
                    <div v-else class="text-center">
                      <div>Нет поставщиков</div>
                      <q-btn
                        flat
                        color="primary"
                        label="+ Добавить нового"
                        @click="openSupplierDialog"
                        class="full-width q-mt-sm"
                      />
                    </div>
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
                >
                  <q-tooltip>Добавить поставщика</q-tooltip>
                </q-btn>
              </template>
            </q-select>
          </div>

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
          <!-- Выбор товара (только ингредиенты и товары перепродажи) -->
          <template v-slot:body-cell-product="props">
            <q-td :props="props">
              <q-select
                :model-value="getProductById(props.row.productId)"
                :options="availableProducts"
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
                        <span v-if="scope.opt.type === 'ingredient'">
                          Ингредиент | Остаток: {{ scope.opt.currentStock }} {{ scope.opt.unit }}
                          <br>
                          1 {{ scope.opt.unit }} = {{ scope.opt.baseRatio }} {{ scope.opt.baseUnit }}
                        </span>
                        <span v-else>
                          Товар перепродажи | Остаток: {{ scope.opt.currentStock }} {{ scope.opt.unit }}
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

    <!-- Диалог добавления поставщика -->
    <supplier-dialog
      v-model="supplierDialog"
      @ok="onSupplierCreated"
      @hide="newSupplier = null"
    />
  </q-dialog>
</template>

<script lang="ts">
import { defineComponent, ref, computed, onMounted } from 'vue';
import { Product } from 'src/types/product.types';
import { Supplier } from 'src/types/supplier.types';
import supplierService from 'src/services/supplier.service';
import SupplierDialog from 'components/suppliers/SupplierDialog.vue';

interface InvoiceItem {
  id: number;
  productId: number | null;
  quantity: number;
  price: number;
  unitType: 'unit' | 'baseUnit';
}

export default defineComponent({
  name: 'InvoiceDialog',

  components: { SupplierDialog },

  props: {
    products: {
      type: Array as () => Product[],
      required: true
    }
  },

  emits: ['ok', 'hide'],

  setup(props, { emit }) {
    const dialog = ref<any>(null);
    const supplierDialog = ref(false);
    const newSupplier = ref<Supplier | null>(null);
    const suppliers = ref<Supplier[]>([]);
    const filteredSuppliers = ref<Supplier[]>([]);
    const loadingSuppliers = ref(false);
    let nextId = 1;

    const form = ref({
      supplierId: null as number | null,
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

    // ✅ Фильтруем товары: только ингредиенты и товары перепродажи
    const availableProducts = computed(() => {
      return props.products.filter(p =>
        p.type === 'ingredient' || p.type === 'resale'
      );
    });

    const totalAmount = computed(() => {
      return form.value.items.reduce((sum, item) => {
        return sum + (Number(item.price) || 0) * (Number(item.quantity) || 0);
      }, 0);
    });

    const canSubmit = computed(() => {
      if (!form.value.supplierId) return false;
      if (!form.value.number) return false;
      if (form.value.items.length === 0) return false;
      return form.value.items.every(item =>
        item.productId && Number(item.quantity) > 0
      );
    });

    // Загрузка поставщиков
    const loadSuppliers = async () => {
      loadingSuppliers.value = true;
      try {
        const response = await supplierService.getSuppliers();
        suppliers.value = response.data || [];
        filteredSuppliers.value = [...suppliers.value];
      } catch (error) {
        console.error('Ошибка загрузки поставщиков:', error);
      } finally {
        loadingSuppliers.value = false;
      }
    };

    const filterSuppliers = (val: string, update: any) => {
      if (val === '') {
        update(() => {
          filteredSuppliers.value = [...suppliers.value];
        });
        return;
      }

      update(() => {
        const needle = val.toLowerCase();
        filteredSuppliers.value = suppliers.value.filter(
          s => s.name.toLowerCase().includes(needle)
        );
      });
    };

    const onSupplierSelect = (supplierId: number | null) => {
      form.value.supplierId = supplierId;
    };

    const openSupplierDialog = () => {
      supplierDialog.value = true;
    };

    const onSupplierCreated = async (supplierData: any) => {
      try {
        const result = await supplierService.createSupplier(supplierData);
        await loadSuppliers();
        form.value.supplierId = result.id;
        supplierDialog.value = false;
      } catch (error) {
        console.error('Ошибка создания поставщика:', error);
      }
    };

    const getProductById = (id: number | null) => {
      if (!id) return null;
      return availableProducts.value.find(p => p.id === id) || null;
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
      loadSuppliers();
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
        unitType: 'baseUnit'
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
      // Для ингредиентов подставляем цену за базовую единицу
      if (selectedProduct.type === 'ingredient') {
        item.price = (selectedProduct.costPrice || 0) / (selectedProduct.baseRatio || 1);
      } else {
        // Для товаров перепродажи — цена за единицу хранения
        item.price = selectedProduct.costPrice || 0;
      }
      item.unitType = 'baseUnit';
    };

    const onUnitTypeChange = (item: InvoiceItem, newUnitType: 'unit' | 'baseUnit') => {
      if (!item.productId) return;

      const ratio = getProductRatio(item.productId);
      const product = getProductById(item.productId);
      if (!product) return;

      if (newUnitType === 'unit' && item.unitType === 'baseUnit') {
        item.price = item.price * ratio;
        item.quantity = item.quantity / ratio;
      } else if (newUnitType === 'baseUnit' && item.unitType === 'unit') {
        item.price = item.price / ratio;
        item.quantity = item.quantity * ratio;
      }

      item.unitType = newUnitType;
    };

    const onSubmit = () => {
      const invalidItems = form.value.items.filter(
        item => !item.productId || !item.quantity || Number(item.quantity) <= 0
      );

      if (invalidItems.length > 0) {
        console.error('Есть незаполненные позиции:', invalidItems);
        return;
      }

      const items = form.value.items.map(item => {
        const ratio = getProductRatio(item.productId);

        let quantityInStorage: number;
        let priceInStorage: number;

        if (item.unitType === 'unit') {
          quantityInStorage = item.quantity;
          priceInStorage = item.price;
        } else {
          quantityInStorage = item.quantity / ratio;
          priceInStorage = item.price * ratio;
        }

        return {
          productId: Number(item.productId),
          quantity: quantityInStorage,
          price: priceInStorage,
          supplierId: form.value.supplierId
        };
      });

      const invoiceData = {
        supplierId: form.value.supplierId,
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
        supplierId: null,
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

    onMounted(() => {
      loadSuppliers();
    });

    return {
      dialog,
      supplierDialog,
      form,
      suppliers,
      filteredSuppliers,
      loadingSuppliers,
      availableProducts,
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
      formatMoney,
      filterSuppliers,
      openSupplierDialog,
      onSupplierCreated
    };
  }
});
</script>
