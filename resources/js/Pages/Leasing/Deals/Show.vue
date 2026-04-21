<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import SmsButton from '@/Components/SmsButton.vue';

const props = defineProps({
    deal: Object,
    lenders: Array,
    creditPulls: { type: Array, default: () => [] },
    creditConfigured: { type: Boolean, default: false },
});
const d = props.deal;
const fmt = (v) => v ? '$' + parseFloat(v).toLocaleString() : '-';
const activeTab = ref('summary');

const stageLabels = { lead: 'Lead', quote: 'Quote', application: 'Application', submission: 'Submission', pending: 'Pending', finalize: 'Finalize', outstanding: 'Outstanding', complete: 'Complete', lost: 'Lost' };
const allStages = ['lead', 'quote', 'application', 'submission', 'pending', 'finalize', 'outstanding', 'complete'];

const noteForm = useForm({ body: '' });
const addNote = () => { noteForm.post(route('leasing.deals.note', d.id), { onSuccess: () => noteForm.reset() }); };

// Credit pull (inline)
const latestPull = computed(() => props.creditPulls?.[0] || null);
const creditPullForm = useForm({});
const runCreditPull = () => {
    if (!confirm('Run a soft credit pull for this customer? This will not affect their credit score.')) return;
    creditPullForm.post(route('leasing.deals.credit-pull', d.id), { preserveScroll: true });
};

const quoteForm = useForm({ lender_id: '', payment_type: d.payment_type, term: 36, mileage_per_year: 10000, monthly_payment: '', das: '', sell_price: d.sell_price || '', msrp: d.msrp || '', rebates: 0, notes: '' });
const addQuote = () => { quoteForm.post(route('leasing.deals.quote', d.id), { onSuccess: () => quoteForm.reset() }); };

const completeTask = (taskId) => router.post(route('leasing.deals.task', { deal: d.id, task: taskId }));
const transitionTo = (stage) => router.post(route('leasing.deals.transition', d.id), { stage });
const selectQuote = (quoteId) => router.post(route('leasing.deals.select-quote', { deal: d.id, quote: quoteId }));

const priorityColors = { low: 'bg-green-100 text-green-800', medium: 'bg-yellow-100 text-yellow-800', high: 'bg-red-100 text-red-800' };

// Calculator state
const calc = ref({
    type: d.payment_type || 'lease',
    msrp: d.msrp || '',
    sell_price: d.sell_price || '',
    term: d.term || 36,
    annual_mileage: d.mileage_per_year || 10000,
    residual_pct: '',
    money_factor: '',
    apr: '',
    acquisition_fee: 595,
    down_payment: 0,
    trade_equity: 0,
    rebates_total: 0,
    tax_rate: 0.08875,
    doc_fees: 199,
});
const calcResult = ref(null);
const calcLoading = ref(false);
const matchedProgram = ref(null);

const findProgram = async () => {
    if (!d.vehicle_make || !d.vehicle_model) {
        alert('Set vehicle make/model on the deal first');
        return;
    }
    const res = await fetch(route('quote-calculator.find-program'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
        credentials: 'same-origin',
        body: JSON.stringify({
            make: d.vehicle_make,
            model: d.vehicle_model,
            term: parseInt(calc.value.term),
            annual_mileage: parseInt(calc.value.annual_mileage),
            credit_score: d.credit_score,
        }),
    });
    const data = await res.json();
    matchedProgram.value = data.program;
    if (data.program) {
        calc.value.residual_pct = data.program.residual_pct || '';
        calc.value.money_factor = data.program.money_factor || '';
        calc.value.apr = data.program.apr || '';
        calc.value.acquisition_fee = data.program.acquisition_fee || 595;
    } else {
        alert('No matching lender program found. Add one in Lender Programs first.');
    }
};

const runCalculator = async () => {
    calcLoading.value = true;
    try {
        const res = await fetch(route('quote-calculator.calculate'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
            credentials: 'same-origin',
            body: JSON.stringify(calc.value),
        });
        calcResult.value = await res.json();
    } catch (e) { console.error(e); }
    calcLoading.value = false;
};

