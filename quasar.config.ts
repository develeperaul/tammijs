import { defineConfig } from '#q-app/wrappers';
import { config } from 'dotenv';
config();
export default defineConfig((/* ctx */) => {

  return {
    // Подключаем TypeScript
    supportTS: {
      tsCheckerConfig: {
        eslint: {
          enabled: true,
          files: './src/**/*.{ts,tsx,js,jsx,vue}',
        },
      },
    },

    // Boot файлы
    boot: [
      'axios',
    ],

    // CSS
    css: [
      'app.scss',
    ],

    // Внешние зависимости
    extras: [
      'roboto-font', // шрифт
      'material-icons', // иконки
    ],

    // Сборка
    build: {
      target: {
        browser: ['es2019', 'edge88', 'firefox78', 'chrome87', 'safari13.1'],
        node: 'node20',
      },

      vueRouterMode: 'history', // или 'hash'

      // Переменные окружения
      env: {
        API_URL: process.env.API_URL || '/api',
        API_TOKEN: process.env.API_TOKEN || '',
        APP_NAME: JSON.stringify('Food CRM'),
        APP_VERSION: JSON.stringify(process.env.npm_package_version),
      },

      // Дополнительные настройки Vite
      extendViteConf(viteConf) {
        // Алиасы
        viteConf.resolve = viteConf.resolve || {};
        viteConf.resolve.alias = {
          ...viteConf.resolve.alias,
          '@': '/src',
          'components': '/src/components',
          'composables': '/src/composables',
          'types': '/src/types',
          'services': '/src/services',
        };
      },
    },

    // DevServer
    devServer: {
      open: true, // открывать браузер
      // port: 9000,
      proxy: {
        // Прокси для API
        '/api': {
          target: 'https://tammi.2apps.ru/',
          changeOrigin: true,
          // rewrite: (path) => path.replace(/^\/api/, '')
        },
      },
    },

    // Настройка фреймворка
    framework: {
      config: {
        notify: {}, // конфиг уведомлений
        loading: {}, // конфиг загрузки
        // dark: 'auto', // авто-темная тема
      },

      // Плагины Quasar
      plugins: [
        'Notify',
        'Dialog',
        'Loading',
        'LocalStorage',
        'SessionStorage',
      ],

      // Иконки
      iconSet: 'material-icons',
      lang: 'ru', // русский язык
    },

    // Анимации
    animations: 'all', // все анимации

    // PWA (если нужно)
    pwa: {
      workboxOptions: {
        skipWaiting: true,
        clientsClaim: true,
      },
      manifest: {
        name: 'Food CRM',
        short_name: 'FoodCRM',
        description: 'Система учета для общепита',
        display: 'standalone',
        orientation: 'portrait',
        background_color: '#ffffff',
        theme_color: '#027be3',
        icons: [
          {
            src: 'icons/icon-128x128.png',
            sizes: '128x128',
            type: 'image/png',
          },
          {
            src: 'icons/icon-192x192.png',
            sizes: '192x192',
            type: 'image/png',
          },
          {
            src: 'icons/icon-256x256.png',
            sizes: '256x256',
            type: 'image/png',
          },
          {
            src: 'icons/icon-384x384.png',
            sizes: '384x384',
            type: 'image/png',
          },
          {
            src: 'icons/icon-512x512.png',
            sizes: '512x512',
            type: 'image/png',
          },
        ],
      },
    },

    // Electron (если нужно)
    electron: {
      bundler: 'builder', // или 'packager'
      builder: {
        appId: 'food-crm',
        productName: 'Food CRM',
        directories: {
          output: 'dist/electron',
        },
      },
    },

    // Capacitor (мобильное приложение)
    capacitor: {
      hideSplashscreen: true,
    },

    // SSR (если нужно)
    ssr: {
      prodPort: 3000,
      middlewares: ['render'],
      pwa: false,
    },
  };
});
