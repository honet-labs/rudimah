<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue';

const props = withDefaults(
  defineProps<{
    option: Record<string, any>;
    height?: string;
    width?: string;
    loading?: boolean;
  }>(),
  {
    height: '320px',
    width: '100%',
    loading: false,
  }
);

const chartRef = ref<HTMLDivElement | null>(null);
const isChartLoading = ref(true);
const chartError = ref<string | null>(null);

let chartInstance: any = null;
let resizeObserver: ResizeObserver | null = null;
let themeObserver: MutationObserver | null = null;
let cachedECharts: any = null;

async function loadECharts(): Promise<any> {
  if (cachedECharts) return cachedECharts;

  // 1. Cek window.echarts jika sudah dimuat
  if (typeof window !== 'undefined' && (window as any).echarts) {
    cachedECharts = (window as any).echarts;
    return cachedECharts;
  }

  // 2. Coba import dari package npm 'echarts'
  try {
    const mod = await import('echarts');
    cachedECharts = (mod as any).default || mod;
    return cachedECharts;
  } catch {
    // 3. Fallback CDN jika bundle lokal belum mencakup package
    if (typeof window !== 'undefined') {
      return new Promise((resolve, reject) => {
        const scriptId = 'apache-echarts-cdn';
        if (document.getElementById(scriptId)) {
          const checkInterval = setInterval(() => {
            if ((window as any).echarts) {
              clearInterval(checkInterval);
              cachedECharts = (window as any).echarts;
              resolve(cachedECharts);
            }
          }, 50);
          return;
        }

        const script = document.createElement('script');
        script.id = scriptId;
        script.src = 'https://cdn.jsdelivr.net/npm/echarts@5.6.0/dist/echarts.min.js';
        script.async = true;
        script.onload = () => {
          cachedECharts = (window as any).echarts;
          resolve(cachedECharts);
        };
        script.onerror = () => reject(new Error('Gagal memuat library Apache ECharts'));
        document.head.appendChild(script);
      });
    }
  }

  throw new Error('Apache ECharts tidak tersedia di lingkungan ini');
}

function getIsDark(): boolean {
  if (typeof document === 'undefined') return false;
  return document.documentElement.classList.contains('dark');
}

function getSanitizedOption(rawOption: Record<string, any>, isDark: boolean): Record<string, any> {
  const opt = JSON.parse(JSON.stringify(rawOption || {}));

  // Skema warna default selaras dengan token desain RUDIMAH
  if (!opt.color || !Array.isArray(opt.color) || opt.color.length === 0) {
    opt.color = ['#0d9488', '#0284c7', '#6366f1', '#f59e0b', '#ec4899', '#10b981', '#8b5cf6'];
  }

  // Latar belakang transparan agar menyatu dengan kartu RUDIMAH
  opt.backgroundColor = 'transparent';

  // Penyesuaian tipografi & garis axis
  const textMuted = isDark ? '#94a3b8' : '#64748b';
  const splitLineColor = isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)';

  if (opt.textStyle) {
    opt.textStyle.fontFamily = 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
  } else {
    opt.textStyle = { fontFamily: 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif' };
  }

  // Tooltip modern styling
  if (!opt.tooltip) {
    opt.tooltip = { trigger: 'axis' };
  }
  opt.tooltip = {
    ...opt.tooltip,
    backgroundColor: isDark ? 'rgba(15, 23, 42, 0.95)' : 'rgba(255, 255, 255, 0.95)',
    borderColor: isDark ? '#334155' : '#e2e8f0',
    borderWidth: 1,
    padding: [8, 12],
    textStyle: {
      color: isDark ? '#f8fafc' : '#0f172a',
      fontSize: 12,
    },
    extraCssText: 'box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); border-radius: 8px;',
  };

  // Sesuaikan grid jika belum tersetting
  if (!opt.grid) {
    opt.grid = {
      left: '3%',
      right: '4%',
      bottom: '5%',
      top: opt.title ? '18%' : '10%',
      containLabel: true,
    };
  }

  // Styling Axis
  const adjustAxis = (axis: any) => {
    if (!axis) return;
    if (Array.isArray(axis)) {
      axis.forEach(adjustAxis);
      return;
    }
    axis.axisLine = { lineStyle: { color: isDark ? '#334155' : '#cbd5e1' } };
    axis.axisLabel = {
      color: textMuted,
      fontSize: 11,
      ...(axis.axisLabel || {}),
    };
    if (axis.splitLine) {
      axis.splitLine.lineStyle = { color: splitLineColor, ...(axis.splitLine.lineStyle || {}) };
    }
  };

  if (opt.xAxis) adjustAxis(opt.xAxis);
  if (opt.yAxis) adjustAxis(opt.yAxis);

  return opt;
}

