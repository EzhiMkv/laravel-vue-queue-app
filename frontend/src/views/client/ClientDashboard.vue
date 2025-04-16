<template>
  <div class="client-dashboard">
    <h1 class="text-2xl font-bold mb-6">Личный кабинет клиента</h1>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
      <div class="bg-white rounded-lg shadow p-4">
        <h2 class="text-lg font-semibold mb-2">Активных очередей</h2>
        <div class="text-3xl font-bold">{{ activePositions.length }}</div>
        <div class="text-sm text-gray-500">Вы стоите в {{ activePositions.length }} очередях</div>
      </div>
      
      <div class="bg-white rounded-lg shadow p-4">
        <h2 class="text-lg font-semibold mb-2">Ближайшая очередь</h2>
        <div v-if="nextQueue">
          <div class="text-xl font-bold">{{ nextQueue.name }}</div>
          <div class="text-sm text-gray-500">
            Позиция: {{ nextQueue.position }}, примерное время: {{ formatTime(nextQueue.estimatedWaitTime) }}
          </div>
        </div>
        <div v-else class="text-gray-500">Нет активных очередей</div>
      </div>
      
      <div class="bg-white rounded-lg shadow p-4">
        <h2 class="text-lg font-semibold mb-2">Всего посещений</h2>
        <div class="text-3xl font-bold">{{ history.length }}</div>
        <div class="text-sm text-gray-500">За всё время</div>
      </div>
    </div>
    
    <div class="flex mb-6">
      <button 
        v-for="tab in tabs" 
        :key="tab.id"
        @click="activeTab = tab.id"
        :class="[
          'px-4 py-2 mr-2 rounded-t-lg',
          activeTab === tab.id 
            ? 'bg-white text-blue-600 font-semibold border-t border-l border-r' 
            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
        ]"
      >
        {{ tab.name }}
      </button>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
      <!-- Мои очереди -->
      <div v-if="activeTab === 'queues'">
        <div class="flex justify-between mb-4">
          <h2 class="text-xl font-semibold">Мои очереди</h2>
          <button 
            @click="showQueueSelector = true"
            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
          >
            Встать в очередь
          </button>
        </div>
        
        <div v-if="activePositions.length === 0" class="text-center py-8 text-gray-500">
          Вы не стоите ни в одной очереди
        </div>
        
        <div v-else class="grid grid-cols-1 gap-4">
          <div 
            v-for="position in activePositions" 
            :key="position.id"
            class="border rounded-lg p-4 hover:bg-gray-50"
          >
            <div class="flex justify-between items-start">
              <div>
                <h3 class="text-lg font-semibold">{{ position.queue.name }}</h3>
                <div class="text-sm text-gray-500">{{ position.queue.description }}</div>
              </div>
              <button 
                @click="leaveQueue(position.queue.id)"
                class="text-red-600 hover:text-red-800"
              >
                Выйти из очереди
              </button>
            </div>
            
            <div class="mt-4 grid grid-cols-3 gap-4 text-center">
              <div>
                <div class="text-2xl font-bold">{{ position.position }}</div>
                <div class="text-xs text-gray-500">Позиция</div>
              </div>
              <div>
                <div class="text-2xl font-bold">{{ formatTime(position.estimatedWaitTime) }}</div>
                <div class="text-xs text-gray-500">Ожидание</div>
              </div>
              <div>
                <div class="text-2xl font-bold">{{ position.queueLength }}</div>
                <div class="text-xs text-gray-500">Всего в очереди</div>
              </div>
            </div>
            
            <div class="mt-4">
              <div class="w-full bg-gray-200 rounded-full h-2.5">
                <div 
                  class="bg-blue-600 h-2.5 rounded-full" 
                  :style="{ width: `${100 - (position.position / position.queueLength * 100)}%` }"
                ></div>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Доступные очереди -->
      <div v-if="activeTab === 'available'">
        <div class="flex justify-between mb-4">
          <h2 class="text-xl font-semibold">Доступные очереди</h2>
        </div>
        
        <div v-if="availableQueues.length === 0" class="text-center py-8 text-gray-500">
          Нет доступных очередей
        </div>
        
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div 
            v-for="queue in availableQueues" 
            :key="queue.id"
            class="border rounded-lg p-4 hover:bg-gray-50"
          >
            <div class="flex justify-between items-start">
              <div>
                <h3 class="text-lg font-semibold">{{ queue.name }}</h3>
                <div class="text-sm text-gray-500">{{ queue.description }}</div>
              </div>
              <button 
                @click="joinQueue(queue.id)"
                class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700"
              >
                Встать в очередь
              </button>
            </div>
            
            <div class="mt-4 grid grid-cols-2 gap-4 text-center">
              <div>
                <div class="text-2xl font-bold">{{ queue.clientCount || 0 }}</div>
                <div class="text-xs text-gray-500">Клиентов в очереди</div>
              </div>
              <div>
                <div class="text-2xl font-bold">{{ formatTime(queue.estimatedWaitTime) }}</div>
                <div class="text-xs text-gray-500">Среднее время ожидания</div>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- История -->
      <div v-if="activeTab === 'history'">
        <div class="mb-4">
          <h2 class="text-xl font-semibold">История обслуживания</h2>
        </div>
        
        <div v-if="history.length === 0" class="text-center py-8 text-gray-500">
          У вас пока нет истории обслуживания
        </div>
        
        <table v-else class="w-full border-collapse">
          <thead>
            <tr class="bg-gray-100">
              <th class="border p-2 text-left">Дата</th>
              <th class="border p-2 text-left">Очередь</th>
              <th class="border p-2 text-left">Оператор</th>
              <th class="border p-2 text-left">Время обслуживания</th>
              <th class="border p-2 text-left">Статус</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in history" :key="item.id" class="hover:bg-gray-50">
              <td class="border p-2">{{ formatDate(item.created_at) }}</td>
              <td class="border p-2">{{ item.queue.name }}</td>
              <td class="border p-2">{{ item.operator?.name || 'Не указан' }}</td>
              <td class="border p-2">{{ formatTime(item.service_time) }}</td>
              <td class="border p-2">
                <span 
                  :class="[
                    'px-2 py-1 rounded text-xs font-semibold',
                    item.status === 'completed' ? 'bg-green-100 text-green-800' : 
                    item.status === 'skipped' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'
                  ]"
                >
                  {{ 
                    item.status === 'completed' ? 'Завершено' : 
                    item.status === 'skipped' ? 'Пропущено' : 'Отменено' 
                  }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <!-- Профиль -->
      <div v-if="activeTab === 'profile'">
        <div class="mb-4">
          <h2 class="text-xl font-semibold">Мой профиль</h2>
        </div>
        
        <form @submit.prevent="updateProfile" class="max-w-md">
          <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="name">
              Имя
            </label>
            <input 
              v-model="profile.name"
              class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
              id="name" 
              type="text" 
              placeholder="Ваше имя"
            >
          </div>
          
          <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="email">
              Email
            </label>
            <input 
              v-model="profile.email"
              class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-gray-100" 
              id="email" 
              type="email" 
              placeholder="Ваш email"
              disabled
            >
          </div>
          
          <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="phone">
              Телефон
            </label>
            <input 
              v-model="profile.phone"
              class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
              id="phone" 
              type="text" 
              placeholder="Ваш телефон"
            >
          </div>
          
          <div class="flex items-center justify-between">
            <button 
              class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" 
              type="submit"
            >
              Сохранить
            </button>
          </div>
        </form>
      </div>
    </div>
    
    <!-- Модальное окно выбора очереди -->
    <div v-if="showQueueSelector" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 max-w-md w-full">
        <h2 class="text-xl font-semibold mb-4">Выберите очередь</h2>
        
        <div v-if="availableQueues.length === 0" class="text-center py-4 text-gray-500">
          Нет доступных очередей
        </div>
        
        <div v-else class="mb-4">
          <div 
            v-for="queue in availableQueues" 
            :key="queue.id"
            @click="joinQueue(queue.id)"
            class="p-3 border rounded mb-2 cursor-pointer hover:bg-gray-50"
          >
            <div class="font-medium">{{ queue.name }}</div>
            <div class="text-sm text-gray-500">{{ queue.clientCount || 0 }} клиентов в очереди</div>
          </div>
        </div>
        
        <div class="flex justify-end">
          <button 
            @click="showQueueSelector = false" 
            class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300"
          >
            Отмена
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

// Активная вкладка
const activeTab = ref('queues')

// Вкладки
const tabs = [
  { id: 'queues', name: 'Мои очереди' },
  { id: 'available', name: 'Доступные очереди' },
  { id: 'history', name: 'История' },
  { id: 'profile', name: 'Профиль' }
]

// Данные
const activePositions = ref([])
const availableQueues = ref([])
const history = ref([])
const profile = ref({
  name: '',
  email: '',
  phone: ''
})
const showQueueSelector = ref(false)

// Вычисляемые свойства
const nextQueue = computed(() => {
  if (activePositions.value.length === 0) return null
  
  // Находим очередь с наименьшей позицией
  return [...activePositions.value].sort((a, b) => a.position - b.position)[0]
})

// Форматирование времени
const formatTime = (seconds) => {
  if (!seconds) return '0:00'
  
  const mins = Math.floor(seconds / 60)
  const secs = seconds % 60
  return `${mins}:${secs.toString().padStart(2, '0')}`
}

// Форматирование даты
const formatDate = (dateString) => {
  if (!dateString) return ''
  
  const date = new Date(dateString)
  return new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  }).format(date)
}

