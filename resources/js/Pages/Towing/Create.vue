<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import CustomerSelect from '@/Components/CustomerSelect.vue';

const props = defineProps({ trucks: Array, drivers: Array });

const form = useForm({
    customer_id: '', caller_name: '', caller_phone: '',
    insurance_company: '', reference_number: '',
    vehicle_year: '', vehicle_make: '', vehicle_model: '', vehicle_color: '', vehicle_plate: '', vehicle_vin: '',
    pickup_address: '', pickup_city: '', pickup_state: 'NY', pickup_zip: '',
    dropoff_address: '', dropoff_city: '', dropoff_state: 'NY', dropoff_zip: '',
    reason: 'accident', priority: 'normal',
    quoted_amount: '',
    tow_truck_id: '', tow_driver_id: '',
    notes: '',
});

const submit = () => form.post(route('towing.store'));
</script>

<template>
    <AppLayout title="New Tow Job">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('towing.index')" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                <h2 class="font-bold text-xl text-gray-900">🚛 New Tow Job</h2>
            </div>
        </template>

        <form @submit.prevent="submit" class="p-6 max-w-5xl mx-auto space-y-5">
            <div class="bg-white rounded-xl border p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-700">Customer (optional — pick if known)</label>
                    <CustomerSelect v-model="form.customer_id" class="mt-1" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Priority</label>
                    <select v-model="form.priority" class="mt-1 block w-full border-gray-300 rounded-lg text-sm">
                        <option value="low">Low</option>
                        <option value="normal">Normal</option>
                        <option value="high">High</option>
                        <option value="urgent">🚨 Urgent</option>
                    </select>
                </div>
                <div><label class="block text-xs font-medium text-gray-700">Caller name</label><input v-model="form.caller_name" class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
                <div><label class="block text-xs font-medium text-gray-700">Caller phone</label><input v-model="form.caller_phone" class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Reason</label>
                    <select v-model="form.reason" class="mt-1 w-full border-gray-300 rounded-lg text-sm">
                        <option value="accident">Accident</option>
                        <option value="breakdown">Breakdown</option>
                        <option value="repo">Repo</option>
                        <option value="illegal_parking">Illegal Parking</option>
                        <option value="transport">Transport</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div><label class="block text-xs font-medium text-gray-700">Insurance company</label><input v-model="form.insurance_company" class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
                <div><label class="block text-xs font-medium text-gray-700">Reference / claim #</label><input v-model="form.reference_number" class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
                <div><label class="block text-xs font-medium text-gray-700">Quoted $</label><input v-model="form.quoted_amount" type="number" step="0.01" class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
            </div>

            <div class="bg-white rounded-xl border p-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">🚗 Vehicle</h3>
                <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
                    <div><label class="block text-xs">Year</label><input v-model="form.vehicle_year" class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
                    <div><label class="block text-xs">Make</label><input v-model="form.vehicle_make" class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
                    <div><label class="block text-xs">Model</label><input v-model="form.vehicle_model" class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
                    <div><label class="block text-xs">Color</label><input v-model="form.vehicle_color" class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
                    <div><label class="block text-xs">Plate</label><input v-model="form.vehicle_plate" class="mt-1 w-full border-gray-300 rounded-lg text-sm uppercase" /></div>
                    <div><label class="block text-xs">VIN</label><input v-model="form.vehicle_vin" maxlength="17" class="mt-1 w-full border-gray-300 rounded-lg text-sm uppercase" /></div>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-5">
                <div class="bg-white rounded-xl border p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">📍 Pickup *</h3>
                    <input v-model="form.pickup_address" placeholder="Street address" class="w-full border-gray-300 rounded-lg text-sm mb-2" required />
                    <div class="grid grid-cols-3 gap-2">
                        <input v-model="form.pickup_city" placeholder="City" class="border-gray-300 rounded-lg text-sm" />
                        <input v-model="form.pickup_state" placeholder="ST" maxlength="2" class="border-gray-300 rounded-lg text-sm uppercase" />
                        <input v-model="form.pickup_zip" placeholder="Zip" class="border-gray-300 rounded-lg text-sm" />
                    </div>
                </div>
                <div class="bg-white rounded-xl border p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">🏁 Drop-off *</h3>
                    <input v-model="form.dropoff_address" placeholder="Street address (or shop)" class="w-full border-gray-300 rounded-lg text-sm mb-2" required />
                    <div class="grid grid-cols-3 gap-2">
                        <input v-model="form.dropoff_city" placeholder="City" class="border-gray-300 rounded-lg text-sm" />
                        <input v-model="form.dropoff_state" placeholder="ST" maxlength="2" class="border-gray-300 rounded-lg text-sm uppercase" />
                        <input v-model="form.dropoff_zip" placeholder="Zip" class="border-gray-300 rounded-lg text-sm" />
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700">Truck</label>
                    <select v-model="form.tow_truck_id" class="mt-1 w-full border-gray-300 rounded-lg text-sm">
                        <option value="">— Unassigned —</option>
                        <option v-for="t in trucks" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Driver</label>
                    <select v-model="form.tow_driver_id" class="mt-1 w-full border-gray-300 rounded-lg text-sm">
                        <option value="">— Unassigned —</option>
                        <option v-for="d in drivers" :key="d.id" :value="d.id">{{ d.name }} ({{ d.phone }})</option>
                    </select>
                </div>
                <div class="md:col-span-2"><label class="block text-xs font-medium text-gray-700">Notes</label><textarea v-model="form.notes" rows="3" class="mt-1 w-full border-gray-300 rounded-lg text-sm"></textarea></div>
            </div>

            <div class="flex justify-end gap-3">
                <Link :href="route('towing.index')" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg">Cancel</Link>
                <button type="submit" :disabled="form.processing" class="bg-indigo-600 text-white px-6 py-2 rounded-lg text-sm hover:bg-indigo-700 disabled:opacity-50">
                    {{ form.processing ? 'Creating…' : 'Create Tow Job' }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
