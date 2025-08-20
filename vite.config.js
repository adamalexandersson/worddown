import { defineConfig } from 'vite';
import { resolve } from 'path';
import { viteStaticCopy } from 'vite-plugin-static-copy';
import fs from 'fs';
import path from 'path';
import tailwindcss from '@tailwindcss/vite';
import react from "@vitejs/plugin-react"

function addIconsToManifest() {
  return {
    name: 'add-icons-to-manifest',
    closeBundle: async () => {
      const distDir = path.resolve(__dirname, 'dist');
      const iconsDir = path.join(distDir, 'icons');
      const manifestPath = path.join(distDir, '.vite/manifest.json');

      if (!fs.existsSync(iconsDir) || !fs.existsSync(manifestPath)) return;

      const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf-8'));
      const iconFiles = fs.readdirSync(iconsDir);

      iconFiles.forEach(file => {
        if (file.endsWith('.svg')) {
          // Key as used in your PHP: 'icons/worddown.svg'
          manifest[`resources/assets/icons/${file}`] = {
            file: `icons/${file}`,
            src: `resources/assets/icons/${file}`,
            isAsset: true
          };
        }
      });

      fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));
    }
  };
}

export default defineConfig({
  build: {
    manifest: true,
    outDir: 'dist',
    rollupOptions: {
      input: {
        admin: resolve(__dirname, 'resources/assets/js/admin.tsx'),
      },
      output: {
        entryFileNames: 'js/[name].[hash].js',
        chunkFileNames: 'js/[name].[hash].js',
        assetFileNames: ({name}) => {
          if (/\.(css)$/.test(name ?? '')) {
            return 'css/[name].[hash][extname]';
          }
          return 'assets/[name].[hash][extname]';
        }
      }
    }
  },
  plugins: [
    react(),
    tailwindcss(),
    viteStaticCopy({
      targets: [
        {
          src: 'resources/assets/icons',
          dest: ''
        }
      ]
    }),
    addIconsToManifest()
  ],
  resolve: {
    extensions: ['.mjs', '.js', '.ts', '.jsx', '.tsx', '.json'],
    alias: {
      '@': path.resolve(__dirname, 'resources/assets/js'),
      '@icons': path.resolve(__dirname, 'resources/assets/icons'),
    },
  },
}); 