<script setup>
import { ref, watch, onMounted, onBeforeUnmount, reactive } from 'vue';
import axios from 'axios';

const props = defineProps({
    modelValue: { type: [Number, String, null], default: null },
    initialLabel: { type: String, default: '' },
    placeholder: { type: String, default: 'Search customers by name, phone or email…' },
});
const emit = defineEmits(['update:modelValue', 'select']);

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

watch(query, () => {
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

// ── Inline Create ──
const showCreate = ref(false);
const createError = ref('');
const creating = ref(false);
const newCustomer = reactive({
    first_name: '', last_name: '', phone: '', email: '',
    address: '', city: '', state: 'NY', zip: '',
    drivers_license_number: '', dl_state: 'NY', dl_expiration: '', date_of_birth: '',
});
const openCreate = (prefillFromQuery = true) => {
    // Split the typed query into first + last name if possible
    if (prefillFromQuery && query.value.trim()) {
        const parts = query.value.trim().split(/\s+/);
        newCustomer.first_name = parts[0] || '';
        newCustomer.last_name  = parts.slice(1).join(' ') || '';
    }
    showCreate.value = true;
    open.value = false;
};
const submitCreate = async () => {
    creating.value = true;
    createError.value = '';
    try {
        const { data } = await axios.post(route('customers.quick-store'), newCustomer);
        const customer = data.customer;
        pick({ id: customer.id, label: `${customer.first_name} ${customer.last_name}`, sub: customer.phone || '', outstanding_balance: 0 });
        showCreate.value = false;
        // reset
        Object.keys(newCustomer).forEach(k => newCustomer[k] = ['state','dl_state'].includes(k) ? 'NY' : '');
    } catch (e) {
        createError.value = e?.response?.data?.message || 'Failed to create customer';
    } finally {
        creating.value = false;
    }
};
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
                <!-- Always-visible Create button at bottom of results -->
                <li class="sticky bottom-0 bg-indigo-50 border-t">
                    <button type="button" @mousedown.prevent="openCreate()"
                        class="w-full px-3 py-2 text-left text-sm font-semibold text-indigo-700 hover:bg-indigo-100">
                        ➕ Create new customer {{ query ? `"${query}"` : '' }}
                    </button>
                </li>
            </ul>

            <div v-else class="p-3">
                <div class="text-xs text-gray-500 mb-2">No customers match "{{ query }}"</div>
                <button type="button" @mousedown.prevent="openCreate()"
                    class="w-full px-3 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700">
                    ➕ Create new customer
                </button>
            </div>
        </div>

        <!-- Inline create modal -->
        <Teleport to="body">
            <div v-if="showCreate" @click.self="showCreate = false"
                 class="fixed inset-0 bg-black/40 flex items-center justify-center z-[100] p-4">
                <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
                    <header class="p-5 border-b flex items-center justify-between">
                        <h3 class="font-bold text-lg">➕ Create Customer</h3>
                        <button @click="showCreate = false" class="text-gray-400 hover:text-gray-700 text-2xl leading-none">×</button>
                    </header>
                    <form @submit.prevent="submitCreate" class="p-5 space-y-3 text-sm">
                        <div class="grid grid-cols-2 gap-3">
                            <div><label class="block text-xs font-semibold">First name *</label><input v-model="newCustomer.first_name" required class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
                            <div><label class="block text-xs font-semibold">Last name *</label><input v-model="newCustomer.last_name"  required class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div><label class="block text-xs font-semibold">Phone</label><input v-model="newCustomer.phone" type="tel" class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
                            <div><label class="block text-xs font-semibold">Email</label><input v-model="newCustomer.email" type="email" class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
                        </div>
                        <div><label class="block text-xs font-semibold">Address</label><input v-model="newCustomer.address" class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="col-span-2"><label class="block text-xs">City</label><input v-model="newCustomer.city" class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
                            <div><label class="block text-xs">ST</label><input v-model="newCustomer.state" maxlength="2" class="mt-1 w-full border-gray-300 rounded-lg text-sm uppercase" /></div>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div><label class="block text-xs">DL #</label><input v-model="newCustomer.drivers_license_number" class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
                            <div><label class="block text-xs">DL State</label><input v-model="newCustomer.dl_state" maxlength="2" class="mt-1 w-full border-gray-300 rounded-lg text-sm uppercase" /></div>
                            <div><label class="block text-xs">DL Exp</label><input v-model="newCustomer.dl_expiration" type="date" class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
                        </div>
                        <div v-if="createError" class="text-xs text-red-600 bg-red-50 border border-red-200 rounded p-2">{{ createError }}</div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="showCreate = false" class="px-4 py-2 text-sm bg-gray-100 rounded-lg">Cancel</button>
                            <button type="submit" :disabled="creating" class="px-5 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50">
                                {{ creating ? 'Creating…' : 'Create & Select' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>
