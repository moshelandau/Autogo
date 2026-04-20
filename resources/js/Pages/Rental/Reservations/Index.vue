<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({ reservations: Object, filters: Object });
const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');

const applyFilters = () => {
    router.get(route('rental.reservations.index'), { search: search.value, status: statusFilter.value }, { preserveState: true, replace: true });
};

watch(search, () => applyFilters());
watch(statusFilter, () => applyFilters());

const fmt = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v || 0);
const statusColors = { open: 'bg-blue-100 text-blue-800', rental: 'bg-green-100 text-green-800', completed: 'bg-gray-100 text-gray-800', cancelled: 'bg-red-100 text-red-800', no_show: 'bg-yellow-100 text-yellow-800' };
</script>

<template>
    <AppLayout title="Reservations">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Reservations</h2>
                <Link :href="route('rental.reservations.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">+ New Reservation</Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <!-- Filters -->
                <div class="bg-white shadow-sm rounded-lg p-4 flex gap-4">
                    <input v-model="search" type="text" placeholder="Search by name, phone, or res #..." class="flex-1 border-gray-300 rounded-md shadow-sm text-sm" />
                    <select v-model="statusFilter" class="border-gray-300 rounded-md shadow-sm text-sm">
                        <option value="">All Statuses</option>
                        <option value="open">Open</option>
                        <option value="rental">On Rent</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <!-- Table -->
                <div class="bg-white shadow-sm rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Res #</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pickup</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Return</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vehicle</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Balance</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="r in reservations.data" :key="r.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3"><Link :href="route('rental.reservations.show', r.id)" class="text-indigo-600 hover:text-indigo-900 text-sm font-mono">{{ r.reservation_number }}</Link></td>
                                <td class="px-4 py-3 text-sm font-medium">{{ r.customer?.first_name }} {{ r.customer?.last_name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ r.pickup_date?.split('T')[0] }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ r.return_date?.split('T')[0] }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ r.vehicle ? `${r.vehicle.make} ${r.vehicle.model}` : r.vehicle_class || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ r.pickup_location?.name || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-right">{{ fmt(r.total_price) }}</td>
                                <td class="px-4 py-3 text-sm text-right" :class="r.outstanding_balance > 0 ? 'text-red-600 font-bold' : ''">{{ fmt(r.outstanding_balance) }}</td>
                                <td class="px-4 py-3"><span class="px-2 py-1 text-xs rounded-full capitalize" :class="statusColors[r.status]">{{ r.status }}</span></td>
                            </tr>
                            <tr v-if="!reservations.data?.length"><td colspan="9" class="px-4 py-8 text-center text-gray-500">No reservations found.</td></tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="reservations.links?.length > 3" class="flex justify-center gap-1">
                    <Link v-for="link in reservations.links" :key="link.label" :href="link.url || '#'"
                          class="px-3 py-1 text-sm rounded" :class="link.active ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100'" v-html="link.label" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
