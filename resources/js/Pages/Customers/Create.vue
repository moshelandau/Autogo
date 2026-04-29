<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';

const form = useForm({
    first_name: '', last_name: '', email: '',
    phones: [{ phone: '', label: 'Mobile', is_primary: true, is_sms_capable: true }],
    address: '', address_2: '', city: '', state: '', zip: '',
    drivers_license_number: '', dl_expiration: '', dl_state: '', date_of_birth: '',
    insurance_company: '', insurance_policy: '', credit_score: '', notes: '',
});

const addPhoneRow = () => form.phones.push({ phone: '', label: 'Mobile', is_primary: false, is_sms_capable: true });
const removePhoneRow = (i) => {
    form.phones.splice(i, 1);
    if (!form.phones.some((p) => p.is_primary) && form.phones.length) form.phones[0].is_primary = true;
};
const setPrimary = (i) => form.phones.forEach((p, idx) => (p.is_primary = idx === i));

const submit = () => form.post(route('customers.store'));
</script>

<template>
    <AppLayout title="New Customer">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('customers.index')" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">New Customer</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="bg-white shadow-sm rounded-lg p-6 space-y-6">
                    <!-- Personal Info -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Personal Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">First Name *</label>
                                <input v-model="form.first_name" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                                <p v-if="form.errors.first_name" class="mt-1 text-sm text-red-600">{{ form.errors.first_name }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Last Name *</label>
                                <input v-model="form.last_name" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                                <p v-if="form.errors.last_name" class="mt-1 text-sm text-red-600">{{ form.errors.last_name }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Email</label>
                                <input v-model="form.email" type="email" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                            </div>
                        </div>

                        <!-- Phones (multiple, each with label + primary + SMS-capable flag) -->
                        <div class="mt-4">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-sm font-medium text-gray-700">Phone numbers</label>
                                <button type="button" @click="addPhoneRow"
                                        class="text-xs text-indigo-600 hover:text-indigo-800">+ Add another</button>
                            </div>
                            <div class="space-y-2">
                                <div v-for="(p, i) in form.phones" :key="i" class="flex flex-wrap items-center gap-2 p-2 bg-gray-50 rounded-md border">
                                    <input v-model="p.phone" type="text" placeholder="Phone *"
                                           class="border-gray-300 rounded-md text-sm w-40 focus:border-indigo-500 focus:ring-indigo-500" />
                                    <select v-model="p.label" class="border-gray-300 rounded-md text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option>Mobile</option><option>Home</option><option>Work</option><option>Other</option>
                                    </select>
                                    <label class="flex items-center gap-1 text-xs text-gray-700">
                                        <input type="radio" :checked="p.is_primary" @change="setPrimary(i)" class="text-indigo-600 focus:ring-indigo-500" />
                                        Primary
                                    </label>
                                    <label class="flex items-center gap-1 text-xs text-gray-700">
                                        <input v-model="p.is_sms_capable" type="checkbox" class="rounded text-indigo-600 focus:ring-indigo-500" />
                                        Can receive SMS
                                    </label>
                                    <button v-if="form.phones.length > 1" type="button" @click="removePhoneRow(i)"
                                            class="ml-auto text-xs text-red-600 hover:text-red-800">Remove</button>
                                </div>
                            </div>
                            <p v-if="form.errors.phones" class="mt-1 text-sm text-red-600">{{ form.errors.phones }}</p>
                            <p class="mt-1 text-[11px] text-gray-500">Mark a phone "Can receive SMS" only if it's a mobile line — voice-only landlines should be unchecked.</p>
                        </div>
                    </div>

                    <!-- Address -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Address</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">Address</label>
                                <input v-model="form.address" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">City</label>
                                <input v-model="form.city" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">State</label>
                                    <input v-model="form.state" type="text" maxlength="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">ZIP</label>
                                    <input v-model="form.zip" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Driver's License -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Driver's License & Insurance</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">DL Number</label>
                                <input v-model="form.drivers_license_number" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">DL State</label>
                                <input v-model="form.dl_state" type="text" maxlength="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">DL Expiration</label>
                                <input v-model="form.dl_expiration" type="date" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Insurance Company</label>
                                <input v-model="form.insurance_company" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Insurance Policy #</label>
                                <input v-model="form.insurance_policy" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Credit Score</label>
                                <input v-model="form.credit_score" type="number" min="300" max="850" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Notes</label>
                        <textarea v-model="form.notes" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"></textarea>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Link :href="route('customers.index')" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">Cancel</Link>
                        <button type="submit" :disabled="form.processing"
                                class="px-4 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50">
                            {{ form.processing ? 'Saving...' : 'Create Customer' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
