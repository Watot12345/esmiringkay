<script setup lang="ts">
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import BrandLogo from '@/Components/BrandLogo.vue';

interface Member {
  id: number;
  name: string;
  role: string;
  is_muted: boolean;
  is_self: boolean;
  is_host: boolean;
}

interface Message {
  id: string;
  sender_id: number;
  sender_name: string;
  body: string;
  time: string;
  is_me: boolean;
}

interface RoomData {
  id: string;
  topic: string;
  vibe: string;
  description?: string;
  capacity: number;
  current_participants: number;
  host_id: number;
  members: Member[];
  messages: Message[];
}

interface CurrentUser {
  id: number;
  name: string;
  isHost: boolean;
  role: string;
}

interface Props {
  room: RoomData;
  currentUser: CurrentUser;
}

const props = defineProps<Props>();

// State
const members = ref<Member[]>([...props.room.members]);
const messages = ref<Message[]>([...props.room.messages]);
const chatInput = ref('');
const isSending = ref(false);
const chatContainer = ref<HTMLDivElement | null>(null);
const isMicMuted = ref(false);
const errorMessage = ref<string | null>(null);

// Echo Presence Channel
let plazaChannel: any = null;

const scrollToBottom = async () => {
  await nextTick();
  if (chatContainer.value) {
    chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
  }
};

onMounted(() => {
  scrollToBottom();

  // Subscribe to Reverb presence channel
  if (typeof window !== 'undefined' && (window as any).Echo) {
    plazaChannel = (window as any).Echo.join(`plaza.${props.room.id}`)
      .here((users: any[]) => {
        // Users currently in room
      })
      .joining((user: any) => {
        // Peer joined
        if (!members.value.some((m) => m.id === user.id)) {
          members.value.push({
            id: user.id,
            name: user.name || 'Stranger',
            role: 'listener',
            is_muted: false,
            is_self: user.id === props.currentUser.id,
            is_host: user.id === props.room.host_id,
          });
        }
      })
      .leaving((user: any) => {
        members.value = members.value.filter((m) => m.id !== user.id);
      })
      .listen('.plaza.message', (data: any) => {
        messages.value.push({
          id: data.timestamp || Date.now().toString(),
          sender_id: data.senderId,
          sender_name: data.senderName,
          body: data.body,
          time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
          is_me: data.senderId === props.currentUser.id,
        });
        scrollToBottom();
      })
      .listen('.plaza.room.updated', (event: any) => {
        if (event.action === 'member_kicked' && event.data?.userId === props.currentUser.id) {
          alert('You have been removed from this space by the host.');
          window.location.href = '/plaza';
        } else if (event.action === 'member_muted' && event.data?.userId === props.currentUser.id) {
          isMicMuted.value = true;
        } else if (event.action === 'member_unmuted' && event.data?.userId === props.currentUser.id) {
          isMicMuted.value = false;
        }
      });
  }
});

onUnmounted(() => {
  if (plazaChannel && typeof window !== 'undefined' && (window as any).Echo) {
    (window as any).Echo.leave(`plaza.${props.room.id}`);
  }
});

// Send Chat Message
const sendMessage = async () => {
  if (!chatInput.value.trim() || isSending.value) return;

  const text = chatInput.value;
  chatInput.value = '';
  isSending.value = true;
  errorMessage.value = null;

  try {
    const res = await fetch(`/plaza/rooms/${props.room.id}/message`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
      },
      body: JSON.stringify({ body: text }),
    });

    const data = await res.json();
    if (!res.ok) {
      errorMessage.value = data.errors?.body?.[0] || 'Message blocked by Sentinel';
      chatInput.value = text;
    } else if (data.success && data.message) {
      messages.value.push(data.message);
      scrollToBottom();
    }
  } catch (e) {
    console.error('Failed to post message', e);
  } finally {
    isSending.value = false;
  }
};

