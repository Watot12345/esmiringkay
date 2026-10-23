<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import BrandLogo from '../../Components/BrandLogo.vue';
import { useWebRTC } from '../../Composables/useWebRTC';
import DoodleBoard from '../../Components/Activities/DoodleBoard.vue';
import TriviaDuel from '../../Components/Activities/TriviaDuel.vue';

interface Props {
  room: {
    id: string;
    vibe: string;
    type: string;
    started_at: string;
  };
  currentUserId?: string;
  iceServers?: any[];
}

const props = withDefaults(defineProps<Props>(), {
  currentUserId: 'guest_user',
  iceServers: () => [],
});

// Timer
const seconds = ref(0);
let timerInterval: any = null;

// WebRTC Composable
const {
  localStream,
  remoteStream,
  isCamActive,
  isMicActive,
  isMaskMode,
  isConnected,
  isConnecting,
  startMedia,
  initPeerConnection,
  handleSignal,
  toggleCam,
  toggleMic,
  toggleMask,
  closePeerConnection,
} = useWebRTC();

const localVideoRef = ref<HTMLVideoElement | null>(null);
const remoteVideoRef = ref<HTMLVideoElement | null>(null);

// Consent blur for video/camera (starts blurred per 08-workflow.md Section 2)
const strangerUnblurred = ref(props.room.type === 'text');

onMounted(async () => {
  timerInterval = setInterval(() => {
    seconds.value++;
  }, 1000);

  // If room is video or mask mode, request media
  if (props.room.type !== 'text') {
    const isMask = props.room.type === 'mask';
    const stream = await startMedia(true, true, isMask);
    if (stream && localVideoRef.value) {
      localVideoRef.value.srcObject = stream;
    }

    // Initialize peer connection
    initPeerConnection(props.room.id, props.iceServers, true, async (signalType, payload) => {
      // Send signal via POST /room/{id}/signal
      try {
        await fetch(`/room/${props.room.id}/signal`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
          },
          body: JSON.stringify({ signalType, payload }),
        });
      } catch (e) {
        console.warn('Signal send error:', e);
      }
    });
  }
});

watch(remoteStream, (newStream) => {
  if (newStream && remoteVideoRef.value) {
    remoteVideoRef.value.srcObject = newStream;
  }
});

onUnmounted(() => {
  if (timerInterval) clearInterval(timerInterval);
  closePeerConnection();
});

function formatTimer(s: number) {
  const mins = Math.floor(s / 60);
  const secs = s % 60;
  return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
}

// Chat state
const messages = ref<{ sender: 'you' | 'stranger' | 'system'; text: string; time: string }[]>([
  {
    sender: 'system',
    text: `Connected with a stranger in ${props.room.vibe} vibe. Say hello!`,
    time: 'Now',
  },
  {
    sender: 'stranger',
    text: 'Hey there! How has your night been so far?',
    time: 'Now',
  },
]);
const inputMessage = ref('');

async function sendMessage() {
  if (!inputMessage.value.trim()) return;
  const text = inputMessage.value.trim();
  messages.value.push({
    sender: 'you',
    text,
    time: 'Now',
  });
  inputMessage.value = '';

  try {
    await fetch(`/room/${props.room.id}/message`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
      },
      body: JSON.stringify({ body: text }),
    });
  } catch (e) {
    console.error(e);
  }

  // Friendly simulated reply after short delay
  setTimeout(() => {
    if (!showPostCall.value) {
      messages.value.push({
        sender: 'stranger',
        text: 'That is really nice to hear. Such a relief to take a breather here.',
        time: 'Now',
      });
    }
  }, 1800);
}

// Activities drawer
const showActivities = ref(false);
const activeActivity = ref<'none' | 'doodle' | 'trivia' | 'breathing' | 'icebreaker'>('none');
const breathPhase = ref<'Inhale' | 'Hold' | 'Exhale'>('Inhale');
let breathInterval: any = null;

function toggleBreathing() {
  if (activeActivity.value === 'breathing') {
    activeActivity.value = 'none';
    if (breathInterval) clearInterval(breathInterval);
  } else {
    activeActivity.value = 'breathing';
    showActivities.value = false;
    let step = 0;
    breathInterval = setInterval(() => {
      step = (step + 1) % 3;
      if (step === 0) breathPhase.value = 'Inhale';
      else if (step === 1) breathPhase.value = 'Hold';
      else breathPhase.value = 'Exhale';
    }, 4000);
  }
}

