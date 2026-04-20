<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';

const props = defineProps({ locations: Array });

const form = useForm({
    vin: '', year: new Date().getFullYear(), make: '', model: '', trim: '', color: '',
    license_plate: '', vehicle_class: 'suv', location_id: '', odometer: 0,
    daily_rate: 65, weekly_rate: 390, monthly_rate: 1500, notes: '',
});

const submit = () => form.post(route('rental.vehicles.store'));
</script>

<template>
    <AppLayout title="Add Vehicle">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('rental.vehicles.index')" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Add Vehicle to Fleet</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="bg-white shadow-sm rounded-lg p-6 space-y-6">
                    <div>
                        <h3 class="text-lg font-medium mb-4">Vehicle Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="md:col-span-3">
                                <label class="block text-sm font-medium text-gray-700">VIN</label>
                                <input v-model="form.vin" type="text" maxlength="17" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="Optional - 17 characters" />
                                <p v-if="form.errors.vin" class="mt-1 text-sm text-red-600">{{ form.errors.vin }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Year *</label>
                                <input v-model="form.year" type="number" min="1990" max="2030" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Make *</label>
                                <input v-model="form.make" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="e.g. Honda" />
                                <p v-if="form.errors.make" class="mt-1 text-sm text-red-600">{{ form.errors.make }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Model *</label>
                                <input v-model="form.model" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="e.g. Odyssey" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Trim</label>
                                <input v-model="form.trim" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Color</label>
                                <input v-model="form.color" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">License Plate</label>
                                <input v-model="form.license_plate" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Class *</label>
                                <select v-model="form.vehicle_class" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                    <option value="car">Car</option>
                                    <option value="suv">SUV (5 Pass)</option>
                                    <option value="minivan">Minivan</option>
                                    <option value="truck">Truck</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Location</label>
                                <select v-model="form.location_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                    <option value="">Select location</option>
                                    <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Odometer</label>
                                <input v-model="form.odometer" type="number" min="0" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-medium mb-4">Rates</h3>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Daily Rate *</label>
                                <div class="mt-1 relative">
                                    <span class="absolute left-3 top-2 text-gray-500 text-sm">$</span>
                                    <input v-model="form.daily_rate" type="number" step="0.01" min="0" class="block w-full pl-7 border-gray-300 rounded-md shadow-sm text-sm" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Weekly Rate</label>
                                <div class="mt-1 relative">
                                    <span class="absolute left-3 top-2 text-gray-500 text-sm">$</span>
                                    <input v-model="form.weekly_rate" type="number" step="0.01" min="0" class="block w-full pl-7 border-gray-300 rounded-md shadow-sm text-sm" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Monthly Rate</label>
                                <div class="mt-1 relative">
                                    <span class="absolute left-3 top-2 text-gray-500 text-sm">$</span>
                                    <input v-model="form.monthly_rate" type="number" step="0.01" min="0" class="block w-full pl-7 border-gray-300 rounded-md shadow-sm text-sm" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Notes</label>
                        <textarea v-model="form.notes" rows="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></textarea>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Link :href="route('rental.vehicles.index')" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">Cancel</Link>
                        <button type="submit" :disabled="form.processing" class="px-4 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50">
                            {{ form.processing ? 'Saving...' : 'Add Vehicle' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
