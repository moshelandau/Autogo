<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, reactive, watch } from 'vue';
import SmsButton from '@/Components/SmsButton.vue';
import CustomerMessages from '@/Components/CustomerMessages.vue';
import CustomerSelect from '@/Components/CustomerSelect.vue';
import NotesPanel from '@/Components/Notes/NotesPanel.vue';

const props = defineProps({
    deal: Object,
    lenders: Array,
    creditPulls: { type: Array, default: () => [] },
    creditConfigured: { type: Boolean, default: false },
});

// `const d = props.deal` snapshots the reference at mount; Inertia replaces
// props.deal with a new object after every action, so the snapshot goes
// stale and the page looks frozen until you hard-refresh. Wrapping it in
// reactive + watch keeps `d.X` reads pointing at the current props.deal
// without forcing a 80-callsite rename to props.deal.X.
const d = reactive({ ...props.deal });
watch(() => props.deal, (val) => Object.assign(d, val), { deep: false });
const fmt = (v) => v ? '$' + parseFloat(v).toLocaleString() : '-';
const fmtDate = (v) => v ? new Date(v).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) : '';
const activeTab = ref('summary');

const stageLabels = { lead: 'Lead', quote: 'Quote', application: 'Application', submission: 'Submission', pending: 'Pending', finalize: 'Finalize', outstanding: 'Outstanding', complete: 'Complete', lost: 'Lost' };
const allStages = ['lead', 'quote', 'application', 'submission', 'pending', 'finalize', 'outstanding', 'complete'];

// Required document checklist on the Documents tab. Sourced from
// customer.documents (the SMS bot saves license uploads there) plus any
// deal-specific docs uploaded from this page. Match logic prefers the
// most recent doc whose `type` equals the row's type.
const REQUIRED_DOCS = [
    { type: 'drivers_license_front', label: "Driver's license — front" },
    { type: 'drivers_license_back',  label: "Driver's license — back" },
    { type: 'insurance_card',        label: 'Insurance card' },
    { type: 'registration',          label: 'Vehicle registration' },
    { type: 'proof_of_residence',    label: 'Proof of residence' },
    { type: 'paystub',               label: 'Paystub' },
    { type: 'w2',                    label: 'W-2 / tax doc' },
];
const REQUIRED_TYPES = REQUIRED_DOCS.map(r => r.type);
const DOC_ACCEPT = 'image/*,application/pdf';

const allDocs = computed(() => {
    const fromCustomer = (d.customer?.documents || []).map(x => ({ ...x, _source: 'customer' }));
    const fromDeal     = (d.documents || []).map(x => ({ ...x, _source: 'deal' }));
    return [...fromCustomer, ...fromDeal].sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));
});
const findDoc = (type) => allDocs.value.find(doc => doc.type === type);
const docsUploadedCount = computed(() => REQUIRED_TYPES.filter(t => !!findDoc(t)).length);
const otherDocs = computed(() => allDocs.value.filter(doc => !REQUIRED_TYPES.includes(doc.type)));

const docUrl = (doc) => {
    if (!doc) return '#';
    // CustomerDocument: public disk → /storage/{path}; deal documents may
    // already include a URL helper. Fall back to /storage/{path}.
    if (doc.url) return doc.url;
    if (doc.path) return '/storage/' + String(doc.path).replace(/^\/+/, '');
    return '#';
};

const uploadDocFor = (type, label, file) => {
    if (!file) return;
    const fd = new FormData();
    fd.append('type', type);
    if (label) fd.append('label', label);
    fd.append('file', file);
    router.post(route('leasing.deals.documents.store', d.id), fd, {
        preserveScroll: true,
        forceFormData: true,
    });
};

// Workflow tab — one inline-editable card per stage in workflow order.
const STYLE_OPTIONS = ['Sedan', 'SUV', 'Coupe', 'Truck', 'Van', 'Convertible', 'Hatchback', 'Wagon', 'Crossover'];
const MILES_OPTIONS = [7500, 10000, 12000, 15000, 18000, 20000];
const INSURANCE_STATUSES = [
    { value: 'pending',       label: 'Pending' },
    { value: 'verified',      label: 'Verified' },
    { value: 'needs_update',  label: 'Needs update' },
    { value: 'n/a',           label: 'N/A' },
];

const workflow = useForm({
    preferences: {
        style:          d.preferences?.style          ?? '',
        budget:         d.preferences?.budget         ?? '',
        miles_per_year: d.preferences?.miles_per_year ?? '',
        passengers:     d.preferences?.passengers     ?? '',
        color:          d.preferences?.color          ?? '',
        brand:          d.preferences?.brand          ?? '',
    },
    co_signer_customer_id:        d.co_signer_customer_id || '',
    insurance_status:             d.insurance_status || '',
    plate_transfer:               !!d.plate_transfer,
    delivery_scheduled_at:        d.delivery_scheduled_at ? d.delivery_scheduled_at.slice(0, 16) : '',
    down_collected_at_delivery:   d.down_collected_at_delivery ?? '',
    paperwork_tracking_number:    d.paperwork_tracking_number  ?? '',
    bd_payment_received_at:       d.bd_payment_received_at     ?? '',
    bd_payment_amount:            d.bd_payment_amount          ?? '',
});

const saveWorkflow = (label) => workflow.put(route('leasing.deals.update', d.id), {
    preserveScroll: true,
    onSuccess: () => { /* server flashes 'Deal updated.' */ },
});

