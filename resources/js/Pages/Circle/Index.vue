<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import BrandLogo from '../../Components/BrandLogo.vue';

interface ConnectionItem {
  id: number;
  type: 'friend' | 'rival' | 'spark' | 'confidant';
  partner: {
    id: number;
    name: string;
    avatar?: string;
  };
  last_message?: {
    body: string;
    time: string;
    is_me: boolean;
  };
  is_online?: boolean;
}

interface Props {
  connections: ConnectionItem[];
}

const props = defineProps<Props>();

type FilterType = 'all' | 'friend' | 'rival' | 'spark' | 'confidant';
const activeFilter = ref<FilterType>('all');

const filteredConnections = computed(() => {
  if (activeFilter.value === 'all') return props.connections;
  return props.connections.filter((c) => c.type === activeFilter.value);
});

const filters = [
  { id: 'all' as FilterType, label: 'All Keeps', icon: '⭕' },
  { id: 'friend' as FilterType, label: 'Friends', icon: '🌿', color: 'text-mint' },
  { id: 'rival' as FilterType, label: 'Rivals', icon: '🎮', color: 'text-gold' },
  { id: 'spark' as FilterType, label: 'Sparks', icon: '✨', color: 'text-coral' },
  { id: 'confidant' as FilterType, label: 'Confidants', icon: '☁️', color: 'text-[#7FB3FF]' },
];

function getBadgeStyle(type: string) {
  switch (type) {
    case 'friend':
      return 'bg-mint/15 text-mint border-mint/30';
    case 'rival':
      return 'bg-gold/15 text-gold border-gold/30';
    case 'spark':
      return 'bg-coral/15 text-coral border-coral/30';
    case 'confidant':
      return 'bg-[#7FB3FF]/15 text-[#7FB3FF] border-[#7FB3FF]/30';
    default:
      return 'bg-surface text-text-muted border-border';
  }
}
</script>

