<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({ deals: Object, filters: Object });
const search = ref(props.filters?.search || '');
const stageFilter = ref(props.filters?.stage || '');

const applyFilters = () => {
    router.get(route('leasing.deals.index'), { search: search.value, stage: stageFilter.value, view: 'list' }, { preserveState: true, replace: true });
};
watch(search, () => applyFilters());
watch(stageFilter, () => applyFilters());

const fmt = (v) => v ? '$' + parseFloat(v).toLocaleString() : '-';
const stageColors = { lead: 'bg-gray-100', quote: 'bg-yellow-100 text-yellow-800', application: 'bg-blue-100 text-blue-800', submission: 'bg-indigo-100 text-indigo-800', pending: 'bg-purple-100 text-purple-800', finalize: 'bg-orange-100 text-orange-800', outstanding: 'bg-pink-100 text-pink-800', complete: 'bg-green-100 text-green-800', lost: 'bg-red-100 text-red-800' };
</script>

<template>
    <AppLayout title="Deals List">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Deals</h2>
                <div class="flex gap-3">
                    <Link :href="route('leasing.deals.index')" class="px-3 py-2 text-sm text-gray-600 bg-white border rounded-md hover:bg-gray-50">Kanban</Link>
                    <Link :href="route('leasing.deals.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">+ New Deal</Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <div class="bg-white shadow-sm rounded-lg p-4 flex gap-4">
                    <input v-model="search" type="text" placeholder="Search deals..." class="flex-1 border-gray-300 rounded-md shadow-sm text-sm" />
                    <select v-model="stageFilter" class="border-gray-300 rounded-md shadow-sm text-sm">
                        <option value="">All Stages</option>
                        <option v-for="s in ['lead','quote','application','submission','pending','finalize','outstanding','complete','lost']" :key="s" :value="s" class="capitalize">{{ s }}</option>
                    </select>
                </div>

                <div class="bg-white shadow-sm rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Deal #</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vehicle</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stage</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Payment</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lender</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Salesperson</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="d in deals.data" :key="d.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3"><Link :href="route('leasing.deals.show', d.id)" class="text-indigo-600 text-sm font-mono">#{{ d.deal_number }}</Link></td>
                                <td class="px-4 py-3 text-sm font-medium">{{ d.customer?.first_name }} {{ d.customer?.last_name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ d.vehicle_make ? `${d.vehicle_year} ${d.vehicle_make} ${d.vehicle_model}` : '-' }}</td>
                                <td class="px-4 py-3 text-sm capitalize">{{ d.payment_type }}</td>
                                <td class="px-4 py-3"><span class="px-2 py-1 text-xs rounded-full capitalize" :class="stageColors[d.stage]">{{ d.stage }}</span></td>
                                <td class="px-4 py-3 text-sm text-right">{{ fmt(d.monthly_payment) }}/mo</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ d.lender?.name || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ d.salesperson?.name || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="deals.links?.length > 3" class="flex justify-center gap-1">
                    <Link v-for="link in deals.links" :key="link.label" :href="link.url || '#'" class="px-3 py-1 text-sm rounded" :class="link.active ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100'" v-html="link.label" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
