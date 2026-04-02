<template>
  <q-page class="q-pa-md">
    <div class="row q-mb-md items-center">
      <div class="col-6">
        <div class="text-h5">Рецепты полуфабрикатов</div>
        <div class="text-caption text-grey-7">Всего: {{ recipes.length }}</div>
      </div>
      <div class="col-6 text-right">
        <q-btn color="primary" label="Создать рецепт" icon="add" @click="openCreateDialog" />
      </div>
    </div>

    <semi-recipe-table
      :recipes="recipes"
      :loading="loading"
      @edit="openEditDialog"
      @delete="confirmDelete"
    />

    <semi-recipe-dialog
      v-model="dialog"
      :recipe="selectedRecipe"
      :semi-finished-list="semiFinishedList"
      :ingredients-list="ingredientsList"
      @ok="saveRecipe"
      @hide="selectedRecipe = null"
    />
  </q-page>
</template>

<script lang="ts">
import { defineComponent, ref, onMounted } from 'vue';
import { useQuasar } from 'quasar';
import semiRecipeService from 'src/services/semi-recipe.service';
import semiFinishedService from 'src/services/semi-finished.service';
import ingredientService from 'src/services/ingredient.service';
import SemiRecipeTable from 'components/semi-recipes/SemiRecipeTable.vue';
import SemiRecipeDialog from 'components/semi-recipes/SemiRecipeDialog.vue';

export default defineComponent({
  name: 'SemiRecipesPage',

  components: { SemiRecipeTable, SemiRecipeDialog },

  setup() {
    const $q = useQuasar();
    const recipes = ref([]);
    const semiFinishedList = ref([]);
    const ingredientsList = ref([]);
    const loading = ref(false);
    const dialog = ref(false);
    const selectedRecipe = ref(null);

    const loadData = async () => {
      loading.value = true;
      try {
        const [recipesData, semiData, ingData] = await Promise.all([
          (await semiRecipeService.getAll()).data,
          (await semiFinishedService.getAll()).data,
          (await ingredientService.getAll()).data
        ]);
        recipes.value = recipesData;
        semiFinishedList.value = semiData;
        ingredientsList.value = ingData;
      } catch (error) {
        $q.notify({ type: 'negative', message: 'Ошибка загрузки данных' });
      } finally {
        loading.value = false;
      }
    };

    const openCreateDialog = () => {
      selectedRecipe.value = null;
      dialog.value = true;
    };

    const openEditDialog = (recipe: any) => {
      selectedRecipe.value = recipe;
      dialog.value = true;
    };

    const saveRecipe = async (formData: any) => {
      try {
        if (selectedRecipe.value) {
          await semiRecipeService.update(selectedRecipe.value.id, formData);
          $q.notify({ type: 'positive', message: 'Рецепт обновлён' });
        } else {
          await semiRecipeService.create(formData);
          $q.notify({ type: 'positive', message: 'Рецепт создан' });
        }
        await loadData();
        dialog.value = false;
      } catch (error: any) {
        $q.notify({ type: 'negative', message: error.message || 'Ошибка' });
      }
    };

    const confirmDelete = (recipe: any) => {
      $q.dialog({
        title: 'Подтверждение',
        message: `Удалить рецепт "${recipe.name}"?`,
        cancel: true
      }).onOk(async () => {
        try {
          await semiRecipeService.delete(recipe.id);
          await loadData();
          $q.notify({ type: 'positive', message: 'Рецепт удалён' });
        } catch (error) {
          $q.notify({ type: 'negative', message: 'Ошибка удаления' });
        }
      });
    };

    onMounted(() => loadData());

    return {
      recipes,
      semiFinishedList,
      ingredientsList,
      loading,
      dialog,
      selectedRecipe,
      openCreateDialog,
      openEditDialog,
      saveRecipe,
      confirmDelete
    };
  }
});
</script>
