import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/js/app.js'],
      refresh: true,
    }),
    vue(),
  ],
  build: {
    sourcemap: false,
    chunkSizeWarningLimit: 1000,
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (id.includes('node_modules')) {
            if (id.includes('vue') || id.includes('@vue') || id.includes('@inertiajs')) return 'vendor-vue';
            if (id.includes('primevue') || id.includes('@primevue') || id.includes('primeicons')) return 'vendor-primevue';
            if (id.includes('chart.js')) return 'vendor-chart';
            if (id.includes('axios')) return 'vendor-axios';
            return 'vendor';
          }
        },
      },
    },
  },
});
