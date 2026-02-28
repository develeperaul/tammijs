<template>
  <div class="row q-gutter-sm items-center">
    <q-input
      :model-value="formattedStartDate"
      label="Начало"
      dense
      outlined
      readonly
      style="width: 140px"
    >
      <template v-slot:append>
        <q-icon name="event" class="cursor-pointer">
          <q-popup-proxy cover transition-show="scale" transition-hide="scale">
            <q-date v-model="startDate" mask="YYYY-MM-DD" />
          </q-popup-proxy>
        </q-icon>
      </template>
    </q-input>

    <span>—</span>

    <q-input
      :model-value="formattedEndDate"
      label="Конец"
      dense
      outlined
      readonly
      style="width: 140px"
    >
      <template v-slot:append>
        <q-icon name="event" class="cursor-pointer">
          <q-popup-proxy cover transition-show="scale" transition-hide="scale">
            <q-date v-model="endDate" mask="YYYY-MM-DD" />
          </q-popup-proxy>
        </q-icon>
      </template>
    </q-input>

    <q-btn-group flat>
      <q-btn label="Сегодня" @click="setToday" />
      <q-btn label="Неделя" @click="setWeek" />
      <q-btn label="Месяц" @click="setMonth" />
    </q-btn-group>
  </div>
</template>

<script lang="ts">
import { defineComponent, ref, watch, computed } from 'vue';
import dayjs from 'dayjs';

export default defineComponent({
  name: 'DateRangePicker',
  props: {
    start: { type: String, required: true },
    end: { type: String, required: true }
  },
  emits: ['update:start', 'update:end'],
  setup(props, { emit }) {
    const startDate = ref(props.start);
    const endDate = ref(props.end);

    watch(startDate, (val) => emit('update:start', val));
    watch(endDate, (val) => emit('update:end', val));

    const formattedStartDate = computed(() => dayjs(startDate.value).format('DD.MM.YYYY'));
    const formattedEndDate = computed(() => dayjs(endDate.value).format('DD.MM.YYYY'));

    const setToday = () => {
      const today = dayjs().format('YYYY-MM-DD');
      startDate.value = today;
      endDate.value = today;
    };

    const setWeek = () => {
      const end = dayjs();
      const start = dayjs().subtract(6, 'day');
      startDate.value = start.format('YYYY-MM-DD');
      endDate.value = end.format('YYYY-MM-DD');
    };

    const setMonth = () => {
      const end = dayjs();
      const start = dayjs().subtract(29, 'day');
      startDate.value = start.format('YYYY-MM-DD');
      endDate.value = end.format('YYYY-MM-DD');
    };

    return {
      startDate,
      endDate,
      formattedStartDate,
      formattedEndDate,
      setToday,
      setWeek,
      setMonth
    };
  }
});
</script>
