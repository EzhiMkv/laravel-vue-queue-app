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
            localStorage.setItem('token', response.data.token);
            axios.defaults.headers.common['Authorization'] = `Bearer ${response.data.token}`;
            router.push({ name: 'dash' });
        })
        .catch(error => {
            if (error.response.status === 422) {
                message.value = error.response.data.message;
            }
        })
        .finally();
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