// Tasks sorted in workflow order (stage progression), then by sort_order
// within stage. Default to all-stages view so the whole workflow is
// visible at a glance; user can collapse to current stage if it gets noisy.
const showAllStageTasks = ref(true);
const sortedTasks = computed(() => {
    const list = [...(d.tasks || [])];
    list.sort((a, b) => {
        const sa = allStages.indexOf(a.stage);
        const sb = allStages.indexOf(b.stage);
        if (sa !== sb) return (sa === -1 ? 999 : sa) - (sb === -1 ? 999 : sb);
        return (a.sort_order || 0) - (b.sort_order || 0);
    });
    return list;
});
const visibleTasks = computed(() =>
    showAllStageTasks.value ? sortedTasks.value : sortedTasks.value.filter(t => t.stage === d.stage),
);

const noteForm = useForm({ body: '' });
const addNote = () => { noteForm.post(route('leasing.deals.note', d.id), { onSuccess: () => noteForm.reset() }); };

// Credit pull (inline)
const latestPull = computed(() => props.creditPulls?.[0] || null);
const creditPullForm = useForm({});
const runCreditPull = () => {
    if (!confirm('Run a soft credit pull for this customer? This will not affect their credit score.')) return;
    creditPullForm.post(route('leasing.deals.credit-pull', d.id), { preserveScroll: true });
};

const quoteForm = useForm({
    lender_id: '',
    // payment_type is required by the backend; fall back to 'lease' if the
    // deal somehow doesn't have one set (otherwise the form silently 422s).
    payment_type: d.payment_type || 'lease',
    term: 36, mileage_per_year: 10000,
    monthly_payment: '', das: '', sell_price: d.sell_price || '', msrp: d.msrp || '', rebates: 0, notes: '',
    // Optional VIN-driven vehicle details — when present, addQuote also
    // updates the deal's vehicle fields so the right car is on file.
    vehicle_vin: d.vehicle_vin || '', vehicle_year: d.vehicle_year || '',
    vehicle_make: d.vehicle_make || '', vehicle_model: d.vehicle_model || '',
    vehicle_trim: d.vehicle_trim || '',
});
const decodingVin = ref(false);
const vinDecodeStatus = ref(''); // '', 'ok', 'partial', 'error', 'invalid_length'
const vinDecodeMessage = ref('');
const decodeVinForQuote = async () => {
    vinDecodeStatus.value = '';
    vinDecodeMessage.value = '';
    if (!quoteForm.vehicle_vin || quoteForm.vehicle_vin.length !== 17) {
        vinDecodeStatus.value = 'invalid_length';
        vinDecodeMessage.value = `VIN must be exactly 17 characters (currently ${(quoteForm.vehicle_vin || '').length}).`;
        return;
    }
    decodingVin.value = true;
    try {
        // Use axios (bootstrap.js wires the XSRF-TOKEN cookie) instead of fetch
        // — fetch + manual X-CSRF-TOKEN header was 419'ing on this endpoint and
        // returning Laravel's HTML CSRF page, which then failed res.json() and
        // surfaced as the unhelpful "Network error" message.
        const { data: result } = await axios.post(route('leasing.vin-decode'), { vin: quoteForm.vehicle_vin });
        console.log('[VIN decode]', result);
        if (!result.success) {
            vinDecodeStatus.value = 'error';
            vinDecodeMessage.value = result.error || 'NHTSA decode failed.';
        } else if (result.data) {
            // NHTSA can return empty strings for unknown fields; treat as missing.
            const got = (k) => (result.data[k] && String(result.data[k]).trim() !== '') ? result.data[k] : null;
            const y = got('year'), mk = got('make'), md = got('model'), tr = got('trim'), msrp = got('msrp');
            if (y)  quoteForm.vehicle_year  = y;
            if (mk) quoteForm.vehicle_make  = mk;
            if (md) quoteForm.vehicle_model = md;
            if (tr) quoteForm.vehicle_trim  = tr;
            if (msrp && !quoteForm.msrp) quoteForm.msrp = msrp;
            const filled = [y, mk, md].filter(Boolean).length;
            if (filled === 3) {
                vinDecodeStatus.value = 'ok';
                vinDecodeMessage.value = `Decoded: ${y} ${mk} ${md}${tr ? ' ' + tr : ''}.`;
            } else if (filled > 0) {
                vinDecodeStatus.value = 'partial';
                vinDecodeMessage.value = 'NHTSA returned partial data — fill the missing fields manually.';
            } else {
                vinDecodeStatus.value = 'error';
                vinDecodeMessage.value = 'NHTSA returned no data for that VIN — fill the fields manually.';
            }
        }
    } catch (e) {
        console.error('[VIN decode]', e);
        vinDecodeStatus.value = 'error';
        const status = e.response?.status;
        const body = e.response?.data;
        const detail = typeof body === 'string' ? body.slice(0, 80) : (body?.message || body?.error || e.message);
        vinDecodeMessage.value = status
            ? `Decode failed: HTTP ${status} — ${detail}`
            : `Decode failed: ${detail || 'unknown error'}`;
    }
    decodingVin.value = false;
};
const justAddedQuote = ref(false);
const addQuote = () => {
    quoteForm.clearErrors();
    quoteForm.post(route('leasing.deals.quote', d.id), {
        preserveScroll: true,
        onSuccess: () => {
            // Keep the VIN/vehicle so subsequent quotes against the same car
            // don't need re-decoding; reset only the per-quote pricing fields
            // so the user can immediately tweak and add another quote.
            quoteForm.reset('lender_id', 'monthly_payment', 'das', 'rebates', 'notes');
            justAddedQuote.value = true;
            setTimeout(() => { justAddedQuote.value = false; }, 2500);
        },
        onError: (errors) => {
            console.warn('[Add Quote] validation errors', errors);
        },
    });
};

