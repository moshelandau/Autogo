<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({ sessions: { type: Array, default: () => [] } });

const statusClass = (s) => ({
    active:    'bg-blue-100 text-blue-700 border-blue-200',
    stalled:   'bg-yellow-100 text-yellow-800 border-yellow-300',
    aborted:   'bg-gray-200 text-gray-700 border-gray-300',
    completed: 'bg-emerald-100 text-emerald-800 border-emerald-300',
}[s] || 'bg-gray-100 text-gray-700');

const fmt = (iso) => iso ? new Date(iso).toLocaleString() : '—';
</script>

<template>
    <AppLayout title="Application Intakes (SMS Bot)">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800">📱 Application Intakes — SMS Bot</h2>
        </template>

        <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="p-4 border-b bg-gray-50 text-sm text-gray-600">
                    Every customer-text lease/rental intake — finished or not. Use this to follow up on stalled conversations.
                </div>
                <table class="min-w-full divide-y text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-2 text-left">Name / Phone</th>
                            <th class="px-4 py-2 text-left">Flow</th>
                            <th class="px-4 py-2 text-left">Status</th>
                            <th class="px-4 py-2 text-left">Progress</th>
                            <th class="px-4 py-2 text-left">Current Step</th>
                            <th class="px-4 py-2 text-left">Last Reply</th>
                            <th class="px-4 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="s in sessions" :key="s.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900">{{ s.name }}</div>
                                <div class="text-xs text-gray-500">{{ s.phone }}</div>
                            </td>
                            <td class="px-4 py-3 capitalize">{{ s.flow }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-block px-2 py-0.5 text-xs font-semibold rounded-full border capitalize"
                                    :class="statusClass(s.status)">{{ s.status }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-24 bg-gray-200 rounded-full h-2">
                                        <div class="bg-indigo-600 h-2 rounded-full" :style="`width: ${s.progress_pct}%`"></div>
                                    </div>
                                    <span class="text-xs text-gray-500">{{ s.progress_pct }}%</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-600 font-mono">{{ s.current_step }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ fmt(s.last_inbound_at) }}</td>
                            <td class="px-4 py-3 text-right space-x-2 text-xs">
                                <Link :href="route('sms.show', s.phone)" class="text-indigo-600 hover:text-indigo-800">SMS Thread</Link>
                                <Link v-if="s.customer_id" :href="route('customers.show', s.customer_id)" class="text-indigo-600 hover:text-indigo-800">Customer</Link>
                                <Link v-if="s.deal_id" :href="route('leasing.deals.show', s.deal_id)" class="text-indigo-600 hover:text-indigo-800">Deal #{{ s.deal_number }}</Link>
                            </td>
                        </tr>
                        <tr v-if="!sessions.length"><td colspan="7" class="px-4 py-12 text-center text-gray-400">No bot intakes yet.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
