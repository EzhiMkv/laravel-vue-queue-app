<template>
  <div class="admin-dashboard">
    <h1 class="text-2xl font-bold mb-6">Админ-панель</h1>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
      <div class="bg-white rounded-lg shadow p-4">
        <h2 class="text-lg font-semibold mb-2">Статистика очередей</h2>
        <div class="text-3xl font-bold">{{ queueStats.totalQueues || 0 }}</div>
        <div class="text-sm text-gray-500">Активных очередей</div>
      </div>
      
      <div class="bg-white rounded-lg shadow p-4">
        <h2 class="text-lg font-semibold mb-2">Операторы</h2>
        <div class="text-3xl font-bold">{{ operatorStats.activeOperators || 0 }} / {{ operatorStats.totalOperators || 0 }}</div>
        <div class="text-sm text-gray-500">Активных / Всего</div>
      </div>
      
      <div class="bg-white rounded-lg shadow p-4">
        <h2 class="text-lg font-semibold mb-2">Клиенты в очередях</h2>
        <div class="text-3xl font-bold">{{ queueStats.totalClients || 0 }}</div>
        <div class="text-sm text-gray-500">Ожидающих клиентов</div>
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
      <!-- Очереди -->
      <div v-if="activeTab === 'queues'">
        <div class="flex justify-between mb-4">
          <h2 class="text-xl font-semibold">Управление очередями</h2>
          <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            Создать очередь
          </button>
        </div>
        
        <table class="w-full border-collapse">
          <thead>
            <tr class="bg-gray-100">
              <th class="border p-2 text-left">Название</th>
              <th class="border p-2 text-left">Статус</th>
              <th class="border p-2 text-left">Клиентов</th>
              <th class="border p-2 text-left">Операторов</th>
              <th class="border p-2 text-left">Действия</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="queues.length === 0">
              <td colspan="5" class="border p-2 text-center text-gray-500">Нет доступных очередей</td>
            </tr>
            <tr v-for="queue in queues" :key="queue.id" class="hover:bg-gray-50">
              <td class="border p-2">{{ queue.name }}</td>
              <td class="border p-2">
                <span 
                  :class="[
                    'px-2 py-1 rounded text-xs font-semibold',
                    queue.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                  ]"
                >
                  {{ queue.status === 'active' ? 'Активна' : 'Неактивна' }}
                </span>
              </td>
              <td class="border p-2">{{ queue.clientCount || 0 }}</td>
              <td class="border p-2">{{ queue.operatorCount || 0 }}</td>
              <td class="border p-2">
                <button class="text-blue-600 hover:text-blue-800 mr-2">Редактировать</button>
                <button class="text-red-600 hover:text-red-800">Удалить</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <!-- Операторы -->
      <div v-if="activeTab === 'operators'">
        <div class="flex justify-between mb-4">
          <h2 class="text-xl font-semibold">Управление операторами</h2>
          <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            Добавить оператора
          </button>
        </div>
        
        <table class="w-full border-collapse">
          <thead>
            <tr class="bg-gray-100">
              <th class="border p-2 text-left">Имя</th>
              <th class="border p-2 text-left">Статус</th>
              <th class="border p-2 text-left">Очередь</th>
              <th class="border p-2 text-left">Клиентов обслужено</th>
              <th class="border p-2 text-left">Действия</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="operators.length === 0">
              <td colspan="5" class="border p-2 text-center text-gray-500">Нет доступных операторов</td>
            </tr>
            <tr v-for="operator in operators" :key="operator.id" class="hover:bg-gray-50">
              <td class="border p-2">{{ operator.name }}</td>
              <td class="border p-2">
                <span 
                  :class="[
                    'px-2 py-1 rounded text-xs font-semibold',
                    operator.status === 'available' ? 'bg-green-100 text-green-800' : 
                    operator.status === 'busy' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'
                  ]"
                >
                  {{ 
                    operator.status === 'available' ? 'Доступен' : 
                    operator.status === 'busy' ? 'Занят' : 'Не в сети' 
                  }}
                </span>
              </td>
              <td class="border p-2">{{ operator.currentQueue?.name || 'Не назначен' }}</td>
              <td class="border p-2">{{ operator.stats?.clientsServedToday || 0 }}</td>
              <td class="border p-2">
                <button class="text-blue-600 hover:text-blue-800 mr-2">Редактировать</button>
                <button class="text-red-600 hover:text-red-800">Удалить</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <!-- Клиенты -->
      <div v-if="activeTab === 'clients'">
        <div class="flex justify-between mb-4">
          <h2 class="text-xl font-semibold">Управление клиентами</h2>
          <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            Добавить клиента
          </button>
        </div>
        
        <table class="w-full border-collapse">
          <thead>
            <tr class="bg-gray-100">
              <th class="border p-2 text-left">Имя</th>
              <th class="border p-2 text-left">Email</th>
              <th class="border p-2 text-left">Телефон</th>
              <th class="border p-2 text-left">Статус</th>
              <th class="border p-2 text-left">Действия</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="clients.length === 0">
              <td colspan="5" class="border p-2 text-center text-gray-500">Нет доступных клиентов</td>
            </tr>
            <tr v-for="client in clients" :key="client.id" class="hover:bg-gray-50">
              <td class="border p-2">{{ client.name }}</td>
              <td class="border p-2">{{ client.email }}</td>
              <td class="border p-2">{{ client.phone || 'Не указан' }}</td>
              <td class="border p-2">
                <span 
                  :class="[
                    'px-2 py-1 rounded text-xs font-semibold',
                    client.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                  ]"
                >
                  {{ client.status === 'active' ? 'Активен' : 'Неактивен' }}
                </span>
              </td>
              <td class="border p-2">
                <button class="text-blue-600 hover:text-blue-800 mr-2">Редактировать</button>
                <button class="text-red-600 hover:text-red-800">Удалить</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <!-- Роли -->
      <div v-if="activeTab === 'roles'">
        <div class="flex justify-between mb-4">
          <h2 class="text-xl font-semibold">Управление ролями</h2>
          <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            Создать роль
          </button>
        </div>
        
        <table class="w-full border-collapse">
          <thead>
            <tr class="bg-gray-100">
              <th class="border p-2 text-left">Название</th>
              <th class="border p-2 text-left">Slug</th>
              <th class="border p-2 text-left">Описание</th>
              <th class="border p-2 text-left">Пользователей</th>
              <th class="border p-2 text-left">Действия</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="roles.length === 0">
              <td colspan="5" class="border p-2 text-center text-gray-500">Нет доступных ролей</td>
            </tr>
            <tr v-for="role in roles" :key="role.id" class="hover:bg-gray-50">
              <td class="border p-2">{{ role.name }}</td>
              <td class="border p-2">{{ role.slug }}</td>
              <td class="border p-2">{{ role.description }}</td>
              <td class="border p-2">{{ role.userCount || 0 }}</td>
              <td class="border p-2">
                <button class="text-blue-600 hover:text-blue-800 mr-2">Редактировать</button>
                <button class="text-red-600 hover:text-red-800">Удалить</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

