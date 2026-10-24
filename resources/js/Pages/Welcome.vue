<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
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

interface VibeOption {
  name: VibeType;
  tagline: string;
  icon: string;
  accent: string;
  borderClass: string;
  bgClass: string;
  glowClass: string;
}

const vibes: VibeOption[] = [
  {
    name: 'Chill',
    tagline: 'Soft, relaxing company',
    icon: '🌿',
    accent: '#5EEAD4',
    borderClass: 'border-mint',
    bgClass: 'bg-mint/15',
    glowClass: 'shadow-[0_0_24px_rgba(94,234,212,0.35)]',
  },
  {
    name: 'Vent',
    tagline: 'Safe space to decompress',
    icon: '☁️',
    accent: '#7FB3FF',
    borderClass: 'border-[#7FB3FF]',
    bgClass: 'bg-[#7FB3FF]/15',
    glowClass: 'shadow-[0_0_24px_rgba(127,179,255,0.35)]',
  },
  {
    name: 'Play',
    tagline: 'Trivia duels & banter',
    icon: '🎮',
    accent: '#FFD36E',
    borderClass: 'border-gold',
    bgClass: 'bg-gold/15',
    glowClass: 'shadow-[0_0_24px_rgba(255,211,110,0.35)]',
  },
  {
    name: 'Spark',
    tagline: 'Warm curious connections',
    icon: '✨',
    accent: '#FF8A7A',
    borderClass: 'border-coral',
    bgClass: 'bg-coral/15',
    glowClass: 'shadow-[0_0_24px_rgba(255,138,122,0.35)]',
  },
  {
    name: 'Debate',
    tagline: 'Playful friendly hot takes',
    icon: '⚡',
    accent: '#8B7CFF',
    borderClass: 'border-primary',
    bgClass: 'bg-primary/15',
    glowClass: 'shadow-[0_0_24px_rgba(139,124,255,0.35)]',
  },
];

const selectedVibe = ref<VibeType>((props.defaultVibe as VibeType) || 'Chill');
const selectedMode = ref<ModeType>('text');
const isFindingMatch = ref(false);
const secondsWaiting = ref(0);

const activeVibeData = computed(() => {
  return vibes.find((v) => v.name === selectedVibe.value) || vibes[0];
});

let pollInterval: any = null;
let timerInterval: any = null;

function getClientId(): string {
  if (typeof window === 'undefined') return 'guest_' + Math.random().toString(36).substring(2, 9);
  let id = sessionStorage.getItem('emiringkay_client_id');
  if (!id) {
    id = 'user_' + Math.random().toString(36).substring(2, 10);
    sessionStorage.setItem('emiringkay_client_id', id);
  }
  return id;
}

function handleCancel() {
  if (pollInterval) clearInterval(pollInterval);
  if (timerInterval) clearInterval(timerInterval);
  pollInterval = null;
  timerInterval = null;
  isFindingMatch.value = false;
  secondsWaiting.value = 0;
}

async function handleMatchCompanion() {
  const userId = getClientId();
  try {
    const res = await fetch('/match', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        vibe: selectedVibe.value,
        mode: selectedMode.value,
        user_id: userId,
        companion: true,
      }),
    });

    const data = await res.json();
    if (data.status === 'matched' && data.room_id) {
      handleCancel();
      router.visit(`/room/${data.room_id}`);
    } else {
      handleCancel();
    }
  } catch (e) {
    console.error(e);
    handleCancel();
  }
}

