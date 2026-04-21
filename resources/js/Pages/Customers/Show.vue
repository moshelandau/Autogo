<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed, nextTick, watch, onBeforeUnmount } from 'vue';
import SmsButton from '@/Components/SmsButton.vue';

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

// ── SMS Messages tab ─────────────────────────────────────
const messages = ref([]);
const messagesLoading = ref(false);
const msgScroll = ref(null);
const replyForm = useForm({
    to: props.customer.phone || '',
    message: '',
    customer_id: props.customer.id,
    subject_type: 'App\\Models\\Customer',
    subject_id: props.customer.id,
});
let lastInboundId = 0;
let audioCtx = null;
const unlockAudio = () => { if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)(); };
if (typeof window !== 'undefined') {
    document.addEventListener('click', unlockAudio, { once: true });
    if ('Notification' in window && Notification.permission === 'default') Notification.requestPermission();
}
const beep = () => {
    if (!audioCtx) return;
    const playTone = (freq, when, dur = 0.35) => {
        const o = audioCtx.createOscillator(), g = audioCtx.createGain();
        o.type = 'sine'; o.frequency.value = freq;
        g.gain.setValueAtTime(0.001, audioCtx.currentTime + when);
        g.gain.exponentialRampToValueAtTime(1.0, audioCtx.currentTime + when + 0.02);
        g.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + when + dur);
        o.connect(g).connect(audioCtx.destination);
        o.start(audioCtx.currentTime + when);
        o.stop(audioCtx.currentTime + when + dur);
    };
    playTone(988, 0);      // B5
    playTone(1319, 0.18);  // E6
    if ('Notification' in window && Notification.permission === 'granted' && document.hidden) {
        new Notification('New SMS from ' + (props.customer?.first_name || 'customer'));
    }
};
const loadMessages = async () => {
    if (!props.customer.phone) return;
    if (messages.value.length === 0) messagesLoading.value = true;
    try {
        const res = await fetch(route('sms.customer-thread', props.customer.id), { credentials: 'same-origin' });
        const data = await res.json();
        const next = data.messages || [];
        const newestInbound = Math.max(0, ...next.filter(m => m.direction === 'inbound').map(m => m.id));
        if (lastInboundId && newestInbound > lastInboundId) beep();
        lastInboundId = newestInbound;
        messages.value = next;
        nextTick(() => { if (msgScroll.value) msgScroll.value.scrollTop = msgScroll.value.scrollHeight; });
    } catch (e) { console.error(e); }
    messagesLoading.value = false;
};
const sendReply = () => {
    if (!replyForm.message.trim()) return;
    replyForm.post(route('sms.send'), {
        preserveScroll: true,
        onSuccess: () => { replyForm.reset('message'); loadMessages(); },
    });
};
// Live updates — poll the thread every 5s while the Messages tab is active
let msgPollTimer = null;
watch(tab, (val) => {
    if (msgPollTimer) { clearInterval(msgPollTimer); msgPollTimer = null; }
    if (val === 'messages' && props.customer.phone) {
        msgPollTimer = setInterval(loadMessages, 3000);
    }
});
onBeforeUnmount(() => { if (msgPollTimer) clearInterval(msgPollTimer); });

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
                    ]" :key="t.id" @click="tab = t.id; if (t.id === 'messages') loadMessages()"
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
                            <div>
                                <dt class="text-gray-500">Phone</dt>
                                <dd class="font-medium flex items-center gap-2">
                                    <span>{{ customer.phone || '-' }}</span>
                                    <SmsButton v-if="customer.phone" :to="customer.phone" :customer-id="customer.id" subject-type="App\\Models\\Customer" :subject-id="customer.id" label="SMS" />
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
                    <div v-if="!customer.phone" class="text-center text-gray-400 py-8">
                        No phone number on file — add one to send SMS.
                    </div>
                    <div v-else>
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-semibold text-gray-700">SMS thread with {{ customer.phone }}</h3>
                            <Link :href="route('sms.show', customer.phone)" class="text-xs text-indigo-600 hover:text-indigo-800">Open full view →</Link>
                        </div>
                        <div ref="msgScroll" class="border rounded-lg bg-gray-50 overflow-y-auto" style="height: calc(100vh - 420px); min-height: 320px">
                            <div class="min-h-full flex flex-col justify-end p-3 space-y-2">
                                <div v-if="messagesLoading" class="text-center text-gray-400 py-6 text-sm">Loading...</div>
                                <div v-else-if="messages.length === 0" class="text-center text-gray-400 py-6 text-sm">No messages yet.</div>
                                <div v-else v-for="m in messages" :key="m.id" class="flex"
                                    :class="m.direction === 'outbound' ? 'justify-end' : 'justify-start'">
                                    <div class="max-w-md">
                                        <div class="px-3 py-1.5 rounded-2xl text-sm whitespace-pre-wrap break-words"
                                            :class="m.direction === 'outbound' ? 'bg-indigo-600 text-white rounded-br-sm' : 'bg-white text-gray-900 border rounded-bl-sm'">
                                            <template v-if="m.attachments && m.attachments.media">
                                                <a v-for="(att, i) in m.attachments.media" :key="i" :href="att.url" target="_blank" class="block mb-1 last:mb-0">
                                                    <img v-if="att.mime && att.mime.startsWith('image/')" :src="att.url"
                                                        class="rounded-lg max-w-full max-h-60 object-contain" :alt="att.name" />
                                                    <span v-else class="underline">{{ att.name }}</span>
                                                </a>
                                            </template>
                                            <span v-if="m.body">{{ m.body }}</span>
                                        </div>
                                        <div class="text-[11px] text-gray-400 mt-0.5"
                                            :class="m.direction === 'outbound' ? 'text-right' : 'text-left'">
                                            {{ new Date(m.sent_at || m.created_at).toLocaleString() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <form @submit.prevent="sendReply" class="mt-3 flex items-end gap-2">
                            <textarea v-model="replyForm.message" rows="2" placeholder="Type a message…"
                                class="flex-1 border-gray-300 rounded-md text-sm resize-none focus:border-indigo-500 focus:ring-indigo-500" />
                            <button type="submit" :disabled="replyForm.processing || !replyForm.message.trim()"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50">
                                {{ replyForm.processing ? 'Sending…' : 'Send' }}
                            </button>
                        </form>
                    </div>
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
    </AppLayout>
</template>
