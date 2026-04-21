<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref, nextTick, onMounted, onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    phone:    { type: String, required: true },
    messages: { type: Array,  default: () => [] },
    customer: { type: Object, default: null },
    staff:    { type: Array,  default: () => [] },
    assignedTo: { type: [Number, String], default: null },
});

const reply = useForm({
    to: props.phone,
    message: '',
    customer_id: props.customer?.id || null,
    subject_type: props.customer ? 'App\\Models\\Customer' : null,
    subject_id: props.customer?.id || null,
});

const threadEl = ref(null);
const scrollBottom = () => nextTick(() => { if (threadEl.value) threadEl.value.scrollTop = threadEl.value.scrollHeight; });
onMounted(scrollBottom);

// Live updates — poll every 3s for new messages
let pollTimer = null;
const poll = () => {
    router.reload({
        only: ['messages'],
        preserveScroll: true,
        preserveState: true,
    });
};
onMounted(() => { pollTimer = setInterval(poll, 3000); });
onBeforeUnmount(() => { if (pollTimer) clearInterval(pollTimer); });

// Notification sound on new inbound — unlock AudioContext on first click
let audioCtx = null;
const unlockAudio = () => {
    if (audioCtx) return;
    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
};
onMounted(() => {
    document.addEventListener('click', unlockAudio, { once: true });
    if ('Notification' in window && Notification.permission === 'default') Notification.requestPermission();
});
const beep = () => {
    if (!audioCtx) return;
    // Two-tone "ding-dong" at full volume so it's audible across the office
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
        new Notification('New SMS', { body: 'Click to view' });
    }
};
let lastInboundId = Math.max(0, ...(props.messages || []).filter(m => m.direction === 'inbound').map(m => m.id));
watch(() => props.messages, (msgs) => {
    const newest = Math.max(0, ...(msgs || []).filter(m => m.direction === 'inbound').map(m => m.id));
    if (newest > lastInboundId) {
        lastInboundId = newest;
        beep();
        scrollBottom();
    }
}, { deep: true });
watch(() => props.messages?.length, () => scrollBottom());

const send = () => {
    if (!reply.message.trim()) return;
    reply.post(route('sms.send'), {
        preserveScroll: true,
        onSuccess: () => { reply.reset('message'); scrollBottom(); },
    });
};

// Assignment + mark-unread
const assignedToLocal = ref(props.assignedTo);
watch(() => props.assignedTo, (v) => { assignedToLocal.value = v; });
const assign = () => {
    router.post(route('sms.assign', props.phone), { user_id: assignedToLocal.value || null }, { preserveScroll: true });
};
const setStatus = (msgId, status) => {
    router.post(route('sms.mark-status', msgId), { status }, { preserveScroll: true });
};
const markAllRead = () => {
    const unread = (props.messages || []).filter(m => m.direction === 'inbound' && m.status === 'received');
    Promise.all(unread.map(m => fetch(route('sms.mark-status', m.id), {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
        body: JSON.stringify({ status: 'read' }),
    }))).then(() => router.reload({ only: ['messages'], preserveScroll: true }));
};

const fmt = (iso) => {
    if (!iso) return '';
    return new Date(iso).toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
};
</script>

<template>
    <AppLayout :title="`SMS — ${customer ? customer.first_name + ' ' + customer.last_name : phone}`">
        <template #header>
            <div class="flex items-center gap-3 flex-wrap">
                <Link :href="route('sms.index')" class="text-indigo-600 hover:text-indigo-800 text-sm">← Inbox</Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ customer ? `${customer.first_name} ${customer.last_name}` : phone }}
                </h2>
                <span v-if="customer" class="text-sm text-gray-500">{{ phone }}</span>
                <div class="ml-auto flex items-center gap-2">
                    <button @click="markAllRead" class="text-xs text-indigo-600 hover:text-indigo-800 underline">Mark all read</button>
                    <label class="text-xs text-gray-500">Assigned to:</label>
                    <select v-model="assignedToLocal" @change="assign"
                        class="text-sm border-gray-300 rounded-md py-1 px-2 focus:border-indigo-500 focus:ring-indigo-500">
                        <option :value="null">— Unassigned —</option>
                        <option v-for="u in staff" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                    <Link v-if="customer" :href="route('customers.show', customer.id)"
                        class="text-sm text-indigo-600 hover:text-indigo-800">View Customer →</Link>
                </div>
            </div>
        </template>

        <div class="flex-1 flex flex-col min-h-0" style="height: calc(100vh - 64px)">
            <div class="flex-1 max-w-3xl w-full mx-auto sm:px-6 lg:px-8 py-4 flex flex-col min-h-0">
                <div class="bg-white shadow-sm rounded-lg flex flex-col flex-1 min-h-0">
                    <!-- Messages — bottom-anchored like iMessage -->
                    <div ref="threadEl" class="flex-1 overflow-y-auto bg-gray-50">
                        <div class="min-h-full flex flex-col justify-end p-4 space-y-3">
                            <div v-if="messages.length === 0" class="text-center text-gray-400 py-8">
                                No messages yet — send the first one below.
                            </div>
                            <div v-for="m in messages" :key="m.id" class="flex group"
                                :class="m.direction === 'outbound' ? 'justify-end' : 'justify-start'">
                                <div class="max-w-md">
                                    <div class="px-4 py-2 rounded-2xl text-sm whitespace-pre-wrap break-words"
                                        :class="m.direction === 'outbound'
                                            ? 'bg-indigo-600 text-white rounded-br-sm'
                                            : 'bg-white text-gray-900 border rounded-bl-sm'">
                                        <template v-if="m.attachments && m.attachments.media">
                                            <a v-for="(att, i) in m.attachments.media" :key="i" :href="att.url" target="_blank" class="block mb-1 last:mb-0">
                                                <img v-if="att.mime && att.mime.startsWith('image/')" :src="att.url"
                                                    class="rounded-lg max-w-full max-h-72 object-contain" :alt="att.name" />
                                                <span v-else class="underline">{{ att.name }}</span>
                                            </a>
                                        </template>
                                        <span v-if="m.body">{{ m.body }}</span>
                                    </div>
                                    <div class="text-xs text-gray-400 mt-1 flex items-center gap-2"
                                        :class="m.direction === 'outbound' ? 'justify-end' : 'justify-start'">
                                        <span>{{ fmt(m.sent_at || m.created_at) }}</span>
                                        <span v-if="m.direction === 'outbound'">· {{ m.status }}</span>
                                        <button v-if="m.direction === 'inbound'"
                                            @click="setStatus(m.id, m.status === 'received' ? 'read' : 'received')"
                                            class="opacity-0 group-hover:opacity-100 transition text-indigo-600 hover:text-indigo-800 underline">
                                            {{ m.status === 'received' ? 'mark read' : 'mark unread' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Reply box -->
                    <form @submit.prevent="send" class="border-t p-3 flex items-end gap-2 bg-white">
                        <textarea v-model="reply.message" rows="2"
                            placeholder="Type a message…"
                            class="flex-1 border-gray-300 rounded-lg text-sm resize-none focus:border-indigo-500 focus:ring-indigo-500"
                            @keydown.enter.exact.prevent="send" />
                        <button type="submit"
                            :disabled="reply.processing || !reply.message.trim()"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50">
                            {{ reply.processing ? 'Sending…' : 'Send' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
