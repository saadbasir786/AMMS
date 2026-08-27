import path from 'path'
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      '@': path.resolve(import.meta.dirname, './src'),
    },
  },
  server: {
    port: 5173,
    allowedHosts: ['5174-iyq2ygwuj8dok8nv991q6-67ff26ad.us3.manus.computer'],
    proxy: {
      '/api': 'http://127.0.0.1:8000',
      '/storage': 'http://127.0.0.1:8000',
    },
  },
  build: {
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (!id.includes('node_modules')) return undefined
          if (id.includes('react-dom') || id.includes('react-router-dom') || id.includes('/react/')) return 'react'
          if (id.includes('lucide-react') || id.includes('radix-ui')) return 'ui'
          return 'vendor'
        },
      },
    },
  },
})
