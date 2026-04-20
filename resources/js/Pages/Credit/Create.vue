<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';

const props = defineProps({ customer: Object, deal: Object, existingPulls: Array, configured: Boolean });

const form = useForm({
    customer_id: props.customer?.id || '',
    deal_id: props.deal?.id || '',
    first_name: props.customer?.first_name || '',
    last_name: props.customer?.last_name || '',
    date_of_birth: props.customer?.date_of_birth || '',
    address: props.customer?.address || '',
    city: props.customer?.city || '',
    state: props.customer?.state || '',
    zip: props.customer?.zip || '',
});

const submit = () => form.post(route('credit.store'));
</script>

<template>
    <AppLayout title="New Soft Credit Pull">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('credit.index')" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                <h2 class="font-bold text-xl text-gray-900">New Soft Credit Pull</h2>
            </div>
        </template>

        <div class="p-6">
            <div class="max-w-3xl mx-auto">
                <div class="bg-green-50 border border-green-200 rounded-xl p-4 text-sm text-green-900 mb-5">
                    <strong>Soft pull only.</strong> AutoGo runs soft pulls for pre-qualification — no SSN required, no impact on the customer's credit score. Hard pulls are performed by the dealer / lender after the application is submitted.
                </div>

                <div v-if="!configured" class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 text-sm text-yellow-800 mb-5">
                    <strong>Mock mode active.</strong> This will return a random credit score for testing. Configure CREDIT700_API_KEY in .env for real pulls.
                </div>

                <div v-if="existingPulls?.length" class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-5">
                    <h3 class="font-semibold text-sm text-blue-900 mb-2">Recent Valid Pulls — Consider reusing instead of new pull</h3>
                    <div v-for="p in existingPulls" :key="p.id" class="flex items-center justify-between py-2 text-sm border-t border-blue-200">
                        <div>
                            <span class="font-bold text-lg">{{ p.credit_score }}</span>
                            <span class="ml-2 capitalize text-xs">{{ p.type }} pull</span>
                            <span class="ml-2 text-xs text-blue-700">{{ new Date(p.created_at).toLocaleDateString() }}</span>
                        </div>
                        <Link :href="route('credit.show', p.id)" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Use this →</Link>
                    </div>
                </div>

                <form @submit.prevent="submit" class="bg-white rounded-xl border p-6 space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="block text-sm font-medium text-gray-700">First Name *</label><input v-model="form.first_name" type="text" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                        <div><label class="block text-sm font-medium text-gray-700">Last Name *</label><input v-model="form.last_name" type="text" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                        <div><label class="block text-sm font-medium text-gray-700">Date of Birth</label><input v-model="form.date_of_birth" type="date" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700">Address</label><input v-model="form.address" type="text" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                        <div><label class="block text-sm font-medium text-gray-700">City</label><input v-model="form.city" type="text" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                        <div class="grid grid-cols-2 gap-2">
                            <div><label class="block text-sm font-medium text-gray-700">State</label><input v-model="form.state" type="text" maxlength="2" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                            <div><label class="block text-sm font-medium text-gray-700">ZIP</label><input v-model="form.zip" type="text" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Link :href="route('credit.index')" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg">Cancel</Link>
                        <button type="submit" :disabled="form.processing" class="px-6 py-2 text-sm text-white rounded-lg disabled:opacity-50 bg-green-600 hover:bg-green-700">
                            {{ form.processing ? 'Pulling...' : 'Run Soft Pull' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
