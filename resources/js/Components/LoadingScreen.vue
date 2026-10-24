<script setup lang="ts">
import BrandLogo from './BrandLogo.vue';

interface Props {
  statusText?: string;
  secondsWaiting?: number;
  showCompanionOption?: boolean;
}

withDefaults(defineProps<Props>(), {
  statusText: 'Connecting to the Plaza...',
  secondsWaiting: 0,
  showCompanionOption: false,
});

defineEmits<{
  (e: 'cancel'): void;
  (e: 'matchCompanion'): void;
}>();
</script>

<template>
  <div class="fixed inset-0 z-50 bg-[#0F1020] flex flex-col items-center justify-center select-none overflow-hidden px-4">
    <!-- Ambient glowing backdrop -->
    <div class="absolute w-72 h-72 rounded-full bg-[#8B7CFF]/15 blur-3xl pointer-events-none -translate-y-8 animate-pulse"></div>
    <div class="absolute w-60 h-60 rounded-full bg-[#FF8A7A]/10 blur-3xl pointer-events-none translate-y-8"></div>

    <!-- Animated Kissing Bubbles Logo Mark -->
    <div class="relative z-10 flex flex-col items-center max-w-sm w-full text-center">
      <BrandLogo size="lg" :animated="true" :show-wordmark="true" />

      <!-- Minimal calm status indicator -->
      <div class="mt-6 flex flex-col items-center gap-2.5 w-full">
        <p class="text-xs text-[#A9A6CC] font-medium tracking-wide">
          {{ statusText }}
        </p>

        <div class="w-36 h-1 bg-[#1A1B33] rounded-full overflow-hidden border border-[#34376A]/40">
          <div class="h-full bg-gradient-to-r from-[#8B7CFF] via-[#5EEAD4] to-[#FF8A7A] rounded-full w-1/2 animate-[pulse_1.5s_ease-in-out_infinite]"></div>
        </div>

        <p v-if="secondsWaiting > 0" class="text-[11px] text-[#A9A6CC]/60 font-mono">
          Waiting {{ secondsWaiting }}s
        </p>
      </div>

      <!-- Action buttons -->
      <div class="mt-6 flex flex-col items-center gap-2.5 w-full">
        <button
          v-if="showCompanionOption"
          @click="$emit('matchCompanion')"
          class="w-full max-w-xs py-2 px-4 rounded-full bg-[#1A1B33] hover:bg-[#24264A] border border-[#8B7CFF]/50 text-white text-xs font-semibold shadow-md transition transform active:scale-95 flex items-center justify-center gap-1.5"
        >
          <span>✨</span>
          <span>Match with Test Companion</span>
        </button>

        <button
          @click="$emit('cancel')"
          class="text-xs text-[#A9A6CC]/70 hover:text-white transition py-1.5 px-4 rounded-full hover:bg-white/5 active:scale-95"
        >
          Cancel & Return Home
        </button>
      </div>
    </div>
  </div>
</template>
