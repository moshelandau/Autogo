<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({ pull: Object });
const p = props.pull;

const tierColors = {
    tier_1: 'bg-emerald-100 text-emerald-800 border-emerald-200',
    tier_2: 'bg-blue-100 text-blue-800 border-blue-200',
    tier_3: 'bg-yellow-100 text-yellow-800 border-yellow-200',
    tier_4: 'bg-red-100 text-red-800 border-red-200',
};
</script>

<template>
    <AppLayout title="Credit Pull">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('credit.index')" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                <h2 class="font-bold text-xl text-gray-900">Credit Pull — {{ p.first_name }} {{ p.last_name }}</h2>
                <span class="px-2 py-1 text-xs rounded-full" :class="p.type === 'soft' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800'">{{ p.type }}</span>
            </div>
        </template>

        <div class="p-6">
            <div class="max-w-3xl mx-auto space-y-6">
                <!-- Score Card -->
                <div class="bg-white rounded-2xl border p-8 text-center">
                    <div class="text-7xl font-extrabold text-gray-900 mb-2">{{ p.credit_score || '-' }}</div>
                    <p class="text-sm text-gray-500 mb-4">{{ p.credit_score_model }}</p>
                    <div v-if="p.credit_tier" class="inline-block px-4 py-2 rounded-full border-2 text-sm font-semibold capitalize" :class="tierColors[p.credit_tier]">
                        {{ p.credit_tier?.replace('_', ' ') }}
                    </div>
                </div>

                <!-- Details -->
                <div class="bg-white rounded-xl border p-6">
                    <h3 class="font-semibold mb-3">Pull Details</h3>
                    <dl class="grid grid-cols-2 gap-4 text-sm">
                        <div><dt class="text-gray-500">Customer</dt><dd class="font-medium">{{ p.first_name }} {{ p.last_name }}</dd></div>
                        <div><dt class="text-gray-500">DOB</dt><dd class="font-medium">{{ p.date_of_birth || '-' }}</dd></div>
                        <div><dt class="text-gray-500">Type</dt><dd class="font-medium capitalize">{{ p.type }} pull</dd></div>
                        <div><dt class="text-gray-500">Bureau</dt><dd class="font-medium capitalize">{{ p.bureau || '-' }}</dd></div>
                        <div><dt class="text-gray-500">Pulled By</dt><dd class="font-medium">{{ p.pulled_by_user?.name || '-' }}</dd></div>
                        <div><dt class="text-gray-500">Pulled At</dt><dd class="font-medium">{{ new Date(p.created_at).toLocaleString() }}</dd></div>
                        <div><dt class="text-gray-500">Expires</dt><dd class="font-medium">{{ p.expires_at ? new Date(p.expires_at).toLocaleDateString() : '-' }}</dd></div>
                        <div><dt class="text-gray-500">Status</dt><dd class="font-medium capitalize">{{ p.status }}</dd></div>
                    </dl>
                </div>

                <div v-if="p.full_report" class="bg-white rounded-xl border p-6">
                    <h3 class="font-semibold mb-3">Full Report (Raw)</h3>
                    <pre class="text-xs bg-gray-50 p-4 rounded-lg overflow-x-auto">{{ JSON.stringify(p.full_report, null, 2) }}</pre>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
