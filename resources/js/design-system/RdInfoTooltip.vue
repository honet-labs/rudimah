<script setup lang="ts">
import { ref, onMounted, onUnmounted, nextTick } from 'vue';

const props = withDefaults(
  defineProps<{
    text?: string;
    title?: string;
    size?: 'sm' | 'md';
    align?: 'left' | 'center' | 'right';
  }>(),
  {
    text: '',
    title: 'Informasi',
    size: 'md',
    align: 'left',
  }
);

const isOpen = ref(false);
const triggerRef = ref<HTMLElement | null>(null);
const popoverRef = ref<HTMLElement | null>(null);
let hoverTimer: ReturnType<typeof setTimeout> | null = null;

const popoverStyle = ref<{
  top: string;
  left: string;
  width: string;
  maxWidth: string;
}>({
  top: '0px',
  left: '0px',
  width: '320px',
  maxWidth: 'calc(100vw - 24px)',
});

function calculatePosition() {
  if (!triggerRef.value) return;
  const rect = triggerRef.value.getBoundingClientRect();
  const screenWidth = window.innerWidth;
  const screenHeight = window.innerHeight;
  const margin = 12;

  // Lebar popover: maksimal 320px atau selebar layar dikurangi margin kiri-kanan
  const popoverWidth = Math.min(320, screenWidth - margin * 2);

  // Hitung posisi horizontal ideal
  let left = rect.left;
  if (props.align === 'right' || rect.left > screenWidth / 2) {
    // Jika tombol berada di paruh kanan layar, sejajarkan dari tepi kanan tombol
    left = rect.right - popoverWidth;
  } else if (props.align === 'center') {
    left = rect.left + rect.width / 2 - popoverWidth / 2;
  }

  // Clamping mutlak: jangan pernah keluar dari batas layar
  if (left + popoverWidth > screenWidth - margin) {
    left = screenWidth - margin - popoverWidth;
  }
  if (left < margin) {
    left = margin;
  }

  // Hitung posisi vertikal (buka ke bawah, atau ke atas jika ruang bawah tidak mencukupi)
  let top = rect.bottom + 8;
  const popoverHeight = popoverRef.value ? popoverRef.value.getBoundingClientRect().height : 140;

  if (rect.bottom + popoverHeight > screenHeight - margin && rect.top > popoverHeight + margin) {
    top = Math.max(margin, rect.top - popoverHeight - 8);
  }

  popoverStyle.value = {
    top: `${Math.round(top)}px`,
    left: `${Math.round(left)}px`,
    width: `${Math.round(popoverWidth)}px`,
    maxWidth: `calc(100vw - ${margin * 2}px)`,
  };
}

function open() {
  if (hoverTimer) {
    clearTimeout(hoverTimer);
    hoverTimer = null;
  }
  calculatePosition();
  isOpen.value = true;
  nextTick(() => {
    calculatePosition();
    requestAnimationFrame(() => {
      calculatePosition();
    });
  });
}

function close() {
  if (hoverTimer) {
    clearTimeout(hoverTimer);
    hoverTimer = null;
  }
  isOpen.value = false;
}

function toggle() {
  if (isOpen.value) {
    close();
  } else {
    open();
  }
}

function handleMouseEnter() {
  if (hoverTimer) clearTimeout(hoverTimer);
  hoverTimer = setTimeout(() => {
    open();
  }, 100);
}

function handleMouseLeave() {
  if (hoverTimer) clearTimeout(hoverTimer);
  hoverTimer = setTimeout(() => {
    close();
  }, 200);
}

function cancelClose() {
  if (hoverTimer) {
    clearTimeout(hoverTimer);
    hoverTimer = null;
  }
}

function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && isOpen.value) {
    close();
  }
}

function handleScrollOrResize() {
  if (isOpen.value) {
    calculatePosition();
  }
}

function handleDocumentClick(event: MouseEvent) {
  if (!isOpen.value) return;
  const target = event.target as Node;
  if (triggerRef.value && triggerRef.value.contains(target)) return;
  if (popoverRef.value && popoverRef.value.contains(target)) return;
  close();
}

onMounted(() => {
  document.addEventListener('click', handleDocumentClick);
  document.addEventListener('keydown', handleKeydown);
  window.addEventListener('resize', handleScrollOrResize);
  window.addEventListener('scroll', handleScrollOrResize, true);
});

onUnmounted(() => {
  if (hoverTimer) clearTimeout(hoverTimer);
  document.removeEventListener('click', handleDocumentClick);
  document.removeEventListener('keydown', handleKeydown);
  window.removeEventListener('resize', handleScrollOrResize);
  window.removeEventListener('scroll', handleScrollOrResize, true);
});
</script>

<template>
  <div
    class="relative inline-flex items-center align-middle"
    @mouseenter="handleMouseEnter"
    @mouseleave="handleMouseLeave"
  >
    <button
      ref="triggerRef"
      type="button"
      :class="[
        'rounded-full inline-flex items-center justify-center transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-teal-700/30 shrink-0 select-none',
        size === 'sm'
          ? 'w-5 h-5 text-slate-400 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-200/60 dark:hover:bg-slate-800'
          : 'w-6 h-6 text-slate-400 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-200/60 dark:hover:bg-slate-800',
        isOpen ? 'text-teal-700 dark:text-teal-300 bg-teal-50 dark:bg-teal-950/40 ring-1 ring-teal-700/30' : ''
      ]"
      :aria-label="title ? `Informasi: ${title}` : 'Informasi'"
      :aria-expanded="isOpen"
      @click.stop="toggle"
    >
      <svg
        :class="size === 'sm' ? 'w-3.5 h-3.5' : 'w-4 h-4'"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        stroke-width="2"
        aria-hidden="true"
      >
        <circle cx="12" cy="12" r="9" stroke="currentColor" />
        <path stroke-linecap="round" d="M12 16v-4M12 8h.01" />
      </svg>
    </button>

    <!-- Popover Informasi (Teleported ke body untuk mencegah clipping/terpotong oleh overflow parent) -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0 translate-y-1 scale-95"
        enter-to-class="opacity-100 translate-y-0 scale-100"
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="opacity-100 translate-y-0 scale-100"
        leave-to-class="opacity-0 translate-y-1 scale-95"
      >
        <div
          v-if="isOpen"
          ref="popoverRef"
          :style="popoverStyle"
          class="fixed z-[99999] rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl p-3 text-left font-normal normal-case tracking-normal cursor-default ring-1 ring-slate-950/5 dark:ring-slate-800/80"
          role="tooltip"
          @mouseenter="cancelClose"
          @mouseleave="handleMouseLeave"
          @click.stop
        >
          <!-- Header Info Card -->
          <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-slate-100 dark:border-slate-800">
            <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
              <svg class="w-3.5 h-3.5 text-teal-600 dark:text-teal-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="9" />
                <path stroke-linecap="round" d="M12 16v-4M12 8h.01" />
              </svg>
              {{ title }}
            </span>
            <button
              type="button"
              class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-0.5 rounded hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer"
              aria-label="Tutup informasi"
              @click.stop="close"
            >
              <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Konten Penjelasan / Deskripsi Informasi -->
          <div class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed break-words">
            <slot>{{ text }}</slot>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>
