<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import BrandLogo from '@/Components/BrandLogo.vue';

interface Member {
  user_id: number;
  user?: {
    display_name?: string;
    name?: string;
  };
}

interface Room {
  id: string;
  topic: string;
  vibe: string;
  description?: string;
  capacity: number;
  current_participants: number;
  host?: {
    display_name?: string;
    name?: string;
  };
  active_members?: Member[];
}

interface DailyPromptAnswer {
  id: number;
  answer: string;
  mood_score: number;
  author: string;
  created_at: string;
}

interface DailyPrompt {
  id: number;
  prompt: string;
  responses_count: number;
  answers: DailyPromptAnswer[];
  user_answered: boolean;
  user_answer?: {
    answer: string;
    mood_score: number;
  };
}

interface PlazaEvent {
  id: number;
  title: string;
  description?: string;
  scheduled_at: string;
  time_until: string;
  vibe: string;
  host_name: string;
  attendees_count: number;
}

interface Badge {
  id: number;
  slug: string;
  name: string;
  description: string;
  icon: string;
  category: string;
}

interface Props {
  rooms: Room[];
  dailyPrompt: DailyPrompt;
  events: PlazaEvent[];
  badges: {
    all: Badge[];
    unlocked_ids: number[];
  };
  currentUser: {
    id: number;
    name: string;
  };
}

const props = defineProps<Props>();

// Vibe Filtering
const selectedVibe = ref<string>('All');
const vibes = ['All', 'Chill', 'Vent', 'Play', 'Deep', 'Spark'];

const filteredRooms = computed(() => {
  if (selectedVibe.value === 'All') return props.rooms;
  return props.rooms.filter((r) => r.vibe.toLowerCase() === selectedVibe.value.toLowerCase());
});

// Daily Prompt Form
const moodEmojis = [
  { score: 1, emoji: '😫', label: 'Overwhelmed' },
  { score: 2, emoji: '😔', label: 'Drained' },
  { score: 3, emoji: '😐', label: 'Neutral' },
  { score: 4, emoji: '😊', label: 'Calm' },
  { score: 5, emoji: '🥰', label: 'Serene' },
];
const promptAnswer = ref('');
const promptMood = ref(3);
const isSubmittingPrompt = ref(false);
const promptSuccess = ref(false);

const submitDailyPrompt = async () => {
  if (!promptAnswer.value.trim() || isSubmittingPrompt.value) return;

  isSubmittingPrompt.value = true;
  try {
    const res = await fetch('/plaza/prompt/answer', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
      },
      body: JSON.stringify({
        answer: promptAnswer.value,
        mood_score: promptMood.value,
      }),
    });
    const data = await res.json();
    if (data.success) {
      promptSuccess.value = true;
      router.reload({ only: ['dailyPrompt', 'badges'] });
    }
  } catch (e) {
    console.error('Failed to submit prompt', e);
  } finally {
    isSubmittingPrompt.value = false;
  }
};

// Create Room Modal
const isCreateModalOpen = ref(false);
const newRoomTopic = ref('');
const newRoomVibe = ref('Chill');
const newRoomDesc = ref('');
const newRoomCapacity = ref(8);
const isCreatingRoom = ref(false);

const openCreateModal = () => {
  isCreateModalOpen.value = true;
};

const closeCreateModal = () => {
  isCreateModalOpen.value = false;
};

const createSpace = async () => {
  if (!newRoomTopic.value.trim() || isCreatingRoom.value) return;

  isCreatingRoom.value = true;
  try {
    const res = await fetch('/plaza/rooms', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
      },
      body: JSON.stringify({
        topic: newRoomTopic.value,
        vibe: newRoomVibe.value,
        description: newRoomDesc.value,
        capacity: newRoomCapacity.value,
      }),
    });
    const data = await res.json();
    if (data.success && data.redirect_url) {
      window.location.href = data.redirect_url;
    }
  } catch (e) {
    console.error('Failed to create space', e);
  } finally {
    isCreatingRoom.value = false;
  }
};

