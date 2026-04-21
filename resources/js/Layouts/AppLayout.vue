<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Banner from '@/Components/Banner.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';

defineProps({ title: String });

const page = usePage();
const sidebarOpen = ref(true);
const mobileMenuOpen = ref(false);

const currentRoute = computed(() => route().current());

const isActive = (pattern) => {
    if (Array.isArray(pattern)) return pattern.some(p => route().current(p));
    return route().current(pattern);
};

const logout = () => router.post(route('logout'));

const pageAccess = computed(() => page.props.pageAccess || {});

const canView = (pageKey) => {
    const access = pageAccess.value;
    if (!access || Object.keys(access).length === 0) return true;
    return access[pageKey]?.can_view !== false;
};

const navSections = [
    {
        label: 'Main',
        items: [
            { name: 'Dashboard', route: 'dashboard', icon: 'home', pattern: 'dashboard', pageKey: 'dashboard' },
            { name: 'Customers', route: 'customers.index', icon: 'users', pattern: 'customers.*', pageKey: 'customers' },
        ]
    },
    {
        label: 'Car Rental',
        items: [
            { name: 'Rental Dashboard', route: 'rental.dashboard', icon: 'car', pattern: 'rental.dashboard', pageKey: 'rental_dashboard' },
            { name: 'Reservations', route: 'rental.reservations.index', icon: 'calendar-check', pattern: 'rental.reservations.*', pageKey: 'reservations' },
            { name: 'Fleet Vehicles', route: 'rental.vehicles.index', icon: 'truck', pattern: 'rental.vehicles.*', pageKey: 'vehicles' },
            { name: 'Calendar', route: 'rental.calendar', icon: 'calendar', pattern: 'rental.calendar', pageKey: 'rental_calendar' },
            { name: 'Rental Claims', route: 'rental-claims.index', icon: 'alert', pattern: ['rental-claims.*'], pageKey: 'rental_claims' },
            { name: 'EZ Pass',         route: 'ezpass.index',       icon: 'tag', pattern: ['ezpass.index','ezpass.show'], pageKey: 'customers' },
            { name: 'EZ Pass Import',  route: 'ezpass.import.show', icon: 'plus-circle', pattern: 'ezpass.import.*', pageKey: 'customers' },
            { name: 'Violations',      route: 'violations.index',   icon: 'alert', pattern: 'violations.*', pageKey: 'customers' },
        ]
    },
    {
        label: 'Leasing / Financing',
        items: [
            { name: 'Deals Pipeline', route: 'leasing.deals.index', icon: 'briefcase', pattern: ['leasing.deals.*'], pageKey: 'deals_pipeline' },
            { name: 'Damage Waivers', route: 'leasing.documents.index', icon: 'clipboard', pattern: ['leasing.documents.*'], pageKey: 'damage_waivers' },
            { name: 'Lenders', route: 'leasing.lenders.index', icon: 'building', pattern: 'leasing.lenders.*', pageKey: 'lenders' },
            { name: 'Credit Pulls', route: 'credit.index', icon: 'shield', pattern: 'credit.*', pageKey: 'deals_pipeline' },
            { name: 'Lender Programs', route: 'lender-programs.index', icon: 'list', pattern: 'lender-programs.*', pageKey: 'lenders' },
        ]
    },
    {
        label: 'Insurance Claims',
        items: [
            { name: 'All Claims', route: 'claims.index', icon: 'shield', pattern: ['claims.index', 'claims.show'], pageKey: 'claims' },
            { name: 'Claims Board', route: 'claims.board', icon: 'list', pattern: 'claims.board', pageKey: 'claims' },
            { name: 'New Claim', route: 'claims.create', icon: 'plus-circle', pattern: 'claims.create', pageKey: 'claims' },
        ]
    },
    {
        label: 'Bodyshop & Towing',
        items: [
            { name: 'Bodyshop Floor', route: 'bodyshop.floor', icon: 'wrench', pattern: 'bodyshop.floor', pageKey: 'claims' },
            { name: 'Workers', route: 'bodyshop.workers', icon: 'user', pattern: 'bodyshop.workers*', pageKey: 'claims' },
            { name: 'Lifts & Bays', route: 'bodyshop.lifts', icon: 'list', pattern: 'bodyshop.lifts*', pageKey: 'claims' },
            { name: 'Tow Jobs', route: 'towing.index', icon: 'truck', pattern: ['towing.index', 'towing.show'], pageKey: 'claims' },
            { name: 'Tow Board', route: 'towing.board', icon: 'list', pattern: 'towing.board', pageKey: 'claims' },
            { name: 'New Tow Job', route: 'towing.create', icon: 'plus-circle', pattern: 'towing.create', pageKey: 'claims' },
        ]
    },
    {
        label: 'Accounting',
        items: [
            { name: 'Chart of Accounts', route: 'accounting.chart-of-accounts', icon: 'list', pattern: 'accounting.chart-of-accounts*', pageKey: 'accounting' },
            { name: 'Journal Entries', route: 'accounting.journal-entries', icon: 'book', pattern: 'accounting.journal-entries*', pageKey: 'journal_entries' },
            { name: 'Profit & Loss', route: 'accounting.profit-loss', icon: 'trending-up', pattern: 'accounting.profit-loss', pageKey: 'reports' },
            { name: 'Balance Sheet', route: 'accounting.balance-sheet', icon: 'bar-chart', pattern: 'accounting.balance-sheet', pageKey: 'reports' },
            { name: 'Trial Balance', route: 'accounting.trial-balance', icon: 'scale', pattern: 'accounting.trial-balance', pageKey: 'reports' },
        ]
    },
    {
        label: 'Office',
        items: [
            { name: 'Office Tasks', route: 'office-tasks.index', icon: 'check-square', pattern: 'office-tasks.*', pageKey: 'office_tasks' },
            { name: 'Parts Orders', route: 'parts.index', icon: 'wrench', pattern: ['parts.*'], pageKey: 'office_tasks' },
            { name: 'Business Docs', route: 'business-documents.index', icon: 'folder', pattern: ['business-documents.*'], pageKey: 'office_tasks' },
        ]
    },
    {
        label: 'System',
        items: [
            { name: 'Users', route: 'users.index', icon: 'users-cog', pattern: ['users.*'], pageKey: 'users' },
            { name: 'Permission Types', route: 'permission-types.index', icon: 'shield', pattern: ['permission-types.*'], pageKey: 'users' },
            { name: 'Settings', route: 'settings', icon: 'settings', pattern: 'settings', pageKey: 'settings' },
        ]
    },
];

