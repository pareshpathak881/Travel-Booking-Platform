import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
  plugins: [react()],

  build: {
    outDir: path.resolve(__dirname, 'dist'),
    emptyOutDir: true,

    rollupOptions: {
      external: ['react', 'react-dom'],
      input: path.resolve(__dirname, 'src/main.jsx'),
      output: {
        format: 'iife',
        entryFileNames: 'assets/js/app.min.js',
        chunkFileNames: 'assets/js/chunks/[name].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'assets/css/app.min.css';
          }
          return 'assets/[name][extname]';
        },
        globals: {
          react: 'React',
          'react-dom': 'ReactDOM',
        },
      },
    },

    target: 'es2020',
    minify: 'esbuild',
    sourcemap: false,
  },
});