// RSVP for Event
const rsvpdEvents = ref<Record<number, boolean>>({});
const rsvpToEvent = async (eventId: number) => {
  try {
    const res = await fetch(`/plaza/events/${eventId}/rsvp`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
      },
    });
    const data = await res.json();
    if (data.success) {
      rsvpdEvents.value[eventId] = true;
      router.reload({ only: ['events'] });
    }
  } catch (e) {
    console.error('Failed to RSVP', e);
  }
};

const getVibeColor = (vibe: string) => {
  switch (vibe.toLowerCase()) {
    case 'chill':
      return 'from-teal-500/20 to-indigo-500/10 text-teal-300 border-teal-500/30';
    case 'vent':
      return 'from-rose-500/20 to-orange-500/10 text-rose-300 border-rose-500/30';
    case 'play':
      return 'from-amber-500/20 to-purple-500/10 text-amber-300 border-amber-500/30';
    case 'deep':
      return 'from-purple-500/20 to-blue-500/10 text-purple-300 border-purple-500/30';
    case 'spark':
      return 'from-pink-500/20 to-rose-500/10 text-pink-300 border-pink-500/30';
    default:
      return 'from-slate-500/20 to-slate-500/10 text-slate-300 border-slate-500/30';
  }
};
</script>

<template>
  <Head title="Soft Night Plaza - Emiringkay" />

  <div class="min-h-screen bg-[#0F1020] text-slate-100 flex flex-col font-sans selection:bg-[#8B7CFF]/30">
    <!-- Ambient Starlight Backing -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden z-0">
      <div class="absolute -top-32 -left-32 w-96 h-96 rounded-full bg-[#8B7CFF]/10 blur-[100px]" />
      <div class="absolute top-1/3 -right-32 w-96 h-96 rounded-full bg-[#5EEAD4]/10 blur-[120px]" />
      <div class="absolute -bottom-32 left-1/3 w-96 h-96 rounded-full bg-[#FF8A7A]/10 blur-[100px]" />
    </div>

    <!-- Navigation Header -->
    <header class="relative z-10 border-b border-slate-800/80 bg-[#1A1B33]/60 backdrop-blur-md px-6 py-3.5 flex items-center justify-between">
      <div class="flex items-center gap-4">
        <Link href="/" class="flex items-center gap-3 group">
          <div class="w-9 h-9 flex items-center justify-center p-1 bg-white/5 rounded-xl border border-white/10 group-hover:border-[#8B7CFF]/50 transition-colors">
            <BrandLogo size="xs" :animated="false" />
          </div>
          <div>
            <span class="font-extrabold tracking-wide text-lg text-white group-hover:text-[#8B7CFF] transition-colors">Emiringkay</span>
            <span class="text-xs ml-2 px-2 py-0.5 rounded-full bg-[#8B7CFF]/20 text-[#8B7CFF] font-medium border border-[#8B7CFF]/30">Plaza</span>
          </div>
        </Link>
      </div>

      <nav class="flex items-center gap-2 sm:gap-4">
        <Link
          href="/"
          class="px-3.5 py-1.5 rounded-xl text-sm font-medium text-slate-300 hover:text-white hover:bg-white/5 transition-colors"
        >
          1:1 Meet
        </Link>
        <Link
          href="/circle"
          class="px-3.5 py-1.5 rounded-xl text-sm font-medium text-slate-300 hover:text-white hover:bg-white/5 transition-colors"
        >
          Circle
        </Link>
        <span class="px-3.5 py-1.5 rounded-xl text-sm font-semibold bg-[#8B7CFF]/20 text-[#8B7CFF] border border-[#8B7CFF]/40">
          Plaza
        </span>
      </nav>
    </header>

    <!-- Main Content -->
    <main class="relative z-10 flex-1 max-w-6xl w-full mx-auto px-4 sm:px-6 py-8 space-y-10">
      
      <!-- Daily Prompt Hero (Stress Relief Ticker & Reflection) -->
      <section class="rounded-3xl p-6 sm:p-8 bg-gradient-to-br from-[#1A1B33]/90 via-[#1A1B33]/60 to-[#0F1020]/80 border border-[#8B7CFF]/20 shadow-2xl relative overflow-hidden">
        <div class="absolute -right-12 -bottom-12 w-64 h-64 bg-[#8B7CFF]/10 rounded-full blur-3xl pointer-events-none" />

        <div class="max-w-3xl space-y-4">
          <div class="flex items-center gap-3">
            <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-[#5EEAD4]/15 text-[#5EEAD4] border border-[#5EEAD4]/30 flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-[#5EEAD4] animate-ping" />
              Daily Reflection
            </span>
            <span class="text-xs text-slate-400 font-medium">
              {{ dailyPrompt.responses_count }} night owls reflected today
            </span>
          </div>

          <h2 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight leading-snug">
            "{{ dailyPrompt.prompt }}"
          </h2>

          <!-- If already answered -->
          <div v-if="dailyPrompt.user_answered" class="p-4 rounded-2xl bg-white/5 border border-white/10 space-y-1">
            <div class="flex items-center justify-between text-xs text-slate-400">
              <span>Your reflection tonight:</span>
              <span class="text-emerald-400 font-medium">Shared with the Plaza</span>
            </div>
            <p class="text-sm text-slate-200 italic">"{{ dailyPrompt.user_answer?.answer }}"</p>
          </div>

          <!-- Answer Form -->
          <form v-else @submit.prevent="submitDailyPrompt" class="space-y-4 pt-2">
            <div>
              <div class="text-xs text-slate-400 mb-2 font-medium">How is your heart feeling right now?</div>
              <div class="flex items-center gap-3">
                <button
                  type="button"
                  v-for="emoji in moodEmojis"
                  :key="emoji.score"
                  @click="promptMood = emoji.score"
                  :title="emoji.label"
                  class="w-11 h-11 rounded-2xl flex items-center justify-center text-xl transition-all border"
                  :class="promptMood === emoji.score 
                    ? 'bg-[#8B7CFF]/30 border-[#8B7CFF] scale-110 shadow-lg shadow-[#8B7CFF]/20 ring-2 ring-[#8B7CFF]/40' 
                    : 'bg-white/5 border-white/10 hover:bg-white/10'"
                >
                  {{ emoji.emoji }}
                </button>
              </div>
            </div>

            <div class="relative">
              <textarea
                v-model="promptAnswer"
                rows="2"
                maxlength="280"
                placeholder="Share a gentle thought, a small comfort, or simply let out a breath..."
                class="w-full rounded-2xl bg-[#0F1020]/90 border border-slate-700/80 px-4 py-3 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-[#8B7CFF] focus:ring-1 focus:ring-[#8B7CFF] transition-all resize-none"
              />
              <div class="absolute right-3 bottom-3 flex items-center gap-3">
                <span class="text-xs text-slate-500">{{ 280 - promptAnswer.length }}</span>
                <button
                  type="submit"
                  :disabled="!promptAnswer.trim() || isSubmittingPrompt"
                  class="px-4 py-1.5 rounded-xl text-xs font-semibold bg-gradient-to-r from-[#8B7CFF] to-[#6C5CE7] text-white disabled:opacity-40 disabled:cursor-not-allowed hover:shadow-md hover:shadow-[#8B7CFF]/30 transition-all flex items-center gap-1.5"
                >
                  <span v-if="isSubmittingPrompt" class="w-3 h-3 border-2 border-white/40 border-t-white rounded-full animate-spin" />
                  <span>Release to Plaza</span>
                </button>
              </div>
            </div>
          </form>

          <!-- Recent Reflections Ticker -->
          <div v-if="dailyPrompt.answers && dailyPrompt.answers.length > 0" class="pt-4 border-t border-slate-800/80">
            <div class="text-xs font-semibold text-slate-400 mb-2 uppercase tracking-wider">Voices in the Night</div>
            <div class="flex gap-3 overflow-x-auto pb-2 scrollbar-none snap-x">
              <div
                v-for="ans in dailyPrompt.answers"
                :key="ans.id"
                class="snap-start shrink-0 w-72 rounded-2xl p-3.5 bg-white/[0.03] border border-white/10 space-y-1.5"
              >
                <div class="flex items-center justify-between text-xs">
                  <span class="font-medium text-[#5EEAD4]">{{ ans.author }}</span>
                  <span class="text-slate-500 text-[10px]">{{ ans.created_at }}</span>
                </div>
                <p class="text-xs text-slate-300 line-clamp-3 leading-relaxed">
                  "{{ ans.answer }}"
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Plaza Group Spaces Header & Filter (FR-31: 8 participants max) -->
      <section class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div class="flex items-center gap-2">
              <h3 class="text-xl font-extrabold text-white tracking-tight">Open Gathering Spaces</h3>
              <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-[#8B7CFF]/20 text-[#8B7CFF] border border-[#8B7CFF]/30">
                Max 8 peers
              </span>
            </div>
            <p class="text-xs text-slate-400 mt-0.5">
              Drop in, listen gently, or step up to speak. Warm cozy spaces for mutual peace.
            </p>
          </div>

          <div class="flex items-center gap-3">
            <!-- Vibe Pill Filters -->
            <div class="flex items-center gap-1.5 p-1 rounded-2xl bg-white/5 border border-white/10 overflow-x-auto">
              <button
                v-for="vibe in vibes"
                :key="vibe"
                @click="selectedVibe = vibe"
                class="px-3 py-1 rounded-xl text-xs font-medium transition-all"
                :class="selectedVibe === vibe
                  ? 'bg-[#8B7CFF] text-white shadow-sm'
                  : 'text-slate-400 hover:text-white hover:bg-white/5'"
              >
                {{ vibe }}
              </button>
            </div>

            <!-- Open Space Button -->
            <button
              @click="openCreateModal"
              class="px-4 py-2 rounded-2xl text-xs font-bold bg-[#5EEAD4]/20 text-[#5EEAD4] border border-[#5EEAD4]/40 hover:bg-[#5EEAD4]/30 hover:scale-[1.02] active:scale-[0.98] transition-all flex items-center gap-1.5 shrink-0 shadow-lg shadow-[#5EEAD4]/10"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              <span>Host a Space</span>
            </button>
          </div>
        </div>

        <!-- Room Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <div
            v-for="room in filteredRooms"
            :key="room.id"
            class="group rounded-3xl p-6 bg-[#1A1B33]/70 hover:bg-[#1A1B33] border border-white/10 hover:border-[#8B7CFF]/40 transition-all duration-300 flex flex-col justify-between shadow-xl relative overflow-hidden"
          >
            <!-- Background Glow -->
            <div class="absolute -right-8 -top-8 w-40 h-40 bg-[#8B7CFF]/5 group-hover:bg-[#8B7CFF]/10 rounded-full blur-2xl transition-all pointer-events-none" />

            <div class="space-y-3 relative z-10">
              <div class="flex items-center justify-between">
                <span
                  class="px-3 py-1 rounded-full text-xs font-semibold border bg-gradient-to-r"
                  :class="getVibeColor(room.vibe)"
                >
                  {{ room.vibe }}
                </span>

                <!-- Participant Capacity Pill (FR-31) -->
                <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white/5 border border-white/10 text-xs font-medium text-slate-300">
                  <span class="w-2 h-2 rounded-full" :class="room.current_participants >= room.capacity ? 'bg-amber-400' : 'bg-emerald-400 animate-pulse'" />
                  <span>{{ room.current_participants }}/{{ room.capacity }} peers</span>
                </div>
              </div>

              <div>
                <h4 class="text-base font-bold text-white group-hover:text-[#8B7CFF] transition-colors">
                  {{ room.topic }}
                </h4>
                <p v-if="room.description" class="text-xs text-slate-400 mt-1 line-clamp-2">
                  {{ room.description }}
                </p>
              </div>
            </div>

            <div class="pt-5 mt-4 border-t border-slate-800/80 flex items-center justify-between relative z-10">
              <div class="flex items-center gap-2">
                <div class="w-6 h-6 rounded-full bg-gradient-to-tr from-[#8B7CFF] to-[#5EEAD4] flex items-center justify-center text-[10px] font-bold text-[#0F1020]">
                  {{ (room.host?.display_name || room.host?.name || 'H').charAt(0) }}
                </div>
                <span class="text-xs text-slate-400">
                  Hosted by <strong class="text-slate-200">{{ room.host?.display_name || room.host?.name || 'Emiringkay' }}</strong>
                </span>
              </div>

              <Link
                :href="`/plaza/rooms/${room.id}`"
                class="px-4 py-2 rounded-xl text-xs font-semibold bg-white/10 hover:bg-[#8B7CFF] text-white hover:shadow-lg hover:shadow-[#8B7CFF]/20 transition-all flex items-center gap-1"
              >
                <span>Step Inside</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </Link>
            </div>
          </div>
        </div>
      </section>

      <!-- Events Calendar & Badges Grid (FR-32) -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Community Events Calendar -->
        <section class="lg:col-span-2 rounded-3xl p-6 bg-[#1A1B33]/60 border border-white/10 space-y-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <div class="w-2.5 h-2.5 rounded-full bg-[#FF8A7A]" />
              <h3 class="text-lg font-bold text-white">Upcoming Plaza Events</h3>
            </div>
            <span class="text-xs text-slate-400">Join the circle</span>
          </div>

          <div class="space-y-3">
            <div
              v-for="event in events"
              :key="event.id"
              class="p-4 rounded-2xl bg-white/[0.03] hover:bg-white/[0.06] border border-white/5 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-4"
            >
              <div class="space-y-1">
                <div class="flex items-center gap-2">
                  <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-[#FF8A7A]/20 text-[#FF8A7A] border border-[#FF8A7A]/30">
                    {{ event.vibe }}
                  </span>
                  <span class="text-xs text-slate-400 font-medium">
                    {{ event.scheduled_at }} ({{ event.time_until }})
                  </span>
                </div>
                <h4 class="text-sm font-semibold text-slate-100">{{ event.title }}</h4>
                <p v-if="event.description" class="text-xs text-slate-400 max-w-lg">{{ event.description }}</p>
              </div>

              <div class="flex items-center gap-3 shrink-0">
                <span class="text-xs text-slate-400 font-medium">
                  {{ event.attendees_count }} attending
                </span>
                <button
                  @click="rsvpToEvent(event.id)"
                  :disabled="rsvpdEvents[event.id]"
                  class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all"
                  :class="rsvpdEvents[event.id]
                    ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30'
                    : 'bg-[#8B7CFF]/20 hover:bg-[#8B7CFF]/30 text-[#8B7CFF] border border-[#8B7CFF]/40'"
                >
                  {{ rsvpdEvents[event.id] ? 'RSVP Confirmed' : 'I will attend' }}
                </button>
              </div>
            </div>
          </div>
        </section>

        <!-- Karma & Badges Showcase (FR-32) -->
        <section class="rounded-3xl p-6 bg-[#1A1B33]/60 border border-white/10 space-y-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <div class="w-2.5 h-2.5 rounded-full bg-[#5EEAD4]" />
              <h3 class="text-lg font-bold text-white">Plaza Badges</h3>
            </div>
            <span class="text-xs text-[#5EEAD4] font-medium">
              {{ badges.unlocked_ids.length }}/{{ badges.all.length }} Unlocked
            </span>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div
              v-for="b in badges.all"
              :key="b.id"
              class="p-3.5 rounded-2xl border transition-all text-center space-y-1.5"
              :class="badges.unlocked_ids.includes(b.id)
                ? 'bg-[#8B7CFF]/15 border-[#8B7CFF]/40 shadow-sm shadow-[#8B7CFF]/20'
                : 'bg-white/[0.02] border-white/5 opacity-50'"
            >
              <div class="w-10 h-10 mx-auto rounded-xl flex items-center justify-center text-lg" :class="badges.unlocked_ids.includes(b.id) ? 'bg-[#8B7CFF]/20 text-[#8B7CFF]' : 'bg-slate-800 text-slate-500'">
                ✨
              </div>
              <div class="text-xs font-bold text-white truncate">{{ b.name }}</div>
              <p class="text-[10px] text-slate-400 line-clamp-2">{{ b.description }}</p>
            </div>
          </div>
        </section>
      </div>

    </main>

    <!-- Host a Space Modal (FR-31) -->
    <div
      v-if="isCreateModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
      @click.self="closeCreateModal"
    >
      <div class="w-full max-w-md rounded-3xl p-6 bg-[#1A1B33] border border-[#8B7CFF]/30 shadow-2xl space-y-5 animate-in fade-in zoom-in-95 duration-200">
        <div class="flex items-center justify-between border-b border-slate-800 pb-4">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-[#8B7CFF]/20 text-[#8B7CFF] flex items-center justify-center font-bold">
              ✦
            </div>
            <div>
              <h3 class="text-base font-bold text-white">Host a Plaza Space</h3>
              <p class="text-[11px] text-slate-400">Open a cozy circle for fellow night owls</p>
            </div>
          </div>
          <button @click="closeCreateModal" class="text-slate-400 hover:text-white p-1">
            ✕
          </button>
        </div>

        <form @submit.prevent="createSpace" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Space Topic / Title</label>
            <input
              v-model="newRoomTopic"
              type="text"
              maxlength="100"
              required
              placeholder="e.g., Midnight Ambient Talk & Stargazing"
              class="w-full rounded-xl bg-[#0F1020] border border-slate-700 px-3.5 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-[#8B7CFF]"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Vibe</label>
            <div class="grid grid-cols-4 gap-2">
              <button
                type="button"
                v-for="v in ['Chill', 'Vent', 'Play', 'Deep']"
                :key="v"
                @click="newRoomVibe = v"
                class="py-2 rounded-xl text-xs font-medium border transition-all"
                :class="newRoomVibe === v
                  ? 'bg-[#8B7CFF]/30 border-[#8B7CFF] text-[#8B7CFF]'
                  : 'bg-white/5 border-white/10 text-slate-400 hover:text-white'"
              >
                {{ v }}
              </button>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Space Description (Optional)</label>
            <textarea
              v-model="newRoomDesc"
              rows="2"
              maxlength="300"
              placeholder="What should listeners or participants expect?"
              class="w-full rounded-xl bg-[#0F1020] border border-slate-700 px-3.5 py-2 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-[#8B7CFF] resize-none"
            />
          </div>

          <div>
            <div class="flex items-center justify-between text-xs font-semibold text-slate-300 mb-1">
              <span>Capacity Cap (FR-31 limit: 8)</span>
              <span class="text-[#5EEAD4]">{{ newRoomCapacity }} peers max</span>
            </div>
            <input
              v-model.number="newRoomCapacity"
              type="range"
              min="2"
              max="8"
              step="1"
              class="w-full accent-[#8B7CFF]"
            />
          </div>

          <div class="pt-2 flex items-center justify-end gap-3">
            <button
              type="button"
              @click="closeCreateModal"
              class="px-4 py-2 rounded-xl text-xs font-medium text-slate-400 hover:text-white"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="!newRoomTopic.trim() || isCreatingRoom"
              class="px-5 py-2.5 rounded-xl text-xs font-bold bg-gradient-to-r from-[#8B7CFF] to-[#6C5CE7] text-white hover:shadow-lg hover:shadow-[#8B7CFF]/30 disabled:opacity-40"
            >
              <span v-if="isCreatingRoom">Opening...</span>
              <span v-else>Open Space</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
