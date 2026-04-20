<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    departmentSummary: Object,
    stats: Object,
});

const departments = [
    { key: 'rental', label: 'Car Rental', desc: 'Fleet & Reservations', icon: 'M8 17h8M8 17l-2-6h12l-2 6M8 17H6a2 2 0 01-2-2v-2h2m12 4h2a2 2 0 002-2v-2h-2M7 11V7a2 2 0 012-2h6a2 2 0 012 2v4', gradient: 'from-blue-500 to-cyan-400', shadow: 'shadow-blue-500/25', link: 'rental.dashboard' },
    { key: 'leasing', label: 'Leasing & Finance', desc: 'Deals Pipeline', icon: 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', gradient: 'from-emerald-500 to-green-400', shadow: 'shadow-emerald-500/25', link: 'leasing.deals.index' },
    { key: 'bodyshop', label: 'Bodyshop & Towing', desc: 'Repairs & Dispatch', icon: 'M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z', gradient: 'from-orange-500 to-amber-400', shadow: 'shadow-orange-500/25', link: 'dashboard' },
    { key: 'insurance', label: 'Insurance Claims', desc: 'Claims Tracking', icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', gradient: 'from-purple-500 to-violet-400', shadow: 'shadow-purple-500/25', link: 'dashboard' },
];

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(value || 0);
};

const quickActions = [
    { name: 'New Reservation', icon: 'M12 4v16m8-8H4', route: 'rental.reservations.create', color: 'bg-blue-50 text-blue-600 hover:bg-blue-100' },
    { name: 'New Deal', icon: 'M12 4v16m8-8H4', route: 'leasing.deals.create', color: 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100' },
    { name: 'New Customer', icon: 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z', route: 'customers.create', color: 'bg-violet-50 text-violet-600 hover:bg-violet-100' },
    { name: 'Add Vehicle', icon: 'M12 4v16m8-8H4', route: 'rental.vehicles.create', color: 'bg-amber-50 text-amber-600 hover:bg-amber-100' },
];
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <div>
                <h2 class="font-bold text-xl text-gray-900 leading-tight">Dashboard</h2>
                <p class="text-sm text-gray-500 mt-0.5">Welcome back! Here's your business overview.</p>
            </div>
        </template>

        <div class="p-6 space-y-6">
            <!-- Department Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
                <Link v-for="dept in departments" :key="dept.key" :href="route(dept.link)"
                      class="group relative overflow-hidden bg-white rounded-2xl border border-gray-100 p-6 hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                    <!-- Gradient accent bar -->
                    <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r" :class="dept.gradient"></div>

                    <div class="flex items-start justify-between mb-5">
                        <div :class="['w-12 h-12 rounded-2xl bg-gradient-to-br flex items-center justify-center shadow-lg', dept.gradient, dept.shadow]">
                            <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="dept.icon" />
                            </svg>
                        </div>
                        <svg class="w-5 h-5 text-gray-300 group-hover:text-gray-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </div>

                    <h3 class="font-bold text-gray-900 text-lg mb-0.5">{{ dept.label }}</h3>
                    <p class="text-xs text-gray-400 mb-4">{{ dept.desc }}</p>

                    <div class="space-y-2.5">
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-medium text-gray-500">Revenue</span>
                            <span class="text-sm font-bold text-emerald-600">
                                {{ formatCurrency(departmentSummary?.[dept.key]?.revenue) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-medium text-gray-500">Expenses</span>
                            <span class="text-sm font-semibold text-gray-600">
                                {{ formatCurrency(departmentSummary?.[dept.key]?.expenses) }}
                            </span>
                        </div>
                        <div class="border-t border-gray-100 pt-2 flex justify-between items-center">
                            <span class="text-xs font-semibold text-gray-700">Net Income</span>
                            <span class="text-base font-extrabold"
                                  :class="(departmentSummary?.[dept.key]?.net_income || 0) >= 0 ? 'text-emerald-600' : 'text-red-500'">
                                {{ formatCurrency(departmentSummary?.[dept.key]?.net_income) }}
                            </span>
                        </div>
                    </div>
                </Link>
            </div>

            <!-- Bottom Section: Quick Stats + Quick Actions -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <!-- Quick Stats -->
                <div class="lg:col-span-1 bg-white rounded-2xl border border-gray-100 p-6">
                    <h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                        </div>
                        Overview
                    </h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Total Customers</p>
                                    <p class="text-lg font-bold text-gray-900">{{ stats?.total_customers || 0 }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Active Locations</p>
                                    <p class="text-lg font-bold text-gray-900">{{ stats?.active_locations || 0 }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 p-6">
                    <h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                        </div>
                        Quick Actions
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <Link v-for="action in quickActions" :key="action.name"
                              :href="route(action.route)"
                              :class="[action.color, 'flex flex-col items-center justify-center p-5 rounded-2xl transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 border border-transparent hover:border-gray-100']">
                            <svg class="w-7 h-7 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="action.icon" />
                            </svg>
                            <span class="text-xs font-semibold text-center">{{ action.name }}</span>
                        </Link>
                    </div>

                    <!-- Quick Navigation -->
                    <div class="mt-5 pt-5 border-t border-gray-100">
                        <div class="flex flex-wrap gap-2">
                            <Link :href="route('rental.dashboard')" class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-50 hover:bg-gray-100 rounded-xl text-xs font-medium text-gray-600 transition-colors">
                                <span class="w-2 h-2 rounded-full bg-blue-400"></span> Rental Manifest
                            </Link>
                            <Link :href="route('leasing.deals.index')" class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-50 hover:bg-gray-100 rounded-xl text-xs font-medium text-gray-600 transition-colors">
                                <span class="w-2 h-2 rounded-full bg-emerald-400"></span> Deals Kanban
                            </Link>
                            <Link :href="route('accounting.profit-loss')" class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-50 hover:bg-gray-100 rounded-xl text-xs font-medium text-gray-600 transition-colors">
                                <span class="w-2 h-2 rounded-full bg-violet-400"></span> P&L Report
                            </Link>
                            <Link :href="route('customers.index')" class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-50 hover:bg-gray-100 rounded-xl text-xs font-medium text-gray-600 transition-colors">
                                <span class="w-2 h-2 rounded-full bg-amber-400"></span> All Customers
                            </Link>
                            <Link :href="route('accounting.chart-of-accounts')" class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-50 hover:bg-gray-100 rounded-xl text-xs font-medium text-gray-600 transition-colors">
                                <span class="w-2 h-2 rounded-full bg-gray-400"></span> Chart of Accounts
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
