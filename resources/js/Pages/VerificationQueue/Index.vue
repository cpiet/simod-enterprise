<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import EnterpriseLayout from '@/Layouts/EnterpriseLayout.vue';
import {
  CheckCircle2,
  XCircle,
  Clock,
  ShieldCheck,
  AlertTriangle,
  Building2,
  ExternalLink,
} from 'lucide-vue-next';

const props = defineProps({
  allPoldas: Array,
  selectedPolda: Object,
  selectedCode: String,
  issues: Object, // Paginated
  metrics: Object,
});
</script>

<template>
  <EnterpriseLayout
    :all-poldas="allPoldas"
    :selected-polda="selectedPolda"
    :selected-code="selectedCode"
    :metrics="metrics"
  >
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2">
          <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400 text-slate-950">
            Verifikasi Kewilayahan
          </span>
          <span class="text-xs font-bold text-slate-400">
            {{ selectedCode === 'ALL' ? 'Mabes Polri' : selectedPolda?.name }}
          </span>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight mt-1">
          Antrean Verifikasi Laporan & Isu
        </h1>
        <p class="text-xs md:text-sm text-slate-300">
          Validasi laporan lapangan sebelum dieskalasi ke pimpinan atau dipublikasikan ke publik.
        </p>
      </div>
    </div>

    <!-- Issues Queue List -->
    <div v-if="issues?.data?.length > 0" class="space-y-4">
      <div
        v-for="issue in issues.data"
        :key="issue.id"
        class="rounded-3xl bg-[#0b1426] border border-slate-800 p-6 shadow-xl space-y-4 hover:border-slate-700 transition"
      >
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-2 border-b border-slate-800/80 pb-3">
          <div class="flex items-center gap-2">
            <span
              class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase"
              :class="issue.priority === 'critical' ? 'bg-rose-500 text-white' : 'bg-amber-400 text-slate-950'"
            >
              Prioritas: {{ issue.priority }}
            </span>
            <span class="text-xs font-bold text-sky-400">
              Satwil: {{ issue.polres?.name || issue.polda?.name }}
            </span>
          </div>
          <span class="text-xs text-slate-400 font-mono">
            Masuk: {{ new Date(issue.created_at).toLocaleString('id-ID') }}
          </span>
        </div>

        <div>
          <h3 class="text-base md:text-lg font-black text-white leading-snug">{{ issue.title }}</h3>
          <p class="text-xs text-slate-300 mt-1 leading-relaxed">{{ issue.summary }}</p>
        </div>

        <div class="flex items-center justify-between pt-3 border-t border-slate-800/80">
          <span class="text-xs font-bold text-amber-400 flex items-center gap-1.5">
            <Clock class="w-3.5 h-3.5" /> Menunggu Persetujuan Kabid Humas
          </span>

          <div class="flex items-center gap-2">
            <Link
              :href="`/issues?polda=${selectedCode}`"
              class="px-4 py-1.5 rounded-xl text-xs font-bold bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-400 border border-emerald-500/40 transition"
            >
              ✓ Verifikasi & Proses
            </Link>
          </div>
        </div>
      </div>
    </div>
    <div v-else class="p-12 rounded-3xl bg-[#0b1426] border border-slate-800 text-center text-slate-400 text-xs">
      Tidak ada antrean verifikasi laporan saat ini. Seluruh laporan telah ditindaklanjuti.
    </div>
  </EnterpriseLayout>
</template>

