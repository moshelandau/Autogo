<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import SmsButton from '@/Components/SmsButton.vue';
import CustomerMessages from '@/Components/CustomerMessages.vue';

const props = defineProps({
    customer: Object,
    documentTypes: Object,
    timeline: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
});

const tab = ref('overview');

// ── Document upload ───────────────────────────────────────
const upload = useForm({ type: 'drivers_license_front', label: '', expires_at: '', file: null });
const fileInput = ref(null);
const onFile = (e) => { upload.file = e.target.files[0] || null; };
const submitUpload = () => {
    if (!upload.file) return;
    upload.post(route('customers.documents.store', props.customer.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            upload.reset('label', 'expires_at', 'file');
            if (fileInput.value) fileInput.value.value = '';
        },
    });
};
const removeDoc = (doc) => {
    if (!confirm('Delete this document?')) return;
    router.delete(route('customers.documents.destroy', [props.customer.id, doc.id]), { preserveScroll: true });
};

const fmtBytes = (b) => {
    if (!b) return '';
    if (b < 1024) return b + ' B';
    if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
    return (b/1048576).toFixed(1) + ' MB';
};

const colorClass = (c) => ({
    indigo:  'bg-indigo-100 text-indigo-700 ring-indigo-200',
    sky:     'bg-sky-100 text-sky-700 ring-sky-200',
    amber:   'bg-amber-100 text-amber-800 ring-amber-200',
    rose:    'bg-rose-100 text-rose-700 ring-rose-200',
    emerald: 'bg-emerald-100 text-emerald-700 ring-emerald-200',
    violet:  'bg-violet-100 text-violet-700 ring-violet-200',
}[c] || 'bg-gray-100 text-gray-700 ring-gray-200');

const filteredTimeline = computed(() => {
    if (kindFilter.value === 'all') return props.timeline;
    return props.timeline.filter(t => t.kind === kindFilter.value);
});

// ── Multi-phone management ────────────────────────────
const phoneAddOpen = ref(false);
const newPhone = ref({ phone: '', label: 'Mobile', is_sms_capable: true, is_primary: false });
const addPhone = () => {
    router.post(route('customers.phones.store', props.customer.id), { ...newPhone.value }, {
        preserveScroll: true,
        onSuccess: () => { phoneAddOpen.value = false; newPhone.value = { phone: '', label: 'Mobile', is_sms_capable: true, is_primary: false }; },
    });
};
const setPrimaryPhone = (p) => router.put(route('customers.phones.update', [props.customer.id, p.id]), { is_primary: true }, { preserveScroll: true });
const removePhone = (p) => {
    if (!confirm(`Remove ${p.phone}?`)) return;
    router.delete(route('customers.phones.destroy', [props.customer.id, p.id]), { preserveScroll: true });
};

// ── Text Application (bot trigger) ─────────────────────
const textAppOpen = ref(false);
const textAppForm = useForm({ phone: props.customer.phone || '', flow: '' });
const sendTextApp = () => {
    textAppForm.post(route('customers.text-application', props.customer.id), {
        preserveScroll: true,
        onSuccess: () => { textAppOpen.value = false; textAppForm.reset('flow'); },
    });
};

const kindFilter = ref('all');
const kinds = [
    { id: 'all',             label: 'All' },
    { id: 'lease',           label: 'Lease/Finance' },
    { id: 'rental',          label: 'Rentals' },
    { id: 'insurance_claim', label: 'Insurance' },
    { id: 'rental_claim',    label: 'Damage' },
    { id: 'payment',         label: 'Payments' },
    { id: 'credit_pull',     label: 'Credit' },
];
</script>

