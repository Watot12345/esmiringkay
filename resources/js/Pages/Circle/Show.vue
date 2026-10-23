<script setup lang="ts">
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import BrandLogo from '../../Components/BrandLogo.vue';

interface Message {
  id: number;
  body: string;
  is_me: boolean;
  time: string;
}

interface Props {
  connection: {
    id: number;
    type: 'friend' | 'rival' | 'spark' | 'confidant';
    partner: {
      id: number;
      name: string;
      avatar?: string;
    };
  };
  messages: Message[];
}

const props = defineProps<Props>();

const chatMessages = ref<Message[]>([...props.messages]);
const inputBody = ref('');
const isEnding = ref(false);

async function handleSendMessage() {
  if (!inputBody.value.trim()) return;
  const text = inputBody.value.trim();
  const optimisticMsg: Message = {
    id: Date.now(),
    body: text,
    is_me: true,
    time: 'Now',
  };
  chatMessages.value.push(optimisticMsg);
  inputBody.value = '';

  try {
    const res = await fetch(`/circle/${props.connection.id}/message`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
      },
      body: JSON.stringify({ body: text }),
    });
    const data = await res.json();
    if (!data.success) {
      console.warn('Failed to send DM');
    }
  } catch (err) {
    console.error(err);
  }
}

async function handleEndConnection() {
  if (!confirm(`Are you sure you want to remove this ${props.connection.type}? You can always meet other people in the Plaza.`)) {
    return;
  }
  isEnding.value = true;
  try {
    await fetch(`/circle/${props.connection.id}/end`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
      },
    });
    router.visit('/circle');
  } catch (err) {
    console.error(err);
    isEnding.value = false;
  }
}

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
  <div class="h-screen w-screen bg-[#0F1020] text-[#F4F2FF] flex flex-col justify-between font-sans select-none overflow-hidden relative">
    
    <!-- Top Bar -->
    <header class="h-16 px-6 border-b border-[#34376A]/40 bg-[#1A1B33]/85 backdrop-blur-md flex items-center justify-between z-20 shrink-0">
      <div class="flex items-center gap-4">
        <Link href="/circle" class="text-xs text-[#A9A6CC] hover:text-white flex items-center gap-1 transition">
          <span>◀</span>
          <span>Circle</span>
        </Link>

        <!-- Partner Info -->
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-[#8B7CFF] to-[#FF8A7A] flex items-center justify-center text-sm shadow-sm">
            {{ connection.partner.avatar || '🎭' }}
          </div>
          <div>
            <div class="flex items-center gap-2">
              <span class="text-sm font-bold text-white">{{ connection.partner.name }}</span>
              <span
                :class="[
                  'px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border',
                  getBadgeStyle(connection.type)
                ]"
              >
                {{ connection.type }}
              </span>
            </div>
            <div class="flex items-center gap-1 text-[11px] text-[#5EEAD4]">
              <span class="w-1.5 h-1.5 rounded-full bg-[#5EEAD4] animate-pulse"></span>
              <span>Online</span>
            </div>
          </div>
        </div>
      </div>

      <!-- End connection safe action -->
      <button
        @click="handleEndConnection"
        :disabled="isEnding"
        class="px-3 py-1.5 rounded-full border border-danger/30 text-xs text-danger hover:bg-danger/10 transition"
      >
        {{ isEnding ? 'Removing...' : 'End Keep' }}
      </button>
    </header>

    <!-- Message Stream -->
    <main class="flex-1 overflow-y-auto p-4 sm:p-6 max-w-2xl w-full mx-auto space-y-3">
      <!-- Conversation starter badge -->
      <div class="py-2 px-4 rounded-2xl bg-[#1A1B33]/60 border border-[#34376A]/40 text-center text-xs text-[#A9A6CC] my-4 mx-auto max-w-sm">
        <span>✨</span> You kept each other as <strong class="text-white capitalize">{{ connection.type }}</strong>. Say hi!
      </div>

      <div
        v-for="msg in chatMessages"
        :key="msg.id"
        :class="[
          'flex flex-col text-xs',
          msg.is_me ? 'items-end' : 'items-start'
        ]"
      >
        <div
          :class="[
            'max-w-[78%] rounded-2xl px-4 py-2.5 leading-relaxed shadow-md',
            msg.is_me
              ? 'bg-gradient-to-r from-[#8B7CFF] to-[#6C5CE7] text-white rounded-br-none'
              : 'bg-[#1A1B33] text-[#F4F2FF] border border-[#34376A]/50 rounded-bl-none'
          ]"
        >
          {{ msg.body }}
        </div>
        <span class="text-[10px] text-[#A9A6CC]/50 mt-1 px-1 font-mono">
          {{ msg.time }}
        </span>
      </div>
    </main>

    <!-- Message Input Bar -->
    <footer class="p-4 bg-[#1A1B33] border-t border-[#34376A]/40 z-20 shrink-0">
      <form @submit.prevent="handleSendMessage" class="max-w-2xl mx-auto flex items-center gap-2">
        <input
          v-model="inputBody"
          type="text"
          placeholder="Send a message to your Keep..."
          class="flex-1 bg-[#24264A] border border-[#34376A]/70 rounded-full px-4 py-2.5 text-xs text-white placeholder-[#A9A6CC]/60 focus:outline-none focus:border-[#8B7CFF] transition"
        />
        <button
          type="submit"
          class="w-10 h-10 rounded-full bg-gradient-to-r from-[#8B7CFF] to-[#FF8A7A] text-white flex items-center justify-center shrink-0 hover:scale-105 active:scale-95 transition shadow-[0_4px_16px_rgba(139,124,255,0.4)] cursor-pointer"
        >
          ➔
        </button>
      </form>
    </footer>

  </div>
</template>
