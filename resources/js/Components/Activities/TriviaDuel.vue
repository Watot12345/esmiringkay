<script setup lang="ts">
import { ref, onMounted } from 'vue';

interface Props {
  roomId: string;
}

const props = defineProps<Props>();

interface Question {
  id: number;
  question: string;
  options: string[];
  correct: number;
}

const currentQuestion = ref<Question | null>(null);
const selectedAnswer = ref<number | null>(null);
const isAnswered = ref(false);
const scores = ref({ you: 0, stranger: 0 });

onMounted(async () => {
  await fetchTriviaState();
});

async function fetchTriviaState() {
  try {
    const res = await fetch(`/room/${props.roomId}/activity/trivia`);
    const data = await res.json();
    if (data.state) {
      currentQuestion.value = data.state.current_question;
      scores.value = data.state.scores || { you: 0, stranger: 0 };
    }
  } catch (err) {
    console.error(err);
  }
}

async function handleSelect(index: number) {
  if (isAnswered.value || !currentQuestion.value) return;
  selectedAnswer.value = index;
  isAnswered.value = true;

  const isCorrect = index === currentQuestion.value.correct;
  if (isCorrect) {
    scores.value.you++;
  }

  try {
    await fetch(`/room/${props.roomId}/activity`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
      },
      body: JSON.stringify({
        kind: 'trivia',
        action: 'answer',
        payload: {
          sender: 'you',
          is_correct: isCorrect,
        },
      }),
    });
  } catch (err) {
    console.error(err);
  }
}

async function handleNextQuestion() {
  selectedAnswer.value = null;
  isAnswered.value = false;

  try {
    const res = await fetch(`/room/${props.roomId}/activity`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
      },
      body: JSON.stringify({
        kind: 'trivia',
        action: 'next_question',
      }),
    });
    const data = await res.json();
    if (data.state?.current_question) {
      currentQuestion.value = data.state.current_question;
    }
  } catch (err) {
    console.error(err);
  }
}
</script>

<template>
  <div class="w-full flex flex-col items-center bg-[#1A1B33]/90 backdrop-blur-md rounded-2xl border border-[#34376A]/60 p-4 shadow-xl">
    <!-- Duel Header & Score Counter -->
    <div class="w-full flex items-center justify-between pb-3 border-b border-[#34376A]/40 mb-3">
      <div class="flex items-center gap-1.5">
        <span class="text-sm">🎮</span>
        <span class="text-xs font-bold text-white tracking-wide">Trivia Duel</span>
      </div>

      <!-- Live Scoreboard -->
      <div class="flex items-center gap-3 bg-[#24264A] px-3 py-1 rounded-full border border-[#34376A]/60 text-xs">
        <span class="text-[#8B7CFF] font-bold">You: {{ scores.you }}</span>
        <span class="text-[#A9A6CC]/40">vs</span>
        <span class="text-gold font-bold">Stranger: {{ scores.stranger }}</span>
      </div>
    </div>

    <!-- Question Stage -->
    <div v-if="currentQuestion" class="w-full flex flex-col items-center text-center my-1">
      <span class="text-[10px] uppercase font-bold text-gold tracking-widest mb-1">Question</span>
      <h4 class="text-xs sm:text-sm font-semibold text-white px-2 leading-snug min-h-[38px] flex items-center justify-center">
        {{ currentQuestion.question }}
      </h4>

      <!-- 4 Answer Options -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 w-full mt-3">
        <button
          v-for="(option, idx) in currentQuestion.options"
          :key="idx"
          @click="handleSelect(idx)"
          :disabled="isAnswered"
          :class="[
            'p-2.5 rounded-xl border text-xs font-medium text-left transition-all duration-200 flex items-center justify-between',
            !isAnswered
              ? 'bg-[#24264A]/70 hover:bg-[#8B7CFF]/20 border-[#34376A]/60 hover:border-[#8B7CFF] text-[#F4F2FF] cursor-pointer'
              : idx === currentQuestion.correct
              ? 'bg-[#5EEAD4]/20 border-[#5EEAD4] text-[#5EEAD4] font-bold'
              : selectedAnswer === idx
              ? 'bg-danger/20 border-danger text-danger'
              : 'bg-[#24264A]/40 border-[#34376A]/30 text-[#A9A6CC]/50'
          ]"
        >
          <span>{{ option }}</span>
          <span v-if="isAnswered && idx === currentQuestion.correct">✓</span>
          <span v-else-if="isAnswered && selectedAnswer === idx">✕</span>
        </button>
      </div>

      <!-- Next Question Button -->
      <div class="w-full flex justify-end mt-4">
        <button
          @click="handleNextQuestion"
          class="px-3.5 py-1.5 rounded-full text-xs font-bold bg-[#8B7CFF] text-white hover:opacity-90 active:scale-95 transition shadow-sm flex items-center gap-1"
        >
          <span>Next Question</span>
          <span>➔</span>
        </button>
      </div>
    </div>

    <div v-else class="text-xs text-[#A9A6CC] py-6">
      Loading next trivia challenge...
    </div>
  </div>
</template>
