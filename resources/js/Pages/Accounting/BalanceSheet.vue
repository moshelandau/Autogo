<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ report: Object });
const fmt = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v || 0);
</script>

<template>
    <AppLayout title="Balance Sheet">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Balance Sheet</h2>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="text-sm text-gray-500">As of {{ report.as_of }}</div>

                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-blue-700 mb-3">Assets</h3>
                    <div v-for="item in report.assets" :key="item.id" class="flex justify-between py-1 text-sm">
                        <span>{{ item.code }} - {{ item.name }}</span>
                        <span>{{ fmt(item.total_debit - item.total_credit) }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-t font-bold text-blue-700">
                        <span>Total Assets</span><span>{{ fmt(report.total_assets) }}</span>
                    </div>
                </div>

                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-red-700 mb-3">Liabilities</h3>
                    <div v-for="item in report.liabilities" :key="item.id" class="flex justify-between py-1 text-sm">
                        <span>{{ item.code }} - {{ item.name }}</span>
                        <span>{{ fmt(item.total_credit - item.total_debit) }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-t font-bold text-red-700">
                        <span>Total Liabilities</span><span>{{ fmt(report.total_liabilities) }}</span>
                    </div>
                </div>

                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-purple-700 mb-3">Equity</h3>
                    <div v-for="item in report.equity" :key="item.id" class="flex justify-between py-1 text-sm">
                        <span>{{ item.code }} - {{ item.name }}</span>
                        <span>{{ fmt(item.total_credit - item.total_debit) }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-t font-bold text-purple-700">
                        <span>Total Equity</span><span>{{ fmt(report.total_equity) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
