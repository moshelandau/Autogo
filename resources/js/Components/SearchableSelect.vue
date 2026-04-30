<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';

/**
 * Searchable dropdown that also accepts custom typed values. Used for
 * vehicle make + color on the lease/finance deal form: pick from the
 * common list quickly (typing filters), or just type your own value if
 * the option you need isn't on the list.
 */
const props = defineProps({
    modelValue: { type: String, default: '' },
    options: { type: Array, default: () => [] },           // ['Honda', 'Toyota', …]
    placeholder: { type: String, default: 'Select or type…' },
    inputClass: { type: String, default: 'w-full border-gray-300 rounded-md shadow-sm text-sm' },
    /** When false, only values from `options` are accepted. Default: allow custom. */
    allowCustom: { type: Boolean, default: true },
});
const emit = defineEmits(['update:modelValue']);

const query = ref(props.modelValue || '');
watch(() => props.modelValue, (v) => { query.value = v || ''; });
watch(query, (v) => emit('update:modelValue', v));

const open = ref(false);
const highlight = ref(0);
const root = ref(null);
const inputRef = ref(null);

const filtered = computed(() => {
    const q = (query.value || '').toLowerCase().trim();
    if (!q) return props.options;
    return props.options.filter(o => o.toLowerCase().includes(q));
});

// Show "Use ‹typed text›" only when the typed value is exactly NEW.
const showCustomHint = computed(() => {
    if (!props.allowCustom) return false;
    const q = query.value.trim();
    if (!q) return false;
    return !props.options.some(o => o.toLowerCase() === q.toLowerCase());
});

const pick = (val) => {
    query.value = val;
    open.value = false;
};

const onKey = (e) => {
    if (!open.value) return;
    const max = filtered.value.length - 1;
    if (e.key === 'ArrowDown') { e.preventDefault(); highlight.value = Math.min(highlight.value + 1, max); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); highlight.value = Math.max(highlight.value - 1, 0); }
    else if (e.key === 'Enter') {
        if (filtered.value[highlight.value]) { e.preventDefault(); pick(filtered.value[highlight.value]); }
        else { open.value = false; }
    } else if (e.key === 'Escape') { open.value = false; }
};

const onClickOutside = (e) => { if (root.value && !root.value.contains(e.target)) open.value = false; };
onMounted(() => document.addEventListener('mousedown', onClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onClickOutside));

const clear = () => { query.value = ''; inputRef.value?.focus(); };
</script>

<template>
    <div ref="root" class="relative">
        <div class="relative">
            <input ref="inputRef" v-model="query" type="text"
                   @focus="open = true; highlight = 0"
                   @input="open = true; highlight = 0"
                   @keydown="onKey"
                   :placeholder="placeholder"
                   autocomplete="off"
                   :class="[inputClass, 'pr-14']" />
            <button v-if="query" type="button" tabindex="-1" @click.prevent="clear"
                    class="absolute right-7 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700 text-sm">×</button>
            <button type="button" tabindex="-1" @click.prevent="open = !open"
                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700 text-xs">▾</button>
        </div>

        <div v-if="open" class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-64 overflow-y-auto">
            <ul v-if="filtered.length">
                <li v-for="(o, i) in filtered" :key="o"
                    @mousedown.prevent="pick(o)"
                    @mouseenter="highlight = i"
                    class="px-3 py-1.5 cursor-pointer text-sm border-b last:border-b-0"
                    :class="highlight === i ? 'bg-indigo-50 text-indigo-900' : 'hover:bg-gray-50'">
                    {{ o }}
                </li>
            </ul>
            <div v-else-if="!showCustomHint" class="px-3 py-2 text-xs text-gray-500">No matches.</div>

            <button v-if="showCustomHint" type="button"
                    @mousedown.prevent="open = false"
                    class="w-full text-left px-3 py-2 text-sm border-t bg-indigo-50 text-indigo-700 font-medium hover:bg-indigo-100">
                Use “{{ query }}”
            </button>
        </div>
    </div>
</template>
