<script setup lang="ts">
import { ref } from 'vue';
import BrandLogo from '../Components/BrandLogo.vue';

type VibeType = 'Chill' | 'Vent' | 'Play' | 'Spark' | 'Debate';
type ModeType = 'text' | 'video' | 'mask';

const vibes = [
  { name: 'Chill' as VibeType, color: 'text-mint border-mint/40 bg-mint/10', icon: '🌿' },
  { name: 'Vent' as VibeType, color: 'text-[#7FB3FF] border-[#7FB3FF]/40 bg-[#7FB3FF]/10', icon: '☁️' },
  { name: 'Play' as VibeType, color: 'text-gold border-gold/40 bg-gold/10', icon: '🎮' },
  { name: 'Spark' as VibeType, color: 'text-coral border-coral/40 bg-coral/10', icon: '✨' },
  { name: 'Debate' as VibeType, color: 'text-primary border-primary/40 bg-primary/10', icon: '⚡' },
];

const selectedVibe = ref<VibeType>('Chill');
const selectedMode = ref<ModeType>('text');
const isMatching = ref(false);

function startMatching() {
  isMatching.value = true;
}
</script>

<template>
  <div class="min-h-screen bg-bg text-text-base flex flex-col justify-between p-4 md:p-8 select-none">
    <!-- Top Header -->
    <header class="w-full max-w-xl mx-auto flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span class="w-2.5 h-2.5 rounded-full bg-mint animate-pulse"></span>
        <span class="text-xs text-text-muted font-medium">1,284 online</span>
      </div>
      <div class="flex items-center gap-2 text-xs text-text-muted">
        <span class="bg-surface px-3 py-1 rounded-pill border border-border">Soft Night Plaza</span>
      </div>
    </header>

    <!-- Main Hero / Meet Stage -->
    <main class="w-full max-w-xl mx-auto flex flex-col items-center justify-center my-auto py-6">
      <!-- Animated Logo ("Kissing Bubbles") -->
      <BrandLogo size="lg" :animated="true" />

      <p class="text-xs md:text-sm text-text-muted text-center mt-2 mb-8">
        Say hi to someone new. Meet. Breathe. Connect.
      </p>

      <!-- Vibe Selector -->
      <div class="w-full flex flex-col items-center gap-3 mb-8">
        <span class="text-xs uppercase tracking-wider text-text-muted font-semibold">Choose your Vibe</span>
        <div class="flex flex-wrap justify-center gap-2.5">
          <button
            v-for="vibe in vibes"
            :key="vibe.name"
            @click="selectedVibe = vibe.name"
            :class="[
              'px-4 py-2 rounded-pill text-xs md:text-sm font-medium border transition-all duration-200',
              selectedVibe === vibe.name
                ? vibe.color + ' ring-2 ring-primary/40 shadow-glow'
                : 'bg-surface text-text-muted border-border hover:border-primary/50'
            ]"
          >
            <span class="mr-1.5">{{ vibe.icon }}</span>
            <span>{{ vibe.name }}</span>
          </button>
        </div>
      </div>

      <!-- Mode Toggle (Text | Video | Mask Video) -->
      <div class="flex items-center bg-surface p-1 rounded-pill border border-border mb-8 text-xs font-medium">
        <button
          @click="selectedMode = 'text'"
          :class="[
            'px-4 py-1.5 rounded-pill transition',
            selectedMode === 'text' ? 'bg-primary text-white shadow-glow' : 'text-text-muted hover:text-text-base'
          ]"
        >
          💬 Text
        </button>
        <button
          @click="selectedMode = 'video'"
          :class="[
            'px-4 py-1.5 rounded-pill transition',
            selectedMode === 'video' ? 'bg-primary text-white shadow-glow' : 'text-text-muted hover:text-text-base'
          ]"
        >
          📹 Video
        </button>
        <button
          @click="selectedMode = 'mask'"
          :class="[
            'px-4 py-1.5 rounded-pill transition',
            selectedMode === 'mask' ? 'bg-primary text-white shadow-glow' : 'text-text-muted hover:text-text-base'
          ]"
        >
          🎭 Mask Mode
        </button>
      </div>

      <!-- Central Start Button (with soft pulsing glow as per 06-ui-ux-structure.md) -->
      <button
        @click="startMatching"
        class="w-48 py-4 rounded-pill bg-primary hover:bg-opacity-90 active:scale-95 text-white font-semibold text-base shadow-glow transition duration-200 flex items-center justify-center gap-2"
      >
        <span>Start</span>
        <span class="text-sm opacity-80">→</span>
      </button>

      <!-- Matching Overlay modal placeholder if clicked -->
      <div
        v-if="isMatching"
        class="fixed inset-0 bg-bg/80 backdrop-blur-md flex flex-col items-center justify-center p-6 z-50 animate-fade-in"
      >
        <div class="bg-surface border border-border rounded-card p-8 max-w-sm w-full flex flex-col items-center shadow-glow text-center">
          <BrandLogo size="md" :animated="true" :speed="1.4" />
          <h3 class="text-lg font-display font-bold mt-4">Looking for someone...</h3>
          <p class="text-xs text-text-muted mt-1">Matching vibe: <span class="text-mint font-semibold">{{ selectedVibe }}</span></p>
          <div class="w-full bg-surface-2 h-1.5 rounded-full overflow-hidden mt-6">
            <div class="bg-primary h-full w-2/3 animate-pulse rounded-full"></div>
          </div>
          <button
            @click="isMatching = false"
            class="mt-6 px-5 py-2 rounded-pill border border-border text-xs text-text-muted hover:text-text-base transition"
          >
            Cancel
          </button>
        </div>
      </div>
    </main>

    <!-- Bottom Navigation / Footer -->
    <footer class="w-full max-w-md mx-auto flex items-center justify-around py-3 bg-surface border border-border rounded-pill text-xs text-text-muted">
      <button class="text-primary font-medium flex flex-col items-center">
        <span>💫</span>
        <span>Meet</span>
      </button>
      <button class="hover:text-text-base flex flex-col items-center">
        <span>🏛️</span>
        <span>Plaza</span>
      </button>
      <button class="hover:text-text-base flex flex-col items-center">
        <span>⭕</span>
        <span>Circle</span>
      </button>
      <button class="hover:text-text-base flex flex-col items-center">
        <span>👤</span>
        <span>Me</span>
      </button>
    </footer>
  </div>
</template>
