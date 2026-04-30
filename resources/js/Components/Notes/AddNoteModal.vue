<script setup>
import { ref, reactive, computed, watch, nextTick } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    show: { type: Boolean, default: false },
    notableType: { type: String, required: true },     // 'deal' | 'customer'
    notableId: { type: [Number, String], required: true },
    editingNote: { type: Object, default: null },      // pass a note to edit; null to create
});
const emit = defineEmits(['close', 'saved']);

const form = useForm({
    notable_type: props.notableType,
    notable_id: props.notableId,
    subject: '',
    body: '',
    reminder_date: '',
    assigned_user_ids: [],
});

watch(() => props.editingNote, (n) => {
    if (n) {
        form.subject = n.subject || '';
        form.body = n.body || '';
        form.reminder_date = n.reminder_date ? String(n.reminder_date).slice(0, 10) : '';
        form.assigned_user_ids = (n.assigned_users || []).map(u => u.id);
    } else {
        form.reset('subject', 'body', 'reminder_date', 'assigned_user_ids');
        form.assigned_user_ids = [];
    }
}, { immediate: true });

// ── Staff list (loaded once on first open) ───────────────────────────
const staff = ref([]);
const loadingStaff = ref(false);
const loadStaff = async () => {
    if (staff.value.length) return;
    loadingStaff.value = true;
    try {
        const { data } = await axios.get(route('users.search'), { params: { q: '' } });
        staff.value = data.users || [];
    } finally { loadingStaff.value = false; }
};
watch(() => props.show, (v) => { if (v) loadStaff(); });

const toggleAssignee = (uid) => {
    const i = form.assigned_user_ids.indexOf(uid);
    if (i === -1) form.assigned_user_ids.push(uid);
    else form.assigned_user_ids.splice(i, 1);
};

// ── @-mention inline typeahead inside the textarea ───────────────────
const bodyRef = ref(null);
const mentionOpen = ref(false);
const mentionQuery = ref('');
const mentionAnchorPos = ref(0);
const mentionHighlight = ref(0);

const mentionMatches = computed(() => {
    const q = mentionQuery.value.toLowerCase();
    return staff.value.filter(u => u.name && u.name.toLowerCase().includes(q)).slice(0, 6);
});

