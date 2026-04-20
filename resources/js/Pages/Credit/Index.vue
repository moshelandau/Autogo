<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({ pulls: Object, stats: Object, configured: Boolean });

const tierColors = {
    tier_1: 'bg-emerald-100 text-emerald-800',
    tier_2: 'bg-blue-100 text-blue-800',
    tier_3: 'bg-yellow-100 text-yellow-800',
    tier_4: 'bg-red-100 text-red-800',
};
</script>

<template>
    <AppLayout title="Credit Pulls">
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-bold text-xl text-gray-900">Credit Pulls</h2>
                    <p class="text-sm text-gray-500">Powered by 700Credit {{ configured ? '✓' : '(MOCK MODE — configure API key)' }}</p>
                </div>
                <Link :href="route('credit.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">+ New Credit Pull</Link>
            </div>
        </template>

        <div class="p-6 space-y-5">
            <div v-if="!configured" class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 text-sm text-yellow-800">
                <strong>Mock mode:</strong> Add <code>CREDIT700_API_KEY</code> to .env to use real 700Credit pulls. Currently returns random scores for testing.
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 text-xs text-blue-900">
                AutoGo runs <strong>soft pulls only</strong> (pre-qualification). Hard pulls are performed by the dealer / lender after the application is submitted.
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div class="bg-white rounded-xl border p-4 text-center"><div class="text-2xl font-bold text-gray-900">{{ stats.total }}</div><div class="text-xs text-gray-500">Total Pulls</div></div>
                <div class="bg-white rounded-xl border p-4 text-center"><div class="text-2xl font-bold text-green-600">{{ stats.soft }}</div><div class="text-xs text-gray-500">Soft Pulls</div></div>
                <div class="bg-white rounded-xl border p-4 text-center"><div class="text-2xl font-bold text-blue-600">{{ stats.this_month }}</div><div class="text-xs text-gray-500">This Month</div></div>
            </div>

            <div class="bg-white rounded-xl border overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Score</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tier</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pulled</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="p in pulls.data" :key="p.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3"><Link :href="route('credit.show', p.id)" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">{{ p.first_name }} {{ p.last_name }}</Link></td>
                            <td class="px-4 py-3"><span class="px-2 py-1 text-xs rounded-full" :class="p.type === 'soft' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800'">{{ p.type }}</span></td>
                            <td class="px-4 py-3 text-center font-bold text-lg">{{ p.credit_score || '-' }}</td>
                            <td class="px-4 py-3"><span v-if="p.credit_tier" class="px-2 py-1 text-xs rounded-full capitalize" :class="tierColors[p.credit_tier]">{{ p.credit_tier?.replace('_', ' ') }}</span></td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ new Date(p.created_at).toLocaleDateString() }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ p.pulled_by_user?.name || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
