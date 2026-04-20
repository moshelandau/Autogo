<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({ entries: Object });

const formatCurrency = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v || 0);
</script>

<template>
    <AppLayout title="Journal Entries">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Journal Entries</h2>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entry #</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Debit</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Credit</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="entry in entries.data" :key="entry.id" class="hover:bg-gray-50">
                                <td class="px-6 py-3">
                                    <Link :href="route('accounting.journal-entries.show', entry.id)" class="text-indigo-600 hover:text-indigo-900 text-sm font-mono">
                                        {{ entry.entry_number }}
                                    </Link>
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600">{{ entry.date }}</td>
                                <td class="px-6 py-3 text-sm text-gray-900">{{ entry.description || '-' }}</td>
                                <td class="px-6 py-3 text-sm text-right text-gray-600">{{ formatCurrency(entry.lines?.reduce((s, l) => s + parseFloat(l.debit), 0)) }}</td>
                                <td class="px-6 py-3 text-sm text-right text-gray-600">{{ formatCurrency(entry.lines?.reduce((s, l) => s + parseFloat(l.credit), 0)) }}</td>
                            </tr>
                            <tr v-if="!entries.data?.length">
                                <td colspan="5" class="px-6 py-8 text-center text-gray-500">No journal entries yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