const onBodyInput = (e) => {
    const el = e.target;
    const pos = el.selectionStart;
    const before = form.body.slice(0, pos);
    const m = before.match(/@([\w\s\-\.\']*)$/);
    if (m) {
        mentionAnchorPos.value = pos - m[0].length;
        mentionQuery.value = m[1] || '';
        mentionOpen.value = true;
        mentionHighlight.value = 0;
    } else {
        mentionOpen.value = false;
    }
};

const insertMention = (user) => {
    const before = form.body.slice(0, mentionAnchorPos.value);
    const after = form.body.slice(bodyRef.value.selectionStart);
    form.body = `${before}@${user.name} ${after}`;
    mentionOpen.value = false;
    if (!form.assigned_user_ids.includes(user.id)) form.assigned_user_ids.push(user.id);
    nextTick(() => bodyRef.value?.focus());
};

const onBodyKeydown = (e) => {
    if (!mentionOpen.value) return;
    if (e.key === 'ArrowDown') { e.preventDefault(); mentionHighlight.value = Math.min(mentionHighlight.value + 1, mentionMatches.value.length - 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); mentionHighlight.value = Math.max(mentionHighlight.value - 1, 0); }
    else if (e.key === 'Enter' || e.key === 'Tab') {
        if (mentionMatches.value[mentionHighlight.value]) {
            e.preventDefault();
            insertMention(mentionMatches.value[mentionHighlight.value]);
        }
    } else if (e.key === 'Escape') { mentionOpen.value = false; }
};

const submit = () => {
    if (props.editingNote) {
        form.put(route('notes.update', props.editingNote.id), { preserveScroll: true, onSuccess: () => { emit('saved'); emit('close'); } });
    } else {
        form.post(route('notes.store'), { preserveScroll: true, onSuccess: () => { form.reset('subject', 'body', 'reminder_date', 'assigned_user_ids'); emit('saved'); emit('close'); } });
    }
};

const showAssigneeList = ref(false);
</script>

<template>
    <Teleport to="body">
        <div v-if="show" @click.self="$emit('close')"
             class="fixed inset-0 bg-black/40 flex items-center justify-center z-[120] p-4">
            <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full max-h-[92vh] overflow-y-auto">
                <header class="p-5 border-b flex items-center justify-between">
                    <h3 class="font-bold text-lg">{{ editingNote ? 'Edit note' : 'Add a note' }}</h3>
                    <button @click="$emit('close')" class="text-gray-400 hover:text-gray-700 text-2xl leading-none">×</button>
                </header>

                <form @submit.prevent="submit" class="p-5 space-y-4 text-sm">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Subject (optional)</label>
                        <input v-model="form.subject" type="text" maxlength="120" placeholder="Short summary"
                               class="w-full border-gray-300 rounded-lg text-sm" />
                    </div>

                    <div class="relative">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Note</label>
                        <p class="text-[11px] text-gray-500 mb-1">Type <span class="font-mono bg-gray-100 px-1 rounded">@</span> to mention a coworker — they'll be auto-assigned and notified.</p>
                        <textarea ref="bodyRef" v-model="form.body" @input="onBodyInput" @keydown="onBodyKeydown"
                                  rows="5" required
                                  class="w-full border-gray-300 rounded-lg text-sm font-mono"></textarea>
                        <p v-if="form.errors.body" class="mt-1 text-xs text-red-600">{{ form.errors.body }}</p>

                        <!-- mention dropdown -->
                        <div v-if="mentionOpen && mentionMatches.length"
                             class="absolute left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-20 max-h-60 overflow-y-auto">
                            <button v-for="(u, i) in mentionMatches" :key="u.id" type="button"
                                    @mousedown.prevent="insertMention(u)"
                                    @mouseenter="mentionHighlight = i"
                                    class="w-full text-left px-3 py-2 flex items-center gap-2 text-sm border-b last:border-b-0"
                                    :class="mentionHighlight === i ? 'bg-indigo-50' : 'hover:bg-gray-50'">
                                <span class="w-7 h-7 bg-indigo-100 text-indigo-700 rounded-full flex items-center justify-center text-xs font-semibold">{{ u.initials }}</span>
                                <span class="font-medium text-gray-900">{{ u.name }}</span>
                                <span class="ml-auto text-xs text-gray-500">{{ u.email }}</span>
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">📅 Remind me on (optional)</label>
                            <input v-model="form.reminder_date" type="date" class="w-full border-gray-300 rounded-lg text-sm" />
                            <p class="text-[11px] text-gray-500 mt-1">Bell pings assignees on this date.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Assigned</label>
                            <button type="button" @click="showAssigneeList = !showAssigneeList"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-left bg-white">
                                {{ form.assigned_user_ids.length ? `${form.assigned_user_ids.length} assigned` : 'No one assigned' }}
                                <span class="float-right text-gray-400">{{ showAssigneeList ? '▴' : '▾' }}</span>
                            </button>
                        </div>
                    </div>

                    <div v-if="form.assigned_user_ids.length" class="flex flex-wrap gap-1.5">
                        <span v-for="uid in form.assigned_user_ids" :key="uid"
                              class="inline-flex items-center gap-1 bg-purple-100 text-purple-800 text-xs font-medium px-2 py-0.5 rounded-full">
                            {{ staff.find(s => s.id === uid)?.name || `#${uid}` }}
                            <button type="button" @click="toggleAssignee(uid)" class="hover:text-purple-900">×</button>
                        </span>
                    </div>

                    <div v-if="showAssigneeList" class="border border-gray-200 rounded-lg p-2 max-h-44 overflow-y-auto">
                        <p v-if="loadingStaff" class="text-xs text-gray-500 px-2 py-1">Loading staff…</p>
                        <label v-for="u in staff" :key="u.id"
                               class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" :checked="form.assigned_user_ids.includes(u.id)" @change="toggleAssignee(u.id)" class="rounded" />
                            <span class="w-6 h-6 bg-indigo-100 text-indigo-700 rounded-full flex items-center justify-center text-[10px] font-semibold">{{ u.initials }}</span>
                            <span class="text-sm text-gray-800">{{ u.name }}</span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t">
                        <button type="button" @click="$emit('close')" class="px-4 py-2 text-sm bg-gray-100 rounded-lg">Cancel</button>
                        <button type="submit" :disabled="form.processing || !form.body"
                                class="px-5 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50">
                            {{ form.processing ? 'Saving…' : (editingNote ? 'Save changes' : 'Add note') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
