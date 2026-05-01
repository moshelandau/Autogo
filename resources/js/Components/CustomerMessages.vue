<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed, nextTick, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    customer:    { type: Object, required: true },
    subjectType: { type: String, default: 'App\\Models\\Customer' },
    subjectId:   { type: [Number, String], default: null },
    pollMs:      { type: Number, default: 3000 },
});

const sid = computed(() => props.subjectId ?? props.customer?.id);

const messages = ref([]);
const messagesLoading = ref(false);
const msgScroll = ref(null);
const smsStaff = ref([]);
const smsAssignedTo = ref(null);
const smsPhone = ref(props.customer?.phone || '');
const smsResolved = ref(null);
const replyForm = useForm({
    to: props.customer?.phone || '',
    message: '',
    customer_id: props.customer?.id,
    subject_type: props.subjectType,
    subject_id: sid.value,
});

const _ext   = (att) => (att?.url || att?.name || '').toLowerCase().split('?')[0].split('.').pop();
const isImage = (att) => (att?.mime || '').startsWith('image/') || ['jpg','jpeg','png','gif','webp','bmp','heic'].includes(_ext(att));
const isAudio = (att) => (att?.mime || '').startsWith('audio/') || ['m4a','mp3','wav','webm','3gp','3gpp','amr','ogg','opus','aac','aiff'].includes(_ext(att));
const isVideo = (att) => (att?.mime || '').startsWith('video/') || ['mp4','mov','m4v','mkv','avi'].includes(_ext(att));

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
    playTone(988, 0);
    playTone(1319, 0.18);
    if ('Notification' in window && Notification.permission === 'granted' && document.hidden) {
        new Notification('New SMS from ' + (props.customer?.first_name || 'customer'));
    }
};

const loadMessages = async (isPoll = false) => {
    if (!props.customer?.phone) return;
    if (messages.value.length === 0) messagesLoading.value = true;
    try {
        const url = route('sms.customer-thread', props.customer.id) + (isPoll ? '?poll=1' : '');
        const res = await fetch(url, { credentials: 'same-origin' });
        const data = await res.json();
        const next = data.messages || [];
        const newestInbound = Math.max(0, ...next.filter(m => m.direction === 'inbound').map(m => m.id));
        if (lastInboundId && newestInbound > lastInboundId) beep();
        lastInboundId = newestInbound;
        messages.value = next;
        smsAssignedTo.value = data.assignedTo ?? null;
        smsStaff.value = data.staff || [];
        smsPhone.value = data.phone || props.customer.phone;
        smsResolved.value = data.resolved || null;
        // First-load only: snapshot which messages WERE unread before the
        // server flipped them to read. Polls return an empty list, so we
        // never accidentally clear the pinned divider.
        if (!isPoll && Array.isArray(data.pre_open_unread_ids) && data.pre_open_unread_ids.length) {
            frozenFirstUnreadId.value = Math.min(...data.pre_open_unread_ids);
        }
        nextTick(() => {
            // If the user arrived via a kanban deep-link with #unread,
            // scroll the divider into view instead of jumping to the
            // bottom — so they immediately see where the unread starts.
            if (window.location.hash === '#unread' && firstUnreadId.value) {
                const el = document.getElementById('unread-divider');
                if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); return; }
            }
            if (msgScroll.value) msgScroll.value.scrollTop = msgScroll.value.scrollHeight;
        });
    } catch (e) { console.error(e); }
    messagesLoading.value = false;
};

const lastReadInboundMsg = computed(() => {
    const inbound = messages.value.filter(m => m.direction === 'inbound');
    const last = inbound[inbound.length - 1];
    return last && last.status !== 'received' ? last : null;
});

// First unread inbound message — the divider in the thread renders right
// above this one. Used by the kanban deep-link (?tab=messages#unread)
// to scroll a flagged conversation directly to where the unread starts.
//
// The server's customerThread auto-marks received -> read on first open,
// so by the time the response renders no message has status='received'.
// We pin the divider to the smallest id from the server's
// pre_open_unread_ids list captured BEFORE the read-marking.
const frozenFirstUnreadId = ref(null);
const firstUnreadId = computed(() => frozenFirstUnreadId.value);

