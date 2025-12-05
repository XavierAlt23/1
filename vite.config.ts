import path from 'path';
import { defineConfig } from 'vite';

export default defineConfig(() => ({
  server: {
    port: 3000,
    host: '0.0.0.0',
    proxy: {
      '/php': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      }
    }
  },
  plugins: [],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, '.'),
      // Permitir importar 'supabaseClient' como bare import desde cualquier ruta
      'supabaseClient': path.resolve(__dirname, 'supabaseClient.js'),
    }
  }
}));
