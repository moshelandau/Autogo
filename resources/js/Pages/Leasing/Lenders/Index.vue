<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({ lenders: Object });

const form = useForm({ name: '', contact_name: '', phone: '', email: '', website: '', programs_notes: '' });
const submit = () => form.post(route('leasing.lenders.store'), { onSuccess: () => form.reset() });
</script>

<template>
    <AppLayout title="Lenders">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Lenders</h2>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Add Lender Form -->
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-medium text-sm mb-3">Add Lender</h3>
                    <form @submit.prevent="submit" class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div><input v-model="form.name" type="text" placeholder="Lender Name *" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" /></div>
                        <div><input v-model="form.contact_name" type="text" placeholder="Contact" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" /></div>
                        <div><input v-model="form.phone" type="text" placeholder="Phone" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" /></div>
                        <div><button type="submit" :disabled="form.processing || !form.name" class="w-full px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700 disabled:opacity-50">Add</button></div>
                    </form>
                </div>

                <!-- Lender List -->
                <div class="bg-white shadow-sm rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Deals</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="l in lenders.data" :key="l.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-medium">{{ l.name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ l.contact_name || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ l.phone || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ l.email || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-right">{{ l.deals_count }}</td>
                                <td class="px-4 py-3"><span :class="l.is_active ? 'text-green-600' : 'text-gray-400'" class="text-sm">{{ l.is_active ? 'Active' : 'Inactive' }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
