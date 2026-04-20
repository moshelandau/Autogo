<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({ vehicles: Object, locations: Array, filters: Object, stats: Object });
const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');

const applyFilters = () => {
    router.get(route('rental.vehicles.index'), { search: search.value, status: statusFilter.value }, { preserveState: true, replace: true });
};

watch(search, () => applyFilters());
watch(statusFilter, () => applyFilters());

const statusColors = {
    available: 'bg-green-100 text-green-800',
    rented: 'bg-blue-100 text-blue-800',
    maintenance: 'bg-orange-100 text-orange-800',
    out_of_service: 'bg-red-100 text-red-800',
    sold: 'bg-gray-100 text-gray-800',
};
</script>

<template>
    <AppLayout title="Fleet Vehicles">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Fleet Vehicles</h2>
                <Link :href="route('rental.vehicles.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">+ Add Vehicle</Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <!-- Stats -->
                <div class="grid grid-cols-4 gap-4">
                    <div class="bg-white rounded-lg shadow-sm p-3 text-center">
                        <div class="text-xl font-bold">{{ stats.total }}</div><div class="text-xs text-gray-500">Total</div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-3 text-center">
                        <div class="text-xl font-bold text-green-600">{{ stats.available }}</div><div class="text-xs text-gray-500">Available</div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-3 text-center">
                        <div class="text-xl font-bold text-blue-600">{{ stats.rented }}</div><div class="text-xs text-gray-500">Rented</div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-3 text-center">
                        <div class="text-xl font-bold text-orange-600">{{ stats.maintenance }}</div><div class="text-xs text-gray-500">Maintenance</div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white shadow-sm rounded-lg p-4 flex gap-4">
                    <input v-model="search" type="text" placeholder="Search vehicles..." class="flex-1 border-gray-300 rounded-md shadow-sm text-sm" />
                    <select v-model="statusFilter" class="border-gray-300 rounded-md shadow-sm text-sm">
                        <option value="">All Statuses</option>
                        <option value="available">Available</option>
                        <option value="rented">Rented</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>

                <!-- Table -->
                <div class="bg-white shadow-sm rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vehicle</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Plate</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Current Renter</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Daily Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="v in vehicles.data" :key="v.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <Link :href="route('rental.vehicles.show', v.id)" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                                        {{ v.year }} {{ v.make }} {{ v.model }}
                                    </Link>
                                    <div v-if="v.color" class="text-xs text-gray-500">{{ v.color }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 capitalize">{{ v.vehicle_class }}</td>
                                <td class="px-4 py-3 text-sm font-mono text-gray-600">{{ v.license_plate || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ v.location?.name || '-' }}</td>
                                <td class="px-4 py-3"><span class="px-2 py-1 text-xs rounded-full capitalize" :class="statusColors[v.status]">{{ v.status }}</span></td>
                                <td class="px-4 py-3 text-sm">{{ v.active_reservation?.customer ? `${v.active_reservation.customer.first_name} ${v.active_reservation.customer.last_name}` : '-' }}</td>
                                <td class="px-4 py-3 text-sm text-right font-medium">${{ v.daily_rate }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="vehicles.links?.length > 3" class="flex justify-center gap-1">
                    <Link v-for="link in vehicles.links" :key="link.label" :href="link.url || '#'"
                          class="px-3 py-1 text-sm rounded" :class="link.active ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100'" v-html="link.label" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
