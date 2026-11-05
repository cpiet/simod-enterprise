<script setup>
import { computed } from 'vue';
import { Check } from 'lucide-vue-next';
import { ISSUE_STEPS, stepIndex } from '@/utils/issueFlow';

const props = defineProps({
  status: { type: String, default: 'monitoring' },
});

const current = computed(() => stepIndex(props.status));
</script>

<template>
  <ol class="flex items-center w-full" aria-label="Tahapan penanganan laporan">
    <li
      v-for="(step, i) in ISSUE_STEPS"
      :key="step.key"
      class="flex items-center"
      :class="i < ISSUE_STEPS.length - 1 ? 'flex-1' : ''"
      :aria-current="i === current ? 'step' : undefined"
    >
      <div class="flex items-center gap-2 shrink-0" :title="step.hint">
        <span
          class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-black border-2 transition"
          :class="i < current
            ? 'bg-emerald-500 border-emerald-500 text-white'
            : i === current
              ? 'bg-amber-400 border-amber-400 text-slate-950 ring-4 ring-amber-400/25'
              : 'bg-transparent border-slate-300 dark:border-slate-700 text-slate-400'"
        >
          <Check v-if="i < current" class="w-3.5 h-3.5" />
          <span v-else>{{ i + 1 }}</span>
        </span>
        <span
          class="text-[11px] font-bold whitespace-nowrap"
          :class="[
            i === current ? 'text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400',
            i === current ? '' : 'hidden sm:inline',
          ]"
        >
          {{ step.label }}
        </span>
      </div>
      <div
        v-if="i < ISSUE_STEPS.length - 1"
        class="flex-1 h-0.5 mx-2 rounded"
        :class="i < current ? 'bg-emerald-500' : 'bg-slate-200 dark:bg-slate-800'"
      ></div>
    </li>
  </ol>
</template>
