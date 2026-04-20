<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({ entry: Object });
const fmt = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v || 0);
</script>

<template>
    <AppLayout title="Journal Entry">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('accounting.journal-entries')" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ entry.entry_number }}</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <dl class="grid grid-cols-2 gap-4 mb-6">
                        <div><dt class="text-sm text-gray-500">Date</dt><dd class="font-medium">{{ entry.date }}</dd></div>
                        <div><dt class="text-sm text-gray-500">Created By</dt><dd class="font-medium">{{ entry.user?.name || '-' }}</dd></div>
                        <div class="col-span-2"><dt class="text-sm text-gray-500">Description</dt><dd class="font-medium">{{ entry.description || '-' }}</dd></div>
                    </dl>

                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Account</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Memo</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Debit</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Credit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="line in entry.lines" :key="line.id">
                                <td class="px-4 py-2 text-sm">{{ line.account?.code }} - {{ line.account?.name }}</td>
                                <td class="px-4 py-2 text-sm text-gray-500">{{ line.memo || '' }}</td>
                                <td class="px-4 py-2 text-sm text-right">{{ parseFloat(line.debit) > 0 ? fmt(line.debit) : '' }}</td>
                                <td class="px-4 py-2 text-sm text-right">{{ parseFloat(line.credit) > 0 ? fmt(line.credit) : '' }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50 font-bold">
                            <tr>
                                <td colspan="2" class="px-4 py-2 text-sm">Totals</td>
                                <td class="px-4 py-2 text-sm text-right">{{ fmt(entry.lines?.reduce((s, l) => s + parseFloat(l.debit), 0)) }}</td>
                                <td class="px-4 py-2 text-sm text-right">{{ fmt(entry.lines?.reduce((s, l) => s + parseFloat(l.credit), 0)) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
