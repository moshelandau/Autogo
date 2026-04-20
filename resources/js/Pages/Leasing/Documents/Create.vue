<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';
import CustomerSelect from '@/Components/CustomerSelect.vue';

const props = defineProps({ customers: Array, deals: Array });

const form = useForm({ customer_id: '', deal_id: '', notes: '' });
const submit = () => form.post(route('leasing.documents.store'));
</script>

<template>
    <AppLayout title="New Document Checklist">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('leasing.documents.index')" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                <h2 class="font-bold text-xl text-gray-900">New Document Checklist</h2>
            </div>
        </template>

        <div class="p-6">
            <div class="max-w-xl mx-auto">
                <form @submit.prevent="submit" class="bg-white rounded-xl border p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Customer *</label>
                        <CustomerSelect v-model="form.customer_id" class="mt-1" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Link to Deal (optional)</label>
                        <select v-model="form.deal_id" class="mt-1 block w-full border-gray-300 rounded-lg text-sm">
                            <option value="">None</option>
                            <option v-for="d in deals" :key="d.id" :value="d.id">#{{ d.deal_number }} - {{ d.customer?.first_name }} {{ d.customer?.last_name }} {{ d.vehicle_make ? `(${d.vehicle_make} ${d.vehicle_model})` : '' }}</option>
                        </select>
                    </div>
                    <div><label class="block text-sm font-medium text-gray-700">Notes</label><textarea v-model="form.notes" rows="2" class="mt-1 block w-full border-gray-300 rounded-lg text-sm"></textarea></div>
                    <p class="text-xs text-gray-500">Will create checklist with 6 required documents: Application, Driver License, Lease Agreement, Window Sticker, Insurance, Damage Waiver</p>
                    <div class="flex justify-end gap-3">
                        <Link :href="route('leasing.documents.index')" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg">Cancel</Link>
                        <button type="submit" :disabled="form.processing" class="px-4 py-2 text-sm text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">Create Checklist</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
