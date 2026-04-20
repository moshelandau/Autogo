<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';

const props = defineProps({ customer: Object });

const form = useForm({
    first_name: props.customer.first_name,
    last_name: props.customer.last_name,
    email: props.customer.email || '',
    phone: props.customer.phone || '',
    secondary_phone: props.customer.secondary_phone || '',
    can_receive_sms: props.customer.can_receive_sms,
    address: props.customer.address || '',
    address_2: props.customer.address_2 || '',
    city: props.customer.city || '',
    state: props.customer.state || '',
    zip: props.customer.zip || '',
    drivers_license_number: props.customer.drivers_license_number || '',
    dl_expiration: props.customer.dl_expiration || '',
    dl_state: props.customer.dl_state || '',
    date_of_birth: props.customer.date_of_birth || '',
    insurance_company: props.customer.insurance_company || '',
    insurance_policy: props.customer.insurance_policy || '',
    credit_score: props.customer.credit_score || '',
    notes: props.customer.notes || '',
    is_active: props.customer.is_active,
});

const submit = () => form.put(route('customers.update', props.customer.id));
</script>

<template>
    <AppLayout title="Edit Customer">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('customers.show', customer.id)" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit: {{ customer.first_name }} {{ customer.last_name }}</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="bg-white shadow-sm rounded-lg p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">First Name *</label>
                            <input v-model="form.first_name" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                            <p v-if="form.errors.first_name" class="mt-1 text-sm text-red-600">{{ form.errors.first_name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Last Name *</label>
                            <input v-model="form.last_name" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Phone</label>
                            <input v-model="form.phone" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email</label>
                            <input v-model="form.email" type="email" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                        </div>
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
                        <div>
                            <label class="block text-sm font-medium text-gray-700">DL Number</label>
                            <input v-model="form.drivers_license_number" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Insurance Company</label>
                            <input v-model="form.insurance_company" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Credit Score</label>
                            <input v-model="form.credit_score" type="number" min="300" max="850" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Notes</label>
                        <textarea v-model="form.notes" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"></textarea>
                    </div>
                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2">
                            <input v-model="form.can_receive_sms" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                            <span class="text-sm text-gray-700">Can receive SMS</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                            <span class="text-sm text-gray-700">Active</span>
                        </label>
                    </div>
                    <div class="flex justify-end gap-3">
                        <Link :href="route('customers.show', customer.id)" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">Cancel</Link>
                        <button type="submit" :disabled="form.processing" class="px-4 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50">
                            {{ form.processing ? 'Saving...' : 'Save Changes' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
