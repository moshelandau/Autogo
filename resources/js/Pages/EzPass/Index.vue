<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import CustomerSelect from '@/Components/CustomerSelect.vue';

const props = defineProps({ accounts: Object, customers: Array, filters: Object });

const search = ref(props.filters?.search || '');
watch(search, (v) => router.get(route('ezpass.index'), v ? { search: v } : {}, { preserveState: true, replace: true }));

const form = useForm({ customer_id: '', account_number: '', tag_number: '', notes: '' });
const addAccount = () => form.post(route('ezpass.store'), { onSuccess: () => form.reset() });

const statusColors = { active: 'bg-green-100 text-green-800', inactive: 'bg-gray-100 text-gray-600', suspended: 'bg-red-100 text-red-800' };
</script>

<template>
    <AppLayout title="EZ Pass">
        <template #header>
            <div>
                <h2 class="font-bold text-xl text-gray-900">EZ Pass Accounts</h2>
                <p class="text-sm text-gray-500">Customer toll pass tracking</p>
            </div>
        </template>

        <div class="p-6 space-y-5">
            <!-- Add Account -->
            <div class="bg-white rounded-xl border p-5">
                <h3 class="font-semibold text-gray-900 mb-3">Add EZ Pass Account</h3>
                <form @submit.prevent="addAccount" class="grid grid-cols-2 md:grid-cols-5 gap-3">
                    <CustomerSelect v-model="form.customer_id" class="col-span-2" placeholder="Select customer *" />
                    <input v-model="form.account_number" type="text" placeholder="Account #" class="border-gray-300 rounded-lg text-sm" />
                    <input v-model="form.tag_number" type="text" placeholder="Tag #" class="border-gray-300 rounded-lg text-sm" />
                    <button type="submit" :disabled="form.processing || !form.customer_id" class="bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700 disabled:opacity-50">Add</button>
                </form>
            </div>

            <!-- Search -->
            <div class="bg-white rounded-xl border p-4">
                <input v-model="search" type="text" placeholder="Search by customer name, account #, or tag #..." class="w-full border-gray-300 rounded-lg text-sm" />
            </div>

            <!-- Accounts Table -->
            <div class="bg-white rounded-xl border overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account #</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tag #</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="acc in accounts.data" :key="acc.id" class="hover:bg-gray-50">
                            <td class="px-5 py-3 text-sm font-medium">{{ acc.customer?.first_name }} {{ acc.customer?.last_name }}</td>
                            <td class="px-5 py-3 text-sm font-mono text-gray-600">{{ acc.account_number || '-' }}</td>
                            <td class="px-5 py-3 text-sm font-mono text-gray-600">{{ acc.tag_number || '-' }}</td>
                            <td class="px-5 py-3"><span class="px-2 py-1 text-xs rounded-full capitalize" :class="statusColors[acc.status]">{{ acc.status }}</span></td>
                            <td class="px-5 py-3 text-xs text-gray-500">{{ acc.notes || '-' }}</td>
                        </tr>
                        <tr v-if="!accounts.data?.length"><td colspan="5" class="px-5 py-8 text-center text-gray-400">No EZ Pass accounts.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
