// vite.config.js
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'

export default defineConfig({
  plugins: [
    laravel({
      input: [
        'resources/css/filament/admin/custom.css', // 👈 importante
      ],
      refresh: true,
    }),
  ],
})
