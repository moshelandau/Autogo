<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({ markdowns: Object, filters: Object, dealers: Array });

const q = ref(props.filters?.q ?? '');
let qTimer = null;
watch(q, (v) => {
    clearTimeout(qTimer);
    qTimer = setTimeout(() => {
        router.get(route('leasing.dealer-markdowns.index'), { q: v }, { preserveState: true, replace: true });
    }, 250);
});

const form = useForm({
    dealer_id: '', dealer_name: '', amount: null, title: '',
    make: '', model: '', year_from: null, year_to: null,
    valid_from: '', valid_through: '', notes: '',
});
const submit = () => form.post(route('leasing.dealer-markdowns.store'), {
    preserveScroll: true, onSuccess: () => form.reset(),
});

const editing = ref(null);
const editForm = useForm({});
const startEdit = (row) => {
    editing.value = row.id;
    Object.assign(editForm, {
        dealer_id: row.dealer_id, dealer_name: row.dealer_name,
        amount: row.amount, title: row.title,
        make: row.make, model: row.model,
        year_from: row.year_from, year_to: row.year_to,
        valid_from: row.valid_from?.slice(0,10), valid_through: row.valid_through?.slice(0,10),
        notes: row.notes, is_active: row.is_active,
    });
};
const saveEdit = () => editForm.put(route('leasing.dealer-markdowns.update', editing.value), {
    preserveScroll: true, onSuccess: () => { editing.value = null; },
});
const cancelEdit = () => { editing.value = null; };
const deactivate = (row) => {
    if (!confirm(`Deactivate "${row.title}"?`)) return;
    router.delete(route('leasing.dealer-markdowns.destroy', row.id), { preserveScroll: true });
};

const fmtDate = (d) => d ? new Date(d).toLocaleDateString() : '—';
const fmtMoney = (a) => a ? '$' + Number(a).toLocaleString() : '—';
const isExpired = (d) => d && new Date(d) < new Date();
</script>

