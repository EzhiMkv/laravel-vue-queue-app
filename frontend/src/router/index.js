import { createRouter, createWebHistory } from 'vue-router'
import LoginView from '@/views/LoginView.vue'
import DashView from '@/views/DashView.vue'
import AdminDashboard from '@/views/admin/AdminDashboard.vue'
import OperatorDashboard from '@/views/operator/OperatorDashboard.vue'
import ClientDashboard from '@/views/client/ClientDashboard.vue'
import axios from 'axios'

const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes: [
        {
            path: '/login',
            name: 'login',
            component: LoginView,
            meta: { requiresAuth: false }
        },
        {
            path: '/',
            name: 'dash',
            component: DashView,
            meta: { requiresAuth: true }
        },
        {
            path: '/admin',
            name: 'admin',
            component: AdminDashboard,
            meta: { requiresAuth: true, role: 'admin' }
        },
        {
            path: '/operator',
            name: 'operator',
            component: OperatorDashboard,
            meta: { requiresAuth: true, role: 'operator' }
        },
        {
            path: '/client',
            name: 'client',
            component: ClientDashboard,
            meta: { requiresAuth: true, role: 'client' }
        }
    ]
})

// Навигационный хук для проверки аутентификации и ролей
router.beforeEach(async (to, from, next) => {
    // Если маршрут не требует аутентификации, пропускаем
    if (!to.meta.requiresAuth) {
        return next()
    }
    
    try {
        // Проверяем, авторизован ли пользователь
        const response = await axios.get('/api/user')
        const user = response.data
        
        // Если требуется определенная роль
        if (to.meta.role && user.role.slug !== to.meta.role) {
            // Перенаправляем на соответствующий дашборд в зависимости от роли
            switch (user.role.slug) {
                case 'admin':
                    return next({ name: 'admin' })
                case 'operator':
                    return next({ name: 'operator' })
                case 'client':
                    return next({ name: 'client' })
                default:
                    return next({ name: 'login' })
            }
        }
        
        // Если пользователь авторизован и имеет нужную роль (или роль не требуется)
        return next()
    } catch (error) {
        // Если не авторизован, перенаправляем на страницу входа
        return next({ name: 'login' })
    }
})

export default router
