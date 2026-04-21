<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import CustomerSelect from '@/Components/CustomerSelect.vue';

const props = defineProps({ customers: Array, prefill: { type: Object, default: () => ({}) } });

const form = useForm({
    customer_id: props.prefill.customer_id || '',
    story: '', accident_date: '', accident_location: '', customer_phone: '',
    vehicle_year: '', vehicle_make: '', vehicle_model: '', vehicle_vin: '', vehicle_plate: '',
    estimate_amount: '', towing_amount: '', notes: '',
    insurance_entries: [{ insurance_company: '', claim_number: '' }],
});

const addInsurance = () => form.insurance_entries.push({ insurance_company: '', claim_number: '' });
const removeInsurance = (i) => form.insurance_entries.splice(i, 1);

const submit = () => form.post(route('claims.store'));
</script>

<template>
    <AppLayout title="New Claim">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('claims.index')" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                <h2 class="font-bold text-xl text-gray-900">New Insurance Claim</h2>
            </div>
        </template>

        <div class="p-6">
            <div class="max-w-3xl mx-auto">
                <form @submit.prevent="submit" class="bg-white rounded-xl border p-6 space-y-6">
                    <!-- Customer -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Customer *</label>
                        <CustomerSelect v-model="form.customer_id" class="mt-1" />
                        <p v-if="form.errors.customer_id" class="mt-1 text-sm text-red-600">{{ form.errors.customer_id }}</p>
                    </div>

                    <!-- Insurance Entries -->
                    <div>
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="text-lg font-medium text-gray-900">Insurance Information</h3>
                            <button type="button" @click="addInsurance" class="text-sm text-indigo-600 hover:text-indigo-800">+ Add Insurance</button>
                        </div>
                        <div v-for="(entry, i) in form.insurance_entries" :key="i" class="grid grid-cols-5 gap-3 mb-2">
                            <div class="col-span-2">
                                <input v-model="entry.insurance_company" type="text" placeholder="Insurance Company *" class="block w-full border-gray-300 rounded-lg text-sm" />
                            </div>
                            <div class="col-span-2">
                                <input v-model="entry.claim_number" type="text" placeholder="Claim Number *" class="block w-full border-gray-300 rounded-lg text-sm" />
                            </div>
                            <div class="flex items-center">
                                <button v-if="form.insurance_entries.length > 1" type="button" @click="removeInsurance(i)" class="text-red-500 hover:text-red-700 text-sm">Remove</button>
                            </div>
                        </div>
                    </div>

                    <!-- Accident Info -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-3">Accident Information</h3>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Story (What happened?)</label>
                                <textarea v-model="form.story" rows="3" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" placeholder="Describe the accident..."></textarea>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div><label class="block text-sm font-medium text-gray-700">Accident Date</label><input v-model="form.accident_date" type="date" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                                <div><label class="block text-sm font-medium text-gray-700">Location</label><input v-model="form.accident_location" type="text" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                                <div><label class="block text-sm font-medium text-gray-700">Customer Phone</label><input v-model="form.customer_phone" type="text" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                            </div>
                        </div>
                    </div>

                    <!-- Vehicle -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-3">Vehicle Information</h3>
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                            <div><input v-model="form.vehicle_year" type="text" placeholder="Year" class="block w-full border-gray-300 rounded-lg text-sm" /></div>
                            <div><input v-model="form.vehicle_make" type="text" placeholder="Make" class="block w-full border-gray-300 rounded-lg text-sm" /></div>
                            <div><input v-model="form.vehicle_model" type="text" placeholder="Model" class="block w-full border-gray-300 rounded-lg text-sm" /></div>
                            <div><input v-model="form.vehicle_vin" type="text" placeholder="VIN" class="block w-full border-gray-300 rounded-lg text-sm" /></div>
                            <div><input v-model="form.vehicle_plate" type="text" placeholder="Plate" class="block w-full border-gray-300 rounded-lg text-sm" /></div>
                        </div>
                    </div>

                    <!-- Financial -->
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-medium text-gray-700">Estimate Amount</label><input v-model="form.estimate_amount" type="number" step="0.01" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                        <div><label class="block text-sm font-medium text-gray-700">Towing Amount</label><input v-model="form.towing_amount" type="number" step="0.01" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                    </div>

                    <div><label class="block text-sm font-medium text-gray-700">Notes</label><textarea v-model="form.notes" rows="2" class="mt-1 block w-full border-gray-300 rounded-lg text-sm"></textarea></div>

                    <div class="flex justify-end gap-3">
                        <Link :href="route('claims.index')" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</Link>
                        <button type="submit" :disabled="form.processing" class="px-4 py-2 text-sm text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {{ form.processing ? 'Creating...' : 'Create Claim' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
