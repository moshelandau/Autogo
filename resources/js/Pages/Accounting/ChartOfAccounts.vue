<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ accounts: Array });

const typeColors = {
    asset: 'bg-blue-100 text-blue-800',
    liability: 'bg-red-100 text-red-800',
    equity: 'bg-purple-100 text-purple-800',
    revenue: 'bg-green-100 text-green-800',
    expense: 'bg-orange-100 text-orange-800',
};
</script>

<template>
    <AppLayout title="Chart of Accounts">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Chart of Accounts</h2>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="account in accounts" :key="account.id" class="hover:bg-gray-50">
                                <td class="px-6 py-3 text-sm font-mono text-gray-600">{{ account.code }}</td>
                                <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ account.name }}</td>
                                <td class="px-6 py-3">
                                    <span class="px-2 py-1 text-xs rounded-full" :class="typeColors[account.type]">
                                        {{ account.type }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600 capitalize">{{ account.department || 'General' }}</td>
                                <td class="px-6 py-3">
                                    <span :class="account.is_active ? 'text-green-600' : 'text-gray-400'" class="text-sm">
                                        {{ account.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
