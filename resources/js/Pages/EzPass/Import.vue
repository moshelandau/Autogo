<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    recentImports: { type: Array, default: () => [] },
    unbilled: { type: Array, default: () => [] },
});

const form = useForm({ file: null });
const onFile = (e) => { form.file = e.target.files[0] || null; };
const submit = () => {
    if (!form.file) return;
    form.post(route('ezpass.import'), { forceFormData: true, onSuccess: () => form.reset('file') });
};

const billReservation = (r) => {
    if (!confirm(`Charge $${r.total.toFixed(2)} to customer's card on file for ${r.count} toll(s) + $${r.admin_fee.toFixed(2)} admin fee? Customer will be emailed the breakdown.`)) return;
    router.post(route('ezpass.bill', r.reservation_id));
};
</script>

<template>
    <AppLayout title="EZ Pass Import">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('ezpass.index')" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                <h2 class="font-bold text-xl text-gray-900">📤 EZ Pass — CSV Import</h2>
            </div>
        </template>

        <div class="p-6 max-w-4xl mx-auto space-y-5">
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 text-sm text-blue-900">
                <strong>How to get the file:</strong>
                <ol class="list-decimal ml-5 mt-2 space-y-1">
                    <li>Log into NY E-ZPass Business Center: <code class="bg-white px-1 rounded">https://e-zpassny.com/business-center</code></li>
                    <li>Statements → choose date range → <strong>Download CSV/Excel</strong></li>
                    <li>Upload it below</li>
                </ol>
                <p class="mt-2 text-xs">Auto-links each toll to the rental vehicle (by plate) and to whichever reservation was active on that toll's date.</p>
            </div>

            <form @submit.prevent="submit" class="bg-white rounded-xl border p-5">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Upload E-ZPass statement (CSV / XLSX)</label>
                <input type="file" accept=".csv,.txt,.xlsx,.xls" @change="onFile"
                       class="w-full text-sm file:mr-3 file:px-4 file:py-2 file:rounded-lg file:border-0 file:bg-indigo-600 file:text-white file:cursor-pointer" />
                <button type="submit" :disabled="form.processing || !form.file"
                        class="mt-4 px-6 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50">
                    {{ form.processing ? 'Importing…' : 'Import transactions' }}
                </button>
                <p v-if="form.errors.file" class="mt-2 text-xs text-red-600">{{ form.errors.file }}</p>
            </form>

            <!-- Unbilled tolls grouped by reservation -->
            <div v-if="unbilled.length" class="bg-white rounded-xl border overflow-hidden">
                <header class="p-4 border-b bg-amber-50 flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold text-amber-900">⚠ Unbilled Tolls ({{ unbilled.length }} rentals)</h3>
                        <p class="text-xs text-amber-800 mt-0.5">Click Bill to auto-charge the card on file ($10/toll admin fee applied per rental agreement) and email the breakdown to the customer.</p>
                    </div>
                </header>
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>
                        <th class="px-3 py-2 text-left">RA#</th>
                        <th class="px-3 py-2 text-left">Customer</th>
                        <th class="px-3 py-2 text-right">Tolls</th>
                        <th class="px-3 py-2 text-right">Subtotal</th>
                        <th class="px-3 py-2 text-right">Admin Fee</th>
                        <th class="px-3 py-2 text-right">Total</th>
                        <th class="px-3 py-2"></th>
                    </tr></thead>
                    <tbody class="divide-y">
                        <tr v-for="r in unbilled" :key="r.reservation_id">
                            <td class="px-3 py-2"><Link :href="route('rental.reservations.show', r.reservation_id)" class="text-indigo-600 hover:text-indigo-800">RA#{{ r.reservation_number }}</Link></td>
                            <td class="px-3 py-2">{{ r.customer || '—' }}</td>
                            <td class="px-3 py-2 text-right">{{ r.count }}</td>
                            <td class="px-3 py-2 text-right">${{ Number(r.subtotal).toFixed(2) }}</td>
                            <td class="px-3 py-2 text-right">${{ Number(r.admin_fee).toFixed(2) }}</td>
                            <td class="px-3 py-2 text-right font-bold">${{ Number(r.total).toFixed(2) }}</td>
                            <td class="px-3 py-2 text-right">
                                <button @click="billReservation(r)" class="text-xs px-3 py-1 bg-rose-600 text-white rounded hover:bg-rose-700">💳 Bill &amp; Notify</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="recentImports.length" class="bg-white rounded-xl border overflow-hidden">
                <header class="p-4 border-b"><h3 class="font-semibold">Recent imports</h3></header>
                <table class="min-w-full divide-y">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>
                        <th class="px-4 py-2 text-left">File</th>
                        <th class="px-4 py-2 text-right">Transactions</th>
                        <th class="px-4 py-2 text-right">Total $</th>
                        <th class="px-4 py-2 text-left">Imported</th>
                    </tr></thead>
                    <tbody class="divide-y text-sm">
                        <tr v-for="r in recentImports" :key="r.source_file">
                            <td class="px-4 py-2">{{ r.source_file }}</td>
                            <td class="px-4 py-2 text-right">{{ r.cnt }}</td>
                            <td class="px-4 py-2 text-right">${{ Number(r.total).toFixed(2) }}</td>
                            <td class="px-4 py-2 text-xs text-gray-500">{{ new Date(r.imported_at).toLocaleString() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
