<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, reactive, watch, onMounted, nextTick } from 'vue';
import SmsButton from '@/Components/SmsButton.vue';
import CustomerMessages from '@/Components/CustomerMessages.vue';
import CustomerSelect from '@/Components/CustomerSelect.vue';
import NotesPanel from '@/Components/Notes/NotesPanel.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { VEHICLE_MAKES, VEHICLE_COLORS } from '@/Components/vehicleOptions.js';

const props = defineProps({
    deal: Object,
    lenders: Array,
    creditPulls: { type: Array, default: () => [] },
    creditConfigured: { type: Boolean, default: false },
    timeline: { type: Array, default: () => [] },
    orgUsers: { type: Array, default: () => [] },
});

// ── Sharing form ──
const sharingForm = useForm({ user_ids: [] });
const seedSharing = () => {
    sharingForm.user_ids = (props.deal?.shared_with || []).map(u => u.id);
};
seedSharing();
watch(() => props.deal?.shared_with, seedSharing, { deep: true });
const toggleShare = (userId) => {
    const idx = sharingForm.user_ids.indexOf(userId);
    if (idx >= 0) sharingForm.user_ids.splice(idx, 1);
    else sharingForm.user_ids.push(userId);
};
const saveSharing = () => sharingForm.put(route('leasing.deals.sharing.update', props.deal.id), { preserveScroll: true });
const isOwner = (userId) => Number(userId) === Number(props.deal?.salesperson_id);
const selectAllShares = () => {
    sharingForm.user_ids = props.orgUsers
        .filter(u => !isOwner(u.id))
        .map(u => u.id);
};

const timelineIcon = (k) => ({
    stage: '🔄', task: '✓', quote: '📊', document: '📄', update: '✏️',
}[k] || '•');
const timelineWhen = (iso) => {
    if (!iso) return '';
    return new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
};

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

// Deep-link support — when the bell links to /leasing/deals/X?tab=notes#note-Y
// (set by MentionedInNoteNotification + NoteReminderDueNotification), open
// the Notes tab on mount and scroll to the specific note. Without this the
// page lands on Summary and the user has to find the note manually.
onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    let wantedTab = params.get('tab');
    // Calculator + Credit are now folded into the Quotes + Workflow tabs.
    // Redirect stale deep-links so the right merged tab opens.
    if (wantedTab === 'calculator') wantedTab = 'quotes';
    if (wantedTab === 'credit')     wantedTab = 'workflow';
    if (wantedTab === 'sharing')    wantedTab = 'summary';
    if (wantedTab && ['summary','customer','tasks','workflow','quotes','notes','documents','vehicle_return','messages','timeline'].includes(wantedTab)) {
        activeTab.value = wantedTab;
    }
    if (window.location.hash) {
        nextTick(() => {
            const target = document.querySelector(window.location.hash);
            if (target) target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    }
});

const stageLabels = { lead: 'Lead', quote: 'Quote', application: 'Application', submission: 'Submission', pending: 'Pending', finalize: 'Finalize', outstanding: 'Outstanding', complete: 'Complete', lost: 'Lost' };

// ── Broker picker (mirrors xDeskPro's Deal Information → Broker typeahead) ──
const brokerQuery = ref('');
const brokerResults = ref([]);
const brokerOpen = ref(false);
const brokerSaving = ref(false);
let brokerTimer = null;
const fetchBrokers = (term) => {
    clearTimeout(brokerTimer);
    brokerTimer = setTimeout(async () => {
        try {
            const r = await fetch(route('leasing.brokers.typeahead', { q: term }), { headers: { 'Accept': 'application/json' } });
            brokerResults.value = await r.json();
        } catch { brokerResults.value = []; }
    }, 200);
};
const onBrokerInput = (e) => {
    brokerQuery.value = e.target.value;
    brokerOpen.value = true;
    fetchBrokers(brokerQuery.value);
};
const pickBroker = (ins) => {
    brokerSaving.value = true;
    router.put(route('leasing.deals.update', d.id), { broker_id: ins?.id || null }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            brokerSaving.value = false;
            brokerOpen.value = false;
            brokerQuery.value = '';
        },
    });
};
const clearBroker = () => pickBroker(null);

// Generic picker factory — Dealer + Lienholder share the typeahead pattern.
function makePicker(routeName, dealField) {
    const query = ref('');
    const results = ref([]);
    const open = ref(false);
    const saving = ref(false);
    let timer = null;
    const fetch_ = (term) => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            try {
                const r = await fetch(route(routeName, { q: term }), { headers: { 'Accept': 'application/json' } });
                results.value = await r.json();
            } catch { results.value = []; }
        }, 200);
    };
    const onInput = (e) => { query.value = e.target.value; open.value = true; fetch_(query.value); };
    const pick = (row) => {
        saving.value = true;
        router.put(route('leasing.deals.update', d.id), { [dealField]: row?.id || null }, {
            preserveScroll: true, preserveState: true,
            onFinish: () => { saving.value = false; open.value = false; query.value = ''; },
        });
    };
    const clear = () => pick(null);
    return { query, results, open, saving, onInput, pick, clear };
}
const dealerPicker = makePicker('leasing.dealers.typeahead', 'dealer_id');
const lienholderPicker = makePicker('leasing.lienholders.typeahead', 'lienholder_id');

// ── Vehicle Return form (xDeskPro Vehicle Return tab) ──
const vrForm = useForm({
    return_type: 'trade_in',
    vin: '', year: null, make: '', model: '', trim: '', color: '',
    odometer: null, condition: '',
    payoff_amount: null, allowance: null, acv: null,
    payoff_to: '', payoff_good_through: '',
    current_plate: '', plate_transfer: false, notes: '',
});
const seedVrForm = () => {
    const vr = d.vehicle_return;
    if (!vr) {
        vrForm.reset();
        return;
    }
    vrForm.return_type = vr.return_type;
    vrForm.vin = vr.vin ?? '';
    vrForm.year = vr.year;
    vrForm.make = vr.make ?? '';
    vrForm.model = vr.model ?? '';
    vrForm.trim = vr.trim ?? '';
    vrForm.color = vr.color ?? '';
    vrForm.odometer = vr.odometer;
    vrForm.condition = vr.condition ?? '';
    vrForm.payoff_amount = vr.payoff_amount;
    vrForm.allowance = vr.allowance;
    vrForm.acv = vr.acv;
    vrForm.payoff_to = vr.payoff_to ?? '';
    vrForm.payoff_good_through = vr.payoff_good_through ? String(vr.payoff_good_through).slice(0,10) : '';
    vrForm.current_plate = vr.current_plate ?? '';
    vrForm.plate_transfer = !!vr.plate_transfer;
    vrForm.notes = vr.notes ?? '';
};
watch(() => d.vehicle_return, seedVrForm, { immediate: true });
const saveVehicleReturn = () => vrForm.post(route('leasing.deals.vehicle-return.store', d.id), { preserveScroll: true });
const removeVehicleReturn = () => {
    if (!d.vehicle_return || !confirm('Remove this vehicle return?')) return;
    router.delete(route('leasing.deals.vehicle-return.destroy', [d.id, d.vehicle_return.id]), { preserveScroll: true });
};

