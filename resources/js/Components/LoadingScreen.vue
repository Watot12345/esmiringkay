<script setup lang="ts">
import BrandLogo from './BrandLogo.vue';

interface Props {
  statusText?: string;
}

withDefaults(defineProps<Props>(), {
  statusText: 'Connecting to the Plaza...',
});
</script>

<template>
  <div class="fixed inset-0 z-50 bg-[#0F1020] flex flex-col items-center justify-center select-none overflow-hidden">
    <!-- Ambient glowing backdrop -->
    <div class="absolute w-72 h-72 rounded-full bg-[#8B7CFF]/15 blur-3xl pointer-events-none -translate-y-8 animate-pulse"></div>
    <div class="absolute w-60 h-60 rounded-full bg-[#FF8A7A]/10 blur-3xl pointer-events-none translate-y-8"></div>

    <!-- Animated Kissing Bubbles Logo Mark Only -->
    <div class="relative z-10 flex flex-col items-center">
      <BrandLogo size="lg" :animated="true" :show-wordmark="true" />

      <!-- Minimal calm status indicator -->
      <div class="mt-6 flex flex-col items-center gap-2">
        <p class="text-xs text-[#A9A6CC] font-medium tracking-wide">
          {{ statusText }}
        </p>
        <div class="w-32 h-1 bg-[#1A1B33] rounded-full overflow-hidden border border-[#34376A]/40">
          <div class="h-full bg-gradient-to-r from-[#8B7CFF] via-[#5EEAD4] to-[#FF8A7A] rounded-full w-1/2 animate-[pulse_1.5s_ease-in-out_infinite]"></div>
        </div>
      </div>
    </div>
  </div>
</template>
