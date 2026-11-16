import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { defineConfig } from 'vite'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    proxy: {
      '/bioseguridad/api': {
        target: 'http://localhost',
        changeOrigin: true,
      },
    },
  },
  base: '/bioseguridad/web/',
})
