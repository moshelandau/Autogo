<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({ programs: Object, lenders: Array, filters: Object, stats: Object });
const lenderFilter = ref(props.filters?.lender_id || '');
const showActiveOnly = ref(props.filters?.active === 'true');

watch([lenderFilter, showActiveOnly], () => {
    router.get(route('lender-programs.index'), {
        lender_id: lenderFilter.value || null,
        active: showActiveOnly.value ? 'true' : null,
    }, { preserveState: true, replace: true });
});
</script>

<template>
    <AppLayout title="Lender Programs">
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-bold text-xl text-gray-900">Lender Programs</h2>
                    <p class="text-sm text-gray-500">Residual %, money factors, APRs per lender/vehicle/term</p>
                </div>
                <Link :href="route('lender-programs.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">+ Add Program</Link>
            </div>
        </template>

        <div class="p-6 space-y-5">
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-white rounded-xl border p-4 text-center"><div class="text-2xl font-bold">{{ stats.total }}</div><div class="text-xs text-gray-500">Total Programs</div></div>
                <div class="bg-white rounded-xl border p-4 text-center"><div class="text-2xl font-bold text-green-600">{{ stats.active }}</div><div class="text-xs text-gray-500">Active</div></div>
                <div class="bg-white rounded-xl border p-4 text-center"><div class="text-2xl font-bold text-red-600">{{ stats.expired }}</div><div class="text-xs text-gray-500">Expired</div></div>
            </div>

            <div class="bg-white rounded-xl border p-4 flex gap-4 items-center">
                <select v-model="lenderFilter" class="border-gray-300 rounded-lg text-sm">
                    <option value="">All Lenders</option>
                    <option v-for="l in lenders" :key="l.id" :value="l.id">{{ l.name }}</option>
                </select>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="showActiveOnly" type="checkbox" class="rounded" />
                    Active only
                </label>
            </div>

            <div class="bg-white rounded-xl border overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lender</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vehicle</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Term</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Mileage</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Residual</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">MF / APR</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Valid Until</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="p in programs.data" :key="p.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-medium">{{ p.lender?.name }}</td>
                            <td class="px-4 py-3 text-sm capitalize">{{ p.program_type }}</td>
                            <td class="px-4 py-3 text-sm">{{ p.year || '' }} {{ p.make || 'All' }} {{ p.model || '' }}</td>
                            <td class="px-4 py-3 text-sm text-center">{{ p.term ? p.term + ' mo' : '-' }}</td>
                            <td class="px-4 py-3 text-sm text-center">{{ p.annual_mileage ? p.annual_mileage.toLocaleString() : '-' }}</td>
                            <td class="px-4 py-3 text-sm text-right font-medium">{{ p.residual_pct ? p.residual_pct + '%' : '-' }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ p.money_factor || (p.apr ? p.apr + '%' : '-') }}</td>
                            <td class="px-4 py-3 text-sm" :class="new Date(p.valid_until) < new Date() ? 'text-red-600' : 'text-gray-600'">{{ p.valid_until }}</td>
                        </tr>
                        <tr v-if="!programs.data?.length"><td colspan="8" class="px-4 py-8 text-center text-gray-400">No programs yet. Add one above.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
