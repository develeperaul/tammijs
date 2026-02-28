import { boot } from 'quasar/wrappers';
import axios from 'axios';

const api = axios.create({
  baseURL: process.env.API_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Добавляем токен в заголовки каждого запроса
api.interceptors.request.use((config) => {
  console.log(process.env);

  const token = process.env.API_TOKEN;
  console.log(token)
  if (token) {
    config.headers['X-API-Key'] = token;
  }
  return config;
});

export default boot(({ app }) => {
  app.config.globalProperties.$api = api;
});

export { api };
