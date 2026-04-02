<template>
  <q-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    @hide="$emit('hide')"
    persistent
  >
    <q-card style="min-width: 700px">
      <q-card-section>
        <div class="text-h6">Производство полуфабриката</div>
      </q-card-section>

      <q-card-section>
        <q-form @submit="onSubmit" class="q-gutter-md">
          <!-- Полуфабрикат -->
          <q-select
            v-model="selectedSemi"
            :options="semiFinishedList"
            option-label="name"
            option-value="id"
            label="Полуфабрикат *"
            outlined
            dense
            emit-value
            map-options
            use-input
            @filter="filterSemi"
            :loading="loadingOptions"
            :rules="[val => !!val || 'Выберите полуфабрикат']"
            @update:model-value="onSemiSelect"
          />

          <!-- Количество готового продукта -->
          <q-input
            v-model.number="producedQuantity"
            type="number"
            label="Количество произведённого полуфабриката *"
            outlined
            dense
            step="0.001"
            :rules="[val => val > 0 || 'Введите положительное число']"
          />

          <q-separator />

          <!-- Ингредиенты для списания -->
          <div class="text-subtitle2">Ингредиенты для списания</div>
          <div class="text-caption text-grey-7 q-mb-sm">
            Состав полуфабриката (будут списаны при производстве)
          </div>

          <div class="bg-grey-2 q-pa-sm rounded-borders q-mb-md">
            <div v-if="loadingOptions" class="text-center q-py-md">
              <q-spinner size="24px" /> Загрузка состава...
            </div>
            <div v-else-if="writeOffItems.length === 0" class="text-center text-grey-7 q-py-md">
              Выберите полуфабрикат для загрузки состава
            </div>

            <div
              v-for="(ing, idx) in writeOffItems"
              :key="idx"
              class="row items-center q-gutter-sm q-mb-sm"
            >
              <div class="col-4">
                <span class="text-weight-bold">{{ ing.name }}</span>
              </div>
              <div class="col-2">
                <q-input
                  v-model.number="ing.quantity"
                  type="number"
                  :label="`Кол-во (${ing.unit})`"
                  dense
                  outlined
                  step="0.001"
                />
              </div>
              <div class="col-2">
                <div class="text-caption">Остаток: {{ ing.currentStock }} {{ ing.unit }}</div>
              </div>
              <div class="col-2">
                <q-checkbox
                  v-model="ing.writeOff"
                  label="Списать"
                />
              </div>
            </div>
          </div>

          <!-- Предварительный расчёт -->
          <div v-if="totalCost > 0" class="bg-grey-2 q-pa-sm rounded-borders">
            <div class="row justify-between">
              <span>Себестоимость партии:</span>
              <span>{{ formatMoney(totalCost) }}</span>
            </div>
            <div class="row justify-between">
              <span>Себестоимость единицы:</span>
              <span>{{ formatMoney(totalCost / producedQuantity) }}</span>
            </div>
          </div>
        </q-form>
        <div v-if="hasNegativeStock" class="text-warning q-mb-sm">
          <q-icon name="warning" /> Внимание: списание приведёт к отрицательному остатку!
        </div>
      </q-card-section>
      <q-card-actions align="right">
        <q-btn flat label="Отмена" color="negative" v-close-popup />
        <q-btn
          flat
          label="Произвести"
          color="positive"
          :disable="!canSubmit"
          @click="onSubmit"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script lang="ts">
import { defineComponent, ref, computed, watch } from 'vue';
import { api } from 'boot/axios';
import { useQuasar } from 'quasar';
import { SemiFinished } from 'src/types/semi-finished.types';
import { Ingredient } from 'src/types/ingredient.types';

interface WriteOffItem {
  id: number;
  name: string;
  ingredientId: number;
  quantity: number;
  unit: string;
  currentStock: number;
  writeOff: boolean;
}

