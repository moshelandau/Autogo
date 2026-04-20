<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({ manifest: Object, fleet: Object });

const fmt = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v || 0);
</script>

<template>
    <AppLayout title="Car Rental">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Car Rental - Daily Manifest</h2>
                <div class="flex gap-3">
                    <Link :href="route('rental.reservations.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">+ New Reservation</Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Fleet Stats -->
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div class="bg-white rounded-lg shadow-sm p-4 text-center">
                        <div class="text-2xl font-bold text-gray-900">{{ fleet.total }}</div>
                        <div class="text-xs text-gray-500">Total Fleet</div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-4 text-center">
                        <div class="text-2xl font-bold text-green-600">{{ fleet.available }}</div>
                        <div class="text-xs text-gray-500">Available</div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-4 text-center">
                        <div class="text-2xl font-bold text-blue-600">{{ fleet.rented }}</div>
                        <div class="text-xs text-gray-500">On Rent</div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-4 text-center">
                        <div class="text-2xl font-bold text-orange-600">{{ fleet.maintenance }}</div>
                        <div class="text-xs text-gray-500">Maintenance</div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-4 text-center">
                        <div class="text-2xl font-bold" :class="fleet.utilization_pct > 60 ? 'text-green-600' : 'text-yellow-600'">{{ fleet.utilization_pct }}%</div>
                        <div class="text-xs text-gray-500">Utilization</div>
                    </div>
                </div>

                <!-- Today's Pickups -->
                <div class="bg-white shadow-sm rounded-lg">
                    <div class="p-4 border-b flex justify-between items-center">
                        <h3 class="font-semibold text-gray-800">Today's Pickups ({{ manifest.pickups?.length || 0 }})</h3>
                        <span class="text-sm text-gray-500">{{ manifest.date }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Res #</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vehicle</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="r in manifest.pickups" :key="r.id" class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm">{{ new Date(r.pickup_date).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }) }}</td>
                                    <td class="px-4 py-3">
                                        <Link :href="route('rental.reservations.show', r.id)" class="text-indigo-600 hover:text-indigo-900 text-sm font-mono">{{ r.reservation_number }}</Link>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium">{{ r.customer?.first_name }} {{ r.customer?.last_name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ r.vehicle ? `${r.vehicle.year} ${r.vehicle.make} ${r.vehicle.model}` : r.vehicle_class || '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ r.pickup_location?.name || '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-right">{{ fmt(r.total_price) }}</td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 text-xs rounded-full" :class="r.status === 'rental' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'">{{ r.status }}</span>
                                    </td>
                                </tr>
                                <tr v-if="!manifest.pickups?.length"><td colspan="7" class="px-4 py-6 text-center text-gray-500">No pickups scheduled today.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Today's Returns -->
                <div class="bg-white shadow-sm rounded-lg">
                    <div class="p-4 border-b">
                        <h3 class="font-semibold text-gray-800">Today's Returns ({{ manifest.returns?.length || 0 }})</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Res #</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vehicle</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Outstanding</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Days</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="r in manifest.returns" :key="r.id" class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm">{{ new Date(r.return_date).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }) }}</td>
                                    <td class="px-4 py-3">
                                        <Link :href="route('rental.reservations.show', r.id)" class="text-indigo-600 hover:text-indigo-900 text-sm font-mono">{{ r.reservation_number }}</Link>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium">{{ r.customer?.first_name }} {{ r.customer?.last_name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ r.vehicle ? `${r.vehicle.year} ${r.vehicle.make} ${r.vehicle.model}` : '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-right" :class="r.outstanding_balance > 0 ? 'text-red-600 font-bold' : 'text-green-600'">{{ fmt(r.outstanding_balance) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ r.total_days }}</td>
                                </tr>
                                <tr v-if="!manifest.returns?.length"><td colspan="6" class="px-4 py-6 text-center text-gray-500">No returns scheduled today.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="flex gap-3 flex-wrap">
                    <Link :href="route('rental.vehicles.index')" class="px-4 py-2 bg-white shadow-sm rounded-md text-sm text-gray-700 hover:bg-gray-50">Fleet Vehicles</Link>
                    <Link :href="route('rental.reservations.index')" class="px-4 py-2 bg-white shadow-sm rounded-md text-sm text-gray-700 hover:bg-gray-50">All Reservations</Link>
                    <Link :href="route('rental.calendar')" class="px-4 py-2 bg-white shadow-sm rounded-md text-sm text-gray-700 hover:bg-gray-50">Calendar</Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
