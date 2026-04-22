<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    customerId: { type: [Number, String], required: true },
    // Which Sola/Cardknox merchant by default (rentals -> high_rental, leasing/sales -> autogo)
    defaultAccount: { type: String, default: 'high_rental' },
});

const cards = ref([]);
const loading = ref(false);
const showAdd = ref(false);
const scanErr = ref('');
const scanBusy = ref('');

const loadCards = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(route('customers.cards.index', props.customerId));
        cards.value = data.data || [];
    } finally { loading.value = false; }
};
onMounted(loadCards);

// ── Manual entry form ─────────────────────────────────
const form = useForm({
    account: props.defaultAccount,
    number: '', exp: '', cvv: '', zip: '',
    cardholder: '', label: '', set_default: false,
});
const submit = () => form.post(route('customers.cards.store', props.customerId), {
    preserveScroll: true,
    onSuccess: () => { form.reset(); showAdd.value = false; loadCards(); },
});

const remove = (id) => {
    if (!confirm('Delete this card?')) return;
    router.delete(route('customers.cards.destroy', [props.customerId, id]), {
        preserveScroll: true, onSuccess: loadCards,
    });
};
const makeDefault = (id) => router.post(route('customers.cards.default', [props.customerId, id]), {}, {
    preserveScroll: true, onSuccess: loadCards,
});

// ── Scanner (Credit card / License / Insurance) ────────
const scanInput = ref(null);
const scanExpect = ref('credit_card');
const triggerScan = (expect) => { scanExpect.value = expect; scanErr.value = ''; scanInput.value?.click(); };

const onScanFile = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    scanBusy.value = scanExpect.value;
    scanErr.value = '';
    try {
        const fd = new FormData();
        fd.append('file', file);
        fd.append('expect', scanExpect.value);
        const { data } = await axios.post(route('scan.any-extract'), fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        if (!data.ok) { scanErr.value = data.error || 'Scan failed'; return; }
        const f = data.fields || {};

        if (data.type === 'credit_card' || scanExpect.value === 'credit_card') {
            // Pre-fill the card form so the user can confirm + tokenize
            showAdd.value = true;
            if (f.card_number) form.number = f.card_number;
            if (f.card_exp)    form.exp    = f.card_exp;
            if (f.cardholder)  form.cardholder = f.cardholder;
        } else if (data.type === 'drivers_license' || scanExpect.value === 'license') {
            await axios.put(route('customers.update', props.customerId), {
                first_name: f.first_name, last_name: f.last_name,
                address: f.address, city: f.city, state: f.state, zip: f.zip,
                drivers_license_number: f.drivers_license_number, dl_state: f.dl_state,
                dl_expiration: f.dl_expiration, date_of_birth: f.date_of_birth,
            });
            alert('License fields written to customer profile.');
        } else if (data.type === 'insurance_card' || scanExpect.value === 'insurance') {
            await axios.put(route('customers.update', props.customerId), {
                insurance_company: f.insurance_company,
                insurance_policy:  f.insurance_policy,
            });
            alert('Insurance fields written to customer profile.');
        }
    } catch (err) {
        scanErr.value = err?.response?.data?.error || err?.message || 'Scan failed';
    } finally {
        scanBusy.value = '';
        if (scanInput.value) scanInput.value.value = '';
    }
};
</script>

