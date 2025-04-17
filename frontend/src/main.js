import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import axios from 'axios';

window.axios = axios;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.baseURL = 'http://0.0.0.0:8000/api';

// Проверяем, есть ли токен и не undefined ли он
const token = localStorage.getItem('token');
if (token && token !== 'undefined') {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
} else {
    // Если токена нет или он undefined, устанавливаем пустой Bearer
    axios.defaults.headers.common['Authorization'] = 'Bearer ';
    // Можно также удалить невалидный токен
    if (token === 'undefined') {
        localStorage.removeItem('token');
    }
}

axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            localStorage.removeItem('token');
            axios.defaults.headers.common['Authorization'] = 'Bearer ';
            router.push({ name: 'login' });
        }
        return Promise.reject(error);
    }
);

const app = createApp(App)

app.use(createPinia())
app.use(router)

app.mount('#app')
