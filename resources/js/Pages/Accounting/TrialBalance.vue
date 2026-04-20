<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ report: Object });
const fmt = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v || 0);
</script>

<template>
    <AppLayout title="Trial Balance">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Trial Balance</h2>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm rounded-lg overflow-x-auto">
                    <div class="p-4 text-sm text-gray-500">As of {{ report.as_of }}</div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Debit</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Credit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="item in report.balances" :key="item.id" class="hover:bg-gray-50">
                                <td class="px-6 py-2 text-sm font-mono text-gray-600">{{ item.code }}</td>
                                <td class="px-6 py-2 text-sm text-gray-900">{{ item.name }}</td>
                                <td class="px-6 py-2 text-sm text-right">{{ item.total_debit > 0 ? fmt(item.total_debit) : '' }}</td>
                                <td class="px-6 py-2 text-sm text-right">{{ item.total_credit > 0 ? fmt(item.total_credit) : '' }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50 font-bold">
                            <tr>
                                <td colspan="2" class="px-6 py-3 text-sm">Totals</td>
                                <td class="px-6 py-3 text-sm text-right">{{ fmt(report.total_debit) }}</td>
                                <td class="px-6 py-3 text-sm text-right">{{ fmt(report.total_credit) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