// Встать в очередь
const joinQueue = async (queueId) => {
  try {
    const response = await axios.post(`/api/client/queues/${queueId}/join`)
    
    if (response.data.success) {
      showQueueSelector.value = false
      
      // Обновляем список активных позиций
      loadPositions()
    }
  } catch (error) {
    console.error('Ошибка при вставании в очередь:', error)
  }
}

// Выйти из очереди
const leaveQueue = async (queueId) => {
  try {
    const response = await axios.post(`/api/client/queues/${queueId}/leave`)
    
    if (response.data.success) {
      // Обновляем список активных позиций
      loadPositions()
    }
  } catch (error) {
    console.error('Ошибка при выходе из очереди:', error)
  }
}

// Обновление профиля
const updateProfile = async () => {
  try {
    const response = await axios.put('/api/client/profile', {
      name: profile.value.name,
      phone: profile.value.phone
    })
    
    if (response.data.success) {
      alert('Профиль успешно обновлен')
    }
  } catch (error) {
    console.error('Ошибка при обновлении профиля:', error)
  }
}

// Загрузка активных позиций
const loadPositions = async () => {
  try {
    const response = await axios.get('/api/client/positions')
    
    if (response.data.success) {
      activePositions.value = response.data.data || []
    }
  } catch (error) {
    console.error('Ошибка при загрузке позиций:', error)
  }
}

