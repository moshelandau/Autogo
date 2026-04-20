<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({ vehicle: Object });
const fmt = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v || 0);

const statusColors = {
    available: 'bg-green-100 text-green-800',
    rented: 'bg-blue-100 text-blue-800',
    maintenance: 'bg-orange-100 text-orange-800',
    out_of_service: 'bg-red-100 text-red-800',
};
</script>

<template>
    <AppLayout title="Vehicle Details">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('rental.vehicles.index')" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        {{ vehicle.year }} {{ vehicle.make }} {{ vehicle.model }} {{ vehicle.trim || '' }}
                    </h2>
                    <span class="px-2 py-1 text-xs rounded-full capitalize" :class="statusColors[vehicle.status] || 'bg-gray-100'">{{ vehicle.status }}</span>
                </div>
                <Link :href="route('rental.vehicles.edit', vehicle.id)" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">Edit</Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <h3 class="text-lg font-medium mb-4">Vehicle Info</h3>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between"><dt class="text-gray-500">VIN</dt><dd class="font-mono">{{ vehicle.vin || '-' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">License Plate</dt><dd class="font-mono">{{ vehicle.license_plate || '-' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Class</dt><dd class="capitalize">{{ vehicle.vehicle_class }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Color</dt><dd>{{ vehicle.color || '-' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Location</dt><dd>{{ vehicle.location?.name || '-' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Odometer</dt><dd>{{ vehicle.odometer?.toLocaleString() }} mi</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Fuel</dt><dd>{{ vehicle.fuel_level || '-' }}</dd></div>
                        </dl>
                    </div>
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <h3 class="text-lg font-medium mb-4">Rates</h3>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between"><dt class="text-gray-500">Daily</dt><dd class="font-bold text-lg">{{ fmt(vehicle.daily_rate) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Weekly</dt><dd>{{ fmt(vehicle.weekly_rate) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Monthly</dt><dd>{{ fmt(vehicle.monthly_rate) }}</dd></div>
                        </dl>
                    </div>
                </div>

                <!-- Recent Reservations -->
                <div class="bg-white shadow-sm rounded-lg">
                    <div class="p-4 border-b"><h3 class="font-semibold">Recent Reservations</h3></div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Res #</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Dates</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="r in vehicle.reservations" :key="r.id">
                                    <td class="px-4 py-2"><Link :href="route('rental.reservations.show', r.id)" class="text-indigo-600 text-sm font-mono">{{ r.reservation_number }}</Link></td>
                                    <td class="px-4 py-2 text-sm">{{ r.customer?.first_name }} {{ r.customer?.last_name }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600">{{ r.pickup_date?.split('T')[0] }} - {{ r.return_date?.split('T')[0] }}</td>
                                    <td class="px-4 py-2"><span class="px-2 py-1 text-xs rounded-full capitalize" :class="{'bg-green-100 text-green-800': r.status === 'completed', 'bg-blue-100 text-blue-800': r.status === 'rental', 'bg-gray-100 text-gray-800': r.status === 'open'}">{{ r.status }}</span></td>
                                    <td class="px-4 py-2 text-sm text-right">{{ fmt(r.total_price) }}</td>
                                </tr>
                                <tr v-if="!vehicle.reservations?.length"><td colspan="5" class="px-4 py-6 text-center text-gray-500">No reservations yet.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