const completeTask = (taskId) => {
    // Optimistic — flip the local task object so the checkbox + line-through
    // appear instantly. Server confirms; on error we roll back.
    const task = (d.tasks || []).find((t) => t.id === taskId);
    if (!task || task.is_completed) return;
    const original = { is_completed: task.is_completed, completed_at: task.completed_at };
    task.is_completed = true;
    task.completed_at = new Date().toISOString();
    router.post(route('leasing.deals.task', { deal: d.id, task: taskId }), {}, {
        preserveScroll: true,
        onError: () => {
            task.is_completed = original.is_completed;
            task.completed_at = original.completed_at;
        },
    });
};
const transitionTo = (stage) => router.post(route('leasing.deals.transition', d.id), { stage });
const selectQuote = (quoteId) => router.post(route('leasing.deals.select-quote', { deal: d.id, quote: quoteId }));

// Inline edit / delete on each quote card
const editingQuoteId = ref(null);
const editQuoteForm = useForm({
    lender_id: '', payment_type: 'lease', term: '', mileage_per_year: '',
    monthly_payment: '', das: '', sell_price: '', msrp: '', rebates: '', notes: '',
});
const beginEditQuote = (q) => {
    editingQuoteId.value = q.id;
    editQuoteForm.lender_id        = q.lender_id ?? '';
    editQuoteForm.payment_type     = q.payment_type || 'lease';
    editQuoteForm.term             = q.term ?? '';
    editQuoteForm.mileage_per_year = q.mileage_per_year ?? '';
    editQuoteForm.monthly_payment  = q.monthly_payment ?? '';
    editQuoteForm.das              = q.das ?? '';
    editQuoteForm.sell_price       = q.sell_price ?? '';
    editQuoteForm.msrp             = q.msrp ?? '';
    editQuoteForm.rebates          = q.rebates ?? '';
    editQuoteForm.notes            = q.notes ?? '';
    editQuoteForm.clearErrors();
};
const cancelEditQuote = () => { editingQuoteId.value = null; editQuoteForm.clearErrors(); };
const saveEditQuote = () => {
    if (!editingQuoteId.value) return;
    editQuoteForm.put(route('leasing.deals.update-quote', { deal: d.id, quote: editingQuoteId.value }), {
        preserveScroll: true,
        onSuccess: () => { editingQuoteId.value = null; },
    });
};
const deleteQuote = (quoteId) => {
    if (!confirm('Delete this quote?')) return;
    router.delete(route('leasing.deals.delete-quote', { deal: d.id, quote: quoteId }), { preserveScroll: true });
};

// Send quote to client via SMS or email — preview + edit before send
const sendQuoteOpen   = ref(false);
const sendQuoteId     = ref(null);
const sendQuoteForm   = useForm({ channel: 'sms', to: '', subject: '', body: '' });
const sendQuoteLoading = ref(false);

const openSendQuote = async (q, channel) => {
    sendQuoteId.value     = q.id;
    sendQuoteForm.channel = channel;
    sendQuoteOpen.value   = true;
    sendQuoteLoading.value = true;
    try {
        const r = await axios.post(route('leasing.deals.preview-send-quote', { deal: d.id, quote: q.id }), { channel });
        sendQuoteForm.to      = r.data.to || '';
        sendQuoteForm.subject = r.data.subject || '';
        sendQuoteForm.body    = r.data.body || '';
    } catch (e) {
        alert('Could not load preview: ' + (e.response?.data?.message || e.message));
        sendQuoteOpen.value = false;
    }
    sendQuoteLoading.value = false;
};

