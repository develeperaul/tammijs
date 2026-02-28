<template>
  <q-layout view="hHh LpR fFf">
    <q-header elevated class="bg-primary text-white">
      <q-toolbar>
        <q-toolbar-title>
          Food CRM - Сотрудник
        </q-toolbar-title>
      </q-toolbar>
      <q-tabs v-model="currentTab" class="bg-primary text-white">
        <q-route-tab to="/employee/sale" label="Продажи" />
        <q-route-tab to="/employee/kitchen" label="Кухня" />
      </q-tabs>
    </q-header>

    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script lang="ts">
import { defineComponent, ref } from 'vue';

export default defineComponent({
  name: 'EmployeeLayout',
  setup() {
    // Определяем переменную для отслеживания активной вкладки
    const currentTab = ref<string>('sale'); // можно установить начальное значение

    return {
      currentTab
    };
  }
});
</script>
