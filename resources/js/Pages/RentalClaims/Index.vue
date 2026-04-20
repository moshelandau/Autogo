<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({ claims: Object, stats: Object, filters: Object });
const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');

const applyFilters = () => {
    router.get(route('rental-claims.index'), { search: search.value, status: statusFilter.value }, { preserveState: true, replace: true });
};
watch(search, () => applyFilters());
watch(statusFilter, () => applyFilters());

const fmt = (v) => v ? '$' + parseFloat(v).toLocaleString() : '-';
const statusColors = { new: 'bg-blue-100 text-blue-800', pending_documents: 'bg-yellow-100 text-yellow-800', completed: 'bg-green-100 text-green-800', approved: 'bg-emerald-100 text-emerald-800' };
const priorityColors = { low: 'text-green-600', medium: 'text-yellow-600', high: 'text-red-600' };
</script>

<template>
    <AppLayout title="Rental Claims">
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-bold text-xl text-gray-900">Rental Claims</h2>
                    <p class="text-sm text-gray-500">Damage claims for rental fleet vehicles</p>
                </div>
                <Link :href="route('rental-claims.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">+ New Rental Claim</Link>
            </div>
        </template>

        <div class="p-6 space-y-5">
            <div class="grid grid-cols-4 gap-4">
                <div class="bg-white rounded-xl border p-4 text-center">
                    <div class="text-2xl font-bold text-blue-600">{{ stats.new }}</div><div class="text-xs text-gray-500">New Claims</div>
                </div>
                <div class="bg-white rounded-xl border p-4 text-center">
                    <div class="text-2xl font-bold text-yellow-600">{{ stats.pending_documents }}</div><div class="text-xs text-gray-500">Pending Docs</div>
                </div>
                <div class="bg-white rounded-xl border p-4 text-center">
                    <div class="text-2xl font-bold text-green-600">{{ stats.completed }}</div><div class="text-xs text-gray-500">Completed</div>
                </div>
                <div class="bg-white rounded-xl border p-4 text-center">
                    <div class="text-2xl font-bold text-emerald-600">{{ stats.approved }}</div><div class="text-xs text-gray-500">Approved</div>
                </div>
            </div>

            <div class="bg-white rounded-xl border p-4 flex gap-4">
                <input v-model="search" type="text" placeholder="Search by customer or claim #..." class="flex-1 border-gray-300 rounded-lg text-sm" />
                <select v-model="statusFilter" class="border-gray-300 rounded-lg text-sm">
                    <option value="">All</option><option value="new">New</option><option value="pending_documents">Pending Docs</option><option value="completed">Completed</option><option value="approved">Approved</option>
                </select>
            </div>

            <div class="bg-white rounded-xl border overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vehicle</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Brand</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Priority</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="claim in claims.data" :key="claim.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3"><Link :href="route('rental-claims.show', claim.id)" class="text-indigo-600 hover:text-indigo-900 font-medium text-sm">{{ claim.customer?.first_name }} {{ claim.customer?.last_name }}</Link></td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ claim.vehicle ? `${claim.vehicle.year} ${claim.vehicle.make} ${claim.vehicle.model}` : '-' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ claim.incident_date || '-' }}</td>
                            <td class="px-4 py-3 text-xs capitalize">{{ claim.brand?.replace('_', ' ') }}</td>
                            <td class="px-4 py-3"><span v-if="claim.priority" class="text-xs font-bold uppercase" :class="priorityColors[claim.priority]">{{ claim.priority }}</span></td>
                            <td class="px-4 py-3 text-sm text-right">{{ fmt(claim.damage_amount) }}</td>
                            <td class="px-4 py-3"><span class="px-2 py-1 text-xs rounded-full capitalize" :class="statusColors[claim.status]">{{ claim.status?.replace('_', ' ') }}</span></td>
                        </tr>
                        <tr v-if="!claims.data?.length"><td colspan="7" class="px-4 py-8 text-center text-gray-500">No rental claims found.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