// Icebreaker
const icebreakerQuestion = ref('What is something small that made you smile today?');
const icebreakers = [
  'What is something small that made you smile today?',
  'If you could teleport anywhere right now for 10 minutes, where?',
  'What song always calms you down when stressed?',
  'What is your go-to late night comfort food?',
];
function nextIcebreaker() {
  activeActivity.value = 'icebreaker';
  showActivities.value = false;
  const next = icebreakers[Math.floor(Math.random() * icebreakers.length)];
  icebreakerQuestion.value = next;
}

// Floating reactions
const reactions = ref<{ id: number; emoji: string; x: number }[]>([]);
let reactionId = 0;
function sendReaction(emoji: string) {
  const id = ++reactionId;
  reactions.value.push({ id, emoji, x: 20 + Math.random() * 60 });
  setTimeout(() => {
    reactions.value = reactions.value.filter((r) => r.id !== id);
  }, 2200);
}

// End / Skip flow & Post-Call Screen
const showPostCall = ref(false);
const selectedMood = ref<number | null>(null);
const selectedKeep = ref<'friend' | 'rival' | 'spark' | 'confidant' | null>(null);
const keepSent = ref(false);

async function handleSkip() {
  try {
    await fetch(`/room/${props.room.id}/skip`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
      },
    });
  } catch (e) {
    console.error(e);
  }
  showPostCall.value = true;
}

function handlePanicSkip() {
  closePeerConnection();
  router.visit('/');
}

async function sendKeep(type: 'friend' | 'rival' | 'spark' | 'confidant') {
  selectedKeep.value = type;
  keepSent.value = true;
  try {
    await fetch(`/room/${props.room.id}/keep`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
      },
      body: JSON.stringify({ type }),
    });
  } catch (e) {
    console.error(e);
  }
}

async function handleReport() {
  try {
    await fetch(`/room/${props.room.id}/report`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
      },
      body: JSON.stringify({ category: 'Inappropriate Content' }),
    });
  } catch (e) {
    console.error(e);
  }
  closePeerConnection();
  router.visit('/');
}

function handleNextMatch() {
  closePeerConnection();
  router.visit('/');
}
</script>

