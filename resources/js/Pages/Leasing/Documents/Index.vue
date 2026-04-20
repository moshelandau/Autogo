<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({ checklists: Object, stats: Object, filters: Object });
const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');

watch(search, () => router.get(route('leasing.documents.index'), { search: search.value, status: statusFilter.value }, { preserveState: true, replace: true }));
watch(statusFilter, () => router.get(route('leasing.documents.index'), { search: search.value, status: statusFilter.value }, { preserveState: true, replace: true }));
</script>

<template>
    <AppLayout title="Lease Documents">
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-bold text-xl text-gray-900">Damage Waiver Documents</h2>
                    <p class="text-sm text-gray-500">Track document collection for leasing customers</p>
                </div>
                <Link :href="route('leasing.documents.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">+ New Checklist</Link>
            </div>
        </template>

        <div class="p-6 space-y-5">
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-white rounded-xl border p-4 text-center">
                    <div class="text-2xl font-bold text-yellow-600">{{ stats.pending }}</div><div class="text-xs text-gray-500">Pending</div>
                </div>
                <div class="bg-white rounded-xl border p-4 text-center">
                    <div class="text-2xl font-bold text-green-600">{{ stats.complete }}</div><div class="text-xs text-gray-500">Complete</div>
                </div>
            </div>

            <div class="bg-white rounded-xl border p-4 flex gap-4">
                <input v-model="search" type="text" placeholder="Search by customer..." class="flex-1 border-gray-300 rounded-lg text-sm" />
                <select v-model="statusFilter" class="border-gray-300 rounded-lg text-sm">
                    <option value="">All</option><option value="pending">Pending</option><option value="complete">Complete</option>
                </select>
            </div>

            <div class="bg-white rounded-xl border overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Deal</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Documents</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Progress</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="cl in checklists.data" :key="cl.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <Link :href="route('leasing.documents.show', cl.id)" class="text-indigo-600 hover:text-indigo-900 font-medium text-sm">
                                    {{ cl.customer?.first_name }} {{ cl.customer?.last_name }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ cl.deal ? `#${cl.deal.deal_number}` : '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-xs font-mono">{{ cl.items?.filter(i => i.is_collected).length }}/{{ cl.items?.length }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-500 h-2 rounded-full transition-all" :style="{ width: cl.items?.length ? (cl.items.filter(i => i.is_collected).length / cl.items.length * 100) + '%' : '0%' }"></div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs rounded-full" :class="cl.status === 'complete' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'">{{ cl.status }}</span>
                            </td>
                        </tr>
                        <tr v-if="!checklists.data?.length"><td colspan="5" class="px-4 py-8 text-center text-gray-500">No checklists found.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
