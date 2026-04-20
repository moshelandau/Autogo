<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({ report: Object });
const fmt = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v || 0);
</script>

<template>
    <AppLayout title="Account Register">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('accounting.chart-of-accounts')" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ report.account.code }} - {{ report.account.name }}
                </h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entry #</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Debit</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Credit</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="(entry, i) in report.entries" :key="i" class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-sm text-gray-600">{{ entry.date }}</td>
                                <td class="px-4 py-2 text-sm font-mono text-indigo-600">{{ entry.entry_number }}</td>
                                <td class="px-4 py-2 text-sm">{{ entry.description }}</td>
                                <td class="px-4 py-2 text-sm text-right">{{ entry.debit > 0 ? fmt(entry.debit) : '' }}</td>
                                <td class="px-4 py-2 text-sm text-right">{{ entry.credit > 0 ? fmt(entry.credit) : '' }}</td>
                                <td class="px-4 py-2 text-sm text-right font-medium">{{ fmt(entry.balance) }}</td>
                            </tr>
                            <tr v-if="!report.entries?.length">
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">No transactions.</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50 font-bold">
                            <tr>
                                <td colspan="5" class="px-4 py-3 text-sm text-right">Ending Balance:</td>
                                <td class="px-4 py-3 text-sm text-right">{{ fmt(report.ending_balance) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