const assignConversation = () => {
    if (!smsPhone.value) return;
    router.post(route('sms.assign', smsPhone.value), { user_id: smsAssignedTo.value || null }, {
        preserveScroll: true,
        onSuccess: () => loadMessages(true),
    });
};
const markLastUnread = () => {
    if (!lastReadInboundMsg.value) return;
    router.post(route('sms.mark-status', lastReadInboundMsg.value.id), { status: 'received' }, {
        preserveScroll: true,
    });
};
const resolveMsgThread = () => {
    if (!props.customer?.phone) return;
    if (!confirm('Mark this conversation resolved?')) return;
    router.post(route('sms.resolve', props.customer.phone), {}, { preserveScroll: true, onSuccess: () => loadMessages(true) });
};
const unresolveMsgThread = () => {
    if (!props.customer?.phone) return;
    router.post(route('sms.unresolve', props.customer.phone), {}, { preserveScroll: true, onSuccess: () => loadMessages(true) });
};

// Trigger the bot-driven application flow (sends intro + first question
// via SMS so the customer can fill out the application piece by piece).
const FLOW_OPTIONS = [
    { value: 'lease',    label: 'Lease application' },
    { value: 'finance',  label: 'Finance application' },
    { value: 'rental',   label: 'Rental booking' },
    { value: 'towing',   label: 'Towing intake' },
    { value: 'bodyshop', label: 'Bodyshop intake' },
];
const appPickerOpen = ref(false);
const appBusyFlow = ref(null);
const startApplication = (flow) => {
    if (!props.customer?.phone) return;
    if (!confirm(`Send the ${flow} application intro to ${props.customer.phone}?`)) return;
    appBusyFlow.value = flow;
    router.post(route('customers.text-application', props.customer.id), {
        flow,
        phone: props.customer.phone,
    }, {
        preserveScroll: true,
        onFinish: () => { appBusyFlow.value = null; appPickerOpen.value = false; loadMessages(true); },
    });
};

// Reply: attachments / voice / emoji / templates
const msgAttachments = ref([]);
const msgFileInput   = ref(null);
const msgRecording   = ref(false);
let msgRecorder = null, msgVoiceChunks = [];
const msgShowEmojis    = ref(false);
const msgShowTemplates = ref(false);
const msgTemplates     = ref([]);
const MSG_EMOJIS = ['👍','👌','🙏','✅','❌','🎉','📞','📱','💬','📅','💵','💳','🚗','🔑','🔧','🚛','🔥','⏰','📍','📄','✨','😀','😊','🤝','👀','💡','⚠️','🆘'];

const onPickMsgFile = (e) => { for (const f of e.target.files) msgAttachments.value.push(f); e.target.value = ''; };
const removeMsgAttachment = (i) => msgAttachments.value.splice(i, 1);
const insertMsgEmoji = (e) => { replyForm.message += e; msgShowEmojis.value = false; };
const loadMsgTemplates = async () => {
    if (msgTemplates.value.length) return;
    try { const { data } = await axios.get(route('sms.templates')); msgTemplates.value = data.data || []; } catch {}
};
const useMsgTemplate = (tpl) => {
    const fn = props.customer?.first_name || '';
    replyForm.message = (tpl.body || '').replace(/\{first_name\}/g, fn).replace(/\{last_name\}/g, props.customer?.last_name || '');
    msgShowTemplates.value = false;
};
const toggleMsgVoice = async () => {
    if (msgRecording.value) { msgRecorder?.stop(); return; }
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        msgRecorder = new MediaRecorder(stream, { mimeType: 'audio/webm' });
        msgVoiceChunks = [];
        msgRecorder.ondataavailable = (e) => msgVoiceChunks.push(e.data);
        msgRecorder.onstop = () => {
            const blob = new Blob(msgVoiceChunks, { type: 'audio/webm' });
            const file = new File([blob], `voice-${Date.now()}.webm`, { type: 'audio/webm' });
            msgAttachments.value.push(file);
            stream.getTracks().forEach(t => t.stop());
            msgRecording.value = false;
        };
        msgRecorder.start();
        msgRecording.value = true;
    } catch (e) { alert('Microphone access denied: ' + e.message); }
};

const sendReply = () => {
    if (!replyForm.message.trim() && !msgAttachments.value.length) return;
    const fd = new FormData();
    fd.append('to', replyForm.to);
    fd.append('message', replyForm.message);
    if (replyForm.customer_id)  fd.append('customer_id',  replyForm.customer_id);
    if (replyForm.subject_type) fd.append('subject_type', replyForm.subject_type);
    if (replyForm.subject_id)   fd.append('subject_id',   replyForm.subject_id);
    msgAttachments.value.forEach(f => fd.append('attachments[]', f));
    replyForm.processing = true;
    axios.post(route('sms.send'), fd, { headers: { 'Content-Type': 'multipart/form-data' } })
        .then(() => { replyForm.reset('message'); msgAttachments.value = []; loadMessages(); })
        .catch(e => alert('Send failed: ' + (e.response?.data?.message || e.message)))
        .finally(() => { replyForm.processing = false; });
};