const filteredNavSections = computed(() => {
    return navSections
        .map(section => ({
            ...section,
            items: section.items.filter(item => !item.pageKey || canView(item.pageKey)),
        }))
        .filter(section => section.items.length > 0);
});

const icons = {
    home: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    users: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
    car: 'M8 17h8M8 17l-2-6h12l-2 6M8 17H6a2 2 0 01-2-2v-2h2m12 4h2a2 2 0 002-2v-2h-2M7 11V7a2 2 0 012-2h6a2 2 0 012 2v4',
    'calendar-check': 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    truck: 'M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10 M13 6h4l3 4v6h-2',
    calendar: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    briefcase: 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    building: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
    list: 'M4 6h16M4 10h16M4 14h16M4 18h16',
    book: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
    'trending-up': 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
    'bar-chart': 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
    scale: 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3',
    clipboard: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
    alert: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    shield: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    'plus-circle': 'M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z',
    'check-square': 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
    wrench: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
    folder: 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
    tag: 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z',
    'users-cog': 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
    settings: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
};
</script>

<template>
    <div>
        <Head :title="title" />
        <Banner />

        <div class="flex h-screen overflow-hidden bg-gray-50">
            <!-- Sidebar -->
            <aside :class="sidebarOpen ? 'w-64' : 'w-20'"
                   class="hidden lg:flex lg:flex-col bg-slate-900 text-white transition-all duration-300 ease-in-out flex-shrink-0">
                <!-- Logo -->
                <div class="flex items-center h-16 px-4 bg-slate-950 border-b border-slate-700/50">
                    <Link :href="route('dashboard')" class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center font-bold text-lg shadow-lg shadow-blue-500/25">
                            AG
                        </div>
                        <div v-if="sidebarOpen" class="flex flex-col">
                            <span class="font-bold text-base tracking-tight">AutoGo</span>
                            <span class="text-[10px] text-slate-400 -mt-0.5 tracking-wide">MANAGEMENT SYSTEM</span>
                        </div>
                    </Link>
                    <button @click="sidebarOpen = !sidebarOpen" class="ml-auto text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>

                <!-- Navigation -->
                <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-6 sidebar-scroll">
                    <div v-for="section in filteredNavSections" :key="section.label">
                        <p v-if="sidebarOpen" class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                            {{ section.label }}
                        </p>
                        <div v-else class="border-t border-slate-700/50 mb-3 mt-1"></div>
                        <ul class="space-y-0.5">
                            <li v-for="item in section.items" :key="item.name">
                                <Link :href="route(item.route)"
                                      :class="[
                                          isActive(item.pattern)
                                              ? 'bg-gradient-to-r from-blue-600/90 to-indigo-600/90 text-white shadow-lg shadow-blue-500/20'
                                              : 'text-slate-300 hover:bg-slate-800/80 hover:text-white',
                                          'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200'
                                      ]">
                                    <svg class="w-5 h-5 flex-shrink-0" :class="isActive(item.pattern) ? 'text-white' : 'text-slate-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" :d="icons[item.icon]" />
                                    </svg>
                                    <span v-if="sidebarOpen" class="truncate">{{ item.name }}</span>
                                </Link>
                            </li>
                        </ul>
                    </div>
                </nav>

                <!-- User Section at Bottom -->
                <div class="border-t border-slate-700/50 p-3">
                    <div class="flex items-center gap-3 px-3 py-2">
                        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center text-xs font-bold shadow-lg shadow-emerald-500/25">
                            {{ $page.props.auth?.user?.name?.charAt(0) || 'A' }}
                        </div>
                        <div v-if="sidebarOpen" class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white truncate">{{ $page.props.auth?.user?.name }}</p>
                            <p class="text-[11px] text-slate-400 truncate">{{ $page.props.auth?.user?.email }}</p>
                        </div>
                        <Dropdown v-if="sidebarOpen" align="right" width="48">
                            <template #trigger>
                                <button class="text-slate-400 hover:text-white transition-colors">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                                    </svg>
                                </button>
                            </template>
                            <template #content>
                                <DropdownLink :href="route('profile.show')">Profile</DropdownLink>
                                <div class="border-t border-gray-200" />
                                <form @submit.prevent="logout">
                                    <DropdownLink as="button">Log Out</DropdownLink>
                                </form>
                            </template>
                        </Dropdown>
                    </div>
                </div>
            </aside>

            <!-- Mobile sidebar overlay -->
            <div v-if="mobileMenuOpen" class="fixed inset-0 z-40 lg:hidden">
                <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="mobileMenuOpen = false"></div>
                <aside class="fixed inset-y-0 left-0 w-72 bg-slate-900 text-white z-50 shadow-2xl overflow-y-auto sidebar-scroll">
                    <div class="flex items-center justify-between h-16 px-4 bg-slate-950 border-b border-slate-700/50">
                        <Link :href="route('dashboard')" class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center font-bold text-lg">AG</div>
                            <span class="font-bold text-base">AutoGo</span>
                        </Link>
                        <button @click="mobileMenuOpen = false" class="text-slate-400 hover:text-white">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <nav class="py-4 px-3 space-y-6">
                        <div v-for="section in filteredNavSections" :key="section.label">
                            <p class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ section.label }}</p>
                            <ul class="space-y-0.5">
                                <li v-for="item in section.items" :key="item.name">
                                    <Link :href="route(item.route)" @click="mobileMenuOpen = false"
                                          :class="[isActive(item.pattern) ? 'bg-gradient-to-r from-blue-600/90 to-indigo-600/90 text-white' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white', 'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all']">
                                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" :d="icons[item.icon]" /></svg>
                                        <span>{{ item.name }}</span>
                                    </Link>
                                </li>
                            </ul>
                        </div>
                    </nav>
                </aside>
            </div>

            <!-- Main Content -->
            <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
                <!-- Top Bar -->
                <header class="bg-white border-b border-gray-200 flex-shrink-0">
                    <div class="flex items-center justify-between h-16 px-4 sm:px-6 lg:px-8">
                        <div class="flex items-center gap-4">
                            <!-- Mobile menu toggle -->
                            <button @click="mobileMenuOpen = true" class="lg:hidden text-gray-500 hover:text-gray-700">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                            </button>
                            <!-- Page Header -->
                            <slot name="header" />
                        </div>

                        <div class="flex items-center gap-4">
                            <!-- Quick Search -->
                            <div class="hidden md:block relative">
                                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                <input type="text" placeholder="Search..." class="w-64 pl-9 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition-all" />
                            </div>

                            <!-- Notifications bell -->
                            <Dropdown align="right" width="80">
                                <template #trigger>
                                    <button class="relative text-gray-400 hover:text-gray-600 transition-colors p-2 rounded-xl hover:bg-gray-100">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                                        <span v-if="$page.props.notifications?.unread_count > 0"
                                              class="absolute -top-0.5 -right-0.5 bg-red-600 text-white text-[10px] font-bold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1">
                                            {{ $page.props.notifications.unread_count }}
                                        </span>
                                    </button>
                                </template>
                                <template #content>
                                    <div class="px-4 py-3 border-b flex items-center justify-between">
                                        <div class="font-semibold text-sm text-gray-900">Notifications</div>
                                        <Link v-if="$page.props.notifications?.unread_count > 0"
                                              :href="route('notifications.read-all')" method="post" as="button"
                                              class="text-[10px] text-indigo-600 hover:text-indigo-800">Mark all read</Link>
                                    </div>
                                    <div class="max-h-96 overflow-y-auto">
                                        <Link v-for="n in $page.props.notifications?.items"
                                              :key="n.id"
                                              :href="route('notifications.read', n.id)" method="post" as="button"
                                              :data="{ _redirect: n.url }"
                                              @click.prevent="$inertia.post(route('notifications.read', n.id), {}, { onSuccess: () => $inertia.visit(n.url) })"
                                              class="w-full text-left flex items-start gap-3 px-4 py-3 border-b last:border-b-0 hover:bg-gray-50">
                                            <div class="text-2xl flex-shrink-0">{{ n.icon }}</div>
                                            <div class="flex-1 min-w-0">
                                                <div class="font-semibold text-xs text-gray-900">{{ n.title }}</div>
                                                <div v-if="n.body" class="text-[11px] text-gray-600 mt-0.5">{{ n.body }}</div>
                                                <div class="text-[10px] text-gray-400 mt-1">{{ new Date(n.created_at).toLocaleString() }}</div>
                                            </div>
                                        </Link>
                                        <div v-if="!$page.props.notifications?.items?.length" class="px-4 py-6 text-center text-xs text-gray-400">
                                            No new notifications
                                        </div>
                                    </div>
                                </template>
                            </Dropdown>

                            <!-- User dropdown (desktop) -->
                            <Dropdown align="right" width="48" class="hidden lg:block">
                                <template #trigger>
                                    <button class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 transition-colors p-1.5 rounded-xl hover:bg-gray-100">
                                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center text-xs font-bold text-white">
                                            {{ $page.props.auth?.user?.name?.charAt(0) || 'A' }}
                                        </div>
                                        <span class="hidden xl:inline font-medium">{{ $page.props.auth?.user?.name }}</span>
                                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                                    </button>
                                </template>
                                <template #content>
                                    <DropdownLink :href="route('profile.show')">Profile</DropdownLink>
                                    <DropdownLink v-if="$page.props.jetstream.hasApiFeatures" :href="route('api-tokens.index')">API Tokens</DropdownLink>
                                    <div class="border-t border-gray-200" />
                                    <form @submit.prevent="logout"><DropdownLink as="button">Log Out</DropdownLink></form>
                                </template>
                            </Dropdown>
                        </div>
                    </div>
                </header>

                <!-- Page Content -->
                <main class="flex-1 overflow-y-auto">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
