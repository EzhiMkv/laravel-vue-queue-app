<script setup>
import { ref } from 'vue'
import router from '@/router/index.js'

const email = ref(null)
const password = ref(null)
const message = ref(null)
const auth = () =>{
    message.value = '';
    axios.post('login', { email:email.value, password:password.value })
        .then(response => {
            console.log('Ответ от сервера:', response.data);
            
            // Проверяем структуру ответа и извлекаем токен
            const tokenData = response.data.data; // Токен находится в data.data
            
            if (tokenData && tokenData.token) {
                console.log('Токен получен:', tokenData.token);
                localStorage.setItem('token', tokenData.token);
                axios.defaults.headers.common['Authorization'] = `Bearer ${tokenData.token}`;
                
                // Получаем информацию о пользователе и перенаправляем на нужный дашборд
                axios.get('/user')
                    .then(userResponse => {
                        const user = userResponse.data;
                        console.log('Данные пользователя:', user);
                        
                        // Перенаправляем на соответствующий дашборд в зависимости от роли
                        switch (user.role.slug) {
                            case 'admin':
                                router.push({ name: 'admin' });
                                break;
                            case 'operator':
                                router.push({ name: 'operator' });
                                break;
                            case 'client':
                                router.push({ name: 'client' });
                                break;
                            default:
                                router.push({ name: 'dash' }); // Дефолтный дашборд, если роль не определена
                        }
                    })
                    .catch(error => {
                        console.error('Ошибка получения данных пользователя:', error);
                        router.push({ name: 'dash' }); // Дефолтный дашборд в случае ошибки
                    });
            } else {
                console.error('Токен не получен или невалидный:', response.data);
                message.value = 'Ошибка авторизации: невалидный токен';
            }
        })
        .catch(error => {
            console.error('Ошибка авторизации:', error);
            if (error.response && error.response.status === 422) {
                message.value = error.response.data.message;
            } else {
                message.value = 'Ошибка авторизации. Проверьте логин и пароль.';
            }
        });
}
</script>

<template>
    <div class="flex min-h-full flex-col justify-center px-6 py-12 lg:px-8">
        <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-sm">
            <form class="space-y-6" @submit.prevent="auth" method="POST">
                <div>
                    <label for="email" class="block text-sm font-medium leading-6 text-gray-900">Логин</label>
                    <div class="mt-2">
                        <input v-model="email" id="email" name="email" autocomplete="login" required class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                    </div>
                </div>
                <div>
                    <div class="flex items-center justify-between">
                        <label for="password" class="block text-sm font-medium leading-6 text-gray-900">Пароль</label>
                    </div>
                    <div class="mt-2">
                        <input id="password" v-model="password" name="password" type="password" autocomplete="current-password" required class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                    </div>
                </div>
                <div v-if="message" class="text-red-500 text-sm mt-2">
                    {{ message }}
                </div>
                <div>
                    <button type="submit"
                            class="flex w-full justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                    >
                        Авторизация
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