let msgPollTimer = null;
onMounted(() => {
    if (!props.customer?.phone) return;
    loadMessages();
    msgPollTimer = setInterval(() => loadMessages(true), props.pollMs);
});
onBeforeUnmount(() => { if (msgPollTimer) clearInterval(msgPollTimer); });
</script>

<template>
    <div v-if="!customer?.phone" class="text-center text-gray-400 py-8">
        No phone number on file — add one to send SMS.
    </div>
    <div v-else>
        <div class="flex items-center justify-between mb-3 gap-3 flex-wrap">
            <h3 class="text-sm font-semibold text-gray-700">SMS thread with {{ customer.phone }}</h3>
            <div class="flex items-center gap-2 ml-auto">
                <label class="text-xs text-gray-500">Assigned to:</label>
                <select v-model="smsAssignedTo" @change="assignConversation"
                    class="text-xs border-gray-300 rounded-md py-1 px-2 focus:border-indigo-500 focus:ring-indigo-500">
                    <option :value="null">— Unassigned —</option>
                    <option v-for="u in smsStaff" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
                <button v-if="!smsResolved" @click="resolveMsgThread"
                    class="text-xs px-2 py-1 bg-emerald-600 text-white rounded hover:bg-emerald-700">✓ Resolve</button>
                <button v-else @click="unresolveMsgThread"
                    class="text-xs px-2 py-1 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">↩ Reopen</button>
                <button v-if="lastReadInboundMsg" @click="markLastUnread"
                    class="text-xs text-orange-600 hover:text-orange-800 underline">Mark unread</button>
                <div class="relative">
                    <button type="button" @click="appPickerOpen = !appPickerOpen"
                        class="text-xs px-2 py-1 bg-purple-600 text-white rounded hover:bg-purple-700">
                        📱 Start application ▾
                    </button>
                    <div v-if="appPickerOpen" class="absolute right-0 top-full mt-1 bg-white border rounded-lg shadow-lg w-56 z-20">
                        <div class="px-3 py-2 text-[11px] text-gray-500 border-b">
                            Sends the bot intro to {{ customer.phone }}. Customer answers piece by piece.
                        </div>
                        <button v-for="f in FLOW_OPTIONS" :key="f.value" type="button"
                            @click="startApplication(f.value)"
                            :disabled="appBusyFlow !== null"
                            class="block w-full text-left px-3 py-2 text-sm hover:bg-purple-50 disabled:opacity-50 border-b last:border-b-0">
                            {{ appBusyFlow === f.value ? 'Sending…' : f.label }}
                        </button>
                    </div>
                </div>
                <Link :href="route('sms.show', customer.phone)" class="text-xs text-indigo-600 hover:text-indigo-800">Open full view →</Link>
            </div>
        </div>
        <div ref="msgScroll" class="border rounded-lg bg-gray-50 overflow-y-auto" style="height: calc(100vh - 420px); min-height: 320px">
            <div class="min-h-full flex flex-col justify-end p-3 space-y-2">
                <div v-if="messagesLoading" class="text-center text-gray-400 py-6 text-sm">Loading...</div>
                <div v-else-if="messages.length === 0" class="text-center text-gray-400 py-6 text-sm">No messages yet.</div>
                <template v-else v-for="m in messages" :key="m.id">
                    <!-- Unread divider — kanban links here with #unread when a deal has unread SMS. -->
                    <div v-if="m.id === firstUnreadId" id="unread-divider"
                         class="flex items-center gap-2 my-1 text-[11px] font-semibold text-red-600 uppercase tracking-wide">
                        <div class="flex-1 h-px bg-red-300"></div>
                        <span>↓ Unread</span>
                        <div class="flex-1 h-px bg-red-300"></div>
                    </div>
                    <div class="flex"
                    :class="m.direction === 'outbound' ? 'justify-end' : 'justify-start'">
                    <div class="max-w-md">
                        <div class="px-3 py-1.5 rounded-2xl text-sm whitespace-pre-wrap break-words"
                            :class="m.direction === 'outbound' ? 'bg-indigo-600 text-white rounded-br-sm' : 'bg-white text-gray-900 border rounded-bl-sm'">
                            <template v-if="m.attachments && m.attachments.media">
                                <div v-for="(att, i) in m.attachments.media" :key="i" class="mb-1 last:mb-0">
                                    <a v-if="isImage(att)" :href="att.url" target="_blank" class="block">
                                        <img :src="att.url" class="rounded-lg max-w-full max-h-60 object-contain" :alt="att.name" />
                                    </a>
                                    <audio v-else-if="isAudio(att)" controls preload="metadata" :src="att.url" class="w-full max-w-xs" />
                                    <video v-else-if="isVideo(att)" controls preload="metadata" :src="att.url" class="rounded-lg max-w-full max-h-60" />
                                    <a v-else :href="att.url" target="_blank" class="underline">📎 {{ att.name }}</a>
                                </div>
                            </template>
                            <span v-if="m.body">{{ m.body }}</span>
                        </div>
                        <div class="text-[11px] text-gray-400 mt-0.5 flex items-center gap-2"
                            :class="m.direction === 'outbound' ? 'justify-end' : 'justify-start'">
                            <span>{{ new Date(m.sent_at || m.created_at).toLocaleString() }}</span>
                            <span v-if="m.direction === 'outbound' && m.sender_label" class="text-gray-500">· {{ m.sender_label }}</span>
                            <span v-if="m.direction === 'outbound'">· {{ m.status }}</span>
                        </div>
                    </div>
                </div>
                </template>
            </div>
        </div>
        <form @submit.prevent="sendReply" class="mt-3 space-y-2">
            <div v-if="msgAttachments.length" class="flex flex-wrap gap-2">
                <span v-for="(a, i) in msgAttachments" :key="i"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs bg-indigo-50 border border-indigo-200 rounded-full">
                    📎 {{ a.name }} ({{ Math.round(a.size/1024) }}KB)
                    <button type="button" @click="removeMsgAttachment(i)" class="text-indigo-600 hover:text-indigo-900 ml-1">×</button>
                </span>
            </div>
            <div class="flex items-end gap-2">
                <textarea v-model="replyForm.message" rows="2" placeholder="Type a message…"
                    class="flex-1 border-gray-300 rounded-md text-sm resize-none focus:border-indigo-500 focus:ring-indigo-500" />
                <button type="submit" :disabled="replyForm.processing || (!replyForm.message.trim() && !msgAttachments.length)"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700 disabled:opacity-50">
                    {{ replyForm.processing ? 'Sending…' : 'Send' }}
                </button>
            </div>
            <div class="flex items-center gap-1 relative">
                <input ref="msgFileInput" type="file" multiple class="hidden" @change="onPickMsgFile"
                       accept="image/*,application/pdf,audio/*" />
                <button type="button" @click="msgFileInput?.click()" title="Attach file" class="p-1.5 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded">📎</button>
                <button type="button" @click="toggleMsgVoice" :title="msgRecording ? 'Stop recording' : 'Record voice'"
                        class="p-1.5 hover:bg-gray-100 rounded"
                        :class="msgRecording ? 'text-red-600 animate-pulse' : 'text-gray-500 hover:text-gray-900'">🎙</button>
                <button type="button" @click="msgShowEmojis = !msgShowEmojis" title="Emojis" class="p-1.5 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded">😊</button>
                <button type="button" @click="loadMsgTemplates(); msgShowTemplates = !msgShowTemplates" title="Templates" class="p-1.5 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded">📋</button>
                <span class="ml-auto text-[11px] text-gray-400">{{ replyForm.message.length }}/1600</span>
                <div v-if="msgShowEmojis" class="absolute bottom-full mb-2 left-12 bg-white border rounded-lg shadow-lg p-2 z-30 grid grid-cols-7 gap-1">
                    <button v-for="e in MSG_EMOJIS" :key="e" type="button" @click="insertMsgEmoji(e)"
                            class="text-xl hover:bg-gray-100 rounded p-1">{{ e }}</button>
                </div>
                <div v-if="msgShowTemplates" class="absolute bottom-full mb-2 left-24 bg-white border rounded-lg shadow-lg w-80 max-h-72 overflow-y-auto z-30">
                    <div class="p-2 border-b text-xs font-semibold text-gray-700">Saved templates</div>
                    <button v-for="t in msgTemplates" :key="t.id" type="button" @click="useMsgTemplate(t)"
                            class="block w-full text-left px-3 py-2 hover:bg-indigo-50 border-b last:border-b-0">
                        <div class="text-sm font-medium">{{ t.label }}</div>
                        <div class="text-[11px] text-gray-500 truncate">{{ t.body }}</div>
                    </button>
                    <div v-if="!msgTemplates.length" class="p-3 text-xs text-gray-400 text-center">No templates yet</div>
                </div>
            </div>
        </form>
    </div>
</template>
