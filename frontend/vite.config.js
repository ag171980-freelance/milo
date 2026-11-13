import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// En desarrollo, /api va al PHP local (puerto 8081). En producción lo
// resuelve el proxy de Netlify (ver netlify.toml), así el front y la API
// comparten origen y no hace falta CORS.
export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    host: true,
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8081',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api/, ''),
      },
    },
  },
});