async function handleStart() {
  handleCancel();
  isFindingMatch.value = true;
  secondsWaiting.value = 0;
  const userId = getClientId();

  timerInterval = setInterval(() => {
    secondsWaiting.value++;
  }, 1000);

  try {
    const res = await fetch('/match', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        vibe: selectedVibe.value,
        mode: selectedMode.value,
        user_id: userId,
      }),
    });

    if (!res.ok) {
      console.error('Match error status:', res.status);
      handleCancel();
      alert('Could not connect to matchmaking queue. Please try again.');
      return;
    }

    const data = await res.json();
    if (data.status === 'matched' && data.room_id) {
      setTimeout(() => {
        handleCancel();
        router.visit(`/room/${data.room_id}`);
      }, 1200); // Kissing bubbles matching reveal
    } else if (data.status === 'waiting' && data.ticket_id) {
      // User is in queue waiting for real peer
      const ticketId = data.ticket_id;
      pollInterval = setInterval(async () => {
        try {
          const pollRes = await fetch(`/match/status?ticket_id=${ticketId}`, {
            headers: { 'Accept': 'application/json' },
          });
          if (pollRes.ok) {
            const pollData = await pollRes.json();
            if (pollData.status === 'matched' && pollData.room_id) {
              handleCancel();
              router.visit(`/room/${pollData.room_id}`);
            }
          }
        } catch (err) {
          console.error('Polling error', err);
        }
      }, 1000);
    } else {
      handleCancel();
    }
  } catch (e) {
    console.error(e);
    handleCancel();
    alert('Network error connecting to matching queue.');
  }
}
</script>

