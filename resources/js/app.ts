import '../css/app.css';
import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { configureEcho, echo } from '@laravel/echo-vue';
import Pusher from 'pusher-js';

// Ensure Pusher is attached to window for Echo/Reverb
if (typeof window !== 'undefined') {
  (window as any).Pusher = Pusher;
}

const isHttps = typeof window !== 'undefined' && window.location.protocol === 'https:';
const hostname = typeof window !== 'undefined' ? window.location.hostname : 'localhost';
const isDev = hostname === 'localhost' || hostname === '127.0.0.1';
const wsPort = isDev ? 8080 : (isHttps ? 443 : (window.location.port ? Number(window.location.port) : 80));

configureEcho({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY || 'qsgvmwadpmdqqovwougw',
  wsHost: hostname,
  wsPort: wsPort,
  wssPort: wsPort,
  forceTLS: isHttps,
  enabledTransports: ['ws', 'wss'],
});

if (typeof window !== 'undefined') {
  try {
    (window as any).Echo = echo();
  } catch (e) {
    console.warn('Echo initialization deferred:', e);
  }
}

createInertiaApp({
  title: (title) => (title ? `${title} - Emiringkay` : 'Emiringkay'),
  resolve: (name) =>
    resolvePageComponent(
      `./Pages/${name}.vue`,
      import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
    ),
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .mount(el);
  },
});
