<script setup>
import { ref, reactive, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AddNoteModal from './AddNoteModal.vue';

const props = defineProps({
    notes: { type: Array, default: () => [] },         // server-provided
    notableType: { type: String, required: true },     // 'deal' | 'customer'
    notableId: { type: [Number, String], required: true },
});

const showModal = ref(false);
const editingNote = ref(null);

const openCreate = () => { editingNote.value = null; showModal.value = true; };
const openEdit = (n) => { editingNote.value = n; showModal.value = true; };

const refresh = () => router.reload({ only: ['deal', 'customer'] });

const fmt = (ts) => ts ? new Date(ts).toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' }) : '';
const fmtDate = (d) => d ? new Date(d).toLocaleDateString() : '';
const isOverdue = (n) => n.reminder_date && !n.is_resolved && new Date(n.reminder_date) <= new Date();

// ── Per-note expanded thread state ───────────────────────
const expanded = reactive({});       // note_id -> bool
const threads  = reactive({});       // note_id -> { comments, activities, assignees, loaded }
const replyDraft = reactive({});     // note_id -> string

const toggleThread = async (n) => {
    expanded[n.id] = !expanded[n.id];
    if (expanded[n.id] && !threads[n.id]?.loaded) {
        const { data } = await axios.get(route('notes.thread', n.id));
        threads[n.id] = { ...data, loaded: true };
    }
};

const submitReply = async (n) => {
    const body = (replyDraft[n.id] || '').trim();
    if (!body) return;
    const { data } = await axios.post(route('notes.comments.store', n.id), { body });
    if (!threads[n.id]) threads[n.id] = { comments: [], activities: [], assignees: [], loaded: true };
    threads[n.id].comments.push({
        id: data.id, body: data.body, user_name: data.user_name,
        user_initials: data.user_initials, created_at: data.created_at,
    });
    replyDraft[n.id] = '';
};

const resolve = (n) => router.post(route('notes.resolve', n.id), {}, { preserveScroll: true });
const reopen  = (n) => router.post(route('notes.reopen', n.id),  {}, { preserveScroll: true });
const remove  = (n) => {
    if (!confirm('Delete this note? This cannot be undone.')) return;
    router.delete(route('notes.destroy', n.id), { preserveScroll: true });
};
const snooze = (n, dateStr) => {
    if (!dateStr) return;
    router.post(route('notes.reminder', n.id), { reminder_date: dateStr }, { preserveScroll: true });
};

// Render @mentions inside the body as soft chips.
const renderBody = (body) => {
    if (!body) return '';
    return String(body)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/@([\w][\w\-\.\']*(?:\s[\w][\w\-\.\']*)?)/g,
                 '<span class="bg-indigo-100 text-indigo-800 px-1 rounded">@$1</span>');
};
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <h4 class="text-sm font-semibold text-gray-700">Notes ({{ notes.length }})</h4>
            <button type="button" @click="openCreate"
                    class="px-3 py-1.5 bg-indigo-600 text-white rounded-md text-xs font-semibold hover:bg-indigo-700">
                + Add note
            </button>
        </div>

        <div v-if="!notes.length" class="text-sm text-gray-500 text-center py-6 border border-dashed rounded-lg">
            No notes yet. Add the first one to start a thread, mention coworkers, or set a reminder.
        </div>

        <div v-for="n in notes" :key="n.id" :id="`note-${n.id}`"
             class="border rounded-lg p-3"
             :class="[
                 n.is_resolved ? 'bg-gray-50 opacity-70' : 'bg-white',
                 isOverdue(n) ? 'border-amber-300 bg-amber-50/50' : 'border-gray-200',
             ]">
            <div class="flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 text-xs text-gray-500 mb-1 flex-wrap">
                        <span class="w-6 h-6 bg-indigo-100 text-indigo-700 rounded-full flex items-center justify-center text-[10px] font-semibold">
                            {{ (n.user?.name || '?').split(/\s+/).map(p => p[0]).slice(0, 2).join('').toUpperCase() }}
                        </span>
                        <span class="font-medium text-gray-700">{{ n.user?.name || 'System' }}</span>
                        <span>•</span>
                        <span>{{ fmt(n.created_at) }}</span>
                        <span v-if="n.reminder_date" class="ml-2 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold"
                              :class="isOverdue(n) ? 'bg-amber-200 text-amber-900' : 'bg-blue-100 text-blue-800'">
                            ⏰ {{ fmtDate(n.reminder_date) }}{{ isOverdue(n) ? ' (overdue)' : '' }}
                        </span>
                        <span v-if="n.is_resolved" class="ml-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-800">
                            ✓ Done
                        </span>
                    </div>
                    <p v-if="n.subject" class="text-sm font-semibold text-gray-900">{{ n.subject }}</p>
                    <p class="text-sm text-gray-800 whitespace-pre-wrap" v-html="renderBody(n.body)"></p>

                    <div v-if="n.assigned_users?.length" class="flex flex-wrap gap-1 mt-2">
                        <span v-for="u in n.assigned_users" :key="u.id"
                              class="inline-flex items-center gap-1 bg-purple-100 text-purple-800 text-[10px] font-medium px-2 py-0.5 rounded-full">
                            {{ u.name }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-col gap-1 shrink-0">
                    <button type="button" @click="toggleThread(n)"
                            class="text-[11px] text-indigo-600 hover:text-indigo-800">
                        {{ expanded[n.id] ? 'Hide' : 'Reply' }}
                    </button>
                    <button v-if="!n.is_resolved && n.reminder_date" type="button" @click="resolve(n)"
                            class="text-[11px] text-emerald-700 hover:text-emerald-900">Mark done</button>
                    <button v-else-if="n.is_resolved" type="button" @click="reopen(n)"
                            class="text-[11px] text-gray-600 hover:text-gray-800">Reopen</button>
                    <button type="button" @click="openEdit(n)" class="text-[11px] text-gray-500 hover:text-gray-700">Edit</button>
                    <button type="button" @click="remove(n)" class="text-[11px] text-red-500 hover:text-red-700">Delete</button>
                </div>
            </div>

            <!-- snooze: change reminder date inline -->
            <div v-if="!n.is_resolved && n.reminder_date && expanded[n.id]" class="mt-2 flex items-center gap-2">
                <label class="text-[11px] text-gray-500">Snooze to:</label>
                <input type="date" :value="String(n.reminder_date).slice(0, 10)"
                       @change="snooze(n, $event.target.value)"
                       class="border-gray-300 rounded text-[11px] py-0.5" />
            </div>

            <!-- expanded thread -->
            <div v-if="expanded[n.id]" class="mt-3 border-t pt-3 space-y-2">
                <div v-for="c in threads[n.id]?.comments || []" :key="c.id" class="flex gap-2">
                    <span class="w-6 h-6 bg-gray-200 text-gray-700 rounded-full flex items-center justify-center text-[10px] font-semibold shrink-0">
                        {{ c.user_initials }}
                    </span>
                    <div class="flex-1">
                        <div class="text-[11px] text-gray-500"><span class="font-medium text-gray-700">{{ c.user_name }}</span> · {{ fmt(c.created_at) }}</div>
                        <p class="text-sm text-gray-800 whitespace-pre-wrap" v-html="renderBody(c.body)"></p>
                    </div>
                </div>

                <div v-for="a in threads[n.id]?.activities || []" :key="`a-${a.id}`"
                     class="text-[11px] text-gray-400 italic pl-8">
                    · {{ a.user_name || 'system' }} {{ a.action }}{{ a.detail ? ': ' + a.detail : '' }} ({{ fmt(a.created_at) }})
                </div>

                <form @submit.prevent="submitReply(n)" class="flex gap-2 pt-1">
                    <input v-model="replyDraft[n.id]" type="text" placeholder="Write a reply…"
                           class="flex-1 border-gray-300 rounded-md text-sm" />
                    <button type="submit" class="px-3 py-1 bg-indigo-600 text-white rounded-md text-xs hover:bg-indigo-700">Reply</button>
                </form>
            </div>
        </div>

        <AddNoteModal :show="showModal" :notable-type="notableType" :notable-id="notableId"
                      :editing-note="editingNote" @close="showModal = false" @saved="refresh" />
    </div>
</template>
