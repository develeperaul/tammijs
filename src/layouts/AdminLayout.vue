<template>
  <q-layout view="hHh LpR fFf">
    <q-header elevated class="bg-primary text-white">
      <q-toolbar>
        <q-btn
          flat
          dense
          round
          icon="menu"
          aria-label="Menu"
          @click="toggleLeftDrawer"
        />

        <q-toolbar-title>
          Food CRM - Администратор
        </q-toolbar-title>
      </q-toolbar>
    </q-header>

    <q-drawer
      v-model="leftDrawerOpen"
      show-if-above
      bordered
      class="bg-grey-1"
    >
      <q-list>
        <q-item-label header class="text-grey-8">
          Меню
        </q-item-label>

        <!-- Товары -->
        <q-item clickable v-ripple to="/products" exact>
          <q-item-section avatar>
            <q-icon name="inventory" />
          </q-item-section>
          <q-item-section>
            Товары
          </q-item-section>
        </q-item>

        <!-- Рецепты -->
        <q-item clickable v-ripple to="/recipes" exact>
          <q-item-section avatar>
            <q-icon name="menu_book" />
          </q-item-section>
          <q-item-section>
            Рецепты
          </q-item-section>
        </q-item>

        <!-- Остатки -->
        <q-item clickable v-ripple to="/stock" exact>
          <q-item-section avatar>
            <q-icon name="warehouse" />
          </q-item-section>
          <q-item-section>
            Остатки
          </q-item-section>
        </q-item>

        <!-- Продажи -->
        <q-item clickable v-ripple to="/sale" exact>
          <q-item-section avatar>
            <q-icon name="point_of_sale" />
          </q-item-section>
          <q-item-section>
            Продажи
          </q-item-section>
        </q-item>

        <!-- Кухня -->
        <q-item clickable v-ripple to="/kitchen" exact>
          <q-item-section avatar>
            <q-icon name="restaurant_menu" />
          </q-item-section>
          <q-item-section>
            Кухня
          </q-item-section>
        </q-item>

        <q-item clickable v-ripple to="/reports" exact>
          <q-item-section avatar>
            <q-icon name="bar_chart" />
          </q-item-section>
          <q-item-section>
            Отчёты
          </q-item-section>
        </q-item>

        <!-- Тест API (опционально) -->
        <q-item clickable v-ripple to="/test" exact>
          <q-item-section avatar>
            <q-icon name="api" />
          </q-item-section>
          <q-item-section>
            Тест API
          </q-item-section>
        </q-item>


      </q-list>
    </q-drawer>

    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script lang="ts">
import { defineComponent, ref } from 'vue';

export default defineComponent({
  name: 'AdminLayout',

  setup() {
    const leftDrawerOpen = ref(false);

    return {
      leftDrawerOpen,
      toggleLeftDrawer() {
        leftDrawerOpen.value = !leftDrawerOpen.value;
      }
    };
  }
});
</script>
