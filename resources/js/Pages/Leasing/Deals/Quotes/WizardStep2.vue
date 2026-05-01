<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { reactive, computed } from 'vue';

const props = defineProps({
    deal:     Object,
    drafts:   Array,
    programs: Array,
    lenders:  Array,
});

// One row per draft quote — user picks a lender + (optional) program
const assignments = reactive(
    props.drafts.map(d => ({
        draft_id:     d.id,
        term:         d.term,
        msrp:         d.msrp,
        lender_id:    d.lender_id || '',
        program_id:   '',
        money_factor: d.money_factor,
        apr:          d.apr,
        residual_pct: '',
    }))
);

// Filter the program pool to those that fit a given draft (same term)
const programsFor = (a) => {
    return props.programs.filter(p => p.term === a.term);
};

// Apply a program → fill the row's MF/APR/residual from it
const applyProgram = (a, programId) => {
    a.program_id = programId;
    if (!programId) return;
    const p = props.programs.find(x => String(x.id) === String(programId));
    if (!p) return;
    a.lender_id    = p.lender_id;
    a.money_factor = p.money_factor;
    a.apr          = p.apr;
    a.residual_pct = p.residual_pct;
};

const fmtMoney  = (v) => v != null && v !== '' ? '$' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—';
const aprFromMf = (mf) => mf ? (Number(mf) * 2400).toFixed(3) : null;

const form = useForm({});
const save = () => {
    form
        .transform(() => ({ assignments }))
        .post(route('leasing.deals.quotes.wizard.step2.save', props.deal.id));
};
const allAssigned = computed(() => assignments.every(a => a.lender_id));
</script>

<template>
    <AppLayout title="Quote Wizard — Lender Selection">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800">Pick a Lender — Deal #{{ deal.deal_number }}</h2>
                    <p class="text-sm text-gray-500">{{ deal.customer?.first_name }} {{ deal.customer?.last_name }} · {{ deal.vehicle_year }} {{ deal.vehicle_make }} {{ deal.vehicle_model }}</p>
                </div>
                <Link :href="route('leasing.deals.show', deal.id)" class="text-sm text-gray-600 hover:underline">← Back to deal</Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

                <!-- 3-step nav -->
                <div class="bg-white shadow-sm rounded-lg p-3 flex items-center gap-2 text-sm">
                    <Link :href="route('leasing.deals.quotes.wizard', deal.id)" class="px-3 py-1 bg-emerald-100 text-emerald-800 rounded hover:bg-emerald-200">✓ 1. Quote Structure</Link>
                    <span class="text-gray-300">›</span>
                    <span class="px-3 py-1 bg-indigo-600 text-white rounded font-semibold">2. Lender Selection</span>
                    <span class="text-gray-300">›</span>
                    <span class="px-3 py-1 bg-gray-100 text-gray-400 rounded">3. Worksheet (next PR)</span>
                </div>

                <div v-if="!programs.length" class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm text-amber-900">
                    <strong>No matching lender programs found.</strong>
                    No programs in <Link :href="route('lender-programs.index')" class="underline">/lender-programs</Link> match this deal's
                    make/model/term/credit score. Pick a lender below by name and enter the rate manually, or add a program first.
                </div>

                <!-- One card per draft quote (one per term) -->
                <div v-for="(a, i) in assignments" :key="a.draft_id"
                     class="bg-white shadow-sm rounded-lg p-5 border-l-4"
                     :class="a.lender_id ? 'border-emerald-500' : 'border-gray-300'">
                    <div class="flex items-baseline justify-between mb-3">
                        <h3 class="font-semibold text-base text-gray-800">
                            Draft #{{ i + 1 }} — {{ a.term }}-month {{ deal.payment_type }}
                        </h3>
                        <span class="text-xs text-gray-500">MSRP {{ fmtMoney(a.msrp) }}</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 text-sm">
                        <!-- Program picker (filtered by term) -->
                        <div class="md:col-span-5">
                            <label class="block text-xs text-gray-500 mb-1">Lender Program (filtered to {{ a.term }}-month)</label>
                            <select :value="a.program_id" @change="applyProgram(a, $event.target.value)"
                                    class="block w-full border-gray-300 rounded-md text-sm">
                                <option value="">— Pick a program (or set lender manually →) —</option>
                                <option v-for="p in programsFor(a)" :key="p.id" :value="p.id">
                                    {{ p.lender?.name }} · MF {{ p.money_factor || '—' }} · APR {{ p.apr || '—' }}% · Res {{ p.residual_pct || '—' }}%
                                    {{ p.min_credit_score ? '(≥' + p.min_credit_score + ')' : '' }}
                                </option>
                            </select>
                            <p v-if="!programsFor(a).length" class="text-[11px] text-amber-700 mt-1">No programs for this term + vehicle.</p>
                        </div>

                        <!-- Lender override -->
                        <div class="md:col-span-3">
                            <label class="block text-xs text-gray-500 mb-1">Lender</label>
                            <select v-model="a.lender_id" class="block w-full border-gray-300 rounded-md text-sm">
                                <option value="">— Pick lender —</option>
                                <option v-for="l in lenders" :key="l.id" :value="l.id">{{ l.name }}</option>
                            </select>
                        </div>

                        <!-- Rate fields (manual override) -->
                        <div class="md:col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">Money Factor</label>
                            <input v-model.number="a.money_factor" type="number" step="0.000001" min="0" placeholder="0.00214"
                                   class="block w-full border-gray-300 rounded-md text-sm" />
                            <p v-if="a.money_factor" class="text-[10px] text-gray-400 mt-0.5">≈ {{ aprFromMf(a.money_factor) }}% APR</p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">APR % (finance)</label>
                            <input v-model.number="a.apr" type="number" step="0.001" min="0" placeholder="6.99"
                                   class="block w-full border-gray-300 rounded-md text-sm" />
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-white shadow-sm rounded-lg p-4 flex items-center justify-between">
                    <Link :href="route('leasing.deals.quotes.wizard', deal.id)"
                          class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md text-sm hover:bg-gray-200">← Back to Quote Structure</Link>
                    <div class="flex items-center gap-2">
                        <span v-if="!allAssigned" class="text-xs text-amber-600">Pick a lender for every draft →</span>
                        <button type="button" @click="save" :disabled="form.processing || !allAssigned"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700 disabled:opacity-50">
                            {{ form.processing ? 'Saving…' : 'Save Lenders → (Worksheet next PR)' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