const submitSendQuote = () => {
    sendQuoteForm.post(route('leasing.deals.send-quote', { deal: d.id, quote: sendQuoteId.value }), {
        preserveScroll: true,
        onSuccess: () => { sendQuoteOpen.value = false; },
    });
};

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
                <Link :href="route('leasing.deals.application.show', d.id)"
                      class="px-4 py-2 text-sm font-semibold bg-emerald-600 text-white rounded-md hover:bg-emerald-700">
                    📄 Application Form
                </Link>
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
                        <button v-for="tab in ['summary', 'tasks', 'workflow', 'calculator', 'quotes', 'credit', 'notes', 'documents', 'messages']" :key="tab"
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
                            <div class="flex items-center justify-between mb-2 text-xs">
                                <span class="text-gray-500">
                                    Showing tasks for
                                    <span v-if="showAllStageTasks" class="font-semibold">all stages</span>
                                    <span v-else class="font-semibold capitalize">{{ stageLabels[d.stage] }} stage</span>
                                    ({{ visibleTasks.length }})
                                </span>
                                <button type="button" @click="showAllStageTasks = !showAllStageTasks"
                                        class="text-indigo-600 hover:text-indigo-800 underline">
                                    {{ showAllStageTasks ? 'Show only current stage' : 'Show all stages' }}
                                </button>
                            </div>
                            <div v-for="task in visibleTasks" :key="task.id"
                                 class="flex items-center gap-3 py-2 border-b last:border-0">
                                <button @click="!task.is_completed && completeTask(task.id)"
                                        class="w-5 h-5 rounded border-2 flex items-center justify-center transition-colors"
                                        :class="task.is_completed ? 'bg-green-500 border-green-500 text-white' : 'border-gray-300 hover:border-green-400'">
                                    <span v-if="task.is_completed" class="text-xs">&#10003;</span>
                                </button>
                                <div class="flex-1">
                                    <span class="text-sm" :class="task.is_completed ? 'line-through text-gray-400' : ''">{{ task.name }}</span>
                                    <span v-if="task.stage && showAllStageTasks" class="ml-2 text-xs text-gray-400 capitalize">({{ task.stage }})</span>
                                </div>
                                <span v-if="task.is_completed && task.completed_at" class="text-xs text-emerald-600">
                                    ✓ Completed {{ fmtDate(task.completed_at) }}
                                </span>
                                <span v-else-if="task.due_date" class="text-xs"
                                      :class="new Date(task.due_date) < new Date() ? 'text-red-600 font-bold' : 'text-gray-400'">
                                    Due {{ fmtDate(task.due_date) }}
                                </span>
                            </div>
                            <p v-if="!visibleTasks.length" class="text-gray-500 text-sm">No tasks for this stage.</p>
                        </div>

                        <!-- Quotes Tab -->
                        <div v-if="activeTab === 'quotes'" class="space-y-4">
                            <!-- Vehicle context — quotes are scoped to this deal's vehicle.
                                 Updates live when a quote's VIN entry changes the deal vehicle. -->
                            <div v-if="d.vehicle_make || d.vehicle_vin"
                                 class="border border-blue-100 bg-blue-50/60 rounded-md px-3 py-2 text-xs">
                                <span class="text-gray-500">Quotes for:</span>
                                <span class="ml-1 font-semibold">{{ d.vehicle_year }} {{ d.vehicle_make }} {{ d.vehicle_model }} {{ d.vehicle_trim }}</span>
                                <span v-if="d.vehicle_vin" class="ml-2 font-mono text-gray-500">VIN {{ d.vehicle_vin }}</span>
                            </div>
                            <div v-else class="border border-amber-200 bg-amber-50 rounded-md px-3 py-2 text-xs text-amber-800">
                                No vehicle on this deal yet — enter a VIN in the Add Quote form below to set it.
                            </div>

                            <div v-for="q in d.quotes" :key="q.id"
                                 class="border rounded-lg p-4" :class="q.is_selected ? 'border-green-500 bg-green-50' : 'border-gray-200'">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <span class="font-bold text-lg">{{ fmt(q.monthly_payment) }}/mo</span>
                                        <span class="text-sm text-gray-500 ml-2">{{ q.term }}mo / {{ q.mileage_per_year?.toLocaleString() }}mi</span>
                                    </div>
                                    <div class="flex gap-2 items-center">
                                        <span v-if="q.is_selected" class="text-xs bg-green-600 text-white px-2 py-1 rounded">Selected</span>
                                        <button v-else type="button" @click="selectQuote(q.id)"
                                                class="text-xs bg-indigo-100 text-indigo-700 px-2 py-1 rounded hover:bg-indigo-200">Select</button>
                                        <button type="button" @click="openSendQuote(q, 'sms')"
                                                class="text-xs bg-emerald-100 text-emerald-700 px-2 py-1 rounded hover:bg-emerald-200">📱 SMS</button>
                                        <button type="button" @click="openSendQuote(q, 'email')"
                                                class="text-xs bg-sky-100 text-sky-700 px-2 py-1 rounded hover:bg-sky-200">✉ Email</button>
                                        <button type="button" @click="beginEditQuote(q)"
                                                class="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded hover:bg-gray-200">Edit</button>
                                        <button type="button" @click="deleteQuote(q.id)"
                                                class="text-xs bg-red-50 text-red-700 px-2 py-1 rounded hover:bg-red-100">Delete</button>
                                    </div>
                                </div>
                                <div v-if="editingQuoteId !== q.id" class="grid grid-cols-4 gap-2 text-xs text-gray-500">
                                    <div>Lender: {{ q.lender?.name || '-' }}</div>
                                    <div>DAS: {{ fmt(q.das) }}</div>
                                    <div>Sell: {{ fmt(q.sell_price) }}</div>
                                    <div>Type: <span class="capitalize">{{ q.payment_type }}</span></div>
                                </div>

                                <!-- Inline edit form for this quote -->
                                <div v-else class="mt-3 border-t pt-3 space-y-3">
                                    <div v-if="Object.keys(editQuoteForm.errors).length"
                                         class="border border-red-300 bg-red-50 text-red-800 rounded-md p-2 text-xs space-y-0.5">
                                        <div class="font-semibold">Couldn't update the quote:</div>
                                        <div v-for="(msg, field) in editQuoteForm.errors" :key="field">• {{ msg }}</div>
                                    </div>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                        <div>
                                            <label class="block text-[11px] text-gray-500">Type *</label>
                                            <select v-model="editQuoteForm.payment_type" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs">
                                                <option value="lease">Lease</option>
                                                <option value="finance">Finance</option>
                                                <option value="one_pay">One-Pay</option>
                                                <option value="balloon">Balloon</option>
                                                <option value="cash">Cash</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-gray-500">Lender</label>
                                            <select v-model="editQuoteForm.lender_id" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs">
                                                <option value="">—</option>
                                                <option v-for="l in lenders" :key="l.id" :value="l.id">{{ l.name }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-gray-500">Monthly $</label>
                                            <input v-model="editQuoteForm.monthly_payment" type="number" step="0.01" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs" />
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-gray-500">Term (mo)</label>
                                            <input v-model="editQuoteForm.term" type="number" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs" />
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-gray-500">Miles / year</label>
                                            <select v-model.number="editQuoteForm.mileage_per_year" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs">
                                                <option value="">—</option>
                                                <option v-for="m in [7500, 10000, 12000, 15000, 18000, 20000]" :key="m" :value="m">{{ m.toLocaleString() }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-gray-500">DAS $</label>
                                            <input v-model="editQuoteForm.das" type="number" step="0.01" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs" />
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-gray-500">Sell $</label>
                                            <input v-model="editQuoteForm.sell_price" type="number" step="0.01" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs" />
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-gray-500">MSRP $</label>
                                            <input v-model="editQuoteForm.msrp" type="number" step="0.01" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs" />
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-gray-500">Rebates $</label>
                                            <input v-model="editQuoteForm.rebates" type="number" step="0.01" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs" />
                                        </div>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="button" @click="saveEditQuote" :disabled="editQuoteForm.processing"
                                                class="px-3 py-1.5 bg-indigo-600 text-white rounded-md text-xs hover:bg-indigo-700 disabled:opacity-50">
                                            {{ editQuoteForm.processing ? 'Saving…' : 'Save' }}
                                        </button>
                                        <button type="button" @click="cancelEditQuote"
                                                class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-md text-xs hover:bg-gray-200">Cancel</button>
                                    </div>
                                </div>
                            </div>

                            <div class="border-t pt-4">
                                <h4 class="font-medium text-sm mb-3">Add Quote</h4>

                                <!-- VIN-driven vehicle entry. Decode auto-fills year/make/model/trim
                                     (NHTSA, free, no key); fields stay editable so you can correct
                                     or fill manually if NHTSA returns partial data. Submitting the
                                     quote also writes these to the deal. -->
                                <div class="bg-indigo-50/60 border border-indigo-100 rounded-lg p-3 mb-3 space-y-2">
                                    <div class="flex flex-wrap items-end gap-2">
                                        <div class="flex-1 min-w-[220px]">
                                            <label class="block text-[11px] font-medium text-gray-700">VIN</label>
                                            <input v-model="quoteForm.vehicle_vin" type="text" maxlength="17" placeholder="17-char VIN"
                                                   class="mt-0.5 block w-full font-mono border-gray-300 rounded-md text-xs" />
                                        </div>
                                        <button type="button" @click="decodeVinForQuote"
                                                :disabled="decodingVin"
                                                class="px-3 py-1.5 bg-blue-600 text-white rounded-md text-xs hover:bg-blue-700 disabled:opacity-50">
                                            {{ decodingVin ? 'Decoding…' : 'Decode' }}
                                        </button>
                                    </div>
                                    <div v-if="vinDecodeMessage" class="text-[11px]"
                                         :class="{
                                            'text-emerald-700': vinDecodeStatus === 'ok',
                                            'text-amber-700':   vinDecodeStatus === 'partial',
                                            'text-red-700':     vinDecodeStatus === 'error' || vinDecodeStatus === 'invalid_length',
                                         }">{{ vinDecodeMessage }}</div>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                        <div>
                                            <label class="block text-[11px] text-gray-500">Year</label>
                                            <input v-model="quoteForm.vehicle_year" type="number" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs" />
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-gray-500">Make</label>
                                            <input v-model="quoteForm.vehicle_make" type="text" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs" />
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-gray-500">Model</label>
                                            <input v-model="quoteForm.vehicle_model" type="text" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs" />
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-gray-500">Trim</label>
                                            <input v-model="quoteForm.vehicle_trim" type="text" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs" />
                                        </div>
                                    </div>
                                </div>

                                <form @submit.prevent="addQuote" class="space-y-3">
                                    <!-- Validation errors / submission feedback. Without this the form
                                         was silently 422'ing and looking like nothing was happening. -->
                                    <div v-if="Object.keys(quoteForm.errors).length"
                                         class="border border-red-300 bg-red-50 text-red-800 rounded-md p-2 text-xs space-y-0.5">
                                        <div class="font-semibold">Couldn't save the quote:</div>
                                        <div v-for="(msg, field) in quoteForm.errors" :key="field">• {{ msg }}</div>
                                    </div>
                                    <div v-else-if="justAddedQuote"
                                         class="border border-emerald-300 bg-emerald-50 text-emerald-800 rounded-md p-2 text-xs">
                                        ✓ Quote added — fill in the next one to add another.
                                    </div>

                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                        <div>
                                            <label class="block text-[11px] text-gray-500">Payment type *</label>
                                            <select v-model="quoteForm.payment_type" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs">
                                                <option value="lease">Lease</option>
                                                <option value="finance">Finance</option>
                                                <option value="one_pay">One-Pay</option>
                                                <option value="balloon">Balloon</option>
                                                <option value="cash">Cash</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-gray-500">Lender</label>
                                            <select v-model="quoteForm.lender_id" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs">
                                                <option value="">—</option>
                                                <option v-for="l in lenders" :key="l.id" :value="l.id">{{ l.name }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-gray-500">Monthly $</label>
                                            <input v-model="quoteForm.monthly_payment" type="number" step="0.01" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs" />
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-gray-500">Term (mo)</label>
                                            <input v-model="quoteForm.term" type="number" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs" />
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-gray-500">Miles / year</label>
                                            <select v-model.number="quoteForm.mileage_per_year" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs">
                                                <option v-for="m in [7500, 10000, 12000, 15000, 18000, 20000]" :key="m" :value="m">{{ m.toLocaleString() }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-gray-500">DAS $</label>
                                            <input v-model="quoteForm.das" type="number" step="0.01" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs" />
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-gray-500">Sell $</label>
                                            <input v-model="quoteForm.sell_price" type="number" step="0.01" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs" />
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-gray-500">MSRP $</label>
                                            <input v-model="quoteForm.msrp" type="number" step="0.01" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs" />
                                        </div>
                                        <div>
                                            <label class="block text-[11px] text-gray-500">Rebates $</label>
                                            <input v-model="quoteForm.rebates" type="number" step="0.01" class="mt-0.5 block w-full border-gray-300 rounded-md text-xs" />
                                        </div>
                                    </div>
                                    <div>
                                        <button type="submit" :disabled="quoteForm.processing"
                                                class="px-4 py-2 bg-indigo-600 text-white rounded-md text-xs hover:bg-indigo-700 disabled:opacity-50">
                                            {{ quoteForm.processing ? 'Saving…' : 'Add Quote' }}
                                        </button>
                                        <span class="ml-3 text-[11px] text-gray-500">Pricing fields reset after save so you can add another quote.</span>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Notes Tab — full mention/assign/reminder/thread system -->
                        <div v-if="activeTab === 'notes'" class="space-y-4">
                            <NotesPanel :notes="d.note_thread || []" notable-type="deal" :notable-id="d.id" />
                        </div>

                        <!-- Documents Tab — required-docs checklist sourced from
                             customer.documents (license front/back uploaded via the
                             SMS bot land here automatically) plus deal-specific docs.
                             Each row: status, file link if uploaded, upload button if not. -->
                        <div v-if="activeTab === 'documents'" class="space-y-3">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-semibold text-gray-700">Required documents</h4>
                                <span class="text-xs text-gray-500">
                                    {{ docsUploadedCount }} of {{ REQUIRED_DOCS.length }} uploaded
                                </span>
                            </div>
                            <div class="space-y-2">
                                <div v-for="req in REQUIRED_DOCS" :key="req.type"
                                     class="flex items-center gap-3 py-2 border-b last:border-0">
                                    <span class="text-lg" :class="findDoc(req.type) ? 'text-emerald-600' : 'text-gray-300'">
                                        {{ findDoc(req.type) ? '✓' : '○' }}
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-medium text-gray-900">{{ req.label }}</div>
                                        <div v-if="findDoc(req.type)" class="text-xs text-gray-500 truncate">
                                            <a :href="docUrl(findDoc(req.type))" target="_blank" class="text-indigo-600 hover:text-indigo-800 underline">
                                                {{ findDoc(req.type).label || findDoc(req.type).original_name || 'View file' }}
                                            </a>
                                            <span v-if="findDoc(req.type).created_at" class="ml-2">· uploaded {{ fmtDate(findDoc(req.type).created_at) }}</span>
                                        </div>
                                        <div v-else class="text-xs text-gray-400 italic">Not uploaded yet</div>
                                    </div>
                                    <label class="text-xs text-indigo-600 hover:text-indigo-800 cursor-pointer underline">
                                        {{ findDoc(req.type) ? 'Replace' : 'Upload' }}
                                        <input type="file" class="hidden" :accept="DOC_ACCEPT"
                                               @change="(e) => uploadDocFor(req.type, req.label, e.target.files[0])" />
                                    </label>
                                </div>
                            </div>

                            <div v-if="otherDocs.length" class="pt-2">
                                <h4 class="text-sm font-semibold text-gray-700 mb-2">Other documents</h4>
                                <div v-for="doc in otherDocs" :key="'other-'+doc.id" class="flex items-center gap-3 py-1.5 border-b last:border-0 text-xs">
                                    <span class="text-emerald-600">✓</span>
                                    <a :href="docUrl(doc)" target="_blank" class="text-indigo-600 hover:text-indigo-800 underline">
                                        {{ doc.label || doc.original_name || doc.type }}
                                    </a>
                                    <span class="text-gray-400 capitalize">· {{ String(doc.type).replace(/_/g, ' ') }}</span>
                                </div>
                            </div>

                            <div class="pt-3 border-t">
                                <label class="inline-flex items-center gap-2 text-sm text-indigo-600 hover:text-indigo-800 cursor-pointer">
                                    <span class="text-lg leading-none">+</span>
                                    <span class="underline">Upload another document</span>
                                    <input type="file" class="hidden" :accept="DOC_ACCEPT"
                                           @change="(e) => uploadDocFor('other', '', e.target.files[0])" />
                                </label>
                            </div>
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
                        <div v-if="activeTab === 'summary'" class="space-y-5">
                            <!-- Customer preferences (lead-stage capture) — visible at a glance.
                                 Edit lives on the Workflow tab; this is read-only summary. -->
                            <div class="border rounded-xl p-4 bg-gray-50/50">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wide">Customer Preferences</h4>
                                    <button type="button" @click="activeTab = 'workflow'"
                                            class="text-xs text-indigo-600 hover:text-indigo-800 underline">
                                        {{ d.preferences && Object.values(d.preferences).some(v => v) ? 'Edit' : '+ Add' }}
                                    </button>
                                </div>
                                <div v-if="d.preferences && Object.values(d.preferences).some(v => v)"
                                     class="grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-2 text-sm">
                                    <div><span class="text-gray-500">Style:</span> <span class="font-medium">{{ d.preferences.style || '—' }}</span></div>
                                    <div><span class="text-gray-500">Brand:</span> <span class="font-medium">{{ d.preferences.brand || '—' }}</span></div>
                                    <div><span class="text-gray-500">Color:</span> <span class="font-medium">{{ d.preferences.color || '—' }}</span></div>
                                    <div><span class="text-gray-500">Budget:</span> <span class="font-medium">{{ d.preferences.budget ? '$' + Number(d.preferences.budget).toLocaleString() + '/mo' : '—' }}</span></div>
                                    <div><span class="text-gray-500">Miles/yr:</span> <span class="font-medium">{{ d.preferences.miles_per_year ? Number(d.preferences.miles_per_year).toLocaleString() : '—' }}</span></div>
                                    <div><span class="text-gray-500">Passengers:</span> <span class="font-medium">{{ d.preferences.passengers || '—' }}</span></div>
                                </div>
                                <p v-else class="text-xs text-gray-400 italic">No preferences captured yet — click "+ Add" to fill them in.</p>
                            </div>

                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div><span class="text-gray-500">Sell Price:</span> {{ fmt(d.sell_price) }}</div>
                                <div><span class="text-gray-500">MSRP:</span> {{ fmt(d.msrp) }}</div>
                                <div><span class="text-gray-500">Trade Allowance:</span> {{ fmt(d.trade_allowance) }}</div>
                                <div><span class="text-gray-500">Trade Payoff:</span> {{ fmt(d.trade_payoff) }}</div>
                                <div><span class="text-gray-500">Drive Off:</span> {{ fmt(d.drive_off) }}</div>
                                <div><span class="text-gray-500">Mileage/Year:</span> {{ d.mileage_per_year?.toLocaleString() || '-' }}</div>
                                <div class="col-span-2"><span class="text-gray-500">Notes:</span> {{ d.notes || '-' }}</div>
                            </div>
                        </div>

                        <!-- Workflow Tab — one card per stage in workflow order.
                             Each card holds the structured fields for that stage and
                             can be saved independently via the shared workflow form. -->
                        <div v-if="activeTab === 'workflow'" class="space-y-4">

                            <!-- 1. Lead — Customer Preferences -->
                            <section class="border rounded-xl p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="text-sm font-semibold text-gray-700">1. Lead — Customer Preferences</h3>
                                    <span class="text-[10px] text-gray-400">What kind of car they want</span>
                                </div>
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
                                    <div>
                                        <label class="block text-xs text-gray-500">Style</label>
                                        <select v-model="workflow.preferences.style" class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                                            <option value="">—</option>
                                            <option v-for="s in STYLE_OPTIONS" :key="s">{{ s }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500">Brand</label>
                                        <input v-model="workflow.preferences.brand" type="text" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500">Color</label>
                                        <input v-model="workflow.preferences.color" type="text" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500">Budget ($/mo)</label>
                                        <input v-model="workflow.preferences.budget" type="number" step="0.01" min="0" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500">Miles / year</label>
                                        <select v-model.number="workflow.preferences.miles_per_year" class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                                            <option value="">—</option>
                                            <option v-for="m in MILES_OPTIONS" :key="m" :value="m">{{ m.toLocaleString() }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500">Passengers</label>
                                        <input v-model.number="workflow.preferences.passengers" type="number" min="1" max="12" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                    </div>
                                </div>
                            </section>

                            <!-- 2. Application — Co-signer (license docs collected via the co-signer's customer page) -->
                            <section class="border rounded-xl p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="text-sm font-semibold text-gray-700">2. Application — Co-signer</h3>
                                    <span class="text-[10px] text-gray-400">License docs collected on the co-signer's customer page</span>
                                </div>
                                <div class="text-sm">
                                    <CustomerSelect v-model="workflow.co_signer_customer_id" />
                                    <p v-if="d.co_signer" class="mt-2 text-xs text-gray-600">
                                        Linked: <strong>{{ d.co_signer.first_name }} {{ d.co_signer.last_name }}</strong> —
                                        <Link :href="route('customers.show', d.co_signer.id)" class="text-indigo-600 hover:text-indigo-800 underline">open profile to upload license front + back</Link>
                                    </p>
                                </div>
                            </section>

                            <!-- 3. Pending — Insurance & plate transfer -->
                            <section class="border rounded-xl p-4">
                                <h3 class="text-sm font-semibold text-gray-700 mb-3">3. Pending — Insurance & registration</h3>
                                <div class="grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <label class="block text-xs text-gray-500">Insurance status</label>
                                        <select v-model="workflow.insurance_status" class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                                            <option value="">—</option>
                                            <option v-for="s in INSURANCE_STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="flex items-center gap-2 mt-5 text-sm text-gray-700">
                                            <input v-model="workflow.plate_transfer" type="checkbox" class="rounded text-indigo-600 focus:ring-indigo-500" />
                                            Plate transfer
                                        </label>
                                    </div>
                                </div>
                            </section>

                            <!-- 4. Finalize — Schedule delivery -->
                            <section class="border rounded-xl p-4">
                                <h3 class="text-sm font-semibold text-gray-700 mb-3">4. Finalize — Delivery</h3>
                                <div class="text-sm">
                                    <label class="block text-xs text-gray-500">Delivery scheduled at</label>
                                    <input v-model="workflow.delivery_scheduled_at" type="datetime-local"
                                           class="mt-1 block w-full md:w-72 border-gray-300 rounded-md text-sm" />
                                </div>
                            </section>

                            <!-- 5. Outstanding — Down at delivery + paperwork tracking -->
                            <section class="border rounded-xl p-4">
                                <h3 class="text-sm font-semibold text-gray-700 mb-3">5. Outstanding — Down + paperwork</h3>
                                <div class="grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <label class="block text-xs text-gray-500">Down collected at delivery ($)</label>
                                        <input v-model="workflow.down_collected_at_delivery" type="number" step="0.01" min="0"
                                               class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500">Paperwork tracking #</label>
                                        <input v-model="workflow.paperwork_tracking_number" type="text" placeholder="USPS / FedEx / UPS tracking"
                                               class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                    </div>
                                </div>
                            </section>

                            <!-- 6. Complete — Bird Dog payment from dealer -->
                            <section class="border rounded-xl p-4">
                                <h3 class="text-sm font-semibold text-gray-700 mb-3">6. Complete — Bird Dog (dealer payment, ~1mo later)</h3>
                                <div class="grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <label class="block text-xs text-gray-500">BD received on</label>
                                        <input v-model="workflow.bd_payment_received_at" type="date"
                                               class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500">BD amount ($)</label>
                                        <input v-model="workflow.bd_payment_amount" type="number" step="0.01" min="0"
                                               class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                    </div>
                                </div>
                            </section>

                            <div class="flex justify-end pt-2">
                                <button @click="saveWorkflow()" :disabled="workflow.processing"
                                        class="px-5 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50">
                                    {{ workflow.processing ? 'Saving…' : 'Save workflow fields' }}
                                </button>
                            </div>
                        </div>

                        <!-- Messages Tab — full SMS thread with this deal's customer.
                             Replies sent from here are subject-tagged to the Deal so they
                             show up in this deal's audit trail. -->
                        <div v-if="activeTab === 'messages'">
                            <CustomerMessages v-if="d.customer"
                                              :customer="d.customer"
                                              subject-type="App\\Models\\Deal"
                                              :subject-id="d.id" />
                            <div v-else class="text-center text-gray-400 py-8">No customer linked to this deal.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- Send-quote modal: SMS or email preview + edit, then send. -->
        <Teleport to="body">
            <div v-if="sendQuoteOpen" @click.self="sendQuoteOpen = false"
                 class="fixed inset-0 bg-black/40 flex items-center justify-center z-[100] p-4">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-hidden flex flex-col">
                    <header class="px-5 py-4 border-b flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-lg">Send quote — {{ sendQuoteForm.channel === 'sms' ? '📱 SMS' : '✉ Email' }}</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Review and edit before sending. Logged to the customer's communication history.</p>
                        </div>
                        <button @click="sendQuoteOpen = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">×</button>
                    </header>
                    <div class="p-5 space-y-3 overflow-y-auto flex-1 text-sm">
                        <div v-if="sendQuoteLoading" class="text-center py-8 text-gray-400">Loading preview…</div>
                        <template v-else>
                            <label class="block">
                                <span class="block text-xs font-medium text-gray-700 mb-1">To {{ sendQuoteForm.channel === 'sms' ? '(phone)' : '(email)' }}</span>
                                <input v-model="sendQuoteForm.to" :type="sendQuoteForm.channel === 'sms' ? 'tel' : 'email'"
                                       class="w-full border-gray-300 rounded-md text-sm" />
                                <p v-if="sendQuoteForm.errors.to" class="text-xs text-red-600 mt-0.5">{{ sendQuoteForm.errors.to }}</p>
                            </label>
                            <label v-if="sendQuoteForm.channel === 'email'" class="block">
                                <span class="block text-xs font-medium text-gray-700 mb-1">Subject</span>
                                <input v-model="sendQuoteForm.subject" type="text" class="w-full border-gray-300 rounded-md text-sm" />
                            </label>
                            <label class="block">
                                <span class="block text-xs font-medium text-gray-700 mb-1">Message</span>
                                <textarea v-model="sendQuoteForm.body" rows="10"
                                          class="w-full border-gray-300 rounded-md text-sm font-mono"></textarea>
                                <p v-if="sendQuoteForm.errors.body" class="text-xs text-red-600 mt-0.5">{{ sendQuoteForm.errors.body }}</p>
                            </label>
                            <p class="text-[11px] text-gray-500">
                                {{ sendQuoteForm.channel === 'sms' ? 'SMS char count: ' + sendQuoteForm.body.length : 'Customer will see this in their inbox.' }}
                            </p>
                        </template>
                    </div>
                    <footer class="px-5 py-3 border-t flex justify-end gap-2 bg-gray-50">
                        <button @click="sendQuoteOpen = false"
                                class="px-3 py-1.5 text-sm bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">Cancel</button>
                        <button @click="submitSendQuote" :disabled="sendQuoteForm.processing || sendQuoteLoading || !sendQuoteForm.to || !sendQuoteForm.body"
                                class="px-4 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50">
                            {{ sendQuoteForm.processing ? 'Sending…' : 'Send' }}
                        </button>
                    </footer>
                </div>
            </div>
        </Teleport>

    </AppLayout>
</template>
