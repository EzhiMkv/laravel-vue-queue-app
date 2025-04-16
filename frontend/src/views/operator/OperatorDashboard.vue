<template>
  <div class="operator-dashboard">
    <h1 class="text-2xl font-bold mb-6">Панель оператора</h1>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div class="bg-white rounded-lg shadow p-4">
        <h2 class="text-lg font-semibold mb-2">Статус</h2>
        <div class="flex items-center">
          <span 
            :class="[
              'inline-block w-3 h-3 rounded-full mr-2',
              operatorStatus === 'available' ? 'bg-green-500' : 
              operatorStatus === 'busy' ? 'bg-yellow-500' : 'bg-gray-500'
            ]"
          ></span>
          <span class="text-lg">
            {{ 
              operatorStatus === 'available' ? 'Доступен' : 
              operatorStatus === 'busy' ? 'Занят' : 'Не в сети' 
            }}
          </span>
        </div>
        <div class="mt-3 flex gap-2">
          <button 
            @click="updateStatus('available')" 
            class="px-3 py-1 bg-green-100 text-green-800 rounded text-sm hover:bg-green-200"
            :disabled="operatorStatus === 'available'"
          >
            Доступен
          </button>
          <button 
            @click="updateStatus('busy')" 
            class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded text-sm hover:bg-yellow-200"
            :disabled="operatorStatus === 'busy'"
          >
            Занят
          </button>
          <button 
            @click="updateStatus('offline')" 
            class="px-3 py-1 bg-gray-100 text-gray-800 rounded text-sm hover:bg-gray-200"
            :disabled="operatorStatus === 'offline'"
          >
            Не в сети
          </button>
        </div>
      </div>
      
      <div class="bg-white rounded-lg shadow p-4">
        <h2 class="text-lg font-semibold mb-2">Текущая очередь</h2>
        <div v-if="currentQueue">
          <div class="text-lg font-medium">{{ currentQueue.name }}</div>
          <div class="text-sm text-gray-500">{{ currentQueue.clientCount || 0 }} клиентов в очереди</div>
        </div>
        <div v-else class="text-gray-500">Не назначено</div>
        
        <button 
          v-if="!currentQueue && availableQueues.length > 0"
          @click="showQueueSelector = true"
          class="mt-3 px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700"
        >
          Выбрать очередь
        </button>
      </div>
      
      <div class="bg-white rounded-lg shadow p-4">
        <h2 class="text-lg font-semibold mb-2">Клиентов сегодня</h2>
        <div class="text-3xl font-bold">{{ stats.clientsServedToday || 0 }}</div>
        <div class="text-sm text-gray-500">из {{ stats.totalClientsToday || 0 }}</div>
      </div>
      
      <div class="bg-white rounded-lg shadow p-4">
        <h2 class="text-lg font-semibold mb-2">Среднее время</h2>
        <div class="text-3xl font-bold">{{ formatTime(stats.averageServiceTime) }}</div>
        <div class="text-sm text-gray-500">обслуживания клиента</div>
      </div>
    </div>
    
    <!-- Текущий клиент -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
      <h2 class="text-xl font-semibold mb-4">Текущий клиент</h2>
      
      <div v-if="currentClient" class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <div class="mb-4">
            <span class="text-gray-500">Имя:</span>
            <span class="ml-2 font-medium">{{ currentClient.name }}</span>
          </div>
          <div class="mb-4">
            <span class="text-gray-500">Email:</span>
            <span class="ml-2 font-medium">{{ currentClient.email }}</span>
          </div>
          <div class="mb-4">
            <span class="text-gray-500">Телефон:</span>
            <span class="ml-2 font-medium">{{ currentClient.phone || 'Не указан' }}</span>
          </div>
          <div class="mb-4">
            <span class="text-gray-500">Время в очереди:</span>
            <span class="ml-2 font-medium">{{ formatTime(currentClient.waitTime) }}</span>
          </div>
        </div>
        
        <div class="flex flex-col justify-center items-center">
          <div class="text-center mb-4">
            <div class="text-lg font-semibold">Время обслуживания</div>
            <div class="text-3xl font-bold">{{ formatTime(serviceTime) }}</div>
          </div>
          
          <div class="flex gap-3">
            <button 
              @click="completeService" 
              class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700"
            >
              Завершить
            </button>
            <button 
              @click="skipClient" 
              class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
            >
              Пропустить
            </button>
          </div>
        </div>
      </div>
      
      <div v-else class="text-center py-8">
        <div class="text-gray-500 mb-4">Нет текущего клиента</div>
        <button 
          @click="getNextClient" 
          class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
          :disabled="!currentQueue || operatorStatus !== 'available'"
        >
          Вызвать следующего клиента
        </button>
      </div>
    </div>
    
    <!-- Очередь клиентов -->
    <div class="bg-white rounded-lg shadow p-6">
      <h2 class="text-xl font-semibold mb-4">Очередь клиентов</h2>
      
      <table class="w-full border-collapse">
        <thead>
          <tr class="bg-gray-100">
            <th class="border p-2 text-left">Позиция</th>
            <th class="border p-2 text-left">Имя</th>
            <th class="border p-2 text-left">Время ожидания</th>
            <th class="border p-2 text-left">Статус</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="queueClients.length === 0">
            <td colspan="4" class="border p-2 text-center text-gray-500">Очередь пуста</td>
          </tr>
          <tr v-for="(client, index) in queueClients" :key="client.id" class="hover:bg-gray-50">
            <td class="border p-2">{{ index + 1 }}</td>
            <td class="border p-2">{{ client.name }}</td>
            <td class="border p-2">{{ formatTime(client.waitTime) }}</td>
            <td class="border p-2">
              <span 
                :class="[
                  'px-2 py-1 rounded text-xs font-semibold',
                  client.status === 'waiting' ? 'bg-blue-100 text-blue-800' : 
                  client.status === 'serving' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                ]"
              >
                {{ 
                  client.status === 'waiting' ? 'Ожидает' : 
                  client.status === 'serving' ? 'Обслуживается' : 'Завершено' 
                }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
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
            @click="assignToQueue(queue.id)"
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
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

// Данные оператора
const operatorStatus = ref('offline')
const currentQueue = ref(null)
const currentClient = ref(null)
const queueClients = ref([])
const availableQueues = ref([])
const stats = ref({})
const serviceTime = ref(0)
const serviceTimer = ref(null)
const showQueueSelector = ref(false)

// Форматирование времени
const formatTime = (seconds) => {
  if (!seconds) return '0:00'
  
  const mins = Math.floor(seconds / 60)
  const secs = seconds % 60
  return `${mins}:${secs.toString().padStart(2, '0')}`
}

// Обновление статуса оператора
const updateStatus = async (status) => {
  try {
    const response = await axios.put('/api/operator/status', { status })
    
    if (response.data.success) {
      operatorStatus.value = status
    }
  } catch (error) {
    console.error('Ошибка при обновлении статуса:', error)
  }
}

// Получение следующего клиента
const getNextClient = async () => {
  if (!currentQueue.value || operatorStatus.value !== 'available') return
  
  try {
    const response = await axios.get(`/api/operator/clients/next`)
    
    if (response.data.success && response.data.data) {
      currentClient.value = response.data.data
      operatorStatus.value = 'busy'
      
      // Запуск таймера обслуживания
      serviceTime.value = 0
      serviceTimer.value = setInterval(() => {
        serviceTime.value++
      }, 1000)
      
      // Обновление списка клиентов в очереди
      loadQueueClients()
    }
  } catch (error) {
    console.error('Ошибка при получении следующего клиента:', error)
  }
}

// Завершение обслуживания клиента
const completeService = async () => {
  if (!currentClient.value) return
  
  try {
    const response = await axios.post(`/api/operator/clients/${currentClient.value.id}/complete`)
    
    if (response.data.success) {
      // Остановка таймера
      clearInterval(serviceTimer.value)
      serviceTimer.value = null
      
      // Сброс текущего клиента
      currentClient.value = null
      serviceTime.value = 0
      
      // Обновление статуса
      operatorStatus.value = 'available'
      
      // Обновление статистики и списка клиентов
      loadStats()
      loadQueueClients()
    }
  } catch (error) {
    console.error('Ошибка при завершении обслуживания:', error)
  }
}

// Пропуск клиента
const skipClient = async () => {
  if (!currentClient.value) return
  
  try {
    const response = await axios.post(`/api/operator/clients/${currentClient.value.id}/skip`)
    
    if (response.data.success) {
      // Остановка таймера
      clearInterval(serviceTimer.value)
      serviceTimer.value = null
      
      // Сброс текущего клиента
      currentClient.value = null
      serviceTime.value = 0
      
      // Обновление статуса
      operatorStatus.value = 'available'
      
      // Обновление списка клиентов
      loadQueueClients()
    }
  } catch (error) {
    console.error('Ошибка при пропуске клиента:', error)
  }
}

// Назначение на очередь
const assignToQueue = async (queueId) => {
  try {
    const response = await axios.post(`/api/operator/queues/${queueId}/assign`)
    
    if (response.data.success) {
      currentQueue.value = response.data.data.operator.currentQueue
      showQueueSelector.value = false
      
      // Обновление списка клиентов в очереди
      loadQueueClients()
    }
  } catch (error) {
    console.error('Ошибка при назначении на очередь:', error)
  }
}

// Загрузка списка клиентов в очереди
const loadQueueClients = async () => {
  if (!currentQueue.value) {
    queueClients.value = []
    return
  }
  
  try {
    const response = await axios.get(`/api/queues/${currentQueue.value.id}`)
    
    if (response.data.success) {
      queueClients.value = response.data.data.clients || []
    }
  } catch (error) {
    console.error('Ошибка при загрузке клиентов очереди:', error)
  }
}

// Загрузка статистики оператора
const loadStats = async () => {
  try {
    const response = await axios.get('/api/operator/stats')
    
    if (response.data.success) {
      stats.value = response.data.data || {}
    }
  } catch (error) {
    console.error('Ошибка при загрузке статистики:', error)
  }
}

// Загрузка профиля оператора
const loadProfile = async () => {
  try {
    const response = await axios.get('/api/operator/profile')
    
    if (response.data.success) {
      operatorStatus.value = response.data.data.operator.status || 'offline'
      
      if (response.data.data.operator.currentQueue) {
        currentQueue.value = response.data.data.operator.currentQueue
        loadQueueClients()
      }
    }
  } catch (error) {
    console.error('Ошибка при загрузке профиля:', error)
  }
}

// Загрузка доступных очередей
const loadAvailableQueues = async () => {
  try {
    const response = await axios.get('/api/operator/queues')
    
    if (response.data.success) {
      availableQueues.value = response.data.data.queues || []
    }
  } catch (error) {
    console.error('Ошибка при загрузке очередей:', error)
  }
}

// Инициализация
onMounted(() => {
  loadProfile()
  loadStats()
  loadAvailableQueues()
  
  // Периодическое обновление данных
  const interval = setInterval(() => {
    loadQueueClients()
    loadStats()
  }, 30000) // Каждые 30 секунд
  
  onUnmounted(() => {
    clearInterval(interval)
    
    if (serviceTimer.value) {
      clearInterval(serviceTimer.value)
    }
  })
})
</script>