async function renderChart() {
  if (!chartRef.value) return;

  try {
    isChartLoading.value = true;
    chartError.value = null;

    const echarts = await loadECharts();
    const isDark = getIsDark();

    if (!chartInstance) {
      chartInstance = echarts.init(chartRef.value, isDark ? 'dark' : undefined, {
        renderer: 'canvas',
      });
    }

    const sanitized = getSanitizedOption(props.option, isDark);
    chartInstance.setOption(sanitized, true);
    isChartLoading.value = false;
  } catch (err: any) {
    console.error('[RdChart] Gagal merender grafik Apache ECharts:', err);
    chartError.value = err?.message || 'Gagal memuat visualisasi grafik';
    isChartLoading.value = false;
  }
}

function handleResize() {
  if (chartInstance) {
    chartInstance.resize();
  }
}

watch(
  () => props.option,
  async () => {
    await nextTick();
    renderChart();
  },
  { deep: true }
);

watch(
  () => props.loading,
  (val) => {
    if (chartInstance) {
      if (val) {
        chartInstance.showLoading({ text: 'Memuat data...', color: '#0d9488' });
      } else {
        chartInstance.hideLoading();
      }
    }
  }
);

onMounted(async () => {
  await nextTick();
  renderChart();

  if (chartRef.value && typeof ResizeObserver !== 'undefined') {
    resizeObserver = new ResizeObserver(() => {
      handleResize();
    });
    resizeObserver.observe(chartRef.value);
  }

  // Observer perubahan mode tema gelap / terang
  if (typeof MutationObserver !== 'undefined' && typeof document !== 'undefined') {
    themeObserver = new MutationObserver(() => {
      if (chartInstance) {
        chartInstance.dispose();
        chartInstance = null;
        renderChart();
      }
    });
    themeObserver.observe(document.documentElement, {
      attributes: true,
      attributeFilter: ['class'],
    });
  }
});

onUnmounted(() => {
  if (resizeObserver) {
    resizeObserver.disconnect();
    resizeObserver = null;
  }
  if (themeObserver) {
    themeObserver.disconnect();
    themeObserver = null;
  }
  if (chartInstance) {
    chartInstance.dispose();
    chartInstance = null;
  }
});
</script>

<template>
  <div class="relative w-full overflow-hidden select-none">
    <div
      ref="chartRef"
      class="w-full transition-opacity duration-200"
      :style="{ height, width }"
    ></div>

    <!-- State Loading -->
    <div
      v-if="isChartLoading && !chartError"
      class="absolute inset-0 flex items-center justify-center bg-white/60 dark:bg-slate-900/60 backdrop-blur-xs rounded-lg"
    >
      <div class="flex items-center gap-2 text-xs text-slate-500 font-medium">
        <svg class="w-4 h-4 animate-spin text-teal-600" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span>Memuat grafik Apache ECharts...</span>
      </div>
    </div>

    <!-- State Gagal / Error -->
    <div
      v-if="chartError"
      class="absolute inset-0 flex flex-col items-center justify-center p-4 bg-slate-50 dark:bg-slate-800/80 rounded-lg text-center"
    >
      <div class="text-xs text-red-600 dark:text-red-400 font-medium mb-2">
        {{ chartError }}
      </div>
      <button
        type="button"
        class="text-[11px] text-teal-700 dark:text-teal-400 font-semibold hover:underline cursor-pointer"
        @click="renderChart"
      >
        Muat Ulang Grafik
      </button>
    </div>
  </div>
</template>
