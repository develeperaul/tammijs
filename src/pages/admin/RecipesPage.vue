<template>
  <q-page class="q-pa-md">
    <div class="row q-mb-md items-center">
      <div class="col-6">
        <div class="text-h5">Технологические карты (рецептуры)</div>
        <div class="text-caption text-grey-7">
          Всего рецептов: {{ recipes.length }}
        </div>
      </div>
      <div class="col-6 text-right">
        <q-btn color="primary" label="Создать рецепт" icon="add" @click="openCreateDialog" />
      </div>
    </div>

    <recipe-table
      :recipes="recipes"
      :loading="loading"
      @edit="openEditDialog"
      @delete="confirmDelete"
    />

    <recipe-dialog
      v-model="dialog"
      :recipe="selectedRecipe"
      :finished-products="finishedProducts"
      :ingredients-list="ingredientsList"
      @ok="saveRecipe"
      @hide="selectedRecipe = null"
    />
  </q-page>
</template>

<script lang="ts">
import { defineComponent, ref, onMounted } from 'vue';
import { useQuasar } from 'quasar';
import recipeService from 'src/services/recipe.service';
import productService from 'src/services/product.service';
import { Recipe } from 'src/types/recipe.types';
import { Product } from 'src/types/product.types';
import RecipeTable from 'components/recipes/RecipeTable.vue';
import RecipeDialog from 'components/recipes/RecipeDialog.vue';

export default defineComponent({
  name: 'RecipesPage',

  components: { RecipeTable, RecipeDialog },

  setup() {
    const $q = useQuasar();
    const recipes = ref<Recipe[]>([]);
    const loading = ref(false);
    const dialog = ref(false);
    const selectedRecipe = ref<Recipe | null>(null);
    const finishedProducts = ref<Product[]>([]);
    const ingredientsList = ref<Product[]>([]);

    const loadRecipes = async () => {
      loading.value = true;
      try {
        recipes.value = await recipeService.getRecipes();
      } catch (error) {
        $q.notify({ type: 'negative', message: 'Ошибка загрузки рецептов' });
      } finally {
        loading.value = false;
      }
    };

    const loadProducts = async () => {
      try {
        const all = await productService.getProducts();
        finishedProducts.value = all.filter(p => p.type === 'finished');
        ingredientsList.value = all.filter(p => p.type === 'ingredient');
      } catch (error) {
        $q.notify({ type: 'negative', message: 'Ошибка загрузки товаров' });
      }
    };

    const openCreateDialog = () => {
      selectedRecipe.value = null;
      dialog.value = true;
    };

    const openEditDialog = (recipe: Recipe) => {
      selectedRecipe.value = recipe;
      dialog.value = true;
    };

    const saveRecipe = async (formData: any) => {
      try {
        if (selectedRecipe.value) {
          await recipeService.updateRecipe(selectedRecipe.value.id, formData);
          $q.notify({ type: 'positive', message: 'Рецепт обновлён' });
        } else {
          await recipeService.createRecipe(formData);
          $q.notify({ type: 'positive', message: 'Рецепт создан' });
        }
        await loadRecipes();
        dialog.value = false;
      } catch (error: any) {
        $q.notify({ type: 'negative', message: error.message || 'Ошибка сохранения' });
      }
    };

    const confirmDelete = (recipe: Recipe) => {
      $q.dialog({
        title: 'Подтверждение',
        message: `Удалить рецепт "${recipe.name}"?`,
        cancel: true,
        persistent: true
      }).onOk(async () => {
        try {
          await recipeService.deleteRecipe(recipe.id);
          await loadRecipes();
          $q.notify({ type: 'positive', message: 'Рецепт удалён' });
        } catch (error) {
          $q.notify({ type: 'negative', message: 'Ошибка удаления' });
        }
      });
    };

    onMounted(() => {
      loadRecipes();
      loadProducts();
    });

    return {
      recipes,
      loading,
      dialog,
      selectedRecipe,
      finishedProducts,
      ingredientsList,
      openCreateDialog,
      openEditDialog,
      saveRecipe,
      confirmDelete
    };
  }
});
</script>
