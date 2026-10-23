<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import BrandLogo from '../Components/BrandLogo.vue';
import LoadingScreen from '../Components/LoadingScreen.vue';

interface Props {
  onlineCount?: number;
  defaultVibe?: string;
}

const props = withDefaults(defineProps<Props>(), {
  onlineCount: 1284,
  defaultVibe: 'Chill',
});

type VibeType = 'Chill' | 'Vent' | 'Play' | 'Spark' | 'Debate';
type ModeType = 'text' | 'video' | 'mask';

const vibes = [
  { name: 'Chill' as VibeType, color: 'text-mint border-mint/40 bg-mint/10', icon: '🌿', desc: 'Calm casual company' },
  { name: 'Vent' as VibeType, color: 'text-[#7FB3FF] border-[#7FB3FF]/40 bg-[#7FB3FF]/10', icon: '☁️', desc: 'Safe space to decompress' },
  { name: 'Play' as VibeType, color: 'text-gold border-gold/40 bg-gold/10', icon: '🎮', desc: 'Games, trivia, and banter' },
  { name: 'Spark' as VibeType, color: 'text-coral border-coral/40 bg-coral/10', icon: '✨', desc: 'Curious & romantic vibes' },
  { name: 'Debate' as VibeType, color: 'text-primary border-primary/40 bg-primary/10', icon: '⚡', desc: 'Playful friendly discussions' },
];

const selectedVibe = ref<VibeType>((props.defaultVibe as VibeType) || 'Chill');
const selectedMode = ref<ModeType>('text');
const isFindingMatch = ref(false);

async function handleStart() {
  isFindingMatch.value = true;
  try {
    const res = await fetch('/match', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
      },
      body: JSON.stringify({
        vibe: selectedVibe.value,
        mode: selectedMode.value,
      }),
    });

    const data = await res.json();
    if (data.room_id) {
      setTimeout(() => {
        router.visit(`/room/${data.room_id}`);
      }, 1200); // Graceful 1.2s loading with kissing logo
    }
  } catch (e) {
    console.error(e);
    isFindingMatch.value = false;
  }
}
</script>

<template>
  <!-- Full Screen Loading Overlay using the Kissing Bubbles Animated Logo Mark -->
  <LoadingScreen
    v-if="isFindingMatch"
    :status-text="`Finding someone in ${selectedVibe} vibe...`"
  />

  <div class="min-h-screen bg-bg text-text-base flex flex-col justify-between p-4 md:p-8">
    <!-- Header -->
    <header class="w-full max-w-xl mx-auto flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span class="w-2.5 h-2.5 rounded-full bg-mint animate-pulse"></span>
        <span class="text-xs text-text-muted font-medium">{{ onlineCount.toLocaleString() }} online</span>
      </div>
      <div class="px-3 py-1 rounded-pill bg-surface border border-border text-xs text-text-muted">
        Soft Night Plaza
      </div>
    </header>

    <!-- Main Meet Screen -->
    <main class="w-full max-w-xl mx-auto flex flex-col items-center justify-center my-auto py-8">
      <!-- Clean Brand Logo Mark Only -->
      <BrandLogo size="lg" :animated="true" :show-wordmark="true" />

      <p class="text-xs md:text-sm text-text-muted text-center mt-3 mb-8 max-w-xs">
        Say hi to someone new. Meet. Breathe. Connect.
      </p>

      <!-- Vibe Selector -->
      <div class="w-full flex flex-col items-center gap-3 mb-8">
        <span class="text-xs uppercase tracking-wider text-text-muted font-semibold">Select your Vibe</span>
        <div class="flex flex-wrap justify-center gap-2">
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

      <!-- Mode Selector (Text | Video | Mask Video per 06-ui-ux-structure.md) -->
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

      <!-- Start Button with Soft Pulsing Glow -->
      <button
        @click="handleStart"
        class="w-52 py-4 rounded-pill bg-primary hover:opacity-90 active:scale-95 text-white font-semibold text-base shadow-glow transition duration-200 flex items-center justify-center gap-2"
      >
        <span>Start Meeting</span>
        <span class="text-sm">→</span>
      </button>
    </main>

    <!-- Footer Note -->
    <footer class="w-full max-w-xl mx-auto text-center py-4 text-xs text-text-muted">
      Anonymous & safe. Instant skip anytime. 18+ only.
    </footer>
  </div>
</template>