<template>
  <div class="h-screen w-screen bg-bg text-text-base flex flex-col justify-between overflow-hidden relative">
    
    <!-- Top Action Bar -->
    <header class="h-14 px-4 border-b border-border bg-surface/80 backdrop-blur-md flex items-center justify-between z-20 shrink-0">
      <div class="flex items-center gap-3">
        <!-- Clean Brand Logo Mark -->
        <BrandLogo size="xs" :animated="false" />
        <span class="px-2.5 py-0.5 rounded-pill text-xs font-semibold bg-surface-2 border border-border text-primary">
          Vibe: {{ room.vibe }}
        </span>
        <span class="px-2 py-0.5 rounded-pill text-[11px] font-medium bg-surface-2 border border-border text-text-muted">
          {{ room.type.toUpperCase() }}
        </span>
        <span class="font-mono text-xs text-text-muted">{{ formatTimer(seconds) }}</span>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="handlePanicSkip"
          title="Panic Skip (Immediate Exit)"
          class="px-2.5 py-1 text-xs font-medium rounded-pill text-danger bg-danger/10 hover:bg-danger/20 transition"
        >
          🚨 Panic
        </button>
        <button
          @click="handleSkip"
          class="px-4 py-1.5 text-xs font-semibold rounded-pill bg-primary hover:opacity-90 active:scale-95 text-white transition brand-glow"
        >
          Skip ➔
        </button>
      </div>
    </header>

    <!-- Main Live Body -->
    <main class="flex-1 flex flex-col md:flex-row overflow-hidden relative">
      
      <!-- Video / WebRTC Presence Stage -->
      <section class="flex-1 bg-surface-2 relative flex items-center justify-center overflow-hidden min-h-[220px]">
        <!-- Floating Reactions -->
        <div class="absolute inset-0 pointer-events-none z-30 overflow-hidden">
          <div
            v-for="r in reactions"
            :key="r.id"
            :style="{ left: `${r.x}%` }"
            class="absolute bottom-4 text-3xl animate-[bounce_2s_infinite] transition-all"
          >
            {{ r.emoji }}
          </div>
        </div>

        <!-- Video Canvas or Placeholder -->
        <div class="w-full h-full flex flex-col items-center justify-center relative p-4">
          <!-- Stranger Video Tile -->
          <div
            :class="[
              'w-full h-full max-w-lg max-h-[360px] rounded-card border border-border flex flex-col items-center justify-center relative bg-surface overflow-hidden transition-all',
              !strangerUnblurred && room.type !== 'text' ? 'backdrop-blur-2xl' : ''
            ]"
          >
            <!-- WebRTC Remote Video Feed -->
            <video
              v-show="remoteStream && strangerUnblurred"
              ref="remoteVideoRef"
              autoplay
              playsinline
              class="w-full h-full object-cover rounded-card"
            ></video>

            <!-- Fallback Avatar when video not active -->
            <div v-show="!remoteStream || !strangerUnblurred" class="flex flex-col items-center gap-3">
              <div class="w-20 h-20 rounded-full bg-gradient-to-tr from-primary to-coral flex items-center justify-center text-3xl shadow-glow">
                {{ isMaskMode ? '🎭' : '✨' }}
              </div>
              <span class="text-xs text-text-muted font-medium">Stranger (18+ Verified)</span>
            </div>

            <!-- Mutual Consent Blur Overlay per 08-workflow.md Section 2 -->
            <div
              v-if="!strangerUnblurred && room.type !== 'text'"
              class="absolute inset-0 bg-surface/75 backdrop-blur-xl flex flex-col items-center justify-center p-4 text-center z-10"
            >
              <span class="text-xl mb-1">🔒</span>
              <p class="text-xs text-text-muted mb-3">Both sides start blurred for privacy and comfort</p>
              <button
                @click="strangerUnblurred = true"
                class="px-4 py-2 rounded-pill bg-mint text-[#0F1020] text-xs font-semibold shadow-glow hover:opacity-90 active:scale-95 transition"
              >
                Tap to Reveal Video
              </button>
            </div>
          </div>

          <!-- Self PIP Tile (Top Right inside Video) -->
          <div
            v-if="room.type !== 'text'"
            class="absolute top-6 right-6 w-28 h-20 rounded-input bg-surface border border-border shadow-glow flex flex-col items-center justify-center text-xs text-text-muted z-10 overflow-hidden"
          >
            <video
              v-show="localStream && isCamActive"
              ref="localVideoRef"
              autoplay
              playsinline
              muted
              class="w-full h-full object-cover"
            ></video>
            <div v-show="!localStream || !isCamActive" class="flex flex-col items-center">
              <span>You</span>
              <span class="text-[10px] text-coral">Camera Off</span>
            </div>
          </div>

          <!-- Breathing Activity Overlay if Active -->
          <div
            v-if="activeActivity === 'breathing'"
            class="absolute inset-0 bg-bg/85 backdrop-blur-md z-20 flex flex-col items-center justify-center p-6 text-center"
          >
            <div
              :class="[
                'w-36 h-36 rounded-full border-4 border-mint flex items-center justify-center transition-all duration-1000 shadow-glow',
                breathPhase === 'Inhale' ? 'scale-125 bg-mint/20' : breathPhase === 'Hold' ? 'scale-125 bg-mint/30' : 'scale-90 bg-mint/5'
              ]"
            >
              <span class="text-lg font-bold font-display text-mint">{{ breathPhase }}</span>
            </div>
            <p class="text-xs text-text-muted mt-6">30-Second Breathing Sync with Stranger</p>
            <button
              @click="toggleBreathing"
              class="mt-4 px-4 py-1.5 rounded-pill border border-border text-xs text-text-muted hover:text-text-base"
            >
              Close Activity
            </button>
          </div>

          <!-- Doodle Board Overlay if Active -->
          <div
            v-if="activeActivity === 'doodle'"
            class="absolute inset-0 bg-[#0F1020]/90 backdrop-blur-md z-20 flex flex-col items-center justify-center p-4 sm:p-6"
          >
            <div class="w-full max-w-md relative">
              <button
                @click="activeActivity = 'none'"
                class="absolute -top-3 -right-3 w-7 h-7 rounded-full bg-[#24264A] text-white border border-[#34376A] flex items-center justify-center text-xs z-30 hover:bg-danger transition cursor-pointer"
              >
                ✕
              </button>
              <DoodleBoard :room-id="room.id" />
            </div>
          </div>

          <!-- Trivia Duel Overlay if Active -->
          <div
            v-if="activeActivity === 'trivia'"
            class="absolute inset-0 bg-[#0F1020]/90 backdrop-blur-md z-20 flex flex-col items-center justify-center p-4 sm:p-6"
          >
            <div class="w-full max-w-md relative">
              <button
                @click="activeActivity = 'none'"
                class="absolute -top-3 -right-3 w-7 h-7 rounded-full bg-[#24264A] text-white border border-[#34376A] flex items-center justify-center text-xs z-30 hover:bg-danger transition cursor-pointer"
              >
                ✕
              </button>
              <TriviaDuel :room-id="room.id" />
            </div>
          </div>

          <!-- Activities Drawer Modal -->
          <div
            v-if="showActivities"
            class="absolute inset-0 bg-[#0F1020]/80 backdrop-blur-md z-30 flex items-center justify-center p-4"
          >
            <div class="bg-[#1A1B33] border border-[#34376A] rounded-2xl max-w-sm w-full p-5 shadow-2xl relative">
              <div class="flex items-center justify-between pb-3 border-b border-[#34376A]/50 mb-4">
                <span class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-1.5">
                  <span>🎲</span> Shared Activities
                </span>
                <button @click="showActivities = false" class="text-xs text-[#A9A6CC] hover:text-white p-1 cursor-pointer">✕</button>
              </div>

              <div class="grid grid-cols-2 gap-2.5">
                <button
                  @click="activeActivity = 'doodle'; showActivities = false;"
                  class="p-3 rounded-xl bg-[#24264A] hover:bg-[#8B7CFF]/20 border border-[#34376A] hover:border-[#8B7CFF] text-left transition group cursor-pointer"
                >
                  <span class="text-xl mb-1 block group-hover:scale-110 transition-transform">🎨</span>
                  <div class="text-xs font-bold text-white">Doodle Board</div>
                  <div class="text-[10px] text-[#A9A6CC]">Draw together live</div>
                </button>

                <button
                  @click="activeActivity = 'trivia'; showActivities = false;"
                  class="p-3 rounded-xl bg-[#24264A] hover:bg-gold/20 border border-[#34376A] hover:border-gold text-left transition group cursor-pointer"
                >
                  <span class="text-xl mb-1 block group-hover:scale-110 transition-transform">🎮</span>
                  <div class="text-xs font-bold text-white">Trivia Duel</div>
                  <div class="text-[10px] text-[#A9A6CC]">Friendly quiz duel</div>
                </button>

                <button
                  @click="toggleBreathing(); showActivities = false;"
                  class="p-3 rounded-xl bg-[#24264A] hover:bg-mint/20 border border-[#34376A] hover:border-mint text-left transition group cursor-pointer"
                >
                  <span class="text-xl mb-1 block group-hover:scale-110 transition-transform">🫁</span>
                  <div class="text-xs font-bold text-white">Breathe Sync</div>
                  <div class="text-[10px] text-[#A9A6CC]">30-sec calm reset</div>
                </button>

                <button
                  @click="nextIcebreaker(); showActivities = false;"
                  class="p-3 rounded-xl bg-[#24264A] hover:bg-coral/20 border border-[#34376A] hover:border-coral text-left transition group cursor-pointer"
                >
                  <span class="text-xl mb-1 block group-hover:scale-110 transition-transform">💡</span>
                  <div class="text-xs font-bold text-white">Icebreaker</div>
                  <div class="text-[10px] text-[#A9A6CC]">Conversation starter</div>
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Chat & Interactive Drawer Section -->
      <section class="w-full md:w-80 lg:w-96 border-t md:border-t-0 md:border-l border-border bg-surface flex flex-col justify-between shrink-0 h-72 md:h-full">
        <!-- Messages stream -->
        <div class="flex-1 overflow-y-auto p-4 space-y-3">
          <div
            v-for="(m, i) in messages"
            :key="i"
            :class="[
              'flex flex-col text-xs',
              m.sender === 'you' ? 'items-end' : m.sender === 'system' ? 'items-center' : 'items-start'
            ]"
          >
            <div
              v-if="m.sender === 'system'"
              class="py-1 px-3 rounded-pill bg-surface-2 border border-border text-[11px] text-text-muted text-center"
            >
              {{ m.text }}
            </div>
            <div
              v-else
              :class="[
                'max-w-[80%] rounded-card px-3.5 py-2 leading-relaxed',
                m.sender === 'you' ? 'bg-primary text-white rounded-br-none' : 'bg-surface-2 text-text-base border border-border rounded-bl-none'
              ]"
            >
              {{ m.text }}
            </div>
          </div>
        </div>

        <!-- Floating Quick Reactions Bar -->
        <div class="px-4 py-1.5 border-t border-border/50 flex items-center justify-between bg-surface-2/40">
          <button @click="sendReaction('✨')" class="hover:scale-125 transition">✨</button>
          <button @click="sendReaction('🌿')" class="hover:scale-125 transition">🌿</button>
          <button @click="sendReaction('😂')" class="hover:scale-125 transition">😂</button>
          <button @click="sendReaction('❤️')" class="hover:scale-125 transition">❤️</button>
          <button @click="sendReaction('☕')" class="hover:scale-125 transition">☕</button>
        </div>

        <!-- Chat Input Bar -->
        <form @submit.prevent="sendMessage" class="p-3 border-t border-border flex items-center gap-2">
          <input
            v-model="inputMessage"
            type="text"
            placeholder="Type a calm message..."
            class="flex-1 bg-surface-2 border border-border rounded-input px-3 py-2 text-xs text-text-base placeholder-text-muted focus:outline-none focus:border-primary transition"
          />
          <button
            type="submit"
            class="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center shrink-0 hover:opacity-90 active:scale-95 transition"
          >
            ➔
          </button>
        </form>
      </section>
    </main>

    <!-- Bottom Controls Bar (Activities, Mic, Cam, Mask, Keep, Report per 06-ui-ux-structure.md) -->
    <footer class="h-16 px-4 bg-surface border-t border-border flex items-center justify-between z-20 shrink-0">
      <div class="flex items-center gap-2">
        <!-- Media Controls for Video/Mask mode -->
        <template v-if="room.type !== 'text'">
          <button
            @click="toggleMic"
            :class="[
              'px-3 py-1.5 rounded-pill border text-xs font-medium transition flex items-center gap-1.5',
              isMicActive ? 'bg-surface-2 border-border text-text-base' : 'bg-danger/10 border-danger text-danger'
            ]"
          >
            <span>{{ isMicActive ? '🎙️' : '🔇' }}</span>
            <span>{{ isMicActive ? 'Mic' : 'Muted' }}</span>
          </button>

          <button
            @click="toggleCam"
            :class="[
              'px-3 py-1.5 rounded-pill border text-xs font-medium transition flex items-center gap-1.5',
              isCamActive ? 'bg-surface-2 border-border text-text-base' : 'bg-danger/10 border-danger text-danger'
            ]"
          >
            <span>{{ isCamActive ? '📹' : '🚫' }}</span>
            <span>{{ isCamActive ? 'Cam' : 'Off' }}</span>
          </button>

          <button
            @click="toggleMask"
            :class="[
              'px-3 py-1.5 rounded-pill border text-xs font-medium transition flex items-center gap-1.5',
              isMaskMode ? 'bg-coral text-white border-coral font-semibold shadow-glow' : 'bg-surface-2 border-border text-text-muted'
            ]"
          >
            <span>🎭</span>
            <span>Mask</span>
          </button>
        </template>

        <button
          @click="toggleBreathing"
          :class="[
            'px-3 py-1.5 rounded-pill border text-xs font-medium transition flex items-center gap-1.5',
            activeActivity === 'breathing' ? 'bg-mint text-[#0F1020] border-mint font-semibold shadow-glow' : 'bg-surface-2 border-border text-text-muted'
          ]"
        >
          <span>🫁</span>
          <span>Breathe</span>
        </button>

        <button
          @click="nextIcebreaker"
          class="px-3 py-1.5 rounded-pill bg-surface-2 border border-border text-xs text-text-muted hover:border-coral hover:text-coral transition flex items-center gap-1.5"
        >
          <span>💡</span>
          <span>Icebreaker</span>
        </button>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="handleSkip"
          class="px-3.5 py-1.5 rounded-pill border border-border text-xs text-text-muted hover:border-coral hover:text-coral transition"
        >
          Keep as...
        </button>
        <button
          @click="handleReport"
          class="px-2.5 py-1.5 rounded-pill text-xs text-text-muted hover:text-danger transition"
        >
          Report
        </button>
      </div>
    </footer>

    <!-- Post-Call Modal (Stress-relief rating & Keep system per 01-context.md & 06-ui-ux-structure.md) -->
    <div
      v-if="showPostCall"
      class="fixed inset-0 z-50 bg-bg/85 backdrop-blur-md flex items-center justify-center p-4 animate-fade-in"
    >
      <div class="bg-surface border border-border rounded-card max-w-md w-full p-6 text-center brand-glow relative">
        <BrandLogo size="sm" :animated="false" />
        <h3 class="text-base font-display font-bold mt-3">Room Ended</h3>
        <p class="text-xs text-text-muted mt-1">You spent {{ formatTimer(seconds) }} in {{ room.vibe }} vibe.</p>

        <!-- Stress relief score (feeds core PRD metric: "I feel lighter") -->
        <div class="my-6">
          <span class="text-xs uppercase font-semibold text-text-muted tracking-wider">How do you feel right now?</span>
          <div class="flex justify-center gap-3 mt-3">
            <button
              v-for="(emoji, index) in ['😔', '😐', '🙂', '😌', '✨']"
              :key="index"
              @click="selectedMood = index + 1"
              :class="[
                'w-11 h-11 text-xl rounded-full border transition flex items-center justify-center',
                selectedMood === index + 1 ? 'border-primary bg-primary/20 scale-110 shadow-glow' : 'border-border bg-surface-2 opacity-80'
              ]"
            >
              {{ emoji }}
            </button>
          </div>
          <span class="text-[11px] text-text-muted mt-2 block">
            {{ selectedMood ? 'Thank you! Your feedback keeps Emiringkay calm.' : 'Tap an emoji' }}
          </span>
        </div>

        <!-- Keep as... section -->
        <div class="border-t border-border pt-5 pb-2 text-left">
          <span class="text-xs font-semibold text-text-base block mb-2">Want to Keep this connection?</span>
          <div class="grid grid-cols-2 gap-2 text-xs">
            <button
              @click="sendKeep('friend')"
              :class="[
                'p-2.5 rounded-input border transition text-left flex items-center gap-2',
                selectedKeep === 'friend' ? 'border-mint bg-mint/15 text-mint font-semibold' : 'border-border bg-surface-2 text-text-muted'
              ]"
            >
              <span>🌿</span>
              <div>
                <div class="font-medium">Friend</div>
                <div class="text-[10px] opacity-75">Casual chats</div>
              </div>
            </button>

            <button
              @click="sendKeep('rival')"
              :class="[
                'p-2.5 rounded-input border transition text-left flex items-center gap-2',
                selectedKeep === 'rival' ? 'border-gold bg-gold/15 text-gold font-semibold' : 'border-border bg-surface-2 text-text-muted'
              ]"
            >
              <span>🎮</span>
              <div>
                <div class="font-medium">Rival</div>
                <div class="text-[10px] opacity-75">Game & trivia duels</div>
              </div>
            </button>

            <button
              @click="sendKeep('spark')"
              :class="[
                'p-2.5 rounded-input border transition text-left flex items-center gap-2',
                selectedKeep === 'spark' ? 'border-coral bg-coral/15 text-coral font-semibold' : 'border-border bg-surface-2 text-text-muted'
              ]"
            >
              <span>✨</span>
              <div>
                <div class="font-medium">Spark</div>
                <div class="text-[10px] opacity-75">Romantic mutual opt-in</div>
              </div>
            </button>

            <button
              @click="sendKeep('confidant')"
              :class="[
                'p-2.5 rounded-input border transition text-left flex items-center gap-2',
                selectedKeep === 'confidant' ? 'border-[#7FB3FF] bg-[#7FB3FF]/15 text-[#7FB3FF] font-semibold' : 'border-border bg-surface-2 text-text-muted'
              ]"
            >
              <span>☁️</span>
              <div>
                <div class="font-medium">Confidant</div>
                <div class="text-[10px] opacity-75">Safe person to vent to</div>
              </div>
            </button>
          </div>

          <p v-if="keepSent" class="text-[11px] text-mint mt-2 text-center">
            ✓ Keep request recorded. If mutual, they will appear in your Circle!
          </p>
        </div>

        <!-- Next / Exit Action Buttons -->
        <div class="mt-6 flex items-center gap-3">
          <button
            @click="handleNextMatch"
            class="flex-1 py-3 rounded-pill bg-primary text-white font-semibold text-xs shadow-glow hover:opacity-90 transition"
          >
            Meet Someone Next ➔
          </button>
        </div>
      </div>
    </div>

  </div>
</template>
