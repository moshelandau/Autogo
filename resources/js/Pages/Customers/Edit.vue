<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';

const props = defineProps({ customer: Object });

// Seed from customer.phones (multi-row table). Fall back to legacy
// phone/secondary_phone fields if a customer somehow predates the
// customer_phones backfill, so the Edit form is never empty.
const seedPhones = () => {
    const list = (props.customer.phones || []).map((p) => ({
        phone: p.phone || '',
        label: p.label || 'Mobile',
        is_primary: !!p.is_primary,
        is_sms_capable: p.is_sms_capable !== false,
    }));
    if (list.length === 0) {
        if (props.customer.phone) list.push({ phone: props.customer.phone, label: 'Mobile', is_primary: true, is_sms_capable: !!props.customer.can_receive_sms });
        if (props.customer.secondary_phone) list.push({ phone: props.customer.secondary_phone, label: 'Other', is_primary: list.length === 0, is_sms_capable: !!props.customer.can_receive_sms });
    }
    if (list.length === 0) list.push({ phone: '', label: 'Mobile', is_primary: true, is_sms_capable: true });
    if (!list.some((p) => p.is_primary)) list[0].is_primary = true;
    return list;
};

const form = useForm({
    first_name: props.customer.first_name,
    last_name: props.customer.last_name,
    email: props.customer.email || '',
    phones: seedPhones(),
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

const addPhoneRow = () => form.phones.push({ phone: '', label: 'Mobile', is_primary: false, is_sms_capable: true });
const removePhoneRow = (i) => {
    form.phones.splice(i, 1);
    if (!form.phones.some((p) => p.is_primary) && form.phones.length) form.phones[0].is_primary = true;
};
const setPrimary = (i) => form.phones.forEach((p, idx) => (p.is_primary = idx === i));

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
                            <label class="block text-sm font-medium text-gray-700">Email</label>
                            <input v-model="form.email" type="email" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                        </div>
                        <div class="md:col-span-2">
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