// Leave Room
const leaveRoom = async () => {
  try {
    await fetch(`/plaza/rooms/${props.room.id}/leave`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
      },
    });
  } catch (e) {
    // Ignore error on leave
  } finally {
    window.location.href = '/plaza';
  }
};

// Host Moderation Action (FR-31)
const moderateUser = async (targetUserId: number, action: 'mute' | 'unmute' | 'kick') => {
  try {
    await fetch(`/plaza/rooms/${props.room.id}/moderate`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
      },
      body: JSON.stringify({
        target_user_id: targetUserId,
        action: action,
      }),
    });
    if (action === 'kick') {
      members.value = members.value.filter((m) => m.id !== targetUserId);
    }
  } catch (e) {
    console.error('Failed to moderate member', e);
  }
};

// Generate 8 participant slots for FR-31 layout
const participantSlots = computed(() => {
  const slots: Array<{ isOccupied: boolean; member?: Member; slotIndex: number }> = [];
  const maxSlots = props.room.capacity || 8;

  for (let i = 0; i < maxSlots; i++) {
    const member = members.value[i];
    if (member) {
      slots.push({ isOccupied: true, member, slotIndex: i });
    } else {
      slots.push({ isOccupied: false, slotIndex: i });
    }
  }
  return slots;
});
</script>