// Активная вкладка
const activeTab = ref('queues')

// Вкладки
const tabs = [
  { id: 'queues', name: 'Очереди' },
  { id: 'operators', name: 'Операторы' },
  { id: 'clients', name: 'Клиенты' },
  { id: 'roles', name: 'Роли' }
]

// Данные
const queues = ref([])
const operators = ref([])
const clients = ref([])
const roles = ref([])
const queueStats = ref({})
const operatorStats = ref({})

// Загрузка данных
onMounted(async () => {
  try {
    // Загрузка очередей
    const queuesResponse = await axios.get('/api/admin/queues')
    queues.value = queuesResponse.data.data || []
    
    // Загрузка операторов
    const operatorsResponse = await axios.get('/api/admin/operators')
    operators.value = operatorsResponse.data.data || []
    
    // Загрузка клиентов
    const clientsResponse = await axios.get('/api/admin/clients')
    clients.value = clientsResponse.data.data || []
    
    // Загрузка ролей
    const rolesResponse = await axios.get('/api/admin/roles')
    roles.value = rolesResponse.data.data || []
    
    // Загрузка статистики очередей
    const queueStatsResponse = await axios.get('/api/admin/stats/queues')
    queueStats.value = queueStatsResponse.data.data || {}
    
    // Загрузка статистики операторов
    const operatorStatsResponse = await axios.get('/api/admin/stats/operators')
    operatorStats.value = operatorStatsResponse.data.data?.summary || {}
  } catch (error) {
    console.error('Ошибка при загрузке данных:', error)
  }
})
</script>
