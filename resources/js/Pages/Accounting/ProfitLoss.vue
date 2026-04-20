<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ report: Object });
const fmt = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v || 0);
</script>

<template>
    <AppLayout title="Profit & Loss">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Profit & Loss Statement</h2>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <div class="text-sm text-gray-500 mb-6">{{ report.start_date }} to {{ report.end_date }}</div>

                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-green-700 mb-3">Revenue</h3>
                        <div v-for="item in report.revenue" :key="item.id" class="flex justify-between py-1 text-sm">
                            <span>{{ item.code }} - {{ item.name }}</span>
                            <span class="text-green-600">{{ fmt(item.total_credit - item.total_debit) }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-t font-bold text-green-700">
                            <span>Total Revenue</span>
                            <span>{{ fmt(report.total_revenue) }}</span>
                        </div>
                    </div>

                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-red-700 mb-3">Expenses</h3>
                        <div v-for="item in report.expenses" :key="item.id" class="flex justify-between py-1 text-sm">
                            <span>{{ item.code }} - {{ item.name }}</span>
                            <span class="text-red-600">{{ fmt(item.total_debit - item.total_credit) }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-t font-bold text-red-700">
                            <span>Total Expenses</span>
                            <span>{{ fmt(report.total_expenses) }}</span>
                        </div>
                    </div>

                    <div class="flex justify-between py-3 border-t-2 border-gray-900 text-lg font-bold"
                         :class="report.net_income >= 0 ? 'text-green-800' : 'text-red-800'">
                        <span>Net Income</span>
                        <span>{{ fmt(report.net_income) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
