<template>
  <q-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    @hide="$emit('hide')"
    persistent
  >
    <q-card style="min-width: 500px">
      <q-card-section>
        <div class="text-h6">{{ isEdit ? 'Редактировать' : 'Новый' }} поставщик</div>
      </q-card-section>

      <q-card-section>
        <q-form @submit="onSubmit" class="q-gutter-md">
          <!-- Основная информация -->
          <q-input
            v-model="form.name"
            label="Название поставщика *"
            outlined
            dense
            :rules="[val => !!val || 'Введите название']"
          />

          <div class="row q-gutter-sm">
            <q-input
              v-model="form.phone"
              label="Телефон"
              outlined
              dense
              class="col"
              mask="+7 (###) ###-##-##"
              unmasked-value
            />
            <q-input
              v-model="form.email"
              label="Email"
              outlined
              dense
              class="col"
              type="email"
            />
          </div>

          <q-input
            v-model="form.address"
            label="Адрес"
            outlined
            dense
          />

          <div class="row q-gutter-sm">
            <q-input
              v-model="form.inn"
              label="ИНН"
              outlined
              dense
              class="col"
              mask="##############"
              unmasked-value
            />
            <q-input
              v-model="form.kpp"
              label="КПП"
              outlined
              dense
              class="col"
              mask="#########"
              unmasked-value
            />
          </div>

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
          :disable="!form.name"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script lang="ts">
import { defineComponent, ref, computed, watch } from 'vue';
import { Supplier, CreateSupplierDto } from 'src/types/supplier.types';

export default defineComponent({
  name: 'SupplierDialog',

  props: {
    modelValue: {
      type: Boolean,
      required: true
    },
    supplier: {
      type: Object as () => Supplier | null,
      default: null
    }
  },

  emits: ['update:modelValue', 'ok', 'hide'],

  setup(props, { emit }) {
    const isEdit = computed(() => !!props.supplier);

    const form = ref<CreateSupplierDto>({
      name: '',
      phone: '',
      email: '',
      address: '',
      inn: '',
      kpp: '',
      comment: ''
    });

    watch(() => props.supplier, (val) => {
      if (val) {
        form.value = {
          name: val.name,
          phone: val.phone || '',
          email: val.email || '',
          address: val.address || '',
          inn: val.inn || '',
          kpp: val.kpp || '',
          comment: val.comment || ''
        };
      } else {
        form.value = {
          name: '',
          phone: '',
          email: '',
          address: '',
          inn: '',
          kpp: '',
          comment: ''
        };
      }
    }, { immediate: true });

    const onSubmit = () => {
      if (!form.value.name) return;
      emit('ok', form.value);
      emit('update:modelValue', false);
    };

    return {
      isEdit,
      form,
      onSubmit
    };
  }
});
</script>
