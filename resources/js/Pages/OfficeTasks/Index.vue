<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ today: Array, todo: Array, recurring: Array, completed: Array, users: Array, selectedUserId: [String, Number] });

const taskForm = useForm({ title: '', section: 'today', assigned_to: '', priority: '', due_date: '' });
const addTask = () => { taskForm.post(route('office-tasks.store'), { onSuccess: () => taskForm.reset() }); };

const complete = (id) => router.post(route('office-tasks.complete', id));
const uncomplete = (id) => router.post(route('office-tasks.uncomplete', id));
const moveToToday = (id) => router.post(route('office-tasks.move', id), { section: 'today' });
const deleteTask = (id) => { if (confirm('Delete this task?')) router.delete(route('office-tasks.destroy', id)); };

const filterByUser = (userId) => {
    router.get(route('office-tasks.index'), userId ? { user_id: userId } : {}, { preserveState: true });
};

const showCompleted = ref(false);
const priorityColors = { low: 'text-green-500', medium: 'text-yellow-500', high: 'text-red-500' };
</script>

<template>
    <AppLayout title="Office Tasks">
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-bold text-xl text-gray-900">Office Tasks</h2>
                    <p class="text-sm text-gray-500">Daily task management</p>
                </div>
            </div>
        </template>

        <div class="p-6 space-y-5">
            <!-- User filter -->
            <div class="flex gap-2 flex-wrap">
                <button @click="filterByUser(null)" class="px-3 py-1.5 text-xs rounded-lg transition-colors"
                        :class="!selectedUserId ? 'bg-indigo-600 text-white' : 'bg-white border text-gray-600 hover:bg-gray-50'">All</button>
                <button v-for="u in users" :key="u.id" @click="filterByUser(u.id)"
                        class="px-3 py-1.5 text-xs rounded-lg transition-colors"
                        :class="selectedUserId == u.id ? 'bg-indigo-600 text-white' : 'bg-white border text-gray-600 hover:bg-gray-50'">{{ u.name }}</button>
            </div>

            <!-- Quick Add -->
            <form @submit.prevent="addTask" class="bg-white rounded-xl border p-4 flex gap-3">
                <input v-model="taskForm.title" type="text" placeholder="Add a task..." class="flex-1 border-gray-300 rounded-lg text-sm" />
                <select v-model="taskForm.section" class="border-gray-300 rounded-lg text-xs w-28">
                    <option value="today">Today</option><option value="todo">To Do</option>
                </select>
                <button type="submit" :disabled="taskForm.processing || !taskForm.title" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700 disabled:opacity-50">Add</button>
            </form>

            <!-- Today Section -->
            <div class="bg-white rounded-xl border">
                <div class="px-5 py-3 border-b bg-amber-50/50">
                    <h3 class="font-semibold text-amber-800 text-sm">Today ({{ today?.length || 0 }})</h3>
                </div>
                <div class="divide-y">
                    <div v-for="task in today" :key="task.id" class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50">
                        <button @click="complete(task.id)" class="w-5 h-5 rounded-full border-2 border-gray-300 hover:border-green-400 flex-shrink-0 transition-colors"></button>
                        <span class="flex-1 text-sm">{{ task.title }}</span>
                        <span v-if="task.priority" class="text-xs font-bold uppercase" :class="priorityColors[task.priority]">{{ task.priority }}</span>
                        <span v-if="task.assigned_to_user" class="text-xs text-gray-400">{{ task.assigned_to_user.name }}</span>
                        <span v-if="task.comments?.length" class="text-xs text-gray-400">{{ task.comments.length }} comments</span>
                    </div>
                    <div v-if="!today?.length" class="px-5 py-6 text-center text-sm text-gray-400">No tasks for today.</div>
                </div>
            </div>

            <!-- To Do Section -->
            <div class="bg-white rounded-xl border">
                <div class="px-5 py-3 border-b bg-blue-50/50">
                    <h3 class="font-semibold text-blue-800 text-sm">To Do ({{ todo?.length || 0 }})</h3>
                </div>
                <div class="divide-y">
                    <div v-for="task in todo" :key="task.id" class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50">
                        <button @click="complete(task.id)" class="w-5 h-5 rounded-full border-2 border-gray-300 hover:border-green-400 flex-shrink-0"></button>
                        <span class="flex-1 text-sm">{{ task.title }}</span>
                        <button @click="moveToToday(task.id)" class="text-xs text-indigo-600 hover:text-indigo-800">Move to Today</button>
                    </div>
                    <div v-if="!todo?.length" class="px-5 py-4 text-center text-sm text-gray-400">No to-do tasks.</div>
                </div>
            </div>

            <!-- Recurring Tasks -->
            <div v-if="recurring?.length" class="bg-white rounded-xl border">
                <div class="px-5 py-3 border-b bg-purple-50/50">
                    <h3 class="font-semibold text-purple-800 text-sm">Recurring Tasks ({{ recurring?.length || 0 }})</h3>
                </div>
                <div class="divide-y">
                    <div v-for="task in recurring" :key="task.id" class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50">
                        <button @click="complete(task.id)" class="w-5 h-5 rounded-full border-2 border-gray-300 hover:border-green-400 flex-shrink-0"></button>
                        <span class="flex-1 text-sm">{{ task.title }}</span>
                        <span class="text-xs text-purple-500 capitalize">{{ task.recurring_frequency }}</span>
                        <span v-if="task.due_date" class="text-xs" :class="new Date(task.due_date) < new Date() ? 'text-red-600 font-bold' : 'text-gray-400'">{{ task.due_date }}</span>
                    </div>
                </div>
            </div>

            <!-- Completed -->
            <div class="bg-white rounded-xl border">
                <button @click="showCompleted = !showCompleted" class="w-full px-5 py-3 border-b bg-green-50/50 flex justify-between items-center">
                    <h3 class="font-semibold text-green-800 text-sm">Completed ({{ completed?.length || 0 }})</h3>
                    <svg class="w-4 h-4 text-gray-400 transition-transform" :class="showCompleted ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div v-if="showCompleted" class="divide-y">
                    <div v-for="task in completed" :key="task.id" class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50">
                        <button @click="uncomplete(task.id)" class="w-5 h-5 rounded-full bg-green-500 border-2 border-green-500 flex items-center justify-center text-white flex-shrink-0">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                        </button>
                        <span class="flex-1 text-sm line-through text-gray-400">{{ task.title }}</span>
                        <span v-if="task.completed_at" class="text-xs text-gray-400">{{ new Date(task.completed_at).toLocaleDateString() }}</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
