<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';

interface Props {
  roomId: string;
}

const props = defineProps<Props>();

const canvasRef = ref<HTMLCanvasElement | null>(null);
const isDrawing = ref(false);
const activeColor = ref('#5EEAD4');
const brushSize = ref(3);

const colors = [
  { hex: '#5EEAD4', label: 'Mint' },
  { hex: '#FF8A7A', label: 'Coral' },
  { hex: '#FFD36E', label: 'Gold' },
  { hex: '#8B7CFF', label: 'Indigo' },
  { hex: '#FFFFFF', label: 'White' },
  { hex: '#0F1020', label: 'Eraser' },
];

let lastX = 0;
let lastY = 0;
let ctx: CanvasRenderingContext2D | null = null;

onMounted(() => {
  const canvas = canvasRef.value;
  if (!canvas) return;
  ctx = canvas.getContext('2d');
  if (!ctx) return;

  // Set display size matching container
  canvas.width = canvas.parentElement?.clientWidth || 360;
  canvas.height = 240;

  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';

  fetchInitialStrokes();
});

async function fetchInitialStrokes() {
  try {
    const res = await fetch(`/room/${props.roomId}/activity/doodle`);
    const data = await res.json();
    if (data.state?.strokes && ctx) {
      data.state.strokes.forEach((stroke: any) => {
        drawLine(stroke.x0, stroke.y0, stroke.x1, stroke.y1, stroke.color, stroke.size, false);
      });
    }
  } catch (err) {
    console.warn('Could not fetch doodle strokes', err);
  }
}

function startDrawing(e: MouseEvent | TouchEvent) {
  isDrawing.value = true;
  const pos = getPos(e);
  lastX = pos.x;
  lastY = pos.y;
}

function draw(e: MouseEvent | TouchEvent) {
  if (!isDrawing.value || !ctx) return;
  const pos = getPos(e);

  drawLine(lastX, lastY, pos.x, pos.y, activeColor.value, brushSize.value, true);

  lastX = pos.x;
  lastY = pos.y;
}

function stopDrawing() {
  isDrawing.value = false;
}

function getPos(e: MouseEvent | TouchEvent) {
  const canvas = canvasRef.value!;
  const rect = canvas.getBoundingClientRect();
  const clientX = 'touches' in e ? e.touches[0].clientX : e.clientX;
  const clientY = 'touches' in e ? e.touches[0].clientY : e.clientY;
  return {
    x: clientX - rect.left,
    y: clientY - rect.top,
  };
}

function drawLine(x0: number, y0: number, x1: number, y1: number, color: string, size: number, emit = false) {
  if (!ctx) return;
  ctx.beginPath();
  ctx.moveTo(x0, y0);
  ctx.lineTo(x1, y1);
  ctx.strokeStyle = color;
  ctx.lineWidth = size;
  ctx.stroke();
  ctx.closePath();

  if (emit) {
    // Relay stroke to room partner
    fetch(`/room/${props.roomId}/activity`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
      },
      body: JSON.stringify({
        kind: 'doodle',
        action: 'draw',
        payload: { stroke: { x0, y0, x1, y1, color, size } },
      }),
    }).catch(console.warn);
  }
}

async function clearCanvas() {
  if (!ctx || !canvasRef.value) return;
  ctx.clearRect(0, 0, canvasRef.value.width, canvasRef.value.height);

  try {
    await fetch(`/room/${props.roomId}/activity`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
      },
      body: JSON.stringify({
        kind: 'doodle',
        action: 'clear',
      }),
    });
  } catch (err) {
    console.error(err);
  }
}
</script>

<template>
  <div class="w-full flex flex-col items-center bg-[#1A1B33]/90 backdrop-blur-md rounded-2xl border border-[#34376A]/60 p-3 shadow-xl">
    <!-- Header Controls -->
    <div class="w-full flex items-center justify-between mb-2 px-1">
      <div class="flex items-center gap-1.5">
        <span class="text-sm">🎨</span>
        <span class="text-xs font-bold text-white tracking-wide">Shared Doodle Board</span>
      </div>

      <!-- Color picker -->
      <div class="flex items-center gap-1.5">
        <button
          v-for="c in colors"
          :key="c.hex"
          @click="activeColor = c.hex"
          :style="{ backgroundColor: c.hex }"
          :class="[
            'w-5 h-5 rounded-full border transition transform hover:scale-110',
            activeColor === c.hex ? 'border-white scale-125 shadow-[0_0_8px_white]' : 'border-transparent'
          ]"
          :title="c.label"
        ></button>
        <button
          @click="clearCanvas"
          class="ml-2 px-2 py-0.5 rounded-full text-[10px] bg-[#24264A] text-[#A9A6CC] hover:text-white border border-[#34376A] transition"
        >
          Clear
        </button>
      </div>
    </div>

    <!-- HTML5 Collaborative Canvas -->
    <div class="w-full bg-[#0F1020] rounded-xl overflow-hidden border border-[#34376A]/40 touch-none cursor-crosshair">
      <canvas
        ref="canvasRef"
        @mousedown="startDrawing"
        @mousemove="draw"
        @mouseup="stopDrawing"
        @mouseleave="stopDrawing"
        @touchstart.passive="startDrawing"
        @touchmove.passive="draw"
        @touchend="stopDrawing"
        class="w-full h-[240px] block"
      ></canvas>
    </div>

    <span class="text-[10px] text-[#A9A6CC]/60 mt-1.5">Draw together in real time</span>
  </div>
</template>
