<script setup>
import { ref } from 'vue'

const name = ref(null)

const createClient = ()=>{
    axios.post('/clients', { name:name.value })
        .then(response => {
            console.log(response)
            name.value = ''
            getQueue()
        })
        .catch(error => {
        })
        .finally();
}

const queue = ref([])
const getQueue = ()=>{
    axios.get('/queue')
        .then(response => {
            queue.value = response.data
        })
        .catch(error => {
        })
        .finally();
}
getQueue()

const removeClient = (id) => {
    axios.delete(`/clients/${id}` )
        .then(response => {
            getQueue()
        })
        .catch(error => {
        })
        .finally();
}

const proceedQueue = () => {
    axios.get(`/queue/proceed` )
        .then(response => {
            queue.value = response.data
        })
        .catch(error => {
        })
        .finally();
}

const clientPositionFetched = ref(null)
const clientId = ref(null)
const getClientPosition = () => {
    axios.get(`/clients/${clientId.value}/position` )
        .then(response => {
            console.log(response)
            clientPositionFetched.value = response.data
        })
        .catch(error => {
            clientPositionFetched.value = false
        })
        .finally();
}

const nextClient = ref(null)
const getNextClient = () => {
    axios.get(`/queue/next` )
        .then(response => {
            console.log(response)
            nextClient.value = response.data
        })
        .catch(error => {
        })
        .finally();
}
</script>

<template>
    <div class="flex min-h-full flex-col px-10 lg:px-3">
<!--        <div class="sm:mx-auto sm:w-full">-->
        <h2 class="text-4xl font-extrabold text-center m-5" style="margin-top: 20px">Электронная очередь</h2>
          <div class="dash flex">
              <section class="queue">
                  <div class="relative overflow-x-auto flex">
                      <button type="button"
                              v-if="queue.length > 0"
                              @click="proceedQueue"
                              id="proceed-queue"
                              class="text-white bg-blue-700 hover:bg-blue-800 font-medium rounded-lg text-sm px-5 py-2.5 me-2 mb-2 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none">
                          Продвинуть очередь
                      </button>
                      <table class="w-full text-sm text-left rtl:text-right text-gray-500">
                          <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                          <tr>
                              <th scope="col" class="px-6 py-3">
                                  Позиция
                              </th>
                              <th scope="col" class="px-6 py-3">ID</th>
                              <th scope="col" class="px-6 py-3">
                                  Имя
                              </th>
                              <th></th>
                          </tr>
                          </thead>
                          <tbody>
                              <tr v-for="q in queue" :key="Math.random()" class="bg-white border-b">
                                  <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">
                                      {{ q.position }}
                                  </th>
                                  <td class="px-6 py-4">
                                      {{ q.client.id }}
                                  </td>
                                  <td class="px-6 py-4">
                                      {{ q.client.name }}
                                  </td>
                                  <td>
                                      <button type="button" @click="removeClient(q.client.id)" style="color: red">X</button>
                                  </td>
                              </tr>
                          </tbody>
                      </table>
                  </div>
              </section>
              <section class="actions">
                  <div class="flex items-center border-b border-teal-500 py-2 m-5">
                      <input v-model="name"
                             class="appearance-none bg-transparent border-none w-full text-gray-700 mr-3 py-1 px-2 leading-tight focus:outline-none"
                             type="text" placeholder="Имя клиента" aria-label="Full name">
                      <button @click="createClient"
                              class="flex-shrink-0 bg-teal-500 hover:bg-teal-700 border-teal-500 hover:border-teal-700 text-sm border-4 text-white py-1 px-2 rounded" type="button">
                          Добавить клиента
                      </button>
                  </div>
                  <div class="py-2 m-5" v-if="clientPositionFetched">
                      Позиция: {{clientPositionFetched.position}}
                  </div>
                  <div class="flex items-center border-b border-teal-500 py-2 m-5">
                      <input v-model="clientId"
                             class="appearance-none bg-transparent border-none w-full text-gray-700 mr-3 py-1 px-2 leading-tight focus:outline-none"
                             type="text" placeholder="ID клиента" aria-label="Full name">
                      <button @click="getClientPosition"
                              class="flex-shrink-0 bg-teal-500 hover:bg-teal-700 border-teal-500 hover:border-teal-700 text-sm border-4 text-white py-1 px-2 rounded" type="button">
                          Получить позицию в очереди по ID клиента
                      </button>
                  </div>
                  <button type="button"
                          @click="getNextClient"
                          class="text-white bg-blue-700 hover:bg-blue-800 font-medium rounded-lg text-sm px-5 py-2.5 me-2 mb-2 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none">
                      Получить следующего в очереди клиента
                  </button>
                  <div class="py-2 m-5" v-if="nextClient">
                      <p class="italic">Следующий клиент</p>
                      <p>ID: {{nextClient.id}}</p>
                      <p>Имя: {{nextClient.name}}</p>
                  </div>
              </section>
          </div>
    </div>
</template>


<style scoped>
.queue{
    margin-right: 70px;
}
.dash{
    margin-right: auto;
    margin-left: auto;
    table{
        min-width: 600px;
    }
}
#proceed-queue{
    max-height: 58px;
}
</style>


