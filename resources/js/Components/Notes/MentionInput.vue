<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue';
import axios from 'axios';

/**
 * Shared @-mention input. Wraps a <textarea> (or single-line <input>) and
 * pops a coworker typeahead when the user types `@`. Used by both the
 * note-creation modal AND the inline reply form, so the autocomplete works
 * consistently in every note context.
 */
const props = defineProps({
    modelValue: { type: String, default: '' },
    rows: { type: Number, default: 3 },
    multiline: { type: Boolean, default: true },
    placeholder: { type: String, default: '' },
    required: { type: Boolean, default: false },
    inputClass: { type: String, default: 'w-full border-gray-300 rounded-lg text-sm' },
    /** Pre-loaded staff list (preferred). If empty, the component fetches it on mount. */
    staff: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:modelValue', 'mention-picked']);

const local = ref(props.modelValue);
watch(() => props.modelValue, (v) => { local.value = v; });
watch(local, (v) => emit('update:modelValue', v));

const inputRef = ref(null);

// ── staff list ──
const internalStaff = ref([]);
const staffList = computed(() => props.staff.length ? props.staff : internalStaff.value);
onMounted(async () => {
    if (!props.staff.length) {
        try {
            const { data } = await axios.get(route('users.search'), { params: { q: '' } });
            internalStaff.value = data.users || [];
        } catch { /* silent */ }
    }
});

// ── typeahead state ──
const open = ref(false);
const query = ref('');
const anchorPos = ref(0);
const highlight = ref(0);

const matches = computed(() => {
    const q = query.value.toLowerCase();
    return staffList.value.filter(u => u.name && u.name.toLowerCase().includes(q)).slice(0, 6);
});

const onInput = (e) => {
    const el = e.target;
    const pos = el.selectionStart;
    const before = local.value.slice(0, pos);
    const m = before.match(/@([\w\s\-\.\']*)$/);
    if (m) {
        anchorPos.value = pos - m[0].length;
        query.value = m[1] || '';
        open.value = true;
        highlight.value = 0;
    } else {
        open.value = false;
    }
};

const insert = (user) => {
    const before = local.value.slice(0, anchorPos.value);
    const after = local.value.slice(inputRef.value.selectionStart);
    local.value = `${before}@${user.name} ${after}`;
    open.value = false;
    emit('mention-picked', user);
    nextTick(() => inputRef.value?.focus());
};

const onKeydown = (e) => {
    if (!open.value) return;
    if (e.key === 'ArrowDown') { e.preventDefault(); highlight.value = Math.min(highlight.value + 1, matches.value.length - 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); highlight.value = Math.max(highlight.value - 1, 0); }
    else if (e.key === 'Enter' || e.key === 'Tab') {
        if (matches.value[highlight.value]) {
            e.preventDefault();
            insert(matches.value[highlight.value]);
        }
    } else if (e.key === 'Escape') { open.value = false; }
};
</script>

<template>
    <div class="relative">
        <textarea v-if="multiline" ref="inputRef" v-model="local" @input="onInput" @keydown="onKeydown"
                  :rows="rows" :placeholder="placeholder" :required="required" :class="inputClass"></textarea>
        <input v-else ref="inputRef" v-model="local" @input="onInput" @keydown="onKeydown"
               :placeholder="placeholder" :required="required" :class="inputClass" type="text" />

        <div v-if="open && matches.length"
             class="absolute left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-30 max-h-60 overflow-y-auto">
            <button v-for="(u, i) in matches" :key="u.id" type="button"
                    @mousedown.prevent="insert(u)"
                    @mouseenter="highlight = i"
                    class="w-full text-left px-3 py-2 flex items-center gap-2 text-sm border-b last:border-b-0"
                    :class="highlight === i ? 'bg-indigo-50' : 'hover:bg-gray-50'">
                <span class="w-7 h-7 bg-indigo-100 text-indigo-700 rounded-full flex items-center justify-center text-xs font-semibold">{{ u.initials }}</span>
                <span class="font-medium text-gray-900">{{ u.name }}</span>
                <span class="ml-auto text-xs text-gray-500">{{ u.email }}</span>
            </button>
        </div>
    </div>
</template>