<template>
  <div class="min-h-screen bg-[#0F1020] text-[#F4F2FF] flex flex-col justify-between font-sans select-none relative overflow-x-hidden">
    
    <!-- Background Ambient Glows -->
    <div class="absolute -top-32 left-1/2 -translate-x-1/2 w-96 h-96 bg-[#8B7CFF]/15 rounded-full blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-10 right-10 w-72 h-72 bg-[#5EEAD4]/10 rounded-full blur-[100px] pointer-events-none"></div>

    <!-- Header Navigation -->
    <header class="w-full max-w-3xl mx-auto px-6 py-5 flex items-center justify-between border-b border-[#34376A]/40 relative z-10">
      <div class="flex items-center gap-3">
        <Link href="/" class="flex items-center gap-2 hover:opacity-80 transition">
          <BrandLogo size="xs" :animated="false" />
          <span class="text-sm font-bold tracking-tight text-white">Emiringkay</span>
        </Link>
        <span class="text-xs text-[#A9A6CC]/50">/</span>
        <span class="text-xs font-semibold text-[#8B7CFF]">Your Circle</span>
      </div>

      <div class="flex items-center gap-2">
        <Link
          href="/plaza"
          class="px-3 py-1.5 rounded-full text-xs font-semibold bg-[#1A1B33] border border-[#34376A] text-[#A9A6CC] hover:text-white hover:border-[#8B7CFF] transition flex items-center gap-1.5 shadow-sm"
        >
          <span>🏮</span>
          <span>Plaza</span>
        </Link>
        <Link
          href="/"
          class="px-3.5 py-1.5 rounded-full text-xs font-semibold bg-[#1A1B33] border border-[#34376A] text-[#F4F2FF] hover:border-[#8B7CFF] transition flex items-center gap-1.5 shadow-sm"
        >
          <span>💫</span>
          <span>Meet</span>
        </Link>
      </div>
    </header>

    <!-- Main Content Container -->
    <main class="w-full max-w-3xl mx-auto px-4 py-8 flex-1 relative z-10">
      
      <!-- Circle Hero Intro -->
      <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-white flex items-center gap-2">
          <span>⭕</span>
          <span>Your Circle of Keeps</span>
        </h1>
        <p class="text-xs sm:text-sm text-[#A9A6CC] mt-1">
          Good encounters you chose to keep. Chat, vent, or duel in friendly rivalry.
        </p>
      </div>

      <!-- Filter Chips Bar -->
      <div class="flex flex-wrap items-center gap-2 mb-6">
        <button
          v-for="filter in filters"
          :key="filter.id"
          @click="activeFilter = filter.id"
          :class="[
            'px-3.5 py-1.5 rounded-full text-xs font-medium border transition-all duration-200 flex items-center gap-1.5',
            activeFilter === filter.id
              ? 'bg-[#8B7CFF] text-white border-[#8B7CFF] shadow-[0_4px_16px_rgba(139,124,255,0.35)]'
              : 'bg-[#1A1B33]/80 text-[#A9A6CC] border-[#34376A]/60 hover:border-[#8B7CFF]/50 hover:text-white'
          ]"
        >
          <span>{{ filter.icon }}</span>
          <span>{{ filter.label }}</span>
        </button>
      </div>

      <!-- Connections List -->
      <div v-if="filteredConnections.length > 0" class="space-y-3">
        <Link
          v-for="conn in filteredConnections"
          :key="conn.id"
          :href="`/circle/${conn.id}`"
          class="block p-4 rounded-2xl bg-[#1A1B33]/80 hover:bg-[#24264A]/90 border border-[#34376A]/50 hover:border-[#8B7CFF]/60 transition-all duration-200 transform hover:-translate-y-0.5 shadow-lg group cursor-pointer"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3.5">
              <!-- Avatar with presence dot -->
              <div class="relative">
                <div class="w-12 h-12 rounded-full bg-gradient-to-tr from-[#8B7CFF] to-[#FF8A7A] flex items-center justify-center text-xl shadow-md">
                  {{ conn.partner.avatar || '🎭' }}
                </div>
                <span
                  v-if="conn.is_online"
                  class="absolute bottom-0 right-0 w-3.5 h-3.5 rounded-full bg-[#5EEAD4] border-2 border-[#1A1B33] shadow-[0_0_8px_#5EEAD4]"
                  title="Online right now"
                ></span>
              </div>

              <!-- Partner Name & Last Message -->
              <div>
                <div class="flex items-center gap-2">
                  <span class="text-sm font-bold text-white group-hover:text-[#8B7CFF] transition-colors">
                    {{ conn.partner.name }}
                  </span>
                  <span
                    :class="[
                      'px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border',
                      getBadgeStyle(conn.type)
                    ]"
                  >
                    {{ conn.type }}
                  </span>
                </div>

                <p class="text-xs text-[#A9A6CC] mt-1 line-clamp-1">
                  <span v-if="conn.last_message">
                    <span v-if="conn.last_message.is_me" class="opacity-70">You: </span>
                    {{ conn.last_message.body }}
                  </span>
                  <span v-else class="italic opacity-60">Connected! Say hi to start chatting.</span>
                </p>
              </div>
            </div>

            <!-- Timestamp & Arrow -->
            <div class="flex items-center gap-3">
              <span v-if="conn.last_message" class="text-[11px] text-[#A9A6CC]/60 font-mono">
                {{ conn.last_message.time }}
              </span>
              <span class="text-[#A9A6CC]/40 group-hover:text-white transition-colors">➔</span>
            </div>
          </div>
        </Link>
      </div>

      <!-- Empty State -->
      <div
        v-else
        class="p-10 rounded-3xl bg-[#1A1B33]/50 border border-[#34376A]/40 text-center flex flex-col items-center justify-center my-6"
      >
        <div class="w-16 h-16 rounded-full bg-[#24264A] flex items-center justify-center text-2xl mb-4 shadow-inner">
          ⭕
        </div>
        <h3 class="text-base font-bold text-white">Your Circle is waiting</h3>
        <p class="text-xs text-[#A9A6CC] max-w-sm mt-1 mb-6">
          When you have a great conversation in a Room, send a <strong class="text-white">Keep</strong>. If it's mutual, they will appear here as your Friend, Rival, Spark, or Confidant.
        </p>
        <Link
          href="/"
          class="px-6 py-3 rounded-full bg-gradient-to-r from-[#8B7CFF] to-[#FF8A7A] text-white font-bold text-xs shadow-[0_6px_24px_rgba(139,124,255,0.4)] hover:scale-105 active:scale-95 transition"
        >
          Enter the Plaza ➔
        </Link>
      </div>
    </main>

    <!-- Bottom Reassurance -->
    <footer class="w-full max-w-3xl mx-auto px-6 py-5 text-center text-[11px] text-[#A9A6CC]/50 border-t border-[#34376A]/30 relative z-10">
      Either side can end or mute a Keep at any time with one tap. Zero pressure.
    </footer>
  </div>
</template>
