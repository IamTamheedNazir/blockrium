import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/api': {
        target: 'https://maticlegend.com',
        changeOrigin: true,
        secure: true,
      },
    },
  },
  build: { sourcemap: false },
});
