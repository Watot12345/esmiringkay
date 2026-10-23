<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  animated?: boolean;
  size?: 'sm' | 'md' | 'lg' | 'hero';
  showWordmark?: boolean;
  speed?: number;
}

const props = withDefaults(defineProps<Props>(), {
  animated: true,
  size: 'md',
  showWordmark: true,
  speed: 1,
});

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'sm':
      return 'w-24';
    case 'md':
      return 'w-44';
    case 'lg':
      return 'w-72';
    case 'hero':
      return 'w-full max-w-[480px]';
    default:
      return 'w-44';
  }
});

const animDuration = computed(() => `${3.6 / props.speed}s`);
</script>

<template>
  <div :class="['relative select-none flex flex-col items-center', sizeClasses]">
    <svg
      viewBox="0 0 500 360"
      class="w-full h-auto overflow-visible"
      xmlns="http://www.w3.org/2000/svg"
    >
      <defs>
        <!-- Left Bubble (Purple) Gradient -->
        <linearGradient id="emiringkayPurpleGrad" x1="0.2" y1="0" x2="0.8" y2="1">
          <stop offset="0%" stop-color="#9E8DFF" />
          <stop offset="50%" stop-color="#8B7CFF" />
          <stop offset="100%" stop-color="#6C5CE7" />
        </linearGradient>

        <!-- Right Bubble (Coral) Gradient -->
        <linearGradient id="emiringkayCoralGrad" x1="0.8" y1="0" x2="0.2" y2="1">
          <stop offset="0%" stop-color="#FFA89B" />
          <stop offset="50%" stop-color="#FF8A7A" />
          <stop offset="100%" stop-color="#F2685A" />
        </linearGradient>

        <!-- Intersection Lens (Mint) Gradient -->
        <linearGradient id="emiringkayMintGrad" x1="0.5" y1="0" x2="0.5" y2="1">
          <stop offset="0%" stop-color="#7EF2DF" />
          <stop offset="50%" stop-color="#5EEAD4" />
          <stop offset="100%" stop-color="#36D3B9" />
        </linearGradient>

        <!-- 3D Orb on 'i' letters -->
        <radialGradient id="orbPurple" cx="35%" cy="30%" r="65%">
          <stop offset="0%" stop-color="#DDD8FF" />
          <stop offset="45%" stop-color="#8B7CFF" />
          <stop offset="100%" stop-color="#5646C7" />
        </radialGradient>
        <radialGradient id="orbCoral" cx="35%" cy="30%" r="65%">
          <stop offset="0%" stop-color="#FFE0DC" />
          <stop offset="45%" stop-color="#FF8A7A" />
          <stop offset="100%" stop-color="#D94B3C" />
        </radialGradient>
      </defs>

      <!-- Ambient Twinkle Stars -->
      <g v-if="animated" class="ambient-stars">
        <path
          class="star-twinkle-1"
          fill="#FFD36E"
          d="M 140 110 Q 140 118 132 118 Q 140 118 140 126 Q 140 118 148 118 Q 140 118 140 110 Z"
        />
        <circle class="star-twinkle-2" cx="190" cy="80" r="4.5" fill="#5EEAD4" />
        <circle class="star-twinkle-3" cx="360" cy="88" r="6" fill="#FFD36E" />
        <path
          class="star-twinkle-1"
          fill="#5EEAD4"
          d="M 395 125 Q 395 131 389 131 Q 395 131 395 137 Q 395 131 401 131 Q 395 131 395 125 Z"
        />
      </g>

      <!-- Little Heart floating up when bubbles kiss -->
      <g v-if="animated" class="bubble-heart" :style="{ animationDuration: animDuration }">
        <path
          d="M 250 82 C 250 78, 245 72, 238 72 C 229 72, 226 80, 226 86 C 226 96, 238 106, 250 114 C 262 106, 274 96, 274 86 C 274 80, 271 72, 262 72 C 255 72, 250 78, 250 82 Z"
          fill="#FF8A7A"
          opacity="0.95"
          filter="drop-shadow(0 0 8px rgba(255,138,122,0.8))"
        />
      </g>

      <!-- Bubble Left (Purple) -->
      <g
        :class="{ 'kiss-left': animated }"
        :style="{ animationDuration: animDuration }"
      >
        <path
          d="M 215 100 
             C 260 100, 290 135, 290 178 
             C 290 220, 255 255, 212 255 
             C 192 255, 178 248, 168 240
             L 138 274
             C 134 278, 128 274, 131 268
             L 145 224
             C 138 210, 134 195, 134 178
             C 134 135, 170 100, 215 100 Z"
          fill="url(#emiringkayPurpleGrad)"
        />
        <path
          d="M 160 145 C 172 120, 205 108, 235 112 C 215 110, 180 120, 165 140 Z"
          fill="#FFFFFF"
          opacity="0.22"
        />
      </g>

      <!-- Bubble Right (Coral) -->
      <g
        :class="{ 'kiss-right': animated }"
        :style="{ animationDuration: animDuration }"
      >
        <path
          d="M 285 100 
             C 330 100, 366 135, 366 178 
             C 366 195, 362 210, 355 224
             L 369 268
             C 372 274, 366 278, 362 274
             L 332 240
             C 322 248, 308 255, 288 255
             C 245 255, 210 220, 210 178
             C 210 135, 240 100, 285 100 Z"
          fill="url(#emiringkayCoralGrad)"
        />
        <path
          d="M 335 145 C 323 120, 290 108, 260 112 C 280 110, 315 120, 330 140 Z"
          fill="#FFFFFF"
          opacity="0.22"
        />
      </g>

      <!-- Intersection Lens (Mint) -->
      <g
        :class="{ 'kiss-lens': animated }"
        :style="{ animationDuration: animDuration }"
      >
        <path
          d="M 250 106 
             C 272 124, 285 150, 285 178 
             C 285 206, 272 232, 250 250 
             C 228 232, 215 206, 215 178 
             C 215 150, 228 124, 250 106 Z"
          fill="url(#emiringkayMintGrad)"
        />
        <ellipse cx="250" cy="135" rx="14" ry="7" fill="#FFFFFF" opacity="0.3" />
      </g>

      <!-- Smile in Intersection -->
      <g
        :class="{ 'kiss-smile': animated }"
        :style="{ animationDuration: animDuration }"
      >
        <path
          d="M 235 178 Q 250 196 265 178"
          fill="none"
          stroke="currentColor"
          class="text-bg dark:text-[#0F1020]"
          stroke-width="7"
          stroke-linecap="round"
        />
      </g>

      <!-- Wordmark -->
      <g v-if="showWordmark" transform="translate(0, 320)">
        <text
          x="250"
          y="0"
          text-anchor="middle"
          class="font-display text-[46px] font-bold fill-current text-white tracking-tight"
        >
          Emiringkay
        </text>
        <!-- Dot spheres on 'i's -->
        <circle cx="203" cy="-38" r="6" fill="url(#orbPurple)" filter="drop-shadow(0 0 6px rgba(139,124,255,0.8))" />
        <circle cx="272" cy="-38" r="6" fill="url(#orbCoral)" filter="drop-shadow(0 0 6px rgba(255,138,122,0.8))" />
      </g>
    </svg>
  </div>
