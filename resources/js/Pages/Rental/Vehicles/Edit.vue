<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';

const props = defineProps({ vehicle: Object, locations: Array });

const form = useForm({
    vin: props.vehicle.vin || '', year: props.vehicle.year, make: props.vehicle.make, model: props.vehicle.model,
    trim: props.vehicle.trim || '', color: props.vehicle.color || '', license_plate: props.vehicle.license_plate || '',
    vehicle_class: props.vehicle.vehicle_class, status: props.vehicle.status,
    location_id: props.vehicle.location_id || '', odometer: props.vehicle.odometer || 0,
    fuel_level: props.vehicle.fuel_level || '',
    daily_rate: props.vehicle.daily_rate, weekly_rate: props.vehicle.weekly_rate || 0, monthly_rate: props.vehicle.monthly_rate || 0,
    notes: props.vehicle.notes || '',
});

const submit = () => form.put(route('rental.vehicles.update', props.vehicle.id));
</script>

<template>
    <AppLayout title="Edit Vehicle">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('rental.vehicles.show', vehicle.id)" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit: {{ vehicle.year }} {{ vehicle.make }} {{ vehicle.model }}</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="bg-white shadow-sm rounded-lg p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-3"><label class="block text-sm font-medium text-gray-700">VIN</label><input v-model="form.vin" type="text" maxlength="17" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" /></div>
                        <div><label class="block text-sm font-medium text-gray-700">Year *</label><input v-model="form.year" type="number" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" /></div>
                        <div><label class="block text-sm font-medium text-gray-700">Make *</label><input v-model="form.make" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" /></div>
                        <div><label class="block text-sm font-medium text-gray-700">Model *</label><input v-model="form.model" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" /></div>
                        <div><label class="block text-sm font-medium text-gray-700">Trim</label><input v-model="form.trim" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" /></div>
                        <div><label class="block text-sm font-medium text-gray-700">Color</label><input v-model="form.color" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" /></div>
                        <div><label class="block text-sm font-medium text-gray-700">Plate</label><input v-model="form.license_plate" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" /></div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Class</label>
                            <select v-model="form.vehicle_class" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="car">Car</option><option value="suv">SUV</option><option value="minivan">Minivan</option><option value="truck">Truck</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <select v-model="form.status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="available">Available</option><option value="rented">Rented</option><option value="maintenance">Maintenance</option><option value="out_of_service">Out of Service</option><option value="sold">Sold</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Location</label>
                            <select v-model="form.location_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="">Select</option><option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
                            </select>
                        </div>
                        <div><label class="block text-sm font-medium text-gray-700">Odometer</label><input v-model="form.odometer" type="number" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" /></div>
                        <div><label class="block text-sm font-medium text-gray-700">Fuel Level</label><select v-model="form.fuel_level" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="">Select</option><option value="full">Full</option><option value="3/4">3/4</option><option value="1/2">1/2</option><option value="1/4">1/4</option><option value="empty">Empty</option></select></div>
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div><label class="block text-sm font-medium text-gray-700">Daily Rate</label><input v-model="form.daily_rate" type="number" step="0.01" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" /></div>
                        <div><label class="block text-sm font-medium text-gray-700">Weekly Rate</label><input v-model="form.weekly_rate" type="number" step="0.01" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" /></div>
                        <div><label class="block text-sm font-medium text-gray-700">Monthly Rate</label><input v-model="form.monthly_rate" type="number" step="0.01" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" /></div>
                    </div>
                    <div><label class="block text-sm font-medium text-gray-700">Notes</label><textarea v-model="form.notes" rows="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></textarea></div>
                    <div class="flex justify-end gap-3">
                        <Link :href="route('rental.vehicles.show', vehicle.id)" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">Cancel</Link>
                        <button type="submit" :disabled="form.processing" class="px-4 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
