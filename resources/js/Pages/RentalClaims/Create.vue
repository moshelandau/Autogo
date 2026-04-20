<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';
import CustomerSelect from '@/Components/CustomerSelect.vue';

const props = defineProps({ customers: Array, vehicles: Array });

const form = useForm({
    customer_id: '', vehicle_id: '', brand: 'high_rental', priority: 'medium',
    damage_description: '', incident_date: '', damage_amount: '', deductible_amount: '',
    insurance_company: '', insurance_claim_number: '', notes: '',
});

const submit = () => form.post(route('rental-claims.store'));
</script>

<template>
    <AppLayout title="New Rental Claim">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('rental-claims.index')" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                <h2 class="font-bold text-xl text-gray-900">New Rental Claim</h2>
            </div>
        </template>

        <div class="p-6">
            <div class="max-w-3xl mx-auto">
                <form @submit.prevent="submit" class="bg-white rounded-xl border p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Customer *</label>
                            <CustomerSelect v-model="form.customer_id" class="mt-1" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Vehicle</label>
                            <select v-model="form.vehicle_id" class="mt-1 block w-full border-gray-300 rounded-lg text-sm">
                                <option value="">Select</option>
                                <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.year }} {{ v.make }} {{ v.model }} - {{ v.license_plate }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Brand</label>
                            <select v-model="form.brand" class="mt-1 block w-full border-gray-300 rounded-lg text-sm">
                                <option value="high_rental">High Rental</option>
                                <option value="mm_car_rental">M&M Car Rental</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Priority</label>
                            <select v-model="form.priority" class="mt-1 block w-full border-gray-300 rounded-lg text-sm">
                                <option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Damage Description</label>
                        <textarea v-model="form.damage_description" rows="3" class="mt-1 block w-full border-gray-300 rounded-lg text-sm"></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div><label class="block text-sm font-medium text-gray-700">Incident Date</label><input v-model="form.incident_date" type="date" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                        <div><label class="block text-sm font-medium text-gray-700">Damage Amount</label><input v-model="form.damage_amount" type="number" step="0.01" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                        <div><label class="block text-sm font-medium text-gray-700">Deductible</label><input v-model="form.deductible_amount" type="number" step="0.01" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><label class="block text-sm font-medium text-gray-700">Insurance Company</label><input v-model="form.insurance_company" type="text" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                        <div><label class="block text-sm font-medium text-gray-700">Insurance Claim #</label><input v-model="form.insurance_claim_number" type="text" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                    </div>

                    <div><label class="block text-sm font-medium text-gray-700">Notes</label><textarea v-model="form.notes" rows="2" class="mt-1 block w-full border-gray-300 rounded-lg text-sm"></textarea></div>

                    <div class="flex justify-end gap-3">
                        <Link :href="route('rental-claims.index')" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg">Cancel</Link>
                        <button type="submit" :disabled="form.processing" class="px-4 py-2 text-sm text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">Create Claim</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
