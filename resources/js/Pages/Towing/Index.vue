<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({ jobs: Object, stats: Object, filters: Object });
const search = ref(props.filters?.q || '');
const status = ref(props.filters?.status || '');

watch([search, status], ([q, s]) =>
    router.get(route('towing.index'), { q, status: s }, { preserveState: true, replace: true })
);

const statusColor = {
    pending:    'bg-amber-100 text-amber-800',
    dispatched: 'bg-blue-100 text-blue-800',
    en_route:   'bg-indigo-100 text-indigo-800',
    on_scene:   'bg-violet-100 text-violet-800',
    in_transit: 'bg-cyan-100 text-cyan-800',
    completed:  'bg-emerald-100 text-emerald-800',
    cancelled:  'bg-rose-100 text-rose-800',
};
const priorityDot = { low: 'bg-gray-300', normal: 'bg-blue-400', high: 'bg-amber-500', urgent: 'bg-red-500 animate-pulse' };
</script>

<template>
    <AppLayout title="Towing">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-xl text-gray-900">🚛 Towing Jobs</h2>
                    <p class="text-sm text-gray-500">Dispatch board and job history</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('towing.index')" class="px-3 py-1.5 text-sm rounded-lg bg-indigo-600 text-white">📋 List</Link>
                    <Link :href="route('towing.board')" class="px-3 py-1.5 text-sm rounded-lg border bg-white hover:bg-gray-50">📊 Board</Link>
                    <Link :href="route('towing.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">+ New Job</Link>
                </div>
            </div>
        </template>

        <div class="p-6 space-y-5">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl border p-4 text-center"><div class="text-2xl font-bold text-amber-600">{{ stats.pending }}</div><div class="text-xs text-gray-500">Pending Dispatch</div></div>
                <div class="bg-white rounded-xl border p-4 text-center"><div class="text-2xl font-bold text-blue-600">{{ stats.dispatched }}</div><div class="text-xs text-gray-500">Dispatched</div></div>
                <div class="bg-white rounded-xl border p-4 text-center"><div class="text-2xl font-bold text-violet-600">{{ stats.on_scene }}</div><div class="text-xs text-gray-500">On Scene</div></div>
                <div class="bg-white rounded-xl border p-4 text-center"><div class="text-2xl font-bold text-emerald-600">{{ stats.completed_today }}</div><div class="text-xs text-gray-500">Completed Today</div></div>
            </div>

            <div class="bg-white rounded-xl border p-3 flex gap-3">
                <input v-model="search" type="text" placeholder="Search by job#, caller, plate, ref#..."
                       class="flex-1 border-gray-300 rounded-lg text-sm" />
                <select v-model="status" class="border-gray-300 rounded-lg text-sm">
                    <option value="">All Statuses</option>
                    <option v-for="s in ['pending','dispatched','en_route','on_scene','in_transit','completed','cancelled']" :key="s" :value="s">{{ s }}</option>
                </select>
            </div>

            <div class="bg-white rounded-xl border overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-3 py-2 text-left">Job#</th>
                            <th class="px-3 py-2 text-left">Caller</th>
                            <th class="px-3 py-2 text-left">Vehicle</th>
                            <th class="px-3 py-2 text-left">Pickup → Dropoff</th>
                            <th class="px-3 py-2 text-left">Driver / Truck</th>
                            <th class="px-3 py-2 text-right">$</th>
                            <th class="px-3 py-2 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="j in jobs.data" :key="j.id" class="hover:bg-gray-50">
                            <td class="px-3 py-2 text-xs">
                                <Link :href="route('towing.show', j.id)" class="text-indigo-600 hover:text-indigo-800 font-medium">{{ j.job_number }}</Link>
                                <div class="flex items-center gap-1 mt-0.5">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="priorityDot[j.priority]"></span>
                                    <span class="text-[10px] text-gray-500 capitalize">{{ j.priority }}</span>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-sm">
                                <div>{{ j.customer ? `${j.customer.first_name} ${j.customer.last_name}` : (j.caller_name || '-') }}</div>
                                <div class="text-xs text-gray-500">{{ j.caller_phone || j.customer?.phone || '' }}</div>
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-700">
                                <div>{{ j.vehicle_year }} {{ j.vehicle_make }} {{ j.vehicle_model }}</div>
                                <div class="text-gray-500">{{ j.vehicle_plate || j.vehicle_vin?.slice(-6) }}</div>
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-700">
                                <div>📍 {{ j.pickup_address }}{{ j.pickup_city ? `, ${j.pickup_city}` : '' }}</div>
                                <div>🏁 {{ j.dropoff_address }}{{ j.dropoff_city ? `, ${j.dropoff_city}` : '' }}</div>
                            </td>
                            <td class="px-3 py-2 text-xs">
                                <div v-if="j.driver">🧑‍✈️ {{ j.driver.name }}</div>
                                <div v-if="j.truck" class="text-gray-500">🚛 {{ j.truck.name }}</div>
                                <span v-if="!j.driver && !j.truck" class="text-gray-400 italic">Unassigned</span>
                            </td>
                            <td class="px-3 py-2 text-sm text-right font-semibold">{{ j.billed_amount ? `$${Number(j.billed_amount).toFixed(2)}` : (j.quoted_amount ? `~$${Number(j.quoted_amount).toFixed(2)}` : '-') }}</td>
                            <td class="px-3 py-2"><span class="text-xs px-2 py-1 rounded-full capitalize" :class="statusColor[j.status]">{{ j.status?.replace('_',' ') }}</span></td>
                        </tr>
                        <tr v-if="!jobs.data?.length"><td colspan="7" class="px-4 py-8 text-center text-gray-400">No tow jobs.</td></tr>
                    </tbody>
                </table>
            </div>

            <div v-if="jobs.links?.length > 3" class="flex justify-center gap-1">
                <Link v-for="link in jobs.links" :key="link.label" :href="link.url || '#'"
                      class="px-3 py-1 text-sm rounded" :class="link.active ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100'" v-html="link.label" />
            </div>
        </div>
    </AppLayout>
</template>
