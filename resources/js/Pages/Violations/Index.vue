<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({ violations: Object, stats: Object, types: Object, statuses: Object, filters: Object });

const q     = ref(props.filters?.q || '');
const type  = ref(props.filters?.type || '');
const status= ref(props.filters?.status || '');
watch([q, type, status], () => router.get(route('violations.index'), { q: q.value, type: type.value, status: status.value }, { preserveState: true, replace: true }));

const fmt = (v) => '$' + Number(v || 0).toFixed(2);
const statusColor = {
    new: 'bg-amber-100 text-amber-800', received: 'bg-blue-100 text-blue-800',
    renter_notified: 'bg-violet-100 text-violet-800', renter_billed: 'bg-orange-100 text-orange-800',
    paid_by_renter: 'bg-emerald-100 text-emerald-800', paid_by_us: 'bg-gray-100 text-gray-800',
    disputed: 'bg-rose-100 text-rose-800', dismissed: 'bg-slate-100 text-slate-700',
};
</script>

<template>
    <AppLayout title="Violations">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-xl text-gray-900">🚓 Vehicle Violations</h2>
                    <p class="text-sm text-gray-500">Parking · camera · school bus · toll evasion — auto-linked to active rental, auto-billed to renter.</p>
                </div>
                <Link :href="route('violations.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">+ Log Violation</Link>
            </div>
        </template>

        <div class="p-6 space-y-5">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <div class="bg-white rounded-xl border p-4 text-center"><div class="text-2xl font-bold text-amber-600">{{ stats.unbilled_count }}</div><div class="text-xs text-gray-500">Unbilled</div></div>
                <div class="bg-white rounded-xl border p-4 text-center"><div class="text-2xl font-bold text-amber-600">{{ fmt(stats.unbilled_total) }}</div><div class="text-xs text-gray-500">Unbilled $</div></div>
                <div class="bg-white rounded-xl border p-4 text-center"><div class="text-2xl font-bold text-orange-600">{{ stats.billed_count }}</div><div class="text-xs text-gray-500">Billed</div></div>
                <div class="bg-white rounded-xl border p-4 text-center"><div class="text-2xl font-bold text-emerald-600">{{ stats.paid_count }}</div><div class="text-xs text-gray-500">Paid</div></div>
                <div class="bg-white rounded-xl border p-4 text-center"><div class="text-2xl font-bold text-blue-600">{{ stats.this_month }}</div><div class="text-xs text-gray-500">This Month</div></div>
            </div>

            <div class="bg-white rounded-xl border p-3 grid grid-cols-1 md:grid-cols-3 gap-3">
                <input v-model="q" type="text" placeholder="Plate / summons / location…" class="border-gray-300 rounded-lg text-sm" />
                <select v-model="type" class="border-gray-300 rounded-lg text-sm">
                    <option value="">All types</option>
                    <option v-for="(label, val) in types" :key="val" :value="val">{{ label }}</option>
                </select>
                <select v-model="status" class="border-gray-300 rounded-lg text-sm">
                    <option value="">All statuses</option>
                    <option v-for="(label, val) in statuses" :key="val" :value="val">{{ label }}</option>
                </select>
            </div>

            <div class="bg-white rounded-xl border overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>
                        <th class="px-3 py-2 text-left">Type</th>
                        <th class="px-3 py-2 text-left">Plate</th>
                        <th class="px-3 py-2 text-left">Issued</th>
                        <th class="px-3 py-2 text-left">Summons #</th>
                        <th class="px-3 py-2 text-left">Renter</th>
                        <th class="px-3 py-2 text-right">Total Due</th>
                        <th class="px-3 py-2 text-left">Status</th>
                    </tr></thead>
                    <tbody class="divide-y">
                        <tr v-for="v in violations.data" :key="v.id" class="hover:bg-gray-50">
                            <td class="px-3 py-2"><Link :href="route('violations.show', v.id)" class="text-indigo-600 hover:text-indigo-800">{{ types[v.type] }}</Link></td>
                            <td class="px-3 py-2 font-mono text-xs">{{ v.plate }} <span v-if="v.plate_state" class="text-gray-400">({{ v.plate_state }})</span></td>
                            <td class="px-3 py-2 text-xs">{{ v.issued_at?.split('T')[0] }}</td>
                            <td class="px-3 py-2 font-mono text-xs">{{ v.summons_number || v.citation_number || '—' }}</td>
                            <td class="px-3 py-2 text-xs">
                                <span v-if="v.customer">{{ v.customer.first_name }} {{ v.customer.last_name }}</span>
                                <span v-else class="text-amber-600">⚠ No rental matched</span>
                                <div v-if="v.reservation" class="text-[10px] text-gray-400">RA#{{ v.reservation.reservation_number }}</div>
                            </td>
                            <td class="px-3 py-2 text-right font-bold">{{ fmt(v.total_due) }}</td>
                            <td class="px-3 py-2"><span class="text-xs px-2 py-0.5 rounded-full" :class="statusColor[v.status]">{{ statuses[v.status] }}</span></td>
                        </tr>
                        <tr v-if="!violations.data?.length"><td colspan="7" class="px-4 py-8 text-center text-gray-400">No violations logged.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