</template>

<style scoped>
/* Motion bezier curve strictly from 07-theme.md */
.kiss-left {
  animation: kissLeftAnim 3.6s cubic-bezier(0.22, 1, 0.36, 1) infinite;
  transform-origin: 215px 175px;
}

.kiss-right {
  animation: kissRightAnim 3.6s cubic-bezier(0.22, 1, 0.36, 1) infinite;
  transform-origin: 285px 175px;
}

.kiss-lens {
  animation: lensBloomAnim 3.6s cubic-bezier(0.22, 1, 0.36, 1) infinite;
  transform-origin: 250px 175px;
}

.kiss-smile {
  animation: smilePopAnim 3.6s cubic-bezier(0.22, 1, 0.36, 1) infinite;
  transform-origin: 250px 185px;
}

.bubble-heart {
  animation: heartFloatAnim 3.6s cubic-bezier(0.22, 1, 0.36, 1) infinite;
  transform-origin: 250px 85px;
}

@keyframes kissLeftAnim {
  0%, 15% { transform: translate(-32px, 0); }
  38% { transform: translate(0px, 0) scale(1.03, 0.97); }
  45% { transform: translate(3px, 0) scale(0.96, 1.04) rotate(-2deg); }
  55% { transform: translate(0px, 0); }
  75%, 100% { transform: translate(-32px, 0); }
}

@keyframes kissRightAnim {
  0%, 15% { transform: translate(32px, 0); }
  38% { transform: translate(0px, 0) scale(1.03, 0.97); }
  45% { transform: translate(-3px, 0) scale(0.96, 1.04) rotate(2deg); }
  55% { transform: translate(0px, 0); }
  75%, 100% { transform: translate(32px, 0); }
}

@keyframes lensBloomAnim {
  0%, 15% { opacity: 0.15; transform: scale(0.7); }
  38% { opacity: 0.9; transform: scale(1.02); }
  45% {
    opacity: 1;
    transform: scale(1.06);
    filter: drop-shadow(0 0 16px rgba(94, 234, 212, 0.8));
  }
  55% { opacity: 0.95; transform: scale(1); }
  75%, 100% { opacity: 0.15; transform: scale(0.7); }
}

@keyframes smilePopAnim {
  0%, 15% { opacity: 0; transform: scale(0.5); }
  35% { opacity: 0.7; transform: scale(0.9); }
  45% { opacity: 1; transform: scale(1.12); }
  55% { opacity: 1; transform: scale(1); }
  75%, 100% { opacity: 0; transform: scale(0.5); }
}

@keyframes heartFloatAnim {
  0%, 35% { opacity: 0; transform: translateY(10px) scale(0.4); }
  45% { opacity: 1; transform: translateY(-16px) scale(1.1); }
  65% { opacity: 0.7; transform: translateY(-38px) scale(1.2); }
  80%, 100% { opacity: 0; transform: translateY(-50px) scale(0.9); }
}

@keyframes twinkleAnim {
  0%, 100% { opacity: 0.3; transform: scale(0.85); }
  50% { opacity: 1; transform: scale(1.15) rotate(15deg); }
}
.star-twinkle-1 { animation: twinkleAnim 3s ease-in-out infinite; }
.star-twinkle-2 { animation: twinkleAnim 2.4s ease-in-out 0.6s infinite; }
.star-twinkle-3 { animation: twinkleAnim 3.6s ease-in-out 1.2s infinite; }

@media (prefers-reduced-motion: reduce) {
  .kiss-left, .kiss-right, .kiss-lens, .kiss-smile, .bubble-heart, .star-twinkle-1, .star-twinkle-2, .star-twinkle-3 {
    animation: none !important;
  }
}
</style>