// Загрузка доступных очередей
const loadAvailableQueues = async () => {
  try {
    const response = await axios.get('/api/queues')
    
    if (response.data.success) {
      // Фильтруем очереди, в которых клиент уже стоит
      const activeQueueIds = activePositions.value.map(pos => pos.queue.id)
      availableQueues.value = (response.data.data || []).filter(
        queue => !activeQueueIds.includes(queue.id) && queue.status === 'active'
      )
    }
  } catch (error) {
    console.error('Ошибка при загрузке очередей:', error)
  }
}

// Загрузка истории
const loadHistory = async () => {
  try {
    const response = await axios.get('/api/client/history')
    
    if (response.data.success) {
      history.value = response.data.data || []
    }
  } catch (error) {
    console.error('Ошибка при загрузке истории:', error)
  }
}

// Загрузка профиля
const loadProfile = async () => {
  try {
    const response = await axios.get('/api/client/profile')
    
    if (response.data.success) {
      profile.value = {
        name: response.data.data.user.name,
        email: response.data.data.user.email,
        phone: response.data.data.user.phone || ''
      }
    }
  } catch (error) {
    console.error('Ошибка при загрузке профиля:', error)
  }
}

// Инициализация
onMounted(() => {
  loadProfile()
  loadPositions()
  loadHistory()
  
  // После загрузки позиций загружаем доступные очереди
  loadPositions().then(() => {
    loadAvailableQueues()
  })
})
</script>
