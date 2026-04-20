<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({ customer: Object, pulls: Array, latestValidPull: Object });

const tierColors = {
    tier_1: 'bg-emerald-100 text-emerald-800',
    tier_2: 'bg-blue-100 text-blue-800',
    tier_3: 'bg-yellow-100 text-yellow-800',
    tier_4: 'bg-red-100 text-red-800',
};
</script>

<template>
    <AppLayout title="Credit History">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('customers.show', customer.id)" class="text-gray-500 hover:text-gray-700">&larr; Customer</Link>
                    <h2 class="font-bold text-xl text-gray-900">{{ customer.first_name }} {{ customer.last_name }} — Credit History</h2>
                </div>
                <Link :href="route('credit.create', { customer_id: customer.id })" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">+ New Credit Pull</Link>
            </div>
        </template>

        <div class="p-6">
            <div class="max-w-4xl mx-auto space-y-6">
                <!-- Latest Valid -->
                <div v-if="latestValidPull" class="bg-emerald-50 border-2 border-emerald-200 rounded-2xl p-6">
                    <h3 class="text-sm font-semibold text-emerald-800 uppercase mb-2">Most Recent Valid Pull</h3>
                    <div class="flex items-center gap-6">
                        <div class="text-5xl font-extrabold text-gray-900">{{ latestValidPull.credit_score }}</div>
                        <div>
                            <span class="px-3 py-1 text-sm rounded-full capitalize" :class="tierColors[latestValidPull.credit_tier]">{{ latestValidPull.credit_tier?.replace('_', ' ') }}</span>
                            <p class="text-sm text-gray-600 mt-1">{{ latestValidPull.type }} pull · {{ new Date(latestValidPull.created_at).toLocaleDateString() }}</p>
                            <p class="text-xs text-gray-500">Expires: {{ new Date(latestValidPull.expires_at).toLocaleDateString() }}</p>
                        </div>
                    </div>
                </div>

                <!-- Full History -->
                <div class="bg-white rounded-xl border">
                    <div class="px-5 py-3 border-b"><h3 class="font-semibold">All Credit Pulls ({{ pulls?.length || 0 }})</h3></div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Score</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tier</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Deal</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="p in pulls" :key="p.id" class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm">{{ new Date(p.created_at).toLocaleDateString() }}</td>
                                <td class="px-4 py-3"><span class="px-2 py-1 text-xs rounded-full" :class="p.type === 'soft' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800'">{{ p.type }}</span></td>
                                <td class="px-4 py-3 text-center font-bold"><Link :href="route('credit.show', p.id)" class="text-indigo-600">{{ p.credit_score || '-' }}</Link></td>
                                <td class="px-4 py-3"><span v-if="p.credit_tier" class="px-2 py-1 text-xs rounded-full capitalize" :class="tierColors[p.credit_tier]">{{ p.credit_tier?.replace('_', ' ') }}</span></td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ p.deal ? `#${p.deal.deal_number}` : '-' }}</td>
                                <td class="px-4 py-3"><span class="text-xs" :class="p.is_valid ? 'text-green-600' : 'text-gray-400'">{{ p.is_valid ? 'Valid' : 'Expired' }}</span></td>
                            </tr>
                            <tr v-if="!pulls?.length"><td colspan="6" class="px-4 py-8 text-center text-gray-400">No credit pulls yet.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
