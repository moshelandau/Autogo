<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref, nextTick, onMounted, onBeforeUnmount, watch, computed } from 'vue';
import axios from 'axios';

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
        only: ['messages', 'smsUnreadCount'],
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

const attachments = ref([]); // Array<File>
const fileInput = ref(null);
const onPickFile = (e) => { for (const f of e.target.files) attachments.value.push(f); e.target.value = ''; };
const removeAttachment = (i) => attachments.value.splice(i, 1);

const send = () => {
    if (!reply.message.trim() && !attachments.value.length) return;
    const fd = new FormData();
    fd.append('to', reply.to);
    fd.append('message', reply.message);
    if (reply.customer_id)  fd.append('customer_id',  reply.customer_id);
    if (reply.subject_type) fd.append('subject_type', reply.subject_type);
    if (reply.subject_id)   fd.append('subject_id',   reply.subject_id);
    attachments.value.forEach(f => fd.append('attachments[]', f));
    reply.processing = true;
    axios.post(route('sms.send'), fd, { headers: { 'Content-Type': 'multipart/form-data' } })
        .then(() => {
            reply.reset('message');
            attachments.value = [];
            router.reload({ only: ['messages'], preserveScroll: true, preserveState: true });
            scrollBottom();
        })
        .catch(e => alert('Send failed: ' + (e.response?.data?.message || e.message)))
        .finally(() => { reply.processing = false; });
};

// ── Templates ────────────────────────────────────────────
const templates = ref([]);
const showTemplates = ref(false);
const loadTemplates = async () => {
    if (templates.value.length) return;
    try { const { data } = await axios.get(route('sms.templates')); templates.value = data.data || []; } catch {}
};
const useTemplate = (tpl) => {
    const name = props.customer?.first_name || '';
    reply.message = (tpl.body || '').replace(/\{first_name\}/g, name).replace(/\{last_name\}/g, props.customer?.last_name || '');
    showTemplates.value = false;
};

// ── Emojis ────────────────────────────────────────────────
const showEmojis = ref(false);
const EMOJIS = ['👍','👌','🙏','✅','❌','🎉','📞','📱','💬','📅','💵','💳','🚗','🔑','🔧','🚛','🔥','⏰','📍','📄','✨','😀','😊','🤝','👀','💡','⚠️','🆘'];
const insertEmoji = (e) => { reply.message += e; showEmojis.value = false; };

// ── Voice recording ──────────────────────────────────────
const recording = ref(false);
let mediaRecorder = null, voiceChunks = [];
const toggleVoice = async () => {
    if (recording.value) {
        mediaRecorder?.stop();
        return;
    }
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(stream, { mimeType: 'audio/webm' });
        voiceChunks = [];
        mediaRecorder.ondataavailable = (e) => voiceChunks.push(e.data);
        mediaRecorder.onstop = () => {
            const blob = new Blob(voiceChunks, { type: 'audio/webm' });
            const file = new File([blob], `voice-${Date.now()}.webm`, { type: 'audio/webm' });
            attachments.value.push(file);
            stream.getTracks().forEach(t => t.stop());
            recording.value = false;
        };
        mediaRecorder.start();
        recording.value = true;
    } catch (e) {
        alert('Microphone access denied: ' + e.message);
    }
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
// "Mark unread" applies to the most recent inbound message that is currently read.
// Useful for flagging a thread for whoever's assigned to come back to it.
const lastReadInbound = computed(() => {
    const inbound = (props.messages || []).filter(m => m.direction === 'inbound');
    const last = inbound[inbound.length - 1];
    return last && last.status !== 'received' ? last : null;
});

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
                    <button v-if="lastReadInbound" @click="setStatus(lastReadInbound.id, 'received')"
                        class="text-xs text-orange-600 hover:text-orange-800 underline">Mark unread</button>
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
                                        <span v-if="m.direction === 'outbound' && m.sender_label" class="text-gray-600">· {{ m.sender_label }}</span>
                                        <span v-if="m.direction === 'outbound'">· {{ m.status }}</span>
                                        <span v-if="m.direction === 'inbound' && m.status === 'received'"
                                            class="text-orange-500 font-semibold">· unread</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Reply box -->
                    <form @submit.prevent="send" class="border-t bg-white p-3 space-y-2">
                        <!-- Attachment chips -->
                        <div v-if="attachments.length" class="flex flex-wrap gap-2">
                            <span v-for="(a, i) in attachments" :key="i"
                                class="inline-flex items-center gap-1 px-2 py-1 text-xs bg-indigo-50 border border-indigo-200 rounded-full">
                                📎 {{ a.name }} ({{ Math.round(a.size/1024) }}KB)
                                <button type="button" @click="removeAttachment(i)" class="text-indigo-600 hover:text-indigo-900 ml-1">×</button>
                            </span>
                        </div>
                        <div class="flex items-end gap-2">
                            <textarea v-model="reply.message" rows="2" placeholder="Type a message…"
                                class="flex-1 border-gray-300 rounded-lg text-sm resize-none focus:border-indigo-500 focus:ring-indigo-500"
                                @keydown.enter.exact.prevent="send" />
                            <button type="submit"
                                :disabled="reply.processing || (!reply.message.trim() && !attachments.length)"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50">
                                {{ reply.processing ? 'Sending…' : 'Send' }}
                            </button>
                        </div>
                        <!-- Toolbar -->
                        <div class="flex items-center gap-1 relative">
                            <input ref="fileInput" type="file" multiple class="hidden" @change="onPickFile"
                                   accept="image/*,application/pdf,audio/*" />
                            <button type="button" @click="fileInput?.click()" title="Attach file" class="p-1.5 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded">📎</button>
                            <button type="button" @click="toggleVoice" :title="recording ? 'Stop recording' : 'Record voice'"
                                    class="p-1.5 hover:bg-gray-100 rounded"
                                    :class="recording ? 'text-red-600 animate-pulse' : 'text-gray-500 hover:text-gray-900'">🎙</button>
                            <button type="button" @click="showEmojis = !showEmojis" title="Emojis" class="p-1.5 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded">😊</button>
                            <button type="button" @click="loadTemplates(); showTemplates = !showTemplates" title="Templates" class="p-1.5 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded">📋</button>
                            <span class="ml-auto text-[11px] text-gray-400">{{ reply.message.length }}/1600</span>

                            <!-- Emoji popover -->
                            <div v-if="showEmojis" class="absolute bottom-full mb-2 left-12 bg-white border rounded-lg shadow-lg p-2 z-30 grid grid-cols-7 gap-1">
                                <button v-for="e in EMOJIS" :key="e" type="button" @click="insertEmoji(e)"
                                        class="text-xl hover:bg-gray-100 rounded p-1">{{ e }}</button>
                            </div>
                            <!-- Templates popover -->
                            <div v-if="showTemplates" class="absolute bottom-full mb-2 left-24 bg-white border rounded-lg shadow-lg w-80 max-h-72 overflow-y-auto z-30">
                                <div class="p-2 border-b text-xs font-semibold text-gray-700">Saved templates</div>
                                <button v-for="t in templates" :key="t.id" type="button" @click="useTemplate(t)"
                                        class="block w-full text-left px-3 py-2 hover:bg-indigo-50 border-b last:border-b-0">
                                    <div class="text-sm font-medium">{{ t.label }}</div>
                                    <div class="text-[11px] text-gray-500 truncate">{{ t.body }}</div>
                                </button>
                                <div v-if="!templates.length" class="p-3 text-xs text-gray-400 text-center">No templates yet</div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