export default defineComponent({
  name: 'ProduceDialog',

  props: {
    modelValue: { type: Boolean, required: true },
    semiFinishedList: { type: Array as () => SemiFinished[], required: true },
    ingredientsList: { type: Array as () => Ingredient[], required: true }
  },

  emits: ['update:modelValue', 'produced', 'hide'],

  setup(props, { emit }) {
    const $q = useQuasar();
    const selectedSemi = ref<number | null>(null);
    const producedQuantity = ref<number>(1);
    const writeOffItems = ref<WriteOffItem[]>([]);
    const filteredSemiOptions = ref<SemiFinished[]>(props.semiFinishedList);
    const loadingOptions = ref(false);

    const filterSemi = (val: string, update: any) => {
      update(() => {
        const needle = val.toLowerCase();
        filteredSemiOptions.value = props.semiFinishedList.filter(
          item => item.name.toLowerCase().includes(needle)
        );
      });
    };

    const loadSemiComposition = async (semiId: number) => {
  loadingOptions.value = true;
  try {
    const response = await api.get('/index.php', {
      params: { action: 'semi.get', id: semiId }
    });
    console.log('API response:', response);

    // ✅ Берём data.data, так как response.data — это { success, data }
    const semi = response.data.data;
    console.log('Semi:', semi);
    console.log('Semi ingredients:', semi.ingredients);

    const items: WriteOffItem[] = [];

    if (semi.ingredients && Array.isArray(semi.ingredients)) {
      for (const ing of semi.ingredients) {
        const ingredient = props.ingredientsList.find(i => i.id === ing.ingredientId);
        if (ingredient) {
          items.push({
            id: ing.id,
            name: ingredient.name,
            ingredientId: ing.ingredientId,
            quantity: ing.quantity,
            unit: ing.unit,
            currentStock: ingredient.currentStock,
            writeOff: true
          });
        }
      }
    }

    console.log('WriteOff items after mapping:', items);
    writeOffItems.value = items;
  } catch (error) {
    console.error('Error loading composition:', error);
    $q.notify({ type: 'negative', message: 'Ошибка загрузки состава полуфабриката' });
    writeOffItems.value = [];
  } finally {
    loadingOptions.value = false;
  }
};

    const onSemiSelect = async () => {
      if (!selectedSemi.value) {
        writeOffItems.value = [];
        return;
      }
      await loadSemiComposition(selectedSemi.value);
    };

    const totalCost = computed(() => {
      if (!props.ingredientsList) return 0;

      return writeOffItems.value.reduce((sum, item) => {
        if (!item.writeOff) return sum;
        const ingredient = props.ingredientsList.find(i => i.id === item.ingredientId);
        if (!ingredient) return sum;
        const pricePerBaseUnit = ingredient.costPrice / ingredient.baseRatio;
        return sum + (pricePerBaseUnit * item.quantity);
      }, 0);
    });
    const hasNegativeStock = computed(() => {
      return writeOffItems.value.some(item => {
        if (!item.writeOff) return false;
        const ingredient = props.ingredientsList.find(i => i.id === item.ingredientId);
        return ingredient && ingredient.currentStock < item.quantity;
      });
    });
    const canSubmit = computed(() => {
      // Проверка выбора полуфабриката
      if (!selectedSemi.value) return false;

      // Проверка количества готового продукта
      if (!producedQuantity.value || producedQuantity.value <= 0) return false;

      // Проверка, что есть ингредиенты для списания
      if (!writeOffItems.value || writeOffItems.value.length === 0) return false;

      // Проверка, что хотя бы один ингредиент выбран для списания
      const hasWriteOffSelected = writeOffItems.value.some(item => item.writeOff);
      if (!hasWriteOffSelected) return false;

      // Проверка корректности каждого выбранного ингредиента
      const allValid = writeOffItems.value.every(item => {
        // Если ингредиент не выбран для списания — пропускаем
        if (!item.writeOff) return true;

        // Проверяем, что выбран ингредиент (id > 0)
        if (!item.ingredientId || item.ingredientId === 0) return false;

        // Проверяем, что количество > 0
        if (!item.quantity || item.quantity <= 0) return false;

        // Проверяем, что указана единица измерения
        if (!item.unit) return false;

        return true;
      });

      return allValid;
    });

    const onSubmit = async () => {
      if (!canSubmit.value) return;

      const ingredientsToWriteOff = writeOffItems.value
        .filter(item => item.writeOff)
        .map(item => ({
          ingredientId: item.ingredientId,
          quantity: item.quantity * producedQuantity.value,
          unit: item.unit
        }));

      const payload = {
        semiFinishedId: selectedSemi.value,
        producedQuantity: producedQuantity.value,
        ingredients: ingredientsToWriteOff
      };

      try {
        const response = await api.post('/index.php', payload, {
          params: { action: 'semi.produce' }
        });
        $q.notify({ type: 'positive', message: 'Производство выполнено' });
        emit('produced', response.data);
        emit('update:modelValue', false);
      } catch (error: any) {
        $q.notify({ type: 'negative', message: error.message || 'Ошибка' });
      }
    };

    watch(() => props.modelValue, (val) => {
      if (val) {
        selectedSemi.value = null;
        producedQuantity.value = 1;
        writeOffItems.value = [];
        filteredSemiOptions.value = props.semiFinishedList;
      }
    });

    const formatMoney = (val: number) => {
      return new Intl.NumberFormat('ru-RU', {
        style: 'currency', currency: 'RUB', minimumFractionDigits: 2
      }).format(val);
    };

    return {
      selectedSemi,
      producedQuantity,
      writeOffItems,
      filteredSemiOptions,
      loadingOptions,
      totalCost,
      canSubmit,
      filterSemi,
      onSemiSelect,
      onSubmit,
      formatMoney,
      hasNegativeStock
    };
  }
});
</script>
