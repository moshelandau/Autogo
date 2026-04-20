<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ pending: Array, out: Array, users: Array, stats: Object });

const form = useForm({ vehicle_description: '', parts_list: '', vendor: '', assigned_to: '', estimated_cost: '', notes: '' });
const addOrder = () => form.post(route('parts.store'), { onSuccess: () => form.reset() });

const changeStatus = (id, status) => router.post(route('parts.status', id), { status });

const showOut = ref(false);
const statusColors = { pending: 'bg-yellow-100 text-yellow-800', ordered: 'bg-blue-100 text-blue-800', received: 'bg-purple-100 text-purple-800', installed: 'bg-green-100 text-green-800', out: 'bg-gray-100 text-gray-800' };
</script>

<template>
    <AppLayout title="Parts">
        <template #header>
            <div>
                <h2 class="font-bold text-xl text-gray-900">Parts Orders</h2>
                <p class="text-sm text-gray-500">Bodyshop parts tracking by vehicle</p>
            </div>
        </template>

        <div class="p-6 space-y-5">
            <div class="grid grid-cols-4 gap-4">
                <div class="bg-white rounded-xl border p-4 text-center"><div class="text-2xl font-bold text-yellow-600">{{ stats.pending }}</div><div class="text-xs text-gray-500">Pending</div></div>
                <div class="bg-white rounded-xl border p-4 text-center"><div class="text-2xl font-bold text-blue-600">{{ stats.ordered }}</div><div class="text-xs text-gray-500">Ordered</div></div>
                <div class="bg-white rounded-xl border p-4 text-center"><div class="text-2xl font-bold text-purple-600">{{ stats.received }}</div><div class="text-xs text-gray-500">Received</div></div>
                <div class="bg-white rounded-xl border p-4 text-center"><div class="text-2xl font-bold text-green-600">{{ stats.out }}</div><div class="text-xs text-gray-500">Out</div></div>
            </div>

            <!-- Add Parts Order -->
            <div class="bg-white rounded-xl border p-5">
                <h3 class="font-semibold text-gray-900 mb-3">Add Parts Order</h3>
                <form @submit.prevent="addOrder" class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <input v-model="form.vehicle_description" type="text" placeholder="Vehicle (e.g. 2023 Pilot Touring) *" class="col-span-2 border-gray-300 rounded-lg text-sm" />
                    <input v-model="form.vendor" type="text" placeholder="Vendor" class="border-gray-300 rounded-lg text-sm" />
                    <select v-model="form.assigned_to" class="border-gray-300 rounded-lg text-sm">
                        <option value="">Assign to</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                    <textarea v-model="form.parts_list" placeholder="Parts list..." rows="2" class="col-span-2 border-gray-300 rounded-lg text-sm"></textarea>
                    <input v-model="form.estimated_cost" type="number" step="0.01" placeholder="Est. cost" class="border-gray-300 rounded-lg text-sm" />
                    <button type="submit" :disabled="form.processing || !form.vehicle_description" class="bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700 disabled:opacity-50">Add Order</button>
                </form>
            </div>

            <!-- Active Orders -->
            <div class="bg-white rounded-xl border">
                <div class="px-5 py-3 border-b bg-amber-50/50"><h3 class="font-semibold text-amber-800 text-sm">Active Orders ({{ pending?.length || 0 }})</h3></div>
                <div class="divide-y">
                    <div v-for="order in pending" :key="order.id" class="flex items-center gap-4 px-5 py-3 hover:bg-gray-50">
                        <div class="flex-1">
                            <span class="font-medium text-sm">{{ order.vehicle_description }}</span>
                            <span v-if="order.assigned_to_user" class="text-xs text-gray-400 ml-2">{{ order.assigned_to_user.name }}</span>
                            <div v-if="order.parts_list" class="text-xs text-gray-500 mt-0.5 line-clamp-1">{{ order.parts_list }}</div>
                        </div>
                        <span class="px-2 py-1 text-xs rounded-full capitalize" :class="statusColors[order.status]">{{ order.status }}</span>
                        <div class="flex gap-1">
                            <button v-if="order.status === 'pending'" @click="changeStatus(order.id, 'ordered')" class="text-xs text-blue-600 hover:text-blue-800 px-2 py-1 rounded bg-blue-50">Order</button>
                            <button v-if="order.status === 'ordered'" @click="changeStatus(order.id, 'received')" class="text-xs text-purple-600 hover:text-purple-800 px-2 py-1 rounded bg-purple-50">Received</button>
                            <button v-if="order.status === 'received'" @click="changeStatus(order.id, 'installed')" class="text-xs text-green-600 hover:text-green-800 px-2 py-1 rounded bg-green-50">Installed</button>
                            <button v-if="order.status !== 'out'" @click="changeStatus(order.id, 'out')" class="text-xs text-gray-500 hover:text-gray-700 px-2 py-1 rounded bg-gray-50">Out</button>
                        </div>
                    </div>
                    <div v-if="!pending?.length" class="px-5 py-6 text-center text-sm text-gray-400">No active parts orders.</div>
                </div>
            </div>

            <!-- Out (Completed) -->
            <div class="bg-white rounded-xl border">
                <button @click="showOut = !showOut" class="w-full px-5 py-3 border-b bg-green-50/50 flex justify-between items-center">
                    <h3 class="font-semibold text-green-800 text-sm">Out / Completed ({{ out?.length || 0 }})</h3>
                    <svg class="w-4 h-4 text-gray-400 transition-transform" :class="showOut ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div v-if="showOut" class="divide-y">
                    <div v-for="order in out" :key="order.id" class="flex items-center gap-4 px-5 py-3">
                        <span class="flex-1 text-sm text-gray-400 line-through">{{ order.vehicle_description }}</span>
                        <span v-if="order.assigned_to_user" class="text-xs text-gray-400">{{ order.assigned_to_user.name }}</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