// ── Co-Signer assignment via existing CustomerSelect ──
const showCoSignerPicker = ref(false);
const onCoSignerSelected = (customerId) => {
    router.put(route('leasing.deals.update', d.id), { co_signer_customer_id: customerId }, {
        preserveScroll: true, preserveState: true,
        onFinish: () => { showCoSignerPicker.value = false; },
    });
};
const removeCoSigner = () => {
    if (!confirm('Remove co-signer from this deal?')) return;
    router.put(route('leasing.deals.update', d.id), { co_signer_customer_id: null }, { preserveScroll: true, preserveState: true });
};
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

// Deal-level slots (xDeskPro parity — Documents tab)
const DEAL_DOC_SLOTS = [
    { type: 'application',          label: 'Application' },
    { type: 'lease_finance_agreement', label: 'Lease / Finance Agreement' },
    { type: 'dealer_invoice',       label: 'Dealer Invoice' },
    { type: 'customer_invoice',     label: 'Customer Invoice' },
    { type: 'window_sticker',       label: 'Window Sticker' },
    { type: 'rebate_docs',          label: 'Rebate Docs' },
    { type: 'damage_waiver',        label: 'Damage Waiver' },
    { type: 'worksheet',            label: 'Worksheet' },
    { type: 'insurance',            label: 'Insurance' },
];
const DEAL_DOC_TYPES = DEAL_DOC_SLOTS.map(s => s.type);
const findDealDoc = (type) => (d.documents || []).find(x => x.type === type);

const allDocs = computed(() => {
    const fromCustomer = (d.customer?.documents || []).map(x => ({ ...x, _source: 'customer' }));
    const fromDeal     = (d.documents || []).map(x => ({ ...x, _source: 'deal' }));
    return [...fromCustomer, ...fromDeal].sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));
});
const findDoc = (type) => allDocs.value.find(doc => doc.type === type);
const docsUploadedCount = computed(() => REQUIRED_TYPES.filter(t => !!findDoc(t)).length);
const otherDocs = computed(() => allDocs.value.filter(doc => !REQUIRED_TYPES.includes(doc.type) && !DEAL_DOC_TYPES.includes(doc.type)));

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
    // Toggle behavior — tap a checked task to un-complete it. Optimistic
    // flip so the UI feels instant; server confirms via Inertia round-trip,
    // and we roll back on error.
    const task = (d.tasks || []).find((t) => t.id === taskId);
    if (!task) return;
    const original = { is_completed: task.is_completed, completed_at: task.completed_at };
    task.is_completed = !task.is_completed;
    task.completed_at = task.is_completed ? new Date().toISOString() : null;
    router.post(route('leasing.deals.task', { deal: d.id, task: taskId }), {}, {
        preserveScroll: true,
        onError: () => {
            task.is_completed = original.is_completed;
            task.completed_at = original.completed_at;
        },
    });
};
// Inline due-date editor — clicking a task's "Due ..." swaps it for a
// date input. Picking a new date posts to the cascade endpoint, which
// also bumps any later task whose date would now be in the past.
const editingDueId = ref(null);
const updateTaskDueDate = (task, dateStr) => {
    editingDueId.value = null;
    if (!dateStr) return;
    router.post(
        route('leasing.deals.task.due-date', { deal: d.id, task: task.id }),
        { due_date: dateStr },
        { preserveScroll: true }
    );
};

const transitionTo = (stage) => router.post(route('leasing.deals.transition', d.id), { stage });
const toggleTask = (t) => completeTask(t.id);

// ── Action Items (right rail) ──
const newActionItemOpen = ref(false);
const newActionItem = reactive({ title: '', due_date: '' });
const addActionItem = () => {
    if (!newActionItem.title) return;
    router.post(route('leasing.deals.action-items.store', d.id),
        { title: newActionItem.title, due_date: newActionItem.due_date || null },
        { preserveScroll: true, preserveState: true,
          onSuccess: () => { newActionItem.title = ''; newActionItem.due_date = ''; newActionItemOpen.value = false; }});
};
const toggleActionItem = (ai) => {
    router.patch(route('leasing.deals.action-items.update', [d.id, ai.id]),
        { is_completed: !ai.is_completed },
        { preserveScroll: true, preserveState: true });
};
const deleteActionItem = (ai) => {
    if (!confirm('Remove this action item?')) return;
    router.delete(route('leasing.deals.action-items.destroy', [d.id, ai.id]),
        { preserveScroll: true, preserveState: true });
};
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

// ── Live Offers (MarketCheck OEM incentive feed) ──
const liveOffers = ref(null);            // { ok, leases[], finances[], rebates[], captive, calls_used, calls_remaining }
const liveOffersLoading = ref(false);
const selectedOfferId = ref(null);
const appliedRebateIds = ref(new Set()); // ids of rebates user picked to add to cap-cost reduction

const pullLiveOffers = async () => {
    liveOffersLoading.value = true;
    liveOffers.value = null;
    try {
        const { data } = await axios.post(route('leasing.deals.pull-offers', d.id));
        liveOffers.value = data;
    } catch (e) {
        liveOffers.value = { ok: false, error: e.response?.data?.message || e.message };
    }
    liveOffersLoading.value = false;
};

const applyOfferToCalculator = (offer) => {
    selectedOfferId.value = offer.id;
    calc.value.type    = offer.type === 'finance' ? 'finance' : 'lease';
    if (offer.msrp)              calc.value.msrp            = offer.msrp;
    if (offer.term)              calc.value.term            = offer.term;
    if (offer.mileage_limit)     calc.value.annual_mileage  = offer.mileage_limit;
    if (offer.residual_pct)      calc.value.residual_pct    = offer.residual_pct;
    if (offer.money_factor_derived) calc.value.money_factor = offer.money_factor_derived;
    if (offer.apr)               calc.value.apr             = offer.apr;
    if (offer.acquisition_fee)   calc.value.acquisition_fee = offer.acquisition_fee;
    if (offer.down_payment)      calc.value.down_payment    = offer.down_payment;
    if (offer.net_cap_cost)      calc.value.sell_price      = offer.net_cap_cost;
    // Auto-tag captive lender into program label so it shows up on the quote
    if (liveOffers.value?.captive) {
        matchedProgram.value = { lender: { name: liveOffers.value.captive }, _from_marketcheck: true };
    }
};

const toggleRebate = (rebate) => {
    const next = new Set(appliedRebateIds.value);
    next.has(rebate.id) ? next.delete(rebate.id) : next.add(rebate.id);
    appliedRebateIds.value = next;
    // Sum selected rebate cashback into the calc rebates_total field
    const total = (liveOffers.value?.rebates || [])
        .filter(r => next.has(r.id))
        .reduce((sum, r) => sum + (Number(r.cashback) || 0), 0);
    calc.value.rebates_total = total;
};
const isRebateApplied = (id) => appliedRebateIds.value.has(id);