<template>
  <Head :title="`${room.topic} - Plaza Space`" />

  <div class="h-screen bg-[#0F1020] text-slate-100 flex flex-col font-sans overflow-hidden">
    <!-- Ambient Light Effects -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden z-0">
      <div class="absolute top-0 right-1/4 w-96 h-96 rounded-full bg-[#8B7CFF]/10 blur-[120px]" />
      <div class="absolute bottom-0 left-1/4 w-96 h-96 rounded-full bg-[#5EEAD4]/10 blur-[120px]" />
    </div>

    <!-- Space Header -->
    <header class="relative z-10 border-b border-slate-800/80 bg-[#1A1B33]/80 backdrop-blur-md px-6 py-3 flex items-center justify-between">
      <div class="flex items-center gap-4">
        <button
          @click="leaveRoom"
          class="flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-semibold bg-white/5 hover:bg-white/10 text-slate-300 border border-white/10 transition-colors"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
          <span>Back to Plaza</span>
        </button>

        <div class="h-4 w-px bg-slate-800" />

        <div class="flex items-center gap-3">
          <BrandLogo size="xs" :animated="false" />
          <div>
            <div class="flex items-center gap-2">
              <h1 class="text-sm font-bold text-white tracking-wide truncate max-w-sm">
                {{ room.topic }}
              </h1>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-[#8B7CFF]/20 text-[#8B7CFF] border border-[#8B7CFF]/30">
                {{ room.vibe }}
              </span>
            </div>
            <p v-if="room.description" class="text-[11px] text-slate-400 truncate max-w-md">
              {{ room.description }}
            </p>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <!-- Live Participants Counter -->
        <div class="flex items-center gap-2 px-3 py-1 rounded-full bg-white/5 border border-white/10 text-xs font-medium text-slate-300">
          <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" />
          <span>{{ members.length }}/{{ room.capacity }} peers</span>
        </div>

        <!-- Leave Room -->
        <button
          @click="leaveRoom"
          class="px-4 py-1.5 rounded-xl text-xs font-semibold bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 border border-rose-500/30 transition-colors"
        >
          Leave Quietly
        </button>
      </div>
    </header>

    <!-- Space Content: 8-Participant Audio/Video Grid + Live Chat -->
    <div class="relative z-10 flex-1 flex flex-col lg:flex-row overflow-hidden">
      
      <!-- Left: Up to 8 Participant Plaza Grid (FR-31) -->
      <section class="flex-1 p-6 flex flex-col justify-between overflow-y-auto">
        <div>
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
              <span class="text-xs uppercase tracking-wider font-semibold text-slate-400">Plaza Circle (Max 8)</span>
              <span class="text-[11px] text-slate-500">• Spatial Audio Room</span>
            </div>
            <div v-if="currentUser.isHost" class="text-xs text-[#5EEAD4] font-medium flex items-center gap-1.5">
              <span>Host Controls Active</span>
            </div>
          </div>

          <!-- 8 Slots Grid (4x2 on large screens, 2x4 on mobile) -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div
              v-for="slot in participantSlots"
              :key="slot.slotIndex"
              class="aspect-square rounded-3xl p-4 flex flex-col items-center justify-center relative transition-all duration-300 overflow-hidden"
              :class="slot.isOccupied
                ? 'bg-gradient-to-b from-[#1A1B33] to-[#0F1020] border border-[#8B7CFF]/30 shadow-lg shadow-black/40'
                : 'bg-white/[0.02] border border-dashed border-white/10 text-slate-600'"
            >
              <!-- Occupied Slot -->
              <template v-if="slot.isOccupied && slot.member">
                <!-- Avatar Orb with Breathing Wave -->
                <div class="relative mb-3">
                  <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-gradient-to-tr from-[#8B7CFF] to-[#5EEAD4] p-0.5 shadow-md flex items-center justify-center">
                    <div class="w-full h-full rounded-full bg-[#0F1020] flex items-center justify-center text-xl font-extrabold text-white">
                      {{ slot.member.name.charAt(0) }}
                    </div>
                  </div>

                  <!-- Speaking / Active Ring -->
                  <div
                    v-if="!slot.member.is_muted"
                    class="absolute inset-0 rounded-full border-2 border-[#5EEAD4] animate-ping opacity-30 pointer-events-none"
                  />

                  <!-- Mute Badge -->
                  <div
                    v-if="slot.member.is_muted"
                    class="absolute -bottom-1 -right-1 w-6 h-6 rounded-full bg-rose-500/90 text-white flex items-center justify-center text-xs shadow-md"
                  >
                    🔇
                  </div>
                </div>

                <!-- Name & Role -->
                <div class="text-center space-y-0.5 max-w-full px-2">
                  <div class="text-xs sm:text-sm font-bold text-white truncate flex items-center justify-center gap-1">
                    <span>{{ slot.member.name }}</span>
                    <span v-if="slot.member.is_self" class="text-[10px] text-slate-400 font-normal">(you)</span>
                  </div>

                  <div class="flex items-center justify-center gap-1">
                    <span
                      class="px-2 py-0.5 rounded-full text-[10px] font-semibold"
                      :class="slot.member.is_host
                        ? 'bg-[#8B7CFF]/20 text-[#8B7CFF] border border-[#8B7CFF]/40'
                        : 'bg-white/10 text-slate-400'"
                    >
                      {{ slot.member.is_host ? 'Host' : slot.member.role }}
                    </span>
                  </div>
                </div>

                <!-- Host Controls on Member (FR-31) -->
                <div
                  v-if="currentUser.isHost && !slot.member.is_self"
                  class="mt-2 flex items-center gap-1.5 opacity-80 hover:opacity-100 transition-opacity"
                >
                  <button
                    @click="moderateUser(slot.member.id, slot.member.is_muted ? 'unmute' : 'mute')"
                    class="px-2 py-0.5 rounded-md text-[10px] font-medium bg-white/10 hover:bg-white/20 text-slate-300"
                  >
                    {{ slot.member.is_muted ? 'Unmute' : 'Mute' }}
                  </button>
                  <button
                    @click="moderateUser(slot.member.id, 'kick')"
                    class="px-2 py-0.5 rounded-md text-[10px] font-medium bg-rose-500/20 hover:bg-rose-500/30 text-rose-300"
                  >
                    Kick
                  </button>
                </div>
              </template>

              <!-- Empty Slot -->
              <template v-else>
                <div class="w-12 h-12 rounded-full border border-dashed border-slate-700 flex items-center justify-center text-slate-600 mb-2">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                  </svg>
                </div>
                <span class="text-[11px] text-slate-500 font-medium">Open Seat</span>
              </template>
            </div>
          </div>
        </div>

        <!-- Space Bottom Controls -->
        <div class="pt-6 border-t border-slate-800/80 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <button
              @click="isMicMuted = !isMicMuted"
              class="px-4 py-2 rounded-2xl text-xs font-semibold transition-all flex items-center gap-2 border"
              :class="isMicMuted
                ? 'bg-rose-500/20 border-rose-500/40 text-rose-300'
                : 'bg-emerald-500/20 border-emerald-500/40 text-emerald-300'"
            >
              <span>{{ isMicMuted ? '🔇 Mic Muted' : '🎙️ Mic Active' }}</span>
            </button>
          </div>

          <div class="text-xs text-slate-400">
            Enjoying this conversation? You can exchange Keeps in your Circle.
          </div>
        </div>
      </section>

      <!-- Right: Plaza Group Chat & Sentinel Moderation -->
      <aside class="w-full lg:w-96 border-t lg:border-t-0 lg:border-l border-slate-800/80 bg-[#1A1B33]/40 flex flex-col justify-between">
        <!-- Chat Header -->
        <div class="px-5 py-3 border-b border-slate-800/80 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <div class="w-2 h-2 rounded-full bg-[#8B7CFF]" />
            <h2 class="text-xs font-bold text-white uppercase tracking-wider">Group Chat</h2>
          </div>
          <span class="text-[11px] text-slate-400">Ephemeral room</span>
        </div>

        <!-- Chat Stream -->
        <div ref="chatContainer" class="flex-1 p-4 overflow-y-auto space-y-3">
          <div
            v-for="msg in messages"
            :key="msg.id"
            class="flex flex-col space-y-1"
            :class="msg.is_me ? 'items-end' : 'items-start'"
          >
            <div class="flex items-center gap-2 text-[10px] text-slate-400 px-1">
              <span class="font-semibold text-slate-300">{{ msg.sender_name }}</span>
              <span>{{ msg.time }}</span>
            </div>
            <div
              class="px-3.5 py-2 rounded-2xl text-xs max-w-[85%] leading-relaxed"
              :class="msg.is_me
                ? 'bg-[#8B7CFF] text-white rounded-br-none'
                : 'bg-white/10 text-slate-200 rounded-bl-none border border-white/5'"
            >
              {{ msg.body }}
            </div>
          </div>

          <div v-if="messages.length === 0" class="h-full flex items-center justify-center text-xs text-slate-500 text-center py-12">
            Say something warm or ask the room a question...
          </div>
        </div>

        <!-- Error Feedback -->
        <div v-if="errorMessage" class="px-4 py-2 bg-rose-500/20 border-t border-rose-500/40 text-xs text-rose-300">
          ⚠️ {{ errorMessage }}
        </div>

        <!-- Chat Input Form -->
        <form @submit.prevent="sendMessage" class="p-3 border-t border-slate-800/80 bg-[#1A1B33]/80">
          <div class="flex items-center gap-2">
            <input
              v-model="chatInput"
              type="text"
              maxlength="500"
              placeholder="Send a mindful thought to the room..."
              class="flex-1 rounded-xl bg-[#0F1020] border border-slate-700/80 px-3.5 py-2 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-[#8B7CFF]"
            />
            <button
              type="submit"
              :disabled="!chatInput.trim() || isSending"
              class="px-3.5 py-2 rounded-xl text-xs font-semibold bg-[#8B7CFF] text-white disabled:opacity-40 hover:bg-[#7967FA] transition-colors"
            >
              Send
            </button>
          </div>
          <div class="text-[10px] text-slate-500 mt-2 text-center flex items-center justify-center gap-1">
            <span>🛡️ Protected by Sentinel AI moderation</span>
          </div>
        </form>
      </aside>

    </div>
  </div>
</template>
