<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({ claims: Object, stats: Object, filters: Object });
const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');

const applyFilters = () => {
    router.get(route('claims.index'), { search: search.value, status: statusFilter.value }, { preserveState: true, replace: true });
};
watch(search, () => applyFilters());
watch(statusFilter, () => applyFilters());

const fmt = (v) => v ? '$' + parseFloat(v).toLocaleString() : '$0';
const statusColors = { new: 'bg-blue-100 text-blue-800', filed: 'bg-yellow-100 text-yellow-800', in_progress: 'bg-orange-100 text-orange-800', completed: 'bg-green-100 text-green-800' };
</script>

<template>
    <AppLayout title="Insurance Claims">
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-bold text-xl text-gray-900">Insurance Claims</h2>
                    <p class="text-sm text-gray-500">Bodyshop insurance claim tracking</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('claims.index')" class="px-3 py-1.5 text-sm rounded-lg bg-indigo-600 text-white">📋 List</Link>
                    <Link :href="route('claims.board')" class="px-3 py-1.5 text-sm rounded-lg border bg-white hover:bg-gray-50">📊 Board</Link>
                    <Link :href="route('claims.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">+ New Claim</Link>
                </div>
            </div>
        </template>

        <div class="p-6 space-y-5">
            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div class="bg-white rounded-xl border p-4 text-center">
                    <div class="text-2xl font-bold text-blue-600">{{ stats.new }}</div>
                    <div class="text-xs text-gray-500">New</div>
                </div>
                <div class="bg-white rounded-xl border p-4 text-center">
                    <div class="text-2xl font-bold text-yellow-600">{{ stats.filed }}</div>
                    <div class="text-xs text-gray-500">Filed</div>
                </div>
                <div class="bg-white rounded-xl border p-4 text-center">
                    <div class="text-2xl font-bold text-orange-600">{{ stats.in_progress }}</div>
                    <div class="text-xs text-gray-500">In Progress</div>
                </div>
                <div class="bg-white rounded-xl border p-4 text-center">
                    <div class="text-2xl font-bold text-green-600">{{ stats.completed }}</div>
                    <div class="text-xs text-gray-500">Completed</div>
                </div>
                <div class="bg-white rounded-xl border p-4 text-center">
                    <div class="text-2xl font-bold text-red-600">{{ fmt(stats.total_outstanding) }}</div>
                    <div class="text-xs text-gray-500">Outstanding</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-xl border p-4 flex gap-4">
                <input v-model="search" type="text" placeholder="Search by customer, claim #, insurance, vehicle..." class="flex-1 border-gray-300 rounded-lg text-sm" />
                <select v-model="statusFilter" class="border-gray-300 rounded-lg text-sm">
                    <option value="">All Statuses</option>
                    <option value="new">New</option>
                    <option value="filed">Filed</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                </select>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl border overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Insurance / Claim #</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vehicle</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Accident Date</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Steps</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="claim in claims.data" :key="claim.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <Link :href="route('claims.show', claim.id)" class="text-indigo-600 hover:text-indigo-900 font-medium text-sm">
                                    {{ claim.customer?.first_name }} {{ claim.customer?.last_name }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <div v-for="ie in claim.insurance_entries" :key="ie.id" class="text-xs">
                                    <span class="font-medium">{{ ie.insurance_company }}</span>
                                    <span class="text-gray-500 ml-1">#{{ ie.claim_number }}</span>
                                </div>
                                <span v-if="!claim.insurance_entries?.length" class="text-gray-400 text-xs">No insurance</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ claim.vehicle_make ? `${claim.vehicle_year || ''} ${claim.vehicle_make} ${claim.vehicle_model || ''}` : '-' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ claim.accident_date || '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-xs font-mono" :class="claim.steps?.filter(s => s.is_completed).length === claim.steps?.length ? 'text-green-600' : 'text-gray-500'">
                                    {{ claim.steps?.filter(s => s.is_completed).length || 0 }}/{{ claim.steps?.length || 9 }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-right">{{ fmt(claim.approved_amount || claim.estimate_amount) }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs rounded-full capitalize" :class="statusColors[claim.status]">{{ claim.status?.replace('_', ' ') }}</span>
                            </td>
                        </tr>
                        <tr v-if="!claims.data?.length"><td colspan="7" class="px-4 py-8 text-center text-gray-500">No claims found.</td></tr>
                    </tbody>
                </table>
            </div>

            <div v-if="claims.links?.length > 3" class="flex justify-center gap-1">
                <Link v-for="link in claims.links" :key="link.label" :href="link.url || '#'"
                      class="px-3 py-1 text-sm rounded" :class="link.active ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100'" v-html="link.label" />
            </div>
        </div>
    </AppLayout>
</template>
