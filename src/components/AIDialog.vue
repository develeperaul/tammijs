<template>
  <q-dialog ref="dialog" @hide="onDialogHide">
    <q-card class="q-dialog-plugin" style="min-width: 500px; max-width: 80vw;">
      <q-card-section>
        <div class="text-h6">Приход по фото</div>
      </q-card-section>

      <q-card-section v-if="!photo">
        <!-- Загрузка фото -->
        <q-file
          v-model="file"
          label="Выберите фото накладной"
          outlined
          accept=".jpg,.jpeg,.png,.pdf"
          :loading="loading"
          @update:model-value="uploadFile"
        >
          <template v-slot:prepend>
            <q-icon name="photo_camera" />
          </template>
        </q-file>

        <div class="text-caption text-grey-7 q-mt-sm">
          Поддерживаются форматы: JPG, PNG, PDF. Максимальный размер: 10 МБ
        </div>
      </q-card-section>

      <q-card-section v-else>
        <!-- Предпросмотр фото -->
        <div class="row">
          <div class="col-4">
            <q-img
              :src="photoUrl || ''"
              :ratio="1"
              class="rounded-borders"
              fit="cover"
            />
          </div>
          <div class="col-8 q-pl-md">
            <div class="text-subtitle2">Распознано товаров: {{ recognizedItems.length }}</div>
            <div class="text-h6">{{ formatMoney(totalAmount) }}</div>
          </div>
        </div>

        <!-- Список распознанных товаров -->
        <q-list bordered class="q-mt-md">
          <q-item
            v-for="(item, index) in recognizedItems"
            :key="index"
            class="q-py-sm"
          >
            <q-item-section>
              <div class="row items-center">
                <q-checkbox
                  v-model="selectedItems"
                  :val="item"
                  dense
                />
                <div>
                  <div>{{ item.recognizedName }}</div>
                  <div class="text-caption text-grey-7">
                    {{ item.quantity }} {{ item.unit }} × {{ formatMoney(item.price) }}
                  </div>
                </div>
              </div>
            </q-item-section>

            <q-item-section side>
              <div class="text-weight-bold">{{ formatMoney(item.quantity * item.price) }}</div>
              <q-badge
                :color="item.confidence > 0.9 ? 'positive' : 'warning'"
                class="q-mt-xs"
              >
                {{ Math.round(item.confidence * 100) }}%
              </q-badge>
            </q-item-section>

            <!-- Выбор товара из базы -->
            <q-item-section v-if="item.matches && item.matches.length" side>
              <q-select
                v-model="item.selectedProductId"
                :options="item.matches"
                option-label="productName"
                option-value="productId"
                label="Товар"
                dense
                options-dense
                style="min-width: 200px"
                @update:model-value="(val) => selectProduct(item, val)"
              />
            </q-item-section>
          </q-item>
        </q-list>

        <!-- Данные накладной -->
        <div class="row q-mt-md q-gutter-sm">
          <q-input
            v-model="invoiceSupplier"
            label="Поставщик"
            outlined
            dense
            class="col"
          />
          <q-input
            v-model="invoiceNumber"
            label="Номер накладной"
            outlined
            dense
            class="col"
          />
        </div>
      </q-card-section>

      <q-card-actions align="right">
        <q-btn flat label="Отмена" color="negative" @click="onCancelClick" />
        <q-btn
          v-if="!photo"
          flat
          label="Закрыть"
          color="primary"
          @click="onCancelClick"
        />
        <q-btn
          v-else
          flat
          label="Сохранить накладную"
          color="positive"
          :loading="loading"
          :disable="selectedItems.length === 0 || !invoiceSupplier || !invoiceNumber"
          @click="saveInvoice"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script lang="ts">
import { defineComponent, ref, watch } from 'vue';
import { useAI } from 'src/composables/useAI';
import { AIRecognizedItem, ProductMatch } from 'src/types/invoice.types';

export default defineComponent({
  name: 'AIDialog',

  emits: ['ok', 'hide', 'processed'],

  setup(props, { emit }) {
    const { loading, recognizedItems, photoUrl, totalAmount, photoId, recognizeInvoice, confirmInvoice, reset } = useAI();

    const dialog = ref<any>(null);
    const file = ref<File | null>(null);
    const photo = ref<any>(null);
    const selectedItems = ref<AIRecognizedItem[]>([]);
    const invoiceSupplier = ref('');
    const invoiceNumber = ref('');

    const uploadFile = async (newFile: File | null) => {
      if (!newFile) return;

      const success = await recognizeInvoice(newFile);
      if (success) {
        photo.value = newFile;
      }
    };

    const selectProduct = (item: AIRecognizedItem, match: ProductMatch) => {
      item.selectedProductId = match.productId;
    };

    const saveInvoice = async () => {
      const items = selectedItems.value.map(item => ({
        recognizedName: item.recognizedName,
        productId: item.selectedProductId || item.matches?.[0]?.productId,
        quantity: item.quantity,
        price: item.price,
      }));

      // Проверяем что у всех выбран товар
      const missingProduct = items.find(i => !i.productId);
      if (missingProduct) {
        // Показываем предупреждение
        return;
      }

      const success = await confirmInvoice(
        invoiceSupplier.value,
        invoiceNumber.value,
        items
      );

      if (success) {
        emit('processed', items);
        onOkClick();
      }
    };

    const formatMoney = (value: number): string => {
      return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'RUB',
      }).format(value);
    };

    // Показываем диалог
    const show = () => {
      dialog.value?.show();
    };

    // Скрываем диалог
    const hide = () => {
      dialog.value?.hide();
    };

    const onDialogHide = () => {
      reset();
      file.value = null;
      photo.value = null;
      selectedItems.value = [];
      invoiceSupplier.value = '';
      invoiceNumber.value = '';
      emit('hide');
    };

    const onOkClick = () => {
      emit('ok');
      hide();
    };

    const onCancelClick = () => {
      hide();
    };

    return {
      dialog,
      file,
      photo,
      loading,
      recognizedItems,
      photoUrl,
      totalAmount,
      photoId,
      selectedItems,
      invoiceSupplier,
      invoiceNumber,
      uploadFile,
      selectProduct,
      saveInvoice,
      formatMoney,
      show,
      hide,
      onDialogHide,
      onOkClick,
      onCancelClick,
    };
  },
});
</script>