<template>
    <AppLayout title="Dealer Markdowns">
        <template #header><h2 class="font-semibold text-xl text-gray-800">Dealer Markdowns</h2></template>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-900">
                    <p>Custom offers from individual dealer reps that aren't on any OEM site. Enter them here and they'll show up in the Quote Wizard's <strong>Available Rebates</strong> picker alongside MarketCheck OEM rebates.</p>
                </div>

                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-medium text-sm mb-3">Add Dealer Markdown</h3>
                    <form @submit.prevent="submit" class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div>
                            <label class="text-xs text-gray-500">Dealer</label>
                            <select v-model="form.dealer_id" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="">— Pick from list —</option>
                                <option v-for="d in dealers" :key="d.id" :value="d.id">{{ d.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Or dealer name (free-text)</label>
                            <input v-model="form.dealer_name" type="text" placeholder="Hudson Honda" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Amount *</label>
                            <input v-model.number="form.amount" type="number" step="50" min="0" placeholder="500" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Title *</label>
                            <input v-model="form.title" type="text" placeholder="Spring Loyalty Adder" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Make (optional)</label>
                            <input v-model="form.make" type="text" placeholder="Honda" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Model (optional)</label>
                            <input v-model="form.model" type="text" placeholder="CR-V" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Year from</label>
                            <input v-model.number="form.year_from" type="number" min="1990" max="2099" placeholder="2024" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Year to</label>
                            <input v-model.number="form.year_to" type="number" min="1990" max="2099" placeholder="2026" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Valid from</label>
                            <input v-model="form.valid_from" type="date" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Valid through</label>
                            <input v-model="form.valid_through" type="date" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-xs text-gray-500">Notes (rep name, conditions, source)</label>
                            <input v-model="form.notes" type="text" placeholder="From Mike at Hudson — call 4/30, only good through May" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                        </div>
                        <div class="md:col-span-4 flex justify-end">
                            <button type="submit" :disabled="form.processing || !form.amount || !form.title"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700 disabled:opacity-50">Add Markdown</button>
                        </div>
                    </form>
                </div>

                <div class="bg-white shadow-sm rounded-lg">
                    <div class="p-4 border-b">
                        <input v-model="q" type="text" placeholder="Search by title, dealer, make, model, notes…"
                               class="block w-full md:w-96 border-gray-300 rounded-md shadow-sm text-sm" />
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Dealer</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Vehicle Scope</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Validity</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                                    <th class="px-4 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <template v-for="row in markdowns.data" :key="row.id">
                                    <tr v-if="editing !== row.id"
                                        :class="['hover:bg-gray-50',
                                            !row.is_active ? 'opacity-50' : isExpired(row.valid_through) ? 'bg-red-50/40' : '']">
                                        <td class="px-4 py-2 text-sm font-bold text-emerald-700">{{ fmtMoney(row.amount) }}</td>
                                        <td class="px-4 py-2 text-sm">{{ row.title }}</td>
                                        <td class="px-4 py-2 text-sm">{{ row.dealer?.name || row.dealer_name || '—' }}</td>
                                        <td class="px-4 py-2 text-sm">
                                            <span v-if="row.make">{{ row.year_from }}{{ row.year_to ? '–' + row.year_to : '' }} {{ row.make }} {{ row.model || '(any model)' }}</span>
                                            <span v-else class="text-gray-400 italic">Any vehicle</span>
                                        </td>
                                        <td class="px-4 py-2 text-sm">
                                            <span v-if="row.valid_from || row.valid_through">{{ fmtDate(row.valid_from) }} → {{ fmtDate(row.valid_through) }}</span>
                                            <span v-else class="text-gray-400 italic">No window</span>
                                            <span v-if="isExpired(row.valid_through)" class="ml-1 text-xs text-red-600">expired</span>
                                        </td>
                                        <td class="px-4 py-2 text-xs text-gray-600 max-w-xs truncate" :title="row.notes">{{ row.notes || '—' }}</td>
                                        <td class="px-4 py-2 text-right whitespace-nowrap">
                                            <button @click="startEdit(row)" class="text-indigo-600 text-sm hover:underline">Edit</button>
                                            <button v-if="row.is_active" @click="deactivate(row)" class="ml-3 text-red-600 text-sm hover:underline">Deactivate</button>
                                        </td>
                                    </tr>
                                    <tr v-else class="bg-indigo-50">
                                        <td colspan="7" class="p-4">
                                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                                <select v-model="editForm.dealer_id" class="block w-full border-gray-300 rounded-md text-sm">
                                                    <option value="">— Pick dealer —</option>
                                                    <option v-for="d in dealers" :key="d.id" :value="d.id">{{ d.name }}</option>
                                                </select>
                                                <input v-model="editForm.dealer_name" placeholder="Or free-text dealer" class="block w-full border-gray-300 rounded-md text-sm" />
                                                <input v-model.number="editForm.amount" type="number" step="50" placeholder="Amount" class="block w-full border-gray-300 rounded-md text-sm" />
                                                <input v-model="editForm.title" placeholder="Title *" class="block w-full border-gray-300 rounded-md text-sm" />
                                                <input v-model="editForm.make" placeholder="Make" class="block w-full border-gray-300 rounded-md text-sm" />
                                                <input v-model="editForm.model" placeholder="Model" class="block w-full border-gray-300 rounded-md text-sm" />
                                                <input v-model.number="editForm.year_from" type="number" placeholder="Year from" class="block w-full border-gray-300 rounded-md text-sm" />
                                                <input v-model.number="editForm.year_to" type="number" placeholder="Year to" class="block w-full border-gray-300 rounded-md text-sm" />
                                                <input v-model="editForm.valid_from" type="date" class="block w-full border-gray-300 rounded-md text-sm" />
                                                <input v-model="editForm.valid_through" type="date" class="block w-full border-gray-300 rounded-md text-sm" />
                                                <label class="flex items-center text-sm gap-2"><input v-model="editForm.is_active" type="checkbox" /> Active</label>
                                                <input v-model="editForm.notes" placeholder="Notes" class="block w-full border-gray-300 rounded-md text-sm md:col-span-4" />
                                            </div>
                                            <div class="mt-3 flex gap-2">
                                                <button @click="saveEdit" :disabled="editForm.processing"
                                                        class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700">Save</button>
                                                <button @click="cancelEdit" class="px-4 py-2 bg-gray-200 rounded-md text-sm">Cancel</button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                <tr v-if="!markdowns.data.length"><td colspan="7" class="px-4 py-12 text-center text-sm text-gray-400">No dealer markdowns yet. Add one above.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