const saveCalcAsQuote = () => {
    if (!calcResult.value) return;
    quoteForm.payment_type = calc.value.type;
    quoteForm.term = calc.value.term;
    quoteForm.mileage_per_year = calc.value.annual_mileage;
    quoteForm.monthly_payment = calcResult.value.monthly_payment;
    quoteForm.das = calcResult.value.das || calcResult.value.down_payment;
    quoteForm.sell_price = calc.value.sell_price;
    quoteForm.msrp = calc.value.msrp;
    quoteForm.rebates = calc.value.rebates_total;
    if (matchedProgram.value) quoteForm.lender_id = matchedProgram.value.lender_id;
    addQuote();
    activeTab.value = 'quotes';
};
</script>

<template>
    <AppLayout title="Deal Details">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('leasing.deals.index')" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Deal #{{ d.deal_number }}</h2>
                    <span class="px-2 py-1 text-xs rounded-full capitalize" :class="priorityColors[d.priority]">{{ d.priority }}</span>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Stage Pipeline -->
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="flex gap-1 overflow-x-auto">
                        <button v-for="stage in allStages" :key="stage"
                                @click="d.stage !== stage && d.stage !== 'lost' && d.stage !== 'complete' ? transitionTo(stage) : null"
                                class="px-3 py-2 text-xs rounded-md whitespace-nowrap transition-colors"
                                :class="d.stage === stage ? 'bg-indigo-600 text-white' : allStages.indexOf(stage) < allStages.indexOf(d.stage) ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'">
                            {{ stageLabels[stage] }}
                        </button>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <h3 class="text-sm font-medium text-gray-500 mb-3">Customer</h3>
                        <p class="font-bold text-lg">{{ d.customer?.first_name }} {{ d.customer?.last_name }}</p>
                        <p class="text-sm text-gray-600 flex items-center gap-2">
                            <span>{{ d.customer?.phone }}</span>
                            <SmsButton v-if="d.customer?.phone" :to="d.customer.phone" :customer-id="d.customer.id" subject-type="App\\Models\\Deal" :subject-id="d.id" label="SMS" />
                        </p>
                        <p v-if="d.credit_score" class="text-sm text-gray-600">Score: {{ d.credit_score }}</p>
                    </div>
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <h3 class="text-sm font-medium text-gray-500 mb-3">Vehicle</h3>
                        <p v-if="d.vehicle_make" class="font-bold">{{ d.vehicle_year }} {{ d.vehicle_make }} {{ d.vehicle_model }} {{ d.vehicle_trim || '' }}</p>
                        <p v-else class="text-gray-400 italic">No vehicle yet</p>
                        <p v-if="d.vehicle_vin" class="text-xs font-mono text-gray-500 mt-1">VIN: {{ d.vehicle_vin }}</p>
                        <p v-if="d.msrp" class="text-sm text-gray-600 mt-1">MSRP: {{ fmt(d.msrp) }}</p>
                    </div>
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <h3 class="text-sm font-medium text-gray-500 mb-3">Deal Info</h3>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between"><span class="text-gray-500">Type</span><span class="capitalize font-medium">{{ d.payment_type }}</span></div>
                            <div v-if="d.monthly_payment" class="flex justify-between"><span class="text-gray-500">Payment</span><span class="font-bold text-lg text-green-700">{{ fmt(d.monthly_payment) }}/mo</span></div>
                            <div v-if="d.term" class="flex justify-between"><span class="text-gray-500">Term</span><span>{{ d.term }} months</span></div>
                            <div v-if="d.lender" class="flex justify-between"><span class="text-gray-500">Lender</span><span>{{ d.lender.name }}</span></div>
                            <div v-if="d.profit" class="flex justify-between"><span class="text-gray-500">Profit</span><span class="text-green-600 font-bold">{{ fmt(d.profit) }}</span></div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="bg-white shadow-sm rounded-lg">
                    <div class="border-b flex gap-0 overflow-x-auto">
                        <button v-for="tab in ['summary', 'tasks', 'calculator', 'quotes', 'credit', 'notes', 'documents']" :key="tab"
                                @click="activeTab = tab"
                                class="px-6 py-3 text-sm font-medium capitalize whitespace-nowrap"
                                :class="activeTab === tab ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-500 hover:text-gray-700'">
                            {{ tab }}
                            <span v-if="tab === 'tasks' && d.tasks?.filter(t => !t.is_completed).length"
                                  class="ml-1 bg-red-500 text-white text-xs px-1.5 py-0.5 rounded-full">{{ d.tasks.filter(t => !t.is_completed).length }}</span>
                        </button>
                    </div>

                    <div class="p-6">
                        <!-- Tasks Tab -->
                        <div v-if="activeTab === 'tasks'" class="space-y-2">
                            <div v-for="task in d.tasks" :key="task.id"
                                 class="flex items-center gap-3 py-2 border-b last:border-0">
                                <button @click="!task.is_completed && completeTask(task.id)"
                                        class="w-5 h-5 rounded border-2 flex items-center justify-center transition-colors"
                                        :class="task.is_completed ? 'bg-green-500 border-green-500 text-white' : 'border-gray-300 hover:border-green-400'">
                                    <span v-if="task.is_completed" class="text-xs">&#10003;</span>
                                </button>
                                <div class="flex-1">
                                    <span class="text-sm" :class="task.is_completed ? 'line-through text-gray-400' : ''">{{ task.name }}</span>
                                    <span v-if="task.stage" class="ml-2 text-xs text-gray-400 capitalize">({{ task.stage }})</span>
                                </div>
                                <span v-if="task.due_date && !task.is_completed" class="text-xs"
                                      :class="new Date(task.due_date) < new Date() ? 'text-red-600 font-bold' : 'text-gray-400'">
                                    {{ task.due_date }}
                                </span>
                            </div>
                            <p v-if="!d.tasks?.length" class="text-gray-500 text-sm">No tasks yet.</p>
                        </div>

                        <!-- Quotes Tab -->
                        <div v-if="activeTab === 'quotes'" class="space-y-4">
                            <div v-for="q in d.quotes" :key="q.id"
                                 class="border rounded-lg p-4" :class="q.is_selected ? 'border-green-500 bg-green-50' : 'border-gray-200'">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <span class="font-bold text-lg">{{ fmt(q.monthly_payment) }}/mo</span>
                                        <span class="text-sm text-gray-500 ml-2">{{ q.term }}mo / {{ q.mileage_per_year?.toLocaleString() }}mi</span>
                                    </div>
                                    <div class="flex gap-2">
                                        <span v-if="q.is_selected" class="text-xs bg-green-600 text-white px-2 py-1 rounded">Selected</span>
                                        <button v-else @click="selectQuote(q.id)" class="text-xs bg-indigo-100 text-indigo-700 px-2 py-1 rounded hover:bg-indigo-200">Select</button>
                                    </div>
                                </div>
                                <div class="grid grid-cols-4 gap-2 text-xs text-gray-500">
                                    <div>Lender: {{ q.lender?.name || '-' }}</div>
                                    <div>DAS: {{ fmt(q.das) }}</div>
                                    <div>Sell: {{ fmt(q.sell_price) }}</div>
                                    <div>Type: <span class="capitalize">{{ q.payment_type }}</span></div>
                                </div>
                            </div>

                            <div class="border-t pt-4">
                                <h4 class="font-medium text-sm mb-3">Add Quote</h4>
                                <form @submit.prevent="addQuote" class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    <div><select v-model="quoteForm.lender_id" class="block w-full border-gray-300 rounded-md shadow-sm text-xs"><option value="">Lender</option><option v-for="l in lenders" :key="l.id" :value="l.id">{{ l.name }}</option></select></div>
                                    <div><input v-model="quoteForm.monthly_payment" type="number" step="0.01" placeholder="Monthly $" class="block w-full border-gray-300 rounded-md shadow-sm text-xs" /></div>
                                    <div><input v-model="quoteForm.term" type="number" placeholder="Term (mo)" class="block w-full border-gray-300 rounded-md shadow-sm text-xs" /></div>
                                    <div><input v-model="quoteForm.das" type="number" step="0.01" placeholder="DAS $" class="block w-full border-gray-300 rounded-md shadow-sm text-xs" /></div>
                                    <div class="md:col-span-4"><button type="submit" :disabled="quoteForm.processing" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-xs hover:bg-indigo-700">Add Quote</button></div>
                                </form>
                            </div>
                        </div>

                        <!-- Notes Tab -->
                        <div v-if="activeTab === 'notes'" class="space-y-4">
                            <form @submit.prevent="addNote" class="flex gap-3">
                                <input v-model="noteForm.body" type="text" placeholder="Add a note..." class="flex-1 border-gray-300 rounded-md shadow-sm text-sm" />
                                <button type="submit" :disabled="noteForm.processing || !noteForm.body" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700 disabled:opacity-50">Add</button>
                            </form>
                            <div v-for="note in d.deal_notes" :key="note.id" class="border-b py-3 last:border-0">
                                <div class="flex justify-between text-xs text-gray-400 mb-1">
                                    <span>{{ note.user?.name || 'System' }}</span>
                                    <span>{{ new Date(note.created_at).toLocaleString() }}</span>
                                </div>
                                <p class="text-sm text-gray-800">{{ note.body }}</p>
                            </div>
                            <p v-if="!d.deal_notes?.length" class="text-gray-500 text-sm">No notes yet.</p>
                        </div>

                        <!-- Documents Tab -->
                        <div v-if="activeTab === 'documents'" class="space-y-2">
                            <div v-for="doc in d.documents" :key="doc.id" class="flex items-center gap-3 py-2 border-b">
                                <span class="text-sm font-medium">{{ doc.name }}</span>
                                <span class="text-xs text-gray-400 capitalize">{{ doc.type }}</span>
                            </div>
                            <p v-if="!d.documents?.length" class="text-gray-500 text-sm">No documents uploaded yet.</p>
                        </div>

                        <!-- Calculator Tab -->
                        <div v-if="activeTab === 'calculator'" class="space-y-4">
                            <div class="flex items-center gap-2 mb-3">
                                <button @click="findProgram" class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs hover:bg-blue-700">⚡ Auto-Find Lender Program</button>
                                <span v-if="matchedProgram" class="text-xs text-green-600 font-medium">✓ Loaded {{ matchedProgram.lender?.name }} program</span>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <div>
                                    <label class="text-xs text-gray-500">Type</label>
                                    <select v-model="calc.type" class="mt-1 block w-full border-gray-300 rounded-lg text-sm">
                                        <option value="lease">Lease</option><option value="finance">Finance</option>
                                    </select>
                                </div>
                                <div><label class="text-xs text-gray-500">MSRP</label><input v-model="calc.msrp" type="number" step="0.01" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                                <div><label class="text-xs text-gray-500">Sell Price</label><input v-model="calc.sell_price" type="number" step="0.01" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                                <div>
                                    <label class="text-xs text-gray-500">Term</label>
                                    <select v-model="calc.term" class="mt-1 block w-full border-gray-300 rounded-lg text-sm">
                                        <option value="24">24</option><option value="36">36</option><option value="39">39</option><option value="42">42</option><option value="48">48</option><option value="60">60</option><option value="72">72</option>
                                    </select>
                                </div>
                                <div v-if="calc.type === 'lease'">
                                    <label class="text-xs text-gray-500">Annual Mileage</label>
                                    <select v-model="calc.annual_mileage" class="mt-1 block w-full border-gray-300 rounded-lg text-sm">
                                        <option value="7500">7,500</option><option value="10000">10,000</option><option value="12000">12,000</option><option value="15000">15,000</option>
                                    </select>
                                </div>
                                <div v-if="calc.type === 'lease'"><label class="text-xs text-gray-500">Residual %</label><input v-model="calc.residual_pct" type="number" step="0.01" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                                <div v-if="calc.type === 'lease'"><label class="text-xs text-gray-500">Money Factor</label><input v-model="calc.money_factor" type="number" step="0.000001" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                                <div v-if="calc.type === 'finance'"><label class="text-xs text-gray-500">APR %</label><input v-model="calc.apr" type="number" step="0.001" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                                <div><label class="text-xs text-gray-500">Acquisition Fee</label><input v-model="calc.acquisition_fee" type="number" step="0.01" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                                <div><label class="text-xs text-gray-500">Down Payment</label><input v-model="calc.down_payment" type="number" step="0.01" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                                <div><label class="text-xs text-gray-500">Trade Equity</label><input v-model="calc.trade_equity" type="number" step="0.01" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                                <div><label class="text-xs text-gray-500">Rebates Total</label><input v-model="calc.rebates_total" type="number" step="0.01" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                                <div><label class="text-xs text-gray-500">Tax Rate</label><input v-model="calc.tax_rate" type="number" step="0.0001" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                                <div><label class="text-xs text-gray-500">Doc Fees</label><input v-model="calc.doc_fees" type="number" step="0.01" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                            </div>
                            <button @click="runCalculator" :disabled="calcLoading" class="w-full px-4 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 disabled:opacity-50">
                                {{ calcLoading ? 'Calculating...' : '🧮 Calculate Quote' }}
                            </button>

                            <div v-if="calcResult" class="bg-gradient-to-br from-indigo-50 to-blue-50 rounded-2xl border-2 border-indigo-200 p-6">
                                <div class="grid grid-cols-2 gap-6">
                                    <div class="text-center border-r border-indigo-200">
                                        <div class="text-5xl font-extrabold text-indigo-700">{{ fmt(calcResult.monthly_payment) }}</div>
                                        <div class="text-sm text-indigo-600 mt-1">/month</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-5xl font-extrabold text-gray-700">{{ fmt(calcResult.das || calcResult.down_payment) }}</div>
                                        <div class="text-sm text-gray-600 mt-1">{{ calc.type === 'lease' ? 'Drive at Signing' : 'Down Payment' }}</div>
                                    </div>
                                </div>
                                <div class="mt-6 pt-6 border-t border-indigo-200 grid grid-cols-2 gap-3 text-sm">
                                    <div v-if="calc.type === 'lease'"><span class="text-gray-500">Residual Value:</span> {{ fmt(calcResult.residual_value) }}</div>
                                    <div v-if="calc.type === 'lease'"><span class="text-gray-500">Depreciation:</span> {{ fmt(calcResult.depreciation) }}/mo</div>
                                    <div v-if="calc.type === 'lease'"><span class="text-gray-500">Rent Charge:</span> {{ fmt(calcResult.rent_charge) }}/mo</div>
                                    <div v-if="calc.type === 'lease'"><span class="text-gray-500">Monthly Tax:</span> {{ fmt(calcResult.monthly_tax) }}</div>
                                    <div v-if="calc.type === 'lease'"><span class="text-gray-500">APR Equivalent:</span> {{ calcResult.apr_equivalent }}%</div>
                                    <div v-if="calc.type === 'lease'"><span class="text-gray-500">Total Lease Cost:</span> {{ fmt(calcResult.total_lease_cost) }}</div>
                                    <div v-if="calc.type === 'finance'"><span class="text-gray-500">Amount Financed:</span> {{ fmt(calcResult.amount_financed) }}</div>
                                    <div v-if="calc.type === 'finance'"><span class="text-gray-500">Total Interest:</span> {{ fmt(calcResult.total_interest) }}</div>
                                    <div v-if="calc.type === 'finance'"><span class="text-gray-500">Total Paid:</span> {{ fmt(calcResult.total_paid) }}</div>
                                </div>
                                <button @click="saveCalcAsQuote" class="mt-4 w-full px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700">💾 Save as Quote</button>
                            </div>
                        </div>

                        <!-- Credit Tab — inline soft pull (no separate page) -->
                        <div v-if="activeTab === 'credit'" class="space-y-4">
                            <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 text-xs text-blue-900">
                                <strong>Soft pull only.</strong> AutoGo never runs hard pulls — those are done by the dealer / lender after the application is submitted. No SSN required, no impact on the customer's credit score.
                                <span v-if="!creditConfigured" class="block mt-1 text-amber-700">⚠ 700Credit API key not configured — pulls will return mock scores. Set CREDIT700_API_KEY in Settings.</span>
                            </div>

                            <!-- Latest score banner -->
                            <div v-if="latestPull || d.credit_score" class="bg-white border-2 border-emerald-200 rounded-2xl p-6 text-center">
                                <div class="text-6xl font-extrabold text-gray-900">{{ latestPull?.credit_score || d.credit_score }}</div>
                                <p class="text-sm text-gray-500 mt-1">
                                    Latest credit score
                                    <span v-if="latestPull"> · {{ latestPull.bureau }} {{ latestPull.credit_score_model }} · {{ new Date(latestPull.created_at).toLocaleDateString() }}</span>
                                </p>
                                <p v-if="latestPull?.credit_tier" class="text-xs text-gray-500 mt-1 capitalize">Tier: {{ latestPull.credit_tier?.replace('_', ' ') }}</p>
                            </div>
                            <p v-else class="text-center text-sm text-gray-400 py-6">No credit score on file yet.</p>

                            <!-- Inline pull button + history -->
                            <div class="bg-white border rounded-xl p-5">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="font-semibold text-sm">Run a soft pull for {{ d.customer?.first_name }} {{ d.customer?.last_name }}</h4>
                                    <button @click="runCreditPull" :disabled="creditPullForm.processing"
                                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50">
                                        {{ creditPullForm.processing ? 'Pulling…' : '🔍 Run Soft Pull' }}
                                    </button>
                                </div>
                                <p class="text-xs text-gray-500">Uses customer's name + DOB + address on file. Result is cached for 30 days.</p>
                            </div>

                            <!-- Pull history -->
                            <div v-if="creditPulls?.length" class="bg-white border rounded-xl overflow-hidden">
                                <header class="p-4 border-b">
                                    <h4 class="font-semibold text-sm">Pull history ({{ creditPulls.length }})</h4>
                                </header>
                                <table class="min-w-full text-sm">
                                    <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>
                                        <th class="px-3 py-2 text-left">Date</th>
                                        <th class="px-3 py-2 text-center">Score</th>
                                        <th class="px-3 py-2 text-left">Bureau</th>
                                        <th class="px-3 py-2 text-left">Tier</th>
                                        <th class="px-3 py-2 text-left">By</th>
                                    </tr></thead>
                                    <tbody class="divide-y">
                                        <tr v-for="p in creditPulls" :key="p.id">
                                            <td class="px-3 py-2 text-xs">{{ new Date(p.created_at).toLocaleDateString() }}</td>
                                            <td class="px-3 py-2 text-center font-bold">{{ p.credit_score || '—' }}</td>
                                            <td class="px-3 py-2 text-xs uppercase">{{ p.bureau }}</td>
                                            <td class="px-3 py-2 text-xs capitalize">{{ p.credit_tier?.replace('_', ' ') || '—' }}</td>
                                            <td class="px-3 py-2 text-xs text-gray-500">{{ p.pulled_by_user?.name || '—' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Summary Tab -->
                        <div v-if="activeTab === 'summary'" class="grid grid-cols-2 gap-4 text-sm">
                            <div><span class="text-gray-500">Sell Price:</span> {{ fmt(d.sell_price) }}</div>
                            <div><span class="text-gray-500">MSRP:</span> {{ fmt(d.msrp) }}</div>
                            <div><span class="text-gray-500">Trade Allowance:</span> {{ fmt(d.trade_allowance) }}</div>
                            <div><span class="text-gray-500">Trade Payoff:</span> {{ fmt(d.trade_payoff) }}</div>
                            <div><span class="text-gray-500">Drive Off:</span> {{ fmt(d.drive_off) }}</div>
                            <div><span class="text-gray-500">Mileage/Year:</span> {{ d.mileage_per_year?.toLocaleString() || '-' }}</div>
                            <div class="col-span-2"><span class="text-gray-500">Notes:</span> {{ d.notes || '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