// ── Bird Dog + target profit (deal "placement") ──
const birdDog = ref({
    amount: 0,
    source: 'dealer',   // 'dealer' = paid by dealer to AutoGo (adds to profit) | 'down' = applied to cap-cost reduction (lowers monthly) | 'deposit' = held back from customer's deposit (no monthly impact)
});
watch(() => birdDog.value, (v) => {
    if (v.source === 'down') {
        // Bird Dog rolled into cap-cost reduction stacks with rebates
        const rebateBase = (liveOffers.value?.rebates || [])
            .filter(r => appliedRebateIds.value.has(r.id))
            .reduce((s, r) => s + (Number(r.cashback) || 0), 0);
        calc.value.rebates_total = rebateBase + Number(v.amount || 0);
    }
}, { deep: true });

const profitTarget = ref(null);
// Derived AutoGo profit estimate. We don't have dealer reserve / back-end visibility yet (PR C),
// so this is the front-end profit (sell - cost) + Bird Dog (if dealer pays it). Clearly labeled.
const estimatedProfit = computed(() => {
    const sell = Number(calc.value.sell_price) || 0;
    const cost = Number(d.cost) || 0;
    const frontEnd = Math.max(0, sell - cost);
    const bd = birdDog.value.source === 'dealer' ? (Number(birdDog.value.amount) || 0) : 0;
    return { front_end: frontEnd, bird_dog: bd, total: frontEnd + bd };
});
const profitGap = computed(() => {
    if (!profitTarget.value) return null;
    return Number(profitTarget.value) - estimatedProfit.value.total;
});

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
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Stage Pipeline (breadcrumb style — xDeskPro parity) -->
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="flex items-center gap-1 overflow-x-auto whitespace-nowrap">
                        <template v-for="(stage, idx) in allStages" :key="stage">
                            <button @click="d.stage !== stage && d.stage !== 'lost' && d.stage !== 'complete' ? transitionTo(stage) : null"
                                    type="button"
                                    class="flex items-center gap-1.5 px-2.5 py-1.5 text-sm rounded-md transition-colors group"
                                    :class="d.stage === stage
                                        ? 'text-gray-900 font-semibold'
                                        : allStages.indexOf(stage) < allStages.indexOf(d.stage)
                                            ? 'text-green-700 hover:bg-green-50'
                                            : 'text-gray-400 hover:text-gray-600 hover:bg-gray-50'">
                                <span v-if="allStages.indexOf(stage) < allStages.indexOf(d.stage)"
                                      class="text-green-600 font-bold">✓</span>
                                <span v-else-if="d.stage === stage"
                                      class="inline-block w-2 h-2 rounded-full bg-indigo-600 animate-pulse"></span>
                                <span v-else
                                      class="inline-block w-2 h-2 rounded-full border border-gray-300"></span>
                                {{ stageLabels[stage] }}
                            </button>
                            <span v-if="idx < allStages.length - 1" class="text-gray-300 text-sm">›</span>
                        </template>
                    </div>
                </div>

                <!-- Two-column layout: deal body + persistent right rail (Current Tasks + Action Items) -->
                <div class="lg:grid lg:grid-cols-[1fr_320px] lg:gap-6 space-y-6 lg:space-y-0">
                <div class="space-y-6 min-w-0">

                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-medium text-gray-500">Customer</h3>
                            <button type="button" @click="activeTab = 'customer'" class="text-xs text-indigo-600 hover:underline">View</button>
                        </div>
                        <p class="font-bold text-lg">{{ d.customer?.first_name }} {{ d.customer?.last_name }}</p>
                        <p class="text-sm text-gray-600 flex items-center gap-2">
                            <span>{{ d.customer?.phone }}</span>
                            <SmsButton v-if="d.customer?.phone" :to="d.customer.phone" :customer-id="d.customer.id" subject-type="App\\Models\\Deal" :subject-id="d.id" label="SMS" />
                        </p>
                        <p v-if="d.credit_score" class="text-sm text-gray-600">Score: {{ d.credit_score }}</p>
                    </div>
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-medium text-gray-500">Vehicle</h3>
                            <button type="button" @click="activeTab = 'workflow'" class="text-xs text-indigo-600 hover:underline">Edit</button>
                        </div>
                        <p v-if="d.vehicle_make" class="font-bold">{{ d.vehicle_year }} {{ d.vehicle_make }} {{ d.vehicle_model }} {{ d.vehicle_trim || '' }}</p>
                        <p v-else class="text-gray-400 italic">No vehicle yet</p>
                        <p v-if="d.vehicle_vin" class="text-xs font-mono text-gray-500 mt-1">VIN: {{ d.vehicle_vin }}</p>
                        <p v-if="d.msrp" class="text-sm text-gray-600 mt-1">MSRP: {{ fmt(d.msrp) }}</p>
                    </div>
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-medium text-gray-500">Deal Info</h3>
                            <button type="button" @click="activeTab = 'quotes'" class="text-xs text-indigo-600 hover:underline">Quotes</button>
                        </div>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between"><span class="text-gray-500">Type</span><span class="capitalize font-medium">{{ d.payment_type }}</span></div>
                            <div v-if="d.monthly_payment" class="flex justify-between"><span class="text-gray-500">Payment</span><span class="font-bold text-lg text-green-700">{{ fmt(d.monthly_payment) }}/mo</span></div>
                            <div v-if="d.term" class="flex justify-between"><span class="text-gray-500">Term</span><span>{{ d.term }} months</span></div>
                            <div v-if="d.lender" class="flex justify-between"><span class="text-gray-500">Lender</span><span>{{ d.lender.name }}</span></div>
                            <div v-if="d.profit" class="flex justify-between"><span class="text-gray-500">Profit</span><span class="text-green-600 font-bold">{{ fmt(d.profit) }}</span></div>
                        </div>
                    </div>
                </div>

                <!-- Tabs — pack tightly so all 11 fit on a normal-width
                     laptop without wrapping; flex-wrap remains as a safety
                     net for genuinely narrow viewports. -->
                <div class="bg-white shadow-sm rounded-lg">
                    <div class="border-b flex flex-wrap gap-0">
                        <button v-for="tab in ['summary', 'customer', 'tasks', 'workflow', 'quotes', 'notes', 'documents', 'vehicle_return', 'messages', 'timeline']" :key="tab"
                                @click="activeTab = tab"
                                class="px-2.5 py-3 text-sm font-medium capitalize whitespace-nowrap"
                                :class="activeTab === tab ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-500 hover:text-gray-700'">
                            {{ ({ vehicle_return: 'Return', documents: 'Docs', messages: 'Msgs' })[tab] || tab.replace(/_/g, ' ') }}
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
                                <button @click="completeTask(task.id)"
                                        :title="task.is_completed ? 'Click to undo' : 'Mark complete'"
                                        class="w-5 h-5 rounded border-2 flex items-center justify-center transition-colors"
                                        :class="task.is_completed ? 'bg-green-500 border-green-500 text-white hover:bg-green-600' : 'border-gray-300 hover:border-green-400'">
                                    <span v-if="task.is_completed" class="text-xs">&#10003;</span>
                                </button>
                                <div class="flex-1">
                                    <span class="text-sm" :class="task.is_completed ? 'line-through text-gray-400' : ''">{{ task.name }}</span>
                                    <span v-if="task.stage && showAllStageTasks" class="ml-2 text-xs text-gray-400 capitalize">({{ task.stage }})</span>
                                </div>
                                <span v-if="task.is_completed && task.completed_at" class="text-xs text-emerald-600">
                                    ✓ Completed {{ fmtDate(task.completed_at) }}
                                </span>
                                <template v-else>
                                    <input v-if="editingDueId === task.id" type="date"
                                           :value="task.due_date ? String(task.due_date).slice(0, 10) : ''"
                                           @change="updateTaskDueDate(task, $event.target.value)"
                                           @blur="editingDueId = null"
                                           class="border-gray-300 rounded text-xs py-0.5" />
                                    <button v-else type="button" @click="editingDueId = task.id"
                                            class="text-xs hover:bg-gray-100 px-1.5 py-0.5 rounded transition"
                                            :class="task.due_date && new Date(task.due_date) < new Date() ? 'text-red-600 font-bold' : 'text-gray-500 hover:text-gray-800'"
                                            title="Click to change — tasks below will shift forward to match">
                                        {{ task.due_date ? `Due ${fmtDate(task.due_date)}` : 'Set due date' }}
                                    </button>
                                </template>
                            </div>
                            <p v-if="!visibleTasks.length" class="text-gray-500 text-sm">No tasks for this stage.</p>
                        </div>

                        <!-- Quotes Tab -->
                        <div v-if="activeTab === 'quotes'" class="space-y-4">
                            <!-- New Quote Wizard CTA -->
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-gray-700">Quotes</h3>
                                <Link :href="route('leasing.deals.quotes.wizard', d.id)"
                                      class="px-3 py-1.5 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700">
                                    + New Quote (Wizard)
                                </Link>
                            </div>

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
                                        <span v-if="q.is_selected" class="text-xs bg-green-600 text-white px-2 py-1 rounded">✓ Accepted</span>
                                        <button v-else type="button" @click="selectQuote(q.id)"
                                                class="text-xs bg-indigo-100 text-indigo-700 px-2 py-1 rounded hover:bg-indigo-200">Mark Accepted</button>
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
                                            <SearchableSelect v-model="quoteForm.vehicle_make" :options="VEHICLE_MAKES" placeholder="Honda, Toyota, …" input-class="mt-0.5 block w-full border-gray-300 rounded-md text-xs" />
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
                                <div class="flex items-center gap-3">
                                    <span class="text-xs text-gray-500">
                                        {{ docsUploadedCount }} of {{ REQUIRED_DOCS.length }} uploaded
                                    </span>
                                    <a v-if="(d.documents || []).length"
                                       :href="route('leasing.deals.documents.download-all', d.id)"
                                       class="text-xs px-2.5 py-1 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">
                                        ⬇ Download All ({{ (d.documents || []).length }})
                                    </a>
                                </div>
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

                            <div class="pt-4">
                                <h4 class="text-sm font-semibold text-gray-700 mb-2">Deal documents</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-1">
                                    <div v-for="slot in DEAL_DOC_SLOTS" :key="slot.type"
                                         class="flex items-center gap-3 py-2 border-b last:border-0">
                                        <span class="text-lg" :class="findDealDoc(slot.type) ? 'text-emerald-600' : 'text-gray-300'">
                                            {{ findDealDoc(slot.type) ? '✓' : '○' }}
                                        </span>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-medium text-gray-900">{{ slot.label }}</div>
                                            <div v-if="findDealDoc(slot.type)" class="text-xs text-gray-500 truncate">
                                                <a :href="docUrl(findDealDoc(slot.type))" target="_blank" class="text-indigo-600 hover:text-indigo-800 underline">
                                                    {{ findDealDoc(slot.type).name || 'View file' }}
                                                </a>
                                            </div>
                                            <div v-else class="text-xs text-gray-400 italic">Not uploaded</div>
                                        </div>
                                        <label class="text-xs text-indigo-600 hover:text-indigo-800 cursor-pointer underline whitespace-nowrap">
                                            {{ findDealDoc(slot.type) ? 'Replace' : 'Upload' }}
                                            <input type="file" class="hidden" :accept="DOC_ACCEPT"
                                                   @change="(e) => uploadDocFor(slot.type, slot.label, e.target.files[0])" />
                                        </label>
                                    </div>
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

                        <!-- Calculator — merged under the Quotes tab so they sit together. -->
                        <div v-if="activeTab === 'quotes'" class="mt-6 pt-6 border-t space-y-4">
                            <h3 class="text-base font-semibold text-gray-700">📊 Calculator</h3>

                            <!-- Live Offers panel: pulls real OEM lease/finance/cash offers from MarketCheck -->
                            <div class="border-2 border-indigo-200 rounded-xl p-4 bg-indigo-50/30">
                                <div class="flex items-center justify-between mb-2">
                                    <div>
                                        <h4 class="text-sm font-semibold text-gray-800">🚗 Live OEM Offers — MarketCheck</h4>
                                        <p class="text-xs text-gray-500 mt-0.5">Pulls every active lease / finance / cash offer for this vehicle's make in the customer's ZIP. 1 API call per pull.</p>
                                    </div>
                                    <button @click="pullLiveOffers" :disabled="liveOffersLoading"
                                            class="px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs hover:bg-indigo-700 disabled:opacity-50">
                                        {{ liveOffersLoading ? 'Pulling…' : (liveOffers ? '↻ Refresh' : '⬇ Pull Live Offers') }}
                                    </button>
                                </div>

                                <div v-if="liveOffers && !liveOffers.ok" class="mt-2 text-xs text-red-700 bg-red-50 border border-red-200 rounded p-2">
                                    {{ liveOffers.error || 'Failed to pull offers.' }}
                                </div>

                                <div v-if="liveOffers && liveOffers.ok" class="mt-3 space-y-3">
                                    <div class="text-xs text-gray-600 flex items-center gap-3 flex-wrap">
                                        <span><b>{{ liveOffers.num_found }}</b> offers for <b>{{ liveOffers.make }}</b> in <b>{{ liveOffers.zip }}</b></span>
                                        <span v-if="liveOffers.captive" class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded text-[11px]">Captive: {{ liveOffers.captive }}</span>
                                        <span class="text-gray-400">·</span>
                                        <span>{{ liveOffers.calls_used }}/{{ liveOffers.calls_used + liveOffers.calls_remaining }} calls used</span>
                                    </div>

                                    <!-- Lease offers -->
                                    <div v-if="liveOffers.leases.length">
                                        <h5 class="text-xs font-semibold text-gray-700 mb-1">🔑 Lease offers ({{ liveOffers.leases.length }}) — click to apply</h5>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                            <button v-for="o in liveOffers.leases" :key="o.id"
                                                    @click="applyOfferToCalculator(o)" type="button"
                                                    :class="['text-left p-3 rounded-lg border-2 hover:border-indigo-400 transition',
                                                            selectedOfferId === o.id ? 'border-indigo-500 bg-white shadow' : 'border-gray-200 bg-white']">
                                                <div class="flex items-baseline justify-between">
                                                    <div class="font-bold text-lg text-indigo-700">${{ Number(o.monthly).toLocaleString() }}<span class="text-xs text-gray-500 font-normal">/mo</span></div>
                                                    <div class="text-[11px] text-gray-500">{{ o.term }}mo · {{ o.mileage_limit?.toLocaleString() }}mi</div>
                                                </div>
                                                <div class="text-xs text-gray-700 mt-0.5">{{ o.vehicle }}</div>
                                                <div class="text-[11px] text-gray-500 mt-1">
                                                    DAS {{ fmt(o.due_at_signing) }} · MSRP {{ fmt(o.msrp) }} · Res {{ o.residual_pct }}%
                                                </div>
                                                <div v-if="o.money_factor_derived" class="text-[10px] text-gray-400 mt-0.5">MF derived: {{ o.money_factor_derived }} (~{{ o.apr_equivalent }}% APR)</div>
                                                <div class="text-[10px] text-gray-400 mt-0.5">Valid {{ o.valid_from }} → {{ o.valid_through }}</div>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Finance offers -->
                                    <div v-if="liveOffers.finances.length">
                                        <h5 class="text-xs font-semibold text-gray-700 mb-1">💵 Finance offers ({{ liveOffers.finances.length }}) — click to apply</h5>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                            <button v-for="o in liveOffers.finances" :key="o.id"
                                                    @click="applyOfferToCalculator(o)" type="button"
                                                    :class="['text-left p-3 rounded-lg border-2 hover:border-indigo-400 transition',
                                                            selectedOfferId === o.id ? 'border-indigo-500 bg-white shadow' : 'border-gray-200 bg-white']">
                                                <div class="flex items-baseline justify-between">
                                                    <div class="font-bold text-lg text-indigo-700">{{ o.apr ? o.apr + '% APR' : 'See terms' }}</div>
                                                    <div class="text-[11px] text-gray-500">{{ o.term }}mo</div>
                                                </div>
                                                <div class="text-xs text-gray-700 mt-0.5">{{ o.vehicle }}</div>
                                                <div class="text-[10px] text-gray-400 mt-0.5">Valid {{ o.valid_from }} → {{ o.valid_through }}</div>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Rebates / cash offers — checkboxes that sum into rebates_total -->
                                    <div v-if="liveOffers.rebates.length">
                                        <h5 class="text-xs font-semibold text-gray-700 mb-1">💰 Available Rebates ({{ liveOffers.rebates.length }}) — check to add to rebates_total</h5>
                                        <div class="space-y-1">
                                            <label v-for="r in liveOffers.rebates" :key="r.id"
                                                   class="flex items-start gap-2 p-2 rounded hover:bg-white cursor-pointer">
                                                <input type="checkbox" :checked="isRebateApplied(r.id)" @change="toggleRebate(r)" class="mt-0.5" />
                                                <div class="flex-1 text-xs">
                                                    <div class="flex items-baseline gap-2">
                                                        <span class="font-bold text-emerald-700">${{ Number(r.cashback).toLocaleString() }}</span>
                                                        <span class="text-gray-700">{{ r.title }}</span>
                                                    </div>
                                                    <div v-if="r.target_group" class="text-[11px] text-gray-500 mt-0.5 italic">{{ r.target_group }}</div>
                                                    <div class="text-[10px] text-gray-400">Valid {{ r.valid_from }} → {{ r.valid_through }}</div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bird Dog + Profit Target controls -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div class="border rounded-xl p-3 bg-white">
                                    <h4 class="text-xs font-semibold text-gray-700 mb-2">🐦 Bird Dog</h4>
                                    <div class="flex items-center gap-2 mb-2">
                                        <input v-model.number="birdDog.amount" type="number" step="50" min="0" placeholder="$0"
                                               class="block w-32 border-gray-300 rounded text-sm" />
                                        <select v-model="birdDog.source" class="block flex-1 border-gray-300 rounded text-sm">
                                            <option value="dealer">Paid by dealer (adds to profit)</option>
                                            <option value="down">Roll into cap-cost reduction (lowers monthly)</option>
                                            <option value="deposit">Held back from customer deposit (no monthly impact)</option>
                                        </select>
                                    </div>
                                    <p class="text-[11px] text-gray-500">Choose where the BD lands. Most often "paid by dealer" — straight into AutoGo's profit.</p>
                                </div>

                                <div class="border rounded-xl p-3 bg-white">
                                    <h4 class="text-xs font-semibold text-gray-700 mb-2">🎯 Target profit on this deal</h4>
                                    <div class="flex items-center gap-2 mb-2">
                                        <input v-model.number="profitTarget" type="number" step="100" min="0" placeholder="$1500"
                                               class="block w-32 border-gray-300 rounded text-sm" />
                                        <div v-if="profitTarget" class="text-xs">
                                            <div>Estimated: <span class="font-semibold">{{ fmt(estimatedProfit.total) }}</span> <span class="text-gray-400">(front {{ fmt(estimatedProfit.front_end) }} + BD {{ fmt(estimatedProfit.bird_dog) }})</span></div>
                                            <div :class="profitGap > 0 ? 'text-red-600' : 'text-emerald-600'">
                                                Gap: {{ profitGap > 0 ? `Need ${fmt(profitGap)} more` : `${fmt(-profitGap)} above target` }}
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-[11px] text-gray-500">Profit estimate is front-end (sell − cost) + BD if dealer pays. Reserve / back-end income comes in PR D.</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 mb-3">
                                <button @click="findProgram" class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs hover:bg-blue-700">⚡ Auto-Find Lender Program</button>
                                <span v-if="matchedProgram" class="text-xs text-green-600 font-medium">
                                    ✓ {{ matchedProgram._from_marketcheck ? 'Tagged via MarketCheck' : 'Loaded' }} {{ matchedProgram.lender?.name }}
                                </span>
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

                        <!-- Credit — merged under the Workflow tab. Soft pull inline. -->
                        <div v-if="activeTab === 'workflow'" class="mt-6 pt-6 border-t space-y-4">
                            <h3 class="text-base font-semibold text-gray-700">💳 Credit</h3>
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

                            <!-- Dealership / Broker + Carrier / Lienholder (xDeskPro parity — Deal Information row) -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                            <!-- Dealership -->
                            <div class="border rounded-xl p-4 bg-white">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wide">Dealership</h4>
                                    <Link :href="route('leasing.dealers.index')" class="text-xs text-indigo-600 hover:underline">Manage</Link>
                                </div>
                                <div v-if="d.dealer" class="flex items-center justify-between">
                                    <div class="text-sm">
                                        <div class="font-medium">{{ d.dealer.name }}</div>
                                        <div class="text-xs text-gray-500">
                                            <span v-if="d.dealer.city || d.dealer.state">{{ [d.dealer.city, d.dealer.state].filter(Boolean).join(', ') }}</span>
                                            <span v-if="d.dealer.phone"> · {{ d.dealer.phone }}</span>
                                        </div>
                                    </div>
                                    <button type="button" @click="dealerPicker.clear" :disabled="dealerPicker.saving.value" class="text-xs text-red-600 hover:underline">Remove</button>
                                </div>
                                <div v-else class="relative">
                                    <input type="text" :value="dealerPicker.query.value" @input="dealerPicker.onInput" @focus="dealerPicker.open.value = true"
                                           placeholder="Search dealership…"
                                           class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                                    <div v-if="dealerPicker.open.value && dealerPicker.results.value.length"
                                         class="absolute z-10 mt-1 w-full bg-white border rounded-md shadow-lg max-h-64 overflow-y-auto">
                                        <button v-for="row in dealerPicker.results.value" :key="row.id"
                                                type="button" @click="dealerPicker.pick(row)"
                                                class="block w-full text-left px-3 py-2 text-sm hover:bg-indigo-50">
                                            <div class="font-medium">{{ row.name }}</div>
                                            <div class="text-xs text-gray-500">{{ [row.city, row.state].filter(Boolean).join(', ') }}<span v-if="row.phone"> · {{ row.phone }}</span></div>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Insurance Broker + Carrier (xDeskPro parity — Deal Information row) -->
                            <div class="border rounded-xl p-4 bg-white">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wide">Insurance Broker</h4>
                                    <Link :href="route('leasing.brokers.index')" class="text-xs text-indigo-600 hover:underline">Manage Brokers</Link>
                                </div>
                                <div v-if="d.broker" class="flex items-center justify-between">
                                    <div class="text-sm">
                                        <div class="font-medium">{{ d.broker.name }}</div>
                                        <div class="text-xs text-gray-500">
                                            <span v-if="d.broker.first_name || d.broker.last_name">{{ [d.broker.first_name, d.broker.last_name].filter(Boolean).join(' ') }} · </span>
                                            <span v-if="d.broker.phone">{{ d.broker.phone }}</span>
                                            <span v-if="d.broker.email"> · {{ d.broker.email }}</span>
                                        </div>
                                    </div>
                                    <button type="button" @click="clearBroker" :disabled="brokerSaving"
                                            class="text-xs text-red-600 hover:underline">Remove</button>
                                </div>
                                <div v-else class="relative">
                                    <input type="text" :value="brokerQuery" @input="onBrokerInput" @focus="brokerOpen = true"
                                           placeholder="Search broker by company, contact, phone…"
                                           class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                                    <div v-if="brokerOpen && brokerResults.length"
                                         class="absolute z-10 mt-1 w-full bg-white border rounded-md shadow-lg max-h-64 overflow-y-auto">
                                        <button v-for="ins in brokerResults" :key="ins.id"
                                                type="button" @click="pickBroker(ins)"
                                                class="block w-full text-left px-3 py-2 text-sm hover:bg-indigo-50">
                                            <div class="font-medium">{{ ins.name }}</div>
                                            <div class="text-xs text-gray-500">
                                                <span v-if="ins.first_name || ins.last_name">{{ [ins.first_name, ins.last_name].filter(Boolean).join(' ') }}</span>
                                                <span v-if="ins.phone"> · {{ ins.phone }}</span>
                                            </div>
                                        </button>
                                    </div>
                                    <p class="text-xs text-gray-400 italic mt-1">No broker assigned. Type to search; <Link :href="route('leasing.brokers.index')" class="text-indigo-600 hover:underline">add a new one</Link> if missing.</p>
                                </div>
                            </div>

                            <!-- Lienholder -->
                            <div class="border rounded-xl p-4 bg-white">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-xs font-semibold text-gray-700 uppercase tracking-wide">Lienholder</h4>
                                    <Link :href="route('leasing.lienholders.index')" class="text-xs text-indigo-600 hover:underline">Manage</Link>
                                </div>
                                <div v-if="d.lienholder" class="flex items-center justify-between">
                                    <div class="text-sm">
                                        <div class="font-medium">{{ d.lienholder.name }}</div>
                                        <div class="text-xs text-gray-500">
                                            <span v-if="d.lienholder.elt_number">ELT: {{ d.lienholder.elt_number }}</span>
                                            <span v-if="d.lienholder.phone"> · {{ d.lienholder.phone }}</span>
                                        </div>
                                    </div>
                                    <button type="button" @click="lienholderPicker.clear" :disabled="lienholderPicker.saving.value" class="text-xs text-red-600 hover:underline">Remove</button>
                                </div>
                                <div v-else class="relative">
                                    <input type="text" :value="lienholderPicker.query.value" @input="lienholderPicker.onInput" @focus="lienholderPicker.open.value = true"
                                           placeholder="Search lienholder…"
                                           class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                                    <div v-if="lienholderPicker.open.value && lienholderPicker.results.value.length"
                                         class="absolute z-10 mt-1 w-full bg-white border rounded-md shadow-lg max-h-64 overflow-y-auto">
                                        <button v-for="row in lienholderPicker.results.value" :key="row.id"
                                                type="button" @click="lienholderPicker.pick(row)"
                                                class="block w-full text-left px-3 py-2 text-sm hover:bg-indigo-50">
                                            <div class="font-medium">{{ row.name }}</div>
                                            <div class="text-xs text-gray-500"><span v-if="row.elt_number">ELT: {{ row.elt_number }}</span><span v-if="row.phone"> · {{ row.phone }}</span></div>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            </div><!-- /grid Dealer/ Broker /Lienholder -->

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
                                        <SearchableSelect v-model="workflow.preferences.brand" :options="VEHICLE_MAKES" placeholder="Honda, Toyota, …" input-class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500">Color</label>
                                        <SearchableSelect v-model="workflow.preferences.color" :options="VEHICLE_COLORS" placeholder="White, Black, …" input-class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
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

                        <!-- Customer Information Tab (xDeskPro parity) -->
                        <div v-if="activeTab === 'customer'" class="space-y-6">
                            <div v-if="!d.customer" class="text-center text-gray-400 py-8">No customer linked to this deal.</div>
                            <template v-else>
                                <!-- Primary customer -->
                                <div class="border rounded-xl p-5 bg-white">
                                    <div class="flex items-start justify-between mb-3">
                                        <h3 class="text-sm font-semibold text-gray-700">Primary Customer</h3>
                                        <Link :href="route('customers.show', d.customer.id)"
                                              class="text-xs text-indigo-600 hover:underline">Open customer page →</Link>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                        <div>
                                            <div class="text-xs text-gray-500">Name</div>
                                            <div class="font-medium">{{ d.customer.first_name }} {{ d.customer.last_name }}</div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500">Phone</div>
                                            <div>{{ d.customer.phone || '—' }}</div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500">Email</div>
                                            <div>{{ d.customer.email || '—' }}</div>
                                        </div>
                                        <div class="md:col-span-2">
                                            <div class="text-xs text-gray-500">Address</div>
                                            <div>
                                                <span v-if="d.customer.address">{{ d.customer.address }}</span>
                                                <span v-if="d.customer.city || d.customer.state || d.customer.zip">, {{ [d.customer.city, d.customer.state, d.customer.zip].filter(Boolean).join(' ') }}</span>
                                                <span v-if="!d.customer.address && !d.customer.city">—</span>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500">Date of Birth</div>
                                            <div>{{ d.customer.date_of_birth || '—' }}</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Co-signer -->
                                <div class="border rounded-xl p-5 bg-white">
                                    <div class="flex items-start justify-between mb-3">
                                        <h3 class="text-sm font-semibold text-gray-700">Co-Signer</h3>
                                        <button v-if="d.co_signer && !showCoSignerPicker" type="button" @click="removeCoSigner"
                                                class="text-xs text-red-600 hover:underline">Remove co-signer</button>
                                    </div>
                                    <div v-if="d.co_signer && !showCoSignerPicker" class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                        <div>
                                            <div class="text-xs text-gray-500">Name</div>
                                            <div class="font-medium">{{ d.co_signer.first_name }} {{ d.co_signer.last_name }}</div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500">Phone</div>
                                            <div>{{ d.co_signer.phone || '—' }}</div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-500">Email</div>
                                            <div>{{ d.co_signer.email || '—' }}</div>
                                        </div>
                                        <div class="md:col-span-3 pt-2">
                                            <button type="button" @click="showCoSignerPicker = true"
                                                    class="text-xs text-indigo-600 hover:underline">Change co-signer</button>
                                        </div>
                                    </div>
                                    <div v-else>
                                        <p v-if="!d.co_signer" class="text-xs text-gray-500 mb-3">No co-signer assigned.</p>
                                        <CustomerSelect :model-value="null" @update:model-value="onCoSignerSelected" />
                                        <button v-if="showCoSignerPicker" type="button" @click="showCoSignerPicker = false"
                                                class="mt-2 text-xs text-gray-500 hover:underline">Cancel</button>
                                    </div>
                                </div>

                                <!-- Customer's other deals -->
                                <div class="border rounded-xl bg-white">
                                    <div class="p-5 pb-3 flex items-center justify-between">
                                        <h3 class="text-sm font-semibold text-gray-700">Other deals for {{ d.customer.first_name }} {{ d.customer.last_name }}</h3>
                                        <span class="text-xs text-gray-400">{{ (d.customer.deals || []).length }} total</span>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Deal #</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Vehicle</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Stage</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200">
                                                <tr v-for="row in (d.customer.deals || [])" :key="row.id"
                                                    :class="row.id === d.id ? 'bg-indigo-50' : 'hover:bg-gray-50'">
                                                    <td class="px-4 py-2 text-sm">
                                                        <Link :href="route('leasing.deals.show', row.id)" class="text-indigo-600 hover:underline">#{{ row.deal_number }}</Link>
                                                        <span v-if="row.id === d.id" class="ml-1 text-xs text-gray-400">(this deal)</span>
                                                    </td>
                                                    <td class="px-4 py-2 text-sm">{{ [row.vehicle_year, row.vehicle_make, row.vehicle_model].filter(Boolean).join(' ') || '—' }}</td>
                                                    <td class="px-4 py-2 text-sm capitalize">{{ stageLabels[row.stage] || row.stage }}</td>
                                                    <td class="px-4 py-2 text-sm text-gray-500">{{ fmtDate(row.created_at) }}</td>
                                                </tr>
                                                <tr v-if="!(d.customer.deals || []).length">
                                                    <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-400">No other deals.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Vehicle Return Tab — trade-in / lease-return capture -->
                        <div v-if="activeTab === 'vehicle_return'" class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-gray-700">Vehicle Return</h3>
                                <button v-if="d.vehicle_return" type="button" @click="removeVehicleReturn"
                                        class="text-xs text-red-600 hover:underline">Remove</button>
                            </div>
                            <form @submit.prevent="saveVehicleReturn" class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                                <div>
                                    <label class="block text-xs text-gray-500">Type</label>
                                    <select v-model="vrForm.return_type" class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                                        <option value="trade_in">Trade-in</option>
                                        <option value="lease_return">Lease return</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">VIN</label>
                                    <input v-model="vrForm.vin" maxlength="17" class="mt-1 block w-full border-gray-300 rounded-md text-sm uppercase" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">Year</label>
                                    <input v-model.number="vrForm.year" type="number" min="1900" max="2099" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">Color</label>
                                    <input v-model="vrForm.color" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">Make</label>
                                    <input v-model="vrForm.make" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">Model</label>
                                    <input v-model="vrForm.model" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">Trim</label>
                                    <input v-model="vrForm.trim" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">Odometer</label>
                                    <input v-model.number="vrForm.odometer" type="number" min="0" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">Condition</label>
                                    <select v-model="vrForm.condition" class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                                        <option value="">—</option>
                                        <option value="excellent">Excellent</option>
                                        <option value="good">Good</option>
                                        <option value="fair">Fair</option>
                                        <option value="poor">Poor</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">Payoff Amount</label>
                                    <input v-model.number="vrForm.payoff_amount" type="number" step="0.01" min="0" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">Allowance</label>
                                    <input v-model.number="vrForm.allowance" type="number" step="0.01" min="0" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">ACV</label>
                                    <input v-model.number="vrForm.acv" type="number" step="0.01" min="0" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs text-gray-500">Payoff to (bank/leasing co.)</label>
                                    <input v-model="vrForm.payoff_to" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">Payoff good through</label>
                                    <input v-model="vrForm.payoff_good_through" type="date" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">Current plate</label>
                                    <input v-model="vrForm.current_plate" class="mt-1 block w-full border-gray-300 rounded-md text-sm uppercase" />
                                </div>
                                <div class="flex items-end">
                                    <label class="inline-flex items-center gap-2 text-sm">
                                        <input v-model="vrForm.plate_transfer" type="checkbox" /> Transfer plate to new vehicle
                                    </label>
                                </div>
                                <div class="md:col-span-4">
                                    <label class="block text-xs text-gray-500">Notes</label>
                                    <textarea v-model="vrForm.notes" rows="2" class="mt-1 block w-full border-gray-300 rounded-md text-sm"></textarea>
                                </div>
                                <div class="md:col-span-4 flex justify-end">
                                    <button type="submit" :disabled="vrForm.processing"
                                            class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700 disabled:opacity-50">
                                        {{ d.vehicle_return ? 'Save changes' : 'Save vehicle return' }}
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Sharing — folded into the Summary tab so the
                             tab row stays uncluttered. Internal team access
                             is a deal-meta concern, not its own destination. -->
                        <div v-if="activeTab === 'summary'" class="mt-6 pt-6 border-t space-y-4">
                            <h3 class="text-base font-semibold text-gray-700">🔐 Internal Sharing</h3>
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-gray-700">Internal Sharing</h3>
                                <button type="button" @click="selectAllShares" class="text-xs text-indigo-600 hover:underline">Select all</button>
                            </div>
                            <p class="text-xs text-gray-500">Users you select can view this deal in their pipeline. The owner ({{ d.salesperson?.name || 'salesperson' }}) is always included automatically.</p>
                            <div class="border rounded-lg divide-y max-h-96 overflow-y-auto">
                                <label v-for="u in orgUsers" :key="u.id"
                                       class="flex items-center gap-3 px-4 py-2 hover:bg-gray-50 cursor-pointer"
                                       :class="isOwner(u.id) ? 'bg-gray-50' : ''">
                                    <input type="checkbox"
                                           :checked="isOwner(u.id) || sharingForm.user_ids.includes(u.id)"
                                           :disabled="isOwner(u.id)"
                                           @change="toggleShare(u.id)" />
                                    <span class="text-sm">{{ u.name }}</span>
                                    <span v-if="isOwner(u.id)" class="text-xs text-gray-400 italic">(owner — always shared)</span>
                                </label>
                            </div>
                            <div class="flex justify-end">
                                <button type="button" @click="saveSharing" :disabled="sharingForm.processing"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700 disabled:opacity-50">Save sharing</button>
                            </div>
                        </div>

                        <!-- Timeline Tab — unified deal activity feed (xDeskPro parity) -->
                        <div v-if="activeTab === 'timeline'" class="space-y-1">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-sm font-semibold text-gray-700">Activity Timeline</h3>
                                <span class="text-xs text-gray-400">{{ timeline.length }} {{ timeline.length === 1 ? 'event' : 'events' }}</span>
                            </div>
                            <div v-if="!timeline.length" class="text-center text-gray-400 py-12 text-sm">
                                No activity recorded yet for this deal.
                            </div>
                            <ul v-else class="divide-y divide-gray-100">
                                <li v-for="(ev, i) in timeline" :key="i" class="py-2 flex items-start gap-3 text-sm">
                                    <span class="w-5 text-center text-base leading-tight">{{ timelineIcon(ev.kind) }}</span>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-gray-900">{{ ev.label }}<span v-if="ev.meta" class="text-gray-400"> — {{ ev.meta }}</span></div>
                                        <div class="text-xs text-gray-500">
                                            {{ timelineWhen(ev.at) }}
                                            <span v-if="ev.actor"> · by {{ ev.actor }}</span>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                </div><!-- /main column -->

                <!-- Right rail: Current Tasks + Action Items (xDeskPro parity) -->
                <aside class="space-y-4 lg:sticky lg:top-4 lg:self-start lg:max-h-[calc(100vh-2rem)] lg:overflow-y-auto">
                    <div class="bg-white shadow-sm rounded-lg">
                        <div class="px-4 py-3 border-b flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-700">Current Tasks</h3>
                            <button v-if="d.stage !== 'complete' && d.stage !== 'lost'"
                                    type="button" @click="transitionTo('complete')"
                                    class="px-2.5 py-1 text-xs bg-emerald-600 text-white rounded-md hover:bg-emerald-700">
                                Complete Deal
                            </button>
                        </div>
                        <ul class="divide-y divide-gray-100 max-h-[60vh] overflow-y-auto">
                            <li v-for="t in (d.tasks || [])" :key="t.id"
                                class="px-4 py-2 flex items-start gap-2 text-sm">
                                <input type="checkbox" :checked="t.is_completed"
                                       @change="toggleTask(t)"
                                       class="mt-0.5 rounded border-gray-300" />
                                <div class="flex-1 min-w-0">
                                    <div :class="t.is_completed ? 'text-gray-400 line-through' : 'text-gray-900'">{{ t.name }}</div>
                                    <div class="text-[10px] text-gray-400 capitalize">{{ stageLabels[t.stage] || t.stage }}</div>
                                </div>
                                <span v-if="t.due_date && !t.is_completed"
                                      :class="new Date(t.due_date) < new Date() ? 'text-red-600' : 'text-gray-500'"
                                      class="text-xs whitespace-nowrap">
                                    {{ fmtDate(t.due_date) }}
                                </span>
                                <span v-else-if="t.is_completed" class="text-xs text-gray-400">✓</span>
                            </li>
                            <li v-if="!(d.tasks || []).length" class="px-4 py-6 text-center text-xs text-gray-400">No tasks for this deal.</li>
                        </ul>
                    </div>

                    <div class="bg-white shadow-sm rounded-lg">
                        <div class="px-4 py-3 border-b flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-700">Action Items</h3>
                            <button type="button" @click="newActionItemOpen = !newActionItemOpen"
                                    class="text-xs text-indigo-600 hover:underline">+ Add</button>
                        </div>
                        <div v-if="newActionItemOpen" class="px-4 py-2 border-b bg-indigo-50/30">
                            <input v-model="newActionItem.title" type="text" placeholder="What needs doing?"
                                   class="block w-full border-gray-300 rounded-md text-sm" @keyup.enter="addActionItem" />
                            <div class="flex items-center gap-2 mt-2">
                                <input v-model="newActionItem.due_date" type="date" class="flex-1 border-gray-300 rounded-md text-xs" />
                                <button type="button" @click="addActionItem" :disabled="!newActionItem.title"
                                        class="px-2.5 py-1 text-xs bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50">Add</button>
                            </div>
                        </div>
                        <ul class="divide-y divide-gray-100 max-h-[40vh] overflow-y-auto">
                            <li v-for="ai in (d.action_items || [])" :key="ai.id" class="px-4 py-2 flex items-start gap-2 text-sm group">
                                <input type="checkbox" :checked="ai.is_completed"
                                       @change="toggleActionItem(ai)"
                                       class="mt-0.5 rounded border-gray-300" />
                                <div class="flex-1 min-w-0">
                                    <div :class="ai.is_completed ? 'text-gray-400 line-through' : 'text-gray-900'">{{ ai.title }}</div>
                                    <div v-if="ai.due_date && !ai.is_completed"
                                         :class="new Date(ai.due_date) < new Date() ? 'text-red-600' : 'text-gray-500'"
                                         class="text-[10px]">due {{ fmtDate(ai.due_date) }}</div>
                                </div>
                                <button type="button" @click="deleteActionItem(ai)"
                                        class="opacity-0 group-hover:opacity-100 text-red-600 text-xs hover:underline">×</button>
                            </li>
                            <li v-if="!(d.action_items || []).length" class="px-4 py-6 text-center text-xs text-gray-400">No action items yet.</li>
                        </ul>
                    </div>
                </aside>

                </div><!-- /grid wrapper -->
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