<template>
  <div class="min-h-screen bg-[#0F1020] text-[#F4F2FF] flex flex-col justify-between relative overflow-hidden select-none font-sans">
    
    <!-- Ambient Dreamy Night Sky Background with Floating Lantern Orbs -->
    <div class="absolute inset-0 pointer-events-none overflow-hidden z-0">
      <!-- Glow behind logo reacting to selected vibe -->
      <div
        class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[520px] h-[520px] rounded-full blur-[130px] opacity-25 transition-all duration-700 pointer-events-none"
        :style="{ backgroundColor: activeVibeData.accent }"
      ></div>

      <!-- Secondary soft coral lantern -->
      <div class="absolute -top-20 -right-20 w-80 h-80 rounded-full bg-[#FF8A7A]/10 blur-[100px] pointer-events-none"></div>

      <!-- Subtle indigo ground bloom -->
      <div class="absolute -bottom-24 left-1/4 w-96 h-96 rounded-full bg-[#8B7CFF]/15 blur-[120px] pointer-events-none"></div>

      <!-- Twinkling floating particles -->
      <div class="absolute top-16 left-12 w-1.5 h-1.5 rounded-full bg-[#5EEAD4] opacity-60 animate-ping"></div>
      <div class="absolute top-36 right-24 w-2 h-2 rounded-full bg-[#FFD36E] opacity-70 animate-pulse"></div>
      <div class="absolute bottom-40 left-20 w-1.5 h-1.5 rounded-full bg-[#FF8A7A] opacity-50 animate-pulse"></div>
      <div class="absolute bottom-28 right-16 w-1 h-1 rounded-full bg-white opacity-80 animate-ping"></div>
    </div>

    <!-- Full Screen Loading Overlay with Kissing Bubbles Logo Mark -->
    <LoadingScreen
      v-if="isFindingMatch"
      :status-text="secondsWaiting > 6 ? `Still looking for someone in ${selectedVibe} vibe...` : `Finding someone in ${selectedVibe} vibe...`"
      :seconds-waiting="secondsWaiting"
      :show-companion-option="secondsWaiting >= 5"
      @cancel="handleCancel"
      @match-companion="handleMatchCompanion"
    />

    <!-- Top Navigation Bar -->
    <header class="w-full max-w-4xl mx-auto px-6 py-5 flex items-center justify-between relative z-10">
      <div class="flex items-center gap-2.5 bg-[#1A1B33]/80 backdrop-blur-xl px-3.5 py-1.5 rounded-full border border-[#34376A]/60 shadow-lg">
        <span class="relative flex h-2.5 w-2.5">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#5EEAD4] opacity-75"></span>
          <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-[#5EEAD4]"></span>
        </span>
        <span class="text-xs text-[#A9A6CC] font-medium tracking-wide">
          <strong class="text-white font-semibold">{{ onlineCount.toLocaleString() }}</strong> wandering the Plaza
        </span>
      </div>

      <div class="flex items-center gap-2">
        <Link
          href="/circle"
          class="bg-[#1A1B33]/80 hover:bg-[#24264A] backdrop-blur-xl px-3.5 py-1.5 rounded-full border border-[#34376A]/60 text-xs text-[#A9A6CC] hover:text-white flex items-center gap-1.5 transition shadow-sm"
        >
          <span>⭕</span>
          <span class="font-semibold">Circle</span>
        </Link>
        <Link
          href="/plaza"
          class="bg-[#1A1B33]/80 hover:bg-[#24264A] backdrop-blur-xl px-3.5 py-1.5 rounded-full border border-[#34376A]/60 text-xs text-[#A9A6CC] hover:text-white flex items-center gap-1.5 transition shadow-sm"
        >
          <span>🏮</span>
          <span class="font-semibold">Plaza</span>
        </Link>
      </div>
    </header>

    <!-- Main Hero Presentation -->
    <main class="w-full max-w-2xl mx-auto px-4 py-4 flex flex-col items-center justify-center my-auto relative z-10">
      
      <!-- Brand Logo Stage with Subtle Glow Halo -->
      <div class="relative flex flex-col items-center mb-6 group cursor-default">
        <div class="absolute -inset-4 bg-gradient-to-r from-[#8B7CFF]/20 via-[#5EEAD4]/15 to-[#FF8A7A]/20 rounded-full blur-xl opacity-60 group-hover:opacity-100 transition duration-700"></div>
        <BrandLogo size="lg" :animated="true" :show-wordmark="true" />
      </div>

      <!-- Tagline -->
      <div class="text-center mb-8">
        <h2 class="text-base sm:text-lg font-semibold tracking-tight text-white/95">
          Say hi to someone new.
        </h2>
        <p class="text-xs sm:text-sm text-[#A9A6CC] mt-1 font-normal">
          No endless swiping. No performative profiles. Just real human decompressing.
        </p>
      </div>

      <!-- Interactive Vibe Selector Grid -->
      <div class="w-full max-w-xl flex flex-col items-center mb-7">
        <div class="flex items-center justify-between w-full px-2 mb-3">
          <span class="text-xs uppercase tracking-wider text-[#A9A6CC] font-bold flex items-center gap-1.5">
            <span>✨</span> What vibe are you feeling?
          </span>
          <span class="text-xs font-medium" :style="{ color: activeVibeData.accent }">
            {{ activeVibeData.tagline }}
          </span>
        </div>

        <!-- 5 Interactive Vibe Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5 w-full">
          <button
            v-for="vibe in vibes"
            :key="vibe.name"
            @click="selectedVibe = vibe.name"
            :class="[
              'relative group flex flex-col items-center p-3 rounded-2xl border transition-all duration-300 transform active:scale-95 text-center',
              selectedVibe === vibe.name
                ? `${vibe.bgClass} ${vibe.borderClass} ${vibe.glowClass} -translate-y-1 scale-105`
                : 'bg-[#1A1B33]/70 hover:bg-[#24264A]/80 border-[#34376A]/50 hover:border-[#8B7CFF]/50 text-[#A9A6CC] hover:text-white'
            ]"
          >
            <!-- Animated Icon -->
            <span class="text-2xl mb-1 transition-transform duration-300 group-hover:scale-125">
              {{ vibe.icon }}
            </span>

            <span
              :class="[
                'text-xs font-bold tracking-wide transition-colors',
                selectedVibe === vibe.name ? 'text-white' : 'text-[#A9A6CC] group-hover:text-white'
              ]"
            >
              {{ vibe.name }}
            </span>

            <!-- Active Indicator Dot -->
            <span
              v-if="selectedVibe === vibe.name"
              class="w-1.5 h-1.5 rounded-full mt-1.5 animate-pulse"
              :style="{ backgroundColor: vibe.accent }"
            ></span>
          </button>
        </div>
      </div>

      <!-- Mode Selector (Text | Video | Mask Video) -->
      <div class="w-full max-w-md flex flex-col items-center mb-8">
        <div class="w-full bg-[#1A1B33]/90 backdrop-blur-xl p-1.5 rounded-full border border-[#34376A]/70 shadow-inner flex items-center justify-between">
          <button
            @click="selectedMode = 'text'"
            :class="[
              'flex-1 py-2 rounded-full text-xs font-semibold transition-all duration-200 flex items-center justify-center gap-1.5',
              selectedMode === 'text'
                ? 'bg-gradient-to-r from-[#8B7CFF] to-[#6C5CE7] text-white shadow-[0_4px_16px_rgba(139,124,255,0.4)]'
                : 'text-[#A9A6CC] hover:text-white hover:bg-white/5'
            ]"
          >
            <span>💬</span>
            <span>Text Chat</span>
          </button>

          <button
            @click="selectedMode = 'video'"
            :class="[
              'flex-1 py-2 rounded-full text-xs font-semibold transition-all duration-200 flex items-center justify-center gap-1.5',
              selectedMode === 'video'
                ? 'bg-gradient-to-r from-[#8B7CFF] to-[#6C5CE7] text-white shadow-[0_4px_16px_rgba(139,124,255,0.4)]'
                : 'text-[#A9A6CC] hover:text-white hover:bg-white/5'
            ]"
          >
            <span>📹</span>
            <span>Video Call</span>
          </button>

          <button
            @click="selectedMode = 'mask'"
            :class="[
              'flex-1 py-2 rounded-full text-xs font-semibold transition-all duration-200 flex items-center justify-center gap-1.5',
              selectedMode === 'mask'
                ? 'bg-gradient-to-r from-[#FF8A7A] to-[#F2685A] text-white shadow-[0_4px_16px_rgba(255,138,122,0.4)]'
                : 'text-[#A9A6CC] hover:text-white hover:bg-white/5'
            ]"
          >
            <span>🎭</span>
            <span>Mask Mode</span>
          </button>
        </div>

        <p class="text-[11px] text-[#A9A6CC]/70 mt-2 text-center">
          <span v-if="selectedMode === 'text'">Casual anonymous messaging. Quiet & low stress.</span>
          <span v-else-if="selectedMode === 'video'">Camera starts safely blurred until both sides tap reveal.</span>
          <span v-else>Face-obscuring avatar mask so you stay completely anonymous.</span>
        </p>
      </div>

      <!-- Start Button with Glowing Lantern Aura -->
      <button
        @click="handleStart"
        class="relative group px-10 py-4 rounded-full bg-gradient-to-r from-[#8B7CFF] via-[#7B68EE] to-[#FF8A7A] text-white font-bold text-sm sm:text-base shadow-[0_8px_32px_rgba(139,124,255,0.45)] hover:shadow-[0_12px_42px_rgba(139,124,255,0.65)] hover:scale-105 active:scale-95 transition-all duration-300 flex items-center gap-3 overflow-hidden cursor-pointer"
      >
        <!-- Shimmer light sweep on button -->
        <span class="absolute inset-0 w-1/2 h-full bg-white/20 skew-x-12 -translate-x-full group-hover:translate-x-[300%] transition-transform duration-1000 ease-in-out"></span>

        <span>Enter the Plaza</span>
        <span class="text-lg transition-transform duration-300 group-hover:translate-x-1">➔</span>
      </button>

      <span class="text-[11px] text-[#A9A6CC]/60 mt-3 flex items-center gap-1.5">
        <span>⚡</span> Matches average ~8 seconds
      </span>
    </main>

    <!-- Bottom Reassurance Banner -->
    <footer class="w-full max-w-xl mx-auto px-4 py-5 flex items-center justify-center gap-6 text-[11px] text-[#A9A6CC]/60 relative z-10 border-t border-[#34376A]/30">
      <div class="flex items-center gap-1.5">
        <span>🛡️</span>
        <span>Accountable & 18+</span>
      </div>
      <div class="flex items-center gap-1.5">
        <span>⚡</span>
        <span>Instant Skip Anytime</span>
      </div>
      <div class="flex items-center gap-1.5">
        <span>🔒</span>
        <span>Zero Call Recording</span>
      </div>
    </footer>

  </div>
</template>
