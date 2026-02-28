<template>
  <q-page class="q-pa-md">
    <div class="text-h4 text-center q-mb-md">Тестовая страница</div>

    <div class="text-center">
      <q-btn
        label="Нажми меня"
        color="primary"
        @click="showMessage"
      />
    </div>

    <div v-if="message" class="text-center q-mt-md">
      {{ message }}
    </div>
  </q-page>
</template>

<script lang="ts">
import { defineComponent, ref } from 'vue';

export default defineComponent({
  name: 'TestPage',

  setup() {
    const message = ref('');

    const showMessage = () => {
      message.value = 'Страница работает! 🎉';
    };

    return {
      message,
      showMessage,
    };
  },
});
</script>