<template>
    <div class="bg-white border rounded-xl p-4 space-y-3">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-sm text-gray-900">💳 Cards on file</h3>
                <p class="text-[11px] text-gray-500">Tokenized via Cardknox. Full card # never stored.</p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <a :href="route('customers.scan', customerId)" target="_blank"
                   class="text-xs px-3 py-1.5 bg-purple-600 text-white rounded hover:bg-purple-700 font-semibold">
                    🖨 Scan with Plustek (auto-classify)
                </a>
                <button type="button" @click="triggerScan('credit_card')" :disabled="scanBusy==='credit_card'"
                        class="text-xs px-3 py-1.5 bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50">
                    {{ scanBusy==='credit_card' ? 'Scanning…' : '📷 Phone CC' }}
                </button>
                <button type="button" @click="showAdd = !showAdd"
                        class="text-xs px-3 py-1.5 bg-gray-900 text-white rounded hover:bg-black">
                    {{ showAdd ? 'Cancel' : '+ Add manually' }}
                </button>
                <input ref="scanInput" type="file" accept="image/*" capture="environment" class="hidden" @change="onScanFile" />
            </div>
        </div>

        <p v-if="scanErr" class="text-xs text-red-600 bg-red-50 border border-red-200 rounded p-2">{{ scanErr }}</p>

        <!-- Existing cards -->
        <div v-if="loading" class="text-xs text-gray-400">Loading…</div>
        <ul v-else-if="cards.length" class="divide-y text-sm">
            <li v-for="c in cards" :key="c.id" class="py-2 flex items-center justify-between gap-3">
                <div>
                    <span class="font-mono">{{ c.display }}</span>
                    <span v-if="c.label" class="ml-2 text-[11px] px-1.5 py-0.5 bg-gray-100 rounded">{{ c.label }}</span>
                    <span class="ml-2 text-[11px] text-gray-400">· {{ c.account === 'high_rental' ? 'High Rental' : 'AutoGo' }}</span>
                    <span v-if="c.is_default" class="ml-2 text-[11px] px-1.5 py-0.5 bg-emerald-100 text-emerald-800 rounded">Default</span>
                </div>
                <div class="flex gap-2 text-xs">
                    <button v-if="!c.is_default" @click="makeDefault(c.id)" class="text-indigo-600 hover:text-indigo-800">Set default</button>
                    <button @click="remove(c.id)" class="text-red-600 hover:text-red-800">Remove</button>
                </div>
            </li>
        </ul>
        <p v-else class="text-xs text-gray-400">No cards on file yet.</p>

        <!-- Manual add form -->
        <form v-if="showAdd" @submit.prevent="submit" class="mt-3 p-3 border-2 border-indigo-100 rounded-lg bg-indigo-50/50 space-y-3">
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="col-span-2">
                    <label class="block text-xs font-semibold">Merchant account *</label>
                    <select v-model="form.account" class="w-full border-gray-300 rounded-md text-sm">
                        <option value="high_rental">High Car Rental</option>
                        <option value="autogo">AutoGo (leasing / sales)</option>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-semibold">Card number *</label>
                    <input v-model="form.number" inputmode="numeric" autocomplete="off"
                           class="w-full border-gray-300 rounded-md text-sm font-mono" placeholder="•••• •••• •••• ••••" required />
                </div>
                <div><label class="block text-xs font-semibold">Exp (MM/YY) *</label>
                    <input v-model="form.exp" placeholder="MM/YY" maxlength="5" class="w-full border-gray-300 rounded-md text-sm font-mono" required />
                </div>
                <div><label class="block text-xs font-semibold">CVV *</label>
                    <input v-model="form.cvv" type="password" inputmode="numeric" autocomplete="off"
                           class="w-full border-gray-300 rounded-md text-sm font-mono" required maxlength="4" />
                </div>
                <div><label class="block text-xs font-semibold">ZIP</label>
                    <input v-model="form.zip" class="w-full border-gray-300 rounded-md text-sm" />
                </div>
                <div><label class="block text-xs font-semibold">Cardholder</label>
                    <input v-model="form.cardholder" class="w-full border-gray-300 rounded-md text-sm" />
                </div>
                <div class="col-span-2"><label class="block text-xs font-semibold">Label (optional)</label>
                    <input v-model="form.label" class="w-full border-gray-300 rounded-md text-sm" placeholder="Personal / Company Amex" />
                </div>
                <label class="col-span-2 text-xs flex items-center gap-2">
                    <input type="checkbox" v-model="form.set_default" /> Make this the default card
                </label>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" @click="showAdd = false" class="px-3 py-1.5 text-sm bg-gray-100 rounded">Cancel</button>
                <button type="submit" :disabled="form.processing" class="px-4 py-1.5 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50">
                    {{ form.processing ? 'Tokenizing…' : '🔐 Tokenize & Save' }}
                </button>
            </div>
            <p v-if="form.errors.number" class="text-xs text-red-600">{{ form.errors.number }}</p>
            <p class="text-[10px] text-gray-500 italic">Full card number is sent to Cardknox over HTTPS, tokenized, and immediately discarded by our server. We only persist the token + last 4.</p>
        </form>
    </div>
</template>