<template>
    <AppLayout title="Customer Details">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('customers.index')" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                    <h2 class="font-bold text-xl text-gray-900">{{ customer.first_name }} {{ customer.last_name }}</h2>
                    <span v-if="!customer.is_active" class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full">Inactive</span>
                </div>
                <div class="flex gap-2">
                    <Link :href="`/rental/reservations/create?customer_id=${customer.id}`"
                          class="bg-sky-600 text-white px-3 py-2 rounded-md text-sm hover:bg-sky-700">
                        🔑 New Rental
                    </Link>
                    <Link :href="`/leasing/deals/create?customer_id=${customer.id}`"
                          class="bg-violet-600 text-white px-3 py-2 rounded-md text-sm hover:bg-violet-700">
                        🚗 New Deal
                    </Link>
                    <Link :href="`/claims/create?customer_id=${customer.id}`"
                          class="bg-amber-600 text-white px-3 py-2 rounded-md text-sm hover:bg-amber-700">
                        📋 New Claim
                    </Link>
                    <Link :href="route('customers.scan', customer.id)"
                          class="bg-emerald-600 text-white px-3 py-2 rounded-md text-sm hover:bg-emerald-700">
                        📄 Scan Docs
                    </Link>
                    <button @click="textAppOpen = true"
                          class="bg-purple-600 text-white px-3 py-2 rounded-md text-sm hover:bg-purple-700">
                        📱 Text Application
                    </button>
                    <Link :href="route('customers.edit', customer.id)" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">Edit</Link>
                </div>
            </div>
        </template>

        <div class="p-6 space-y-5">
            <!-- Outstanding balance flag -->
            <div v-if="(customer.cached_outstanding_balance || 0) > 0"
                 class="bg-red-50 border-2 border-red-300 rounded-xl p-4 flex items-center gap-3">
                <span class="text-3xl">⚠️</span>
                <div class="flex-1">
                    <div class="font-bold text-red-900">OUTSTANDING BALANCE</div>
                    <div class="text-sm text-red-800">This customer owes <strong>${{ Number(customer.cached_outstanding_balance).toFixed(2) }}</strong> from past rentals. Do NOT start a new rental until balance is collected.</div>
                </div>
            </div>

            <!-- Business stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white border rounded-xl p-4 text-center">
                    <div class="text-2xl font-bold text-indigo-600">{{ stats.deals_count }}</div>
                    <div class="text-xs text-gray-500">Lease/Finance Deals</div>
                </div>
                <div class="bg-white border rounded-xl p-4 text-center">
                    <div class="text-2xl font-bold text-sky-600">{{ stats.reservations_count }}</div>
                    <div class="text-xs text-gray-500">Rentals</div>
                </div>
                <div class="bg-white border rounded-xl p-4 text-center">
                    <div class="text-2xl font-bold text-amber-600">{{ stats.claims_count }}</div>
                    <div class="text-xs text-gray-500">Claims</div>
                </div>
                <div class="bg-white border rounded-xl p-4 text-center">
                    <div class="text-2xl font-bold text-emerald-600">${{ Number(stats.lifetime_revenue || 0).toLocaleString() }}</div>
                    <div class="text-xs text-gray-500">Lifetime Value</div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="bg-white border rounded-xl">
                <nav class="flex border-b">
                    <button v-for="t in [
                        {id:'overview', label:'Overview'},
                        {id:'documents', label:`Documents / IDs (${customer.documents?.length || 0})`},
                        {id:'messages', label:'Messages'},
                        {id:'history', label:`History (${timeline.length})`},
                    ]" :key="t.id" @click="tab = t.id"
                        class="px-5 py-3 text-sm font-medium border-b-2 transition"
                        :class="tab === t.id ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-800'">
                        {{ t.label }}
                    </button>
                </nav>

                <!-- OVERVIEW -->
                <div v-if="tab === 'overview'" class="p-6 space-y-6">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Contact</h3>
                        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                            <div class="md:col-span-2">
                                <dt class="text-gray-500 flex items-center justify-between">
                                    <span>Phone numbers</span>
                                    <button type="button" @click="phoneAddOpen = !phoneAddOpen" class="text-xs text-indigo-600 hover:text-indigo-800">+ Add phone</button>
                                </dt>
                                <dd class="mt-1 space-y-1.5">
                                    <div v-if="!customer.phones || customer.phones.length === 0" class="text-gray-400 italic text-xs">No phones on file</div>
                                    <div v-for="p in (customer.phones || [])" :key="p.id" class="flex items-center gap-2 flex-wrap">
                                        <span class="font-mono">{{ p.phone }}</span>
                                        <span v-if="p.label" class="text-[10px] px-1.5 py-0.5 bg-gray-100 text-gray-600 rounded">{{ p.label }}</span>
                                        <span v-if="p.is_primary" class="text-[10px] px-1.5 py-0.5 bg-emerald-100 text-emerald-700 rounded font-semibold">Primary</span>
                                        <span :class="p.is_sms_capable ? 'text-emerald-600' : 'text-gray-400'" class="text-[10px]">{{ p.is_sms_capable ? '📱 SMS' : '☎ voice only' }}</span>
                                        <SmsButton v-if="p.is_sms_capable" :to="p.phone" :customer-id="customer.id" subject-type="App\\Models\\Customer" :subject-id="customer.id" label="SMS" />
                                        <button v-if="!p.is_primary" @click="setPrimaryPhone(p)" class="text-[10px] text-indigo-600 hover:text-indigo-800 underline">make primary</button>
                                        <button @click="removePhone(p)" class="text-[10px] text-red-600 hover:text-red-800 underline">remove</button>
                                    </div>
                                    <form v-if="phoneAddOpen" @submit.prevent="addPhone" class="mt-2 flex flex-wrap items-end gap-2 p-2 bg-indigo-50 rounded">
                                        <input v-model="newPhone.phone" placeholder="Phone *" required class="border-gray-300 rounded text-xs px-2 py-1 w-32" />
                                        <select v-model="newPhone.label" class="border-gray-300 rounded text-xs px-2 py-1">
                                            <option value="">Label</option><option>Mobile</option><option>Home</option><option>Work</option><option>Other</option>
                                        </select>
                                        <label class="text-xs flex items-center gap-1"><input type="checkbox" v-model="newPhone.is_sms_capable" /> Can receive SMS</label>
                                        <label class="text-xs flex items-center gap-1"><input type="checkbox" v-model="newPhone.is_primary" /> Primary</label>
                                        <button type="submit" class="text-xs bg-indigo-600 text-white px-2 py-1 rounded">Add</button>
                                        <button type="button" @click="phoneAddOpen = false" class="text-xs text-gray-500">Cancel</button>
                                    </form>
                                </dd>
                            </div>
                            <div><dt class="text-gray-500">Email</dt><dd class="font-medium">{{ customer.email || '-' }}</dd></div>
                            <div class="md:col-span-2"><dt class="text-gray-500">Address</dt>
                                <dd class="font-medium">{{ customer.address || '-' }}, {{ customer.city }} {{ customer.state }} {{ customer.zip }}</dd></div>
                            <div><dt class="text-gray-500">SMS</dt>
                                <dd><span :class="customer.can_receive_sms ? 'text-green-600' : 'text-red-600'" class="font-medium">{{ customer.can_receive_sms ? 'Enabled' : 'Disabled' }}</span></dd></div>
                        </dl>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Driver's License & Insurance</h3>
                        <dl class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                            <div><dt class="text-gray-500">DL Number</dt><dd class="font-medium">{{ customer.drivers_license_number || '-' }}</dd></div>
                            <div><dt class="text-gray-500">DL State</dt><dd class="font-medium">{{ customer.dl_state || '-' }}</dd></div>
                            <div><dt class="text-gray-500">DL Expiration</dt><dd class="font-medium">{{ customer.dl_expiration || '-' }}</dd></div>
                            <div><dt class="text-gray-500">Insurance</dt><dd class="font-medium">{{ customer.insurance_company || '-' }}</dd></div>
                            <div><dt class="text-gray-500">Policy #</dt><dd class="font-medium">{{ customer.insurance_policy || '-' }}</dd></div>
                            <div><dt class="text-gray-500">Credit Score</dt><dd class="font-medium">{{ customer.credit_score || '-' }}</dd></div>
                        </dl>
                    </div>
                    <div v-if="customer.notes">
                        <h3 class="text-sm font-semibold text-gray-700 mb-2">Notes</h3>
                        <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ customer.notes }}</p>
                    </div>
                </div>

                <!-- DOCUMENTS -->
                <div v-if="tab === 'documents'" class="p-6 space-y-6">
                    <!-- Upload form -->
                    <form @submit.prevent="submitUpload" class="bg-gray-50 border rounded-xl p-4 grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Document type</label>
                            <select v-model="upload.type" class="w-full border-gray-300 rounded-lg text-sm">
                                <option v-for="(label, val) in documentTypes" :key="val" :value="val">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Label (optional)</label>
                            <input v-model="upload.label" type="text" class="w-full border-gray-300 rounded-lg text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Expires</label>
                            <input v-model="upload.expires_at" type="date" class="w-full border-gray-300 rounded-lg text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">File (jpg/png/pdf, max 10MB)</label>
                            <input ref="fileInput" type="file" @change="onFile" accept=".jpg,.jpeg,.png,.pdf,.heic,.webp"
                                   class="w-full text-xs file:mr-2 file:px-3 file:py-1.5 file:rounded file:border-0 file:bg-indigo-600 file:text-white" />
                        </div>
                        <div class="md:col-span-5 flex justify-end">
                            <button type="submit" :disabled="upload.processing || !upload.file"
                                class="bg-indigo-600 text-white px-4 py-2 text-sm rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                                {{ upload.processing ? 'Uploading…' : 'Upload document' }}
                            </button>
                        </div>
                        <div v-if="upload.errors.file" class="md:col-span-5 text-xs text-red-600">{{ upload.errors.file }}</div>
                    </form>

                    <!-- Doc grid -->
                    <div v-if="customer.documents?.length" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        <div v-for="d in customer.documents" :key="d.id" class="border rounded-xl overflow-hidden bg-white">
                            <a :href="d.url" target="_blank" class="block bg-gray-100 h-40 flex items-center justify-center overflow-hidden">
                                <img v-if="d.is_image" :src="d.url" class="object-cover w-full h-full" />
                                <div v-else class="text-center text-gray-500 text-sm">📄<br/>{{ d.original_name?.split('.').pop()?.toUpperCase() }}</div>
                            </a>
                            <div class="p-3">
                                <div class="text-xs font-semibold text-gray-800">{{ documentTypes[d.type] || d.type }}</div>
                                <div v-if="d.label" class="text-xs text-gray-500">{{ d.label }}</div>
                                <div class="text-[10px] text-gray-400 mt-1">
                                    {{ d.original_name }} · {{ fmtBytes(d.size_bytes) }}
                                    <span v-if="d.expires_at" class="ml-1">· exp {{ d.expires_at }}</span>
                                </div>
                                <div class="flex justify-between items-center mt-2">
                                    <a :href="d.url" target="_blank" class="text-xs text-indigo-600 hover:text-indigo-800">Open</a>
                                    <button @click="removeDoc(d)" class="text-xs text-red-600 hover:text-red-800">Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400 text-center py-8">No documents on file. Upload a driver's license, insurance card, etc. above.</p>
                </div>

                <!-- MESSAGES (SMS thread) -->
                <div v-if="tab === 'messages'" class="p-6">
                    <CustomerMessages :customer="customer" />
                </div>

                <!-- HISTORY -->
                <div v-if="tab === 'history'" class="p-6">
                    <div class="flex flex-wrap gap-2 mb-5">
                        <button v-for="k in kinds" :key="k.id" @click="kindFilter = k.id"
                            class="px-3 py-1.5 text-xs rounded-full border transition"
                            :class="kindFilter === k.id ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-600 border-gray-300 hover:border-gray-400'">
                            {{ k.label }}
                        </button>
                    </div>

                    <div v-if="filteredTimeline.length" class="space-y-3">
                        <div v-for="(item, i) in filteredTimeline" :key="i"
                            class="flex items-start gap-3 bg-white border rounded-xl p-4 hover:shadow-sm transition">
                            <div class="text-2xl">{{ item.icon }}</div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <component :is="item.href ? 'a' : 'span'" :href="item.href"
                                        class="font-medium text-sm text-gray-900" :class="item.href && 'hover:text-indigo-700'">
                                        {{ item.title }}
                                    </component>
                                    <span v-if="item.status" class="text-[10px] px-2 py-0.5 rounded-full ring-1 capitalize"
                                          :class="colorClass(item.color)">{{ item.status }}</span>
                                </div>
                                <div v-if="item.sub" class="text-xs text-gray-500 mt-0.5">{{ item.sub }}</div>
                            </div>
                            <div class="text-right text-xs">
                                <div v-if="item.meta" class="font-bold text-gray-900">{{ item.meta }}</div>
                                <div class="text-gray-400 mt-0.5">{{ new Date(item.date).toLocaleDateString() }}</div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400 text-center py-8">No business history yet.</p>
                </div>
            </div>
        </div>

        <!-- Text Application modal -->
        <Teleport to="body">
            <div v-if="textAppOpen" @click.self="textAppOpen = false"
                 class="fixed inset-0 bg-black/40 flex items-center justify-center z-[100] p-4">
                <div class="bg-white rounded-2xl shadow-xl max-w-md w-full">
                    <header class="p-5 border-b">
                        <h3 class="font-bold text-lg">📱 Text Application to Customer</h3>
                        <p class="text-xs text-gray-500 mt-1">Sends the bot intro + first question via SMS. Customer answers piece by piece.</p>
                    </header>
                    <form @submit.prevent="sendTextApp" class="p-5 space-y-4 text-sm">
                        <div>
                            <label class="block text-xs font-semibold mb-1">Phone</label>
                            <input v-model="textAppForm.phone" type="tel" required
                                   class="w-full border-gray-300 rounded-lg text-sm" placeholder="8455551234" />
                            <p class="text-[11px] text-gray-400 mt-1">Defaults to customer's primary phone. Override if texting an alternate number.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1">Flow</label>
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" v-for="f in [
                                    { id:'lease',   label:'🚗 Lease' },
                                    { id:'finance', label:'💰 Finance' },
                                    { id:'rental',  label:'🔑 Rental' },
                                    { id:'towing',  label:'🚛 Towing' },
                                    { id:'bodyshop',label:'🔧 Bodyshop' },
                                ]" :key="f.id" @click="textAppForm.flow = f.id"
                                    class="px-3 py-2 rounded-lg text-sm border-2 transition"
                                    :class="textAppForm.flow === f.id ? 'border-indigo-600 bg-indigo-50 text-indigo-800' : 'border-gray-200 hover:border-gray-300'">
                                    {{ f.label }}
                                </button>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="textAppOpen = false" class="px-4 py-2 text-sm bg-gray-100 rounded-lg">Cancel</button>
                            <button type="submit" :disabled="textAppForm.processing || !textAppForm.flow"
                                    class="px-5 py-2 bg-purple-600 text-white rounded-lg text-sm font-semibold hover:bg-purple-700 disabled:opacity-50">
                                {{ textAppForm.processing ? 'Sending…' : 'Send' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
