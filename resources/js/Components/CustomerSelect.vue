<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';

const props = defineProps({
    modelValue: { type: [Number, String, null], default: null },
    initialLabel: { type: String, default: '' }, // pass when editing — pre-fills the displayed name
    placeholder: { type: String, default: 'Search customers by name, phone or email…' },
    allowCreate: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue', 'select', 'create-new']);

const query = ref(props.initialLabel || '');
const results = ref([]);
const open = ref(false);
const loading = ref(false);
const highlight = ref(0);
const root = ref(null);
let timer = null;

const fetchResults = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(route('customers.search'), { params: { q: query.value } });
        results.value = data.data || [];
        highlight.value = 0;
    } catch {
        results.value = [];
    } finally {
        loading.value = false;
    }
};

watch(query, (v) => {
    open.value = true;
    clearTimeout(timer);
    timer = setTimeout(fetchResults, 200);
});

const pick = (item) => {
    emit('update:modelValue', item.id);
    emit('select', item);
    query.value = item.label;
    open.value = false;
};

const clear = () => {
    emit('update:modelValue', null);
    query.value = '';
    results.value = [];
    open.value = false;
};

const onKey = (e) => {
    if (!open.value) return;
    if (e.key === 'ArrowDown') { e.preventDefault(); highlight.value = Math.min(highlight.value + 1, results.value.length - 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); highlight.value = Math.max(highlight.value - 1, 0); }
    else if (e.key === 'Enter')   { e.preventDefault(); if (results.value[highlight.value]) pick(results.value[highlight.value]); }
    else if (e.key === 'Escape')  { open.value = false; }
};

const onClickOutside = (e) => { if (root.value && !root.value.contains(e.target)) open.value = false; };

const onFocus = () => { open.value = true; if (!results.value.length) fetchResults(); };

onMounted(() => document.addEventListener('mousedown', onClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onClickOutside));
</script>

<template>
    <div ref="root" class="relative">
        <div class="relative">
            <input
                v-model="query"
                @focus="onFocus"
                @keydown="onKey"
                :placeholder="placeholder"
                type="text"
                autocomplete="off"
                class="w-full border-gray-300 rounded-lg text-sm pl-9 pr-9"
            />
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
            <button v-if="query" type="button" @click="clear"
                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700 text-sm">×</button>
        </div>

        <div v-if="open" class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-80 overflow-y-auto">
            <div v-if="loading" class="px-3 py-3 text-xs text-gray-500">Searching…</div>

            <ul v-else-if="results.length">
                <li v-for="(r, i) in results" :key="r.id"
                    @mousedown.prevent="pick(r)"
                    @mouseenter="highlight = i"
                    class="px-3 py-2 cursor-pointer text-sm border-b last:border-b-0"
                    :class="[
                        highlight === i ? 'bg-indigo-50' : 'hover:bg-gray-50',
                        r.outstanding_balance > 0 ? 'border-l-4 border-l-red-500 bg-red-50/40' : ''
                    ]">
                    <div class="flex items-center justify-between gap-2">
                        <div class="font-medium text-gray-900">{{ r.label }}</div>
                        <span v-if="r.outstanding_balance > 0"
                              class="text-[10px] font-bold text-white bg-red-600 px-2 py-0.5 rounded-full whitespace-nowrap">
                            ⚠ Owes ${{ Number(r.outstanding_balance).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                        </span>
                    </div>
                    <div v-if="r.sub" class="text-xs text-gray-500">{{ r.sub }}</div>
                </li>
            </ul>

            <div v-else class="px-3 py-3 text-xs text-gray-500">
                No customers match "{{ query }}".
                <button v-if="allowCreate" type="button" @mousedown.prevent="emit('create-new', query); open = false"
                    class="ml-2 text-indigo-600 hover:text-indigo-800 font-medium">+ Create new</button>
            </div>
        </div>
    </div>
</template>
