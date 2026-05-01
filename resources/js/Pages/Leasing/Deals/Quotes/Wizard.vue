<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref, reactive, computed, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    deal: Object,
    prefill: Object,
    lenders: { type: Array, default: () => [] },
});

// Mutable state — seeded from prefill
const v        = reactive({ ...props.prefill.vehicle });
const price    = reactive({ ...props.prefill.price });
const trade    = reactive({ ...props.prefill.trade });
const customer = reactive({ ...props.prefill.customer });
const dealer   = reactive({ ...props.prefill.dealer });
const driveOff = reactive({ ...props.prefill.drive_off });
const lease    = reactive({ ...props.prefill.lease, terms: [...(props.prefill.lease.terms || [36])] });
const credit   = reactive({ ...props.prefill.credit });
const paymentType = ref(props.prefill.payment_type);

// Auto-derived: profit = sell - cost
watch([() => price.cost, () => price.sell_price], () => {
    if (price.cost != null && price.sell_price != null) {
        price.profit = Math.max(0, Number(price.sell_price) - Number(price.cost));
    }
});

// VIN decode against MarketCheck
const decodingVin = ref(false);
const decodeVin = async () => {
    if (!v.vin || v.vin.length !== 17) {
        alert('VIN must be exactly 17 characters');
        return;
    }
    decodingVin.value = true;
    try {
        const { data } = await axios.post(route('leasing.deals.quotes.wizard.decode-vin', props.deal.id), { vin: v.vin });
        if (data.error) {
            alert('Decode failed: ' + data.error);
        } else {
            v.year  = data.year || v.year;
            v.make  = data.make || v.make;
            v.model = data.model || v.model;
            v.trim  = data.trim || v.trim;
        }
    } catch (e) {
        alert('Decode failed: ' + e.message);
    } finally {
        decodingVin.value = false;
    }
};

// ZIP → state/county autofill
const zipLooking = ref(false);
const onZipBlur = async () => {
    if (!customer.zip || !/^\d{5}$/.test(customer.zip)) return;
    zipLooking.value = true;
    try {
        const { data } = await axios.post(route('leasing.deals.quotes.wizard.lookup-zip', props.deal.id), { zip: customer.zip });
        if (data.state)  customer.state  = data.state;
        if (data.county) customer.county = data.county;
    } catch (e) { /* ignore */ }
    zipLooking.value = false;
};

// Available Rebates picker — calls MarketCheck
const offers = ref(null);
const offersLoading = ref(false);
const showRebatesPicker = ref(false);
const appliedRebateIds = ref(new Set());

const pullOffers = async () => {
    offersLoading.value = true;
    try {
        // Send wizard form state so the backend uses what's CURRENTLY in
        // the form, not what's saved on the deal. VIN narrows further.
        const payload = {
            vin:   v.vin && v.vin.length === 17 ? v.vin : undefined,
            make:  v.make || undefined,
            model: v.model || undefined,
            year:  v.year || undefined,
            zip:   customer.zip && /^\d{5}$/.test(customer.zip) ? customer.zip : undefined,
        };
        const { data } = await axios.post(route('leasing.deals.quotes.wizard.pull-offers', props.deal.id), payload);
        offers.value = data;
        if (data.ok) {
            showRebatesPicker.value = true;
            // If VIN matched a listing, fill the wizard from it
            if (data.matched_listing) {
                const m = data.matched_listing;
                if (m.year)  v.year  = m.year;
                if (m.make)  v.make  = m.make;
                if (m.model) v.model = m.model;
                if (m.trim)  v.trim  = m.trim;
                if (m.miles) v.odometer = m.miles;
                if (m.msrp)  price.msrp = m.msrp;
                if (m.price) price.sell_price = m.price;
                if (m.inventory_type) v.type = m.inventory_type;
            }
        }
    } catch (e) {
        offers.value = { ok: false, error: e.message };
    }
    offersLoading.value = false;
};

const toggleRebate = (r) => {
    const next = new Set(appliedRebateIds.value);
    next.has(r.id) ? next.delete(r.id) : next.add(r.id);
    appliedRebateIds.value = next;
};
const isRebateApplied = (id) => appliedRebateIds.value.has(id);
const appliedRebates = computed(() => (offers.value?.rebates || []).filter(r => appliedRebateIds.value.has(r.id)));
const rebatesTotal = computed(() => appliedRebates.value.reduce((s, r) => s + (Number(r.cashback) || 0), 0));

// Multi-term selector
const TERM_CHOICES = [12, 18, 24, 27, 30, 33, 36, 39, 42, 48, 60, 72];
const toggleTerm = (t) => {
    const idx = lease.terms.indexOf(t);
    if (idx >= 0) lease.terms.splice(idx, 1);
    else lease.terms.push(t);
    lease.terms.sort((a, b) => a - b);
};
const isTermSelected = (t) => lease.terms.includes(t);

const form = useForm({});
const saveAndContinue = () => {
    form
        .transform(() => ({
            payment_type: paymentType.value,
            vehicle: v,
            price,
            trade,
            customer,
            dealer,
            drive_off: driveOff,
            lease,
            credit,
            applied_rebate_ids: Array.from(appliedRebateIds.value),
            rebates_total: rebatesTotal.value,
        }))
        .post(route('leasing.deals.quotes.wizard.store', props.deal.id));
};

const fmt = (v) => v != null && v !== '' ? '$' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '$0.00';
</script>

<template>
    <AppLayout title="Quote Wizard">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800">Create Quote — Deal #{{ deal.deal_number }}</h2>
                    <p class="text-sm text-gray-500">{{ deal.customer?.first_name }} {{ deal.customer?.last_name }} · {{ v.year }} {{ v.make }} {{ v.model }}</p>
                </div>
                <Link :href="route('leasing.deals.show', deal.id)" class="text-sm text-gray-600 hover:underline">← Back to deal</Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

                <!-- 3-step nav (visual breadcrumb — only step 1 active for now) -->
                <div class="bg-white shadow-sm rounded-lg p-3 flex items-center gap-2 text-sm">
                    <span class="px-3 py-1 bg-indigo-600 text-white rounded font-semibold">1. Quote Structure</span>
                    <span class="text-gray-300">›</span>
                    <span class="px-3 py-1 bg-gray-100 text-gray-400 rounded">2. Lender Selection</span>
                    <span class="text-gray-300">›</span>
                    <span class="px-3 py-1 bg-gray-100 text-gray-400 rounded">3. Worksheet</span>
                </div>

                <!-- Vehicle Selector -->
                <div class="bg-white shadow-sm rounded-lg p-5">
                    <h3 class="font-semibold text-sm text-gray-700 mb-3">Select a vehicle</h3>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                        <div class="md:col-span-4">
                            <label class="block text-xs text-gray-500">VIN</label>
                            <div class="flex gap-1">
                                <input v-model="v.vin" type="text" maxlength="17" placeholder="Decode a VIN"
                                       class="block w-full border-gray-300 rounded-md text-sm uppercase" />
                                <button type="button" @click="decodeVin" :disabled="decodingVin || !v.vin || v.vin.length !== 17"
                                        class="px-3 py-1 bg-gray-700 text-white rounded-md text-sm hover:bg-gray-800 disabled:opacity-50">
                                    {{ decodingVin ? '…' : 'Decode' }}
                                </button>
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs text-gray-500">Type</label>
                            <select v-model="v.type" class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                                <option value="new">New</option>
                                <option value="used">Used</option>
                                <option value="certified">Certified</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs text-gray-500">Year</label>
                            <input v-model.number="v.year" type="number" min="1990" max="2099" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs text-gray-500">Make</label>
                            <input v-model="v.make" type="text" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs text-gray-500">Model</label>
                            <input v-model="v.model" type="text" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs text-gray-500">Trim</label>
                            <input v-model="v.trim" type="text" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs text-gray-500">Odometer</label>
                            <input v-model.number="v.odometer" type="number" min="0" placeholder="0" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                        </div>
                    </div>
                </div>

                <!-- 3-column body -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                    <!-- LEFT: Payment Type + Vehicle Price + Rebates -->
                    <div class="space-y-4">
                        <div class="bg-white shadow-sm rounded-lg p-5">
                            <h3 class="font-semibold text-sm text-gray-700 mb-2">Payment Type</h3>
                            <div class="flex flex-wrap gap-3 text-sm">
                                <label v-for="pt in ['lease','one_pay','finance','balloon','cash']" :key="pt"
                                       class="inline-flex items-center gap-1 cursor-pointer">
                                    <input type="radio" v-model="paymentType" :value="pt" />
                                    <span class="capitalize">{{ pt.replace('_', ' ') }}</span>
                                </label>
                            </div>

                            <h3 class="font-semibold text-sm text-gray-700 mt-5 mb-2">Vehicle Price</h3>
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <label class="block text-xs text-gray-500">Cost</label>
                                    <input v-model.number="price.cost" type="number" step="0.01" min="0" placeholder="$ 0.00" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">MSRP</label>
                                    <input v-model.number="price.msrp" type="number" step="0.01" min="0" placeholder="$ 0.00" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">Profit (auto)</label>
                                    <input v-model.number="price.profit" type="number" step="0.01" placeholder="$ 0.00" class="mt-1 block w-full border-gray-300 rounded-md text-sm bg-gray-50" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">Invoice</label>
                                    <input v-model.number="price.invoice" type="number" step="0.01" min="0" placeholder="$ 0.00" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-xs text-gray-500">Sell Price</label>
                                    <input v-model.number="price.sell_price" type="number" step="0.01" min="0" placeholder="$ 0.00" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                </div>
                            </div>

                            <h3 class="font-semibold text-sm text-gray-700 mt-5 mb-2">Rebates</h3>
                            <div class="flex items-center gap-2 mb-2">
                                <button type="button" @click="pullOffers" :disabled="offersLoading"
                                        class="px-3 py-1.5 bg-gray-700 text-white rounded text-xs hover:bg-gray-800 disabled:opacity-50 whitespace-nowrap">
                                    💰 {{ offersLoading ? 'Pulling…' : (offers ? 'Refresh' : 'Available Rebates') }}
                                </button>
                                <span class="text-[11px] text-gray-500">VIN above narrows to the specific car + dealer</span>
                            </div>

                            <!-- Matched listing card (when VIN found a real car) -->
                            <div v-if="offers?.matched_listing" class="mb-2 p-2 bg-emerald-50 border border-emerald-300 rounded text-xs">
                                <div class="font-semibold text-emerald-800">✓ Matched VIN to a live listing</div>
                                <div class="mt-1 text-gray-700">
                                    {{ offers.matched_listing.year }} {{ offers.matched_listing.make }} {{ offers.matched_listing.model }}
                                    {{ offers.matched_listing.trim }} · {{ offers.matched_listing.exterior_color || '' }}
                                </div>
                                <div class="text-gray-600">
                                    <span v-if="offers.matched_listing.miles">{{ Number(offers.matched_listing.miles).toLocaleString() }}mi · </span>
                                    <span v-if="offers.matched_listing.price">Asking ${{ Number(offers.matched_listing.price).toLocaleString() }} · </span>
                                    <span v-if="offers.matched_listing.msrp">MSRP ${{ Number(offers.matched_listing.msrp).toLocaleString() }}</span>
                                </div>
                                <div class="mt-1 text-gray-700">
                                    🏬 <strong>{{ offers.matched_listing.dealer.name }}</strong>
                                    <span v-if="offers.matched_listing.dealer.city">— {{ offers.matched_listing.dealer.city }}, {{ offers.matched_listing.dealer.state }} {{ offers.matched_listing.dealer.zip }}</span>
                                </div>
                                <div v-if="offers.matched_listing.dealer.phone" class="text-gray-500">{{ offers.matched_listing.dealer.phone }}</div>
                            </div>
                            <div v-else-if="offers?.searched_by_vin && offers?.ok" class="mb-2 p-2 bg-amber-50 border border-amber-200 rounded text-xs text-amber-800">
                                ⚠ VIN didn't match any active listing — fell back to make + customer ZIP.
                            </div>
                            <div v-if="offers && !offers.ok" class="mt-2 text-xs text-red-700 bg-red-50 border border-red-200 rounded p-2">
                                {{ offers.error || 'Failed to pull offers.' }}
                            </div>
                            <div v-if="offers?.ok && offers.rebates.length === 0" class="mt-2 text-xs text-gray-500 italic">
                                No rebates available for {{ offers.make }} in {{ offers.zip }}.
                            </div>
                            <div v-if="showRebatesPicker && offers?.rebates?.length" class="mt-2 max-h-72 overflow-y-auto border rounded">
                                <div v-if="offers.num_dealer_markdowns" class="px-2 py-1 bg-amber-50 border-b text-[11px] text-amber-800">
                                    {{ offers.num_dealer_markdowns }} dealer markdown(s) shown first · {{ offers.num_marketcheck }} OEM rebate(s) below
                                </div>
                                <label v-for="r in offers.rebates" :key="r.id"
                                       class="flex items-start gap-2 p-2 border-b last:border-0 hover:bg-gray-50 cursor-pointer text-xs">
                                    <input type="checkbox" :checked="isRebateApplied(r.id)" @change="toggleRebate(r)" class="mt-0.5" />
                                    <div class="flex-1">
                                        <div class="flex items-baseline gap-2">
                                            <span class="font-bold text-emerald-700">${{ Number(r.cashback).toLocaleString() }}</span>
                                            <span>{{ r.title }}</span>
                                            <span v-if="r.source === 'dealer_markdown'" class="px-1.5 py-0.5 bg-amber-100 text-amber-800 rounded text-[10px] font-semibold">DEALER</span>
                                            <span v-else class="px-1.5 py-0.5 bg-blue-100 text-blue-800 rounded text-[10px] font-semibold">OEM</span>
                                        </div>
                                        <div v-if="r.target_group" class="text-[11px] text-gray-500 italic">{{ r.target_group }}</div>
                                        <div class="text-[10px] text-gray-400">Valid {{ r.valid_from || '—' }} → {{ r.valid_through || '—' }}</div>
                                    </div>
                                </label>
                            </div>
                            <div v-if="appliedRebates.length" class="mt-2 text-xs">
                                <div class="font-semibold text-gray-700 mb-1">Applied: {{ appliedRebates.length }} rebate(s) · Total: <span class="text-emerald-700">{{ fmt(rebatesTotal) }}</span></div>
                                <ul class="space-y-0.5">
                                    <li v-for="r in appliedRebates" :key="r.id" class="text-gray-600 truncate">• {{ r.title }} — ${{ Number(r.cashback).toLocaleString() }}</li>
                                </ul>
                            </div>
                            <div v-else class="mt-2 text-xs text-gray-400 italic">No rebates added</div>
                        </div>
                    </div>

                    <!-- MIDDLE: Trade Information + Customer Location + Drive Off -->
                    <div class="space-y-4">
                        <div class="bg-white shadow-sm rounded-lg p-5">
                            <h3 class="font-semibold text-sm text-gray-700 mb-3">Trade Information</h3>
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <label class="block text-xs text-gray-500">Allowance</label>
                                    <input v-model.number="trade.allowance" type="number" step="0.01" min="0" placeholder="$ 0.00" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                </div>
                                <div>
                                    <div class="flex gap-1 mt-5">
                                        <button type="button" @click="trade.owned_or_leased = 'owned'"
                                                :class="['px-3 py-1 rounded text-xs', trade.owned_or_leased === 'owned' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700']">Owned</button>
                                        <button type="button" @click="trade.owned_or_leased = 'leased'"
                                                :class="['px-3 py-1 rounded text-xs', trade.owned_or_leased === 'leased' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700']">Leased</button>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">ACV</label>
                                    <input v-model.number="trade.acv" type="number" step="0.01" min="0" placeholder="$ 0.00" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">Payoff</label>
                                    <input v-model.number="trade.payoff" type="number" step="0.01" min="0" placeholder="$ 0.00" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                </div>
                            </div>
                        </div>

                        <div class="bg-white shadow-sm rounded-lg p-5">
                            <h3 class="font-semibold text-sm text-gray-700 mb-3">Customer Location</h3>
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <label class="block text-xs text-gray-500">ZIP</label>
                                    <input v-model="customer.zip" @blur="onZipBlur" type="text" maxlength="5" placeholder="10952" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">State</label>
                                    <input v-model="customer.state" type="text" maxlength="2" placeholder="NY" class="mt-1 block w-full border-gray-300 rounded-md text-sm uppercase" />
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-xs text-gray-500">County</label>
                                    <input v-model="customer.county" type="text" placeholder="Auto-filled from ZIP" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                                </div>
                            </div>
                            <p v-if="zipLooking" class="text-[11px] text-indigo-600 italic mt-1">Looking up…</p>
                        </div>

                        <div class="bg-white shadow-sm rounded-lg p-5">
                            <h3 class="font-semibold text-sm text-gray-700 mb-3">Drive Off</h3>
                            <div class="space-y-2 text-sm">
                                <label class="flex items-center gap-2">
                                    <input type="radio" v-model="driveOff.type" value="total_drive_off" />
                                    <span>Total Drive Off:</span>
                                    <input v-model.number="driveOff.amount" :disabled="driveOff.type !== 'total_drive_off'" type="number" step="0.01" min="0" placeholder="$" class="flex-1 border-gray-300 rounded-md text-sm" />
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="radio" v-model="driveOff.type" value="lease_cap_reduction" />
                                    <span>Lease Cap Reduction:</span>
                                    <input v-model.number="driveOff.amount" :disabled="driveOff.type !== 'lease_cap_reduction'" type="number" step="0.01" min="0" placeholder="$" class="flex-1 border-gray-300 rounded-md text-sm" />
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="radio" v-model="driveOff.type" value="sign_and_drive" />
                                    <span>Sign and Drive (zero down)</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT: Lease Deal Structure + Dealer Info + Customer Credit -->
                    <div class="space-y-4">
                        <div class="bg-white shadow-sm rounded-lg p-5">
                            <h3 class="font-semibold text-sm text-gray-700 mb-3">Lease Deal Structure</h3>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Terms (multi-select)</label>
                                <div class="flex flex-wrap gap-1">
                                    <button v-for="t in TERM_CHOICES" :key="t" type="button" @click="toggleTerm(t)"
                                            :class="['px-2 py-1 rounded text-xs border',
                                                isTermSelected(t)
                                                    ? 'bg-indigo-600 text-white border-indigo-600'
                                                    : 'bg-white text-gray-600 border-gray-300 hover:border-gray-400']">
                                        {{ t }}
                                    </button>
                                </div>
                                <p class="text-[11px] text-gray-500 mt-1">One draft quote per selected term will be created.</p>
                            </div>

                            <div class="mt-4">
                                <label class="block text-xs text-gray-500">Mileage / Year</label>
                                <select v-model.number="lease.mileage_per_year" class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                                    <option :value="7500">7,500</option>
                                    <option :value="10000">10,000</option>
                                    <option :value="12000">12,000</option>
                                    <option :value="15000">15,000</option>
                                </select>
                            </div>

                            <div class="mt-4">
                                <label class="block text-xs text-gray-500 mb-1">Acquisition Fee</label>
                                <div class="flex gap-1">
                                    <button type="button" @click="lease.acquisition_fee_type = 'upfront'"
                                            :class="['px-3 py-1 rounded text-xs', lease.acquisition_fee_type === 'upfront' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700']">Upfront</button>
                                    <button type="button" @click="lease.acquisition_fee_type = 'capped'"
                                            :class="['px-3 py-1 rounded text-xs', lease.acquisition_fee_type === 'capped' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700']">Capped</button>
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="block text-xs text-gray-500 mb-1">Lender Loyalty</label>
                                <div class="flex gap-1">
                                    <button type="button" @click="lease.lender_loyalty = true"
                                            :class="['px-3 py-1 rounded text-xs', lease.lender_loyalty ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700']">Yes</button>
                                    <button type="button" @click="lease.lender_loyalty = false"
                                            :class="['px-3 py-1 rounded text-xs', !lease.lender_loyalty ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700']">No</button>
                                </div>
                            </div>

                            <p class="mt-3 text-xs text-indigo-600 italic">Manage Taxes and Fees → coming in Worksheet step</p>
                        </div>

                        <div class="bg-white shadow-sm rounded-lg p-5">
                            <h3 class="font-semibold text-sm text-gray-700 mb-3">Dealer Information</h3>
                            <div>
                                <label class="block text-xs text-gray-500">Dealer ZIP</label>
                                <input v-model="dealer.zip" type="text" maxlength="5" placeholder="10025" class="mt-1 block w-full border-gray-300 rounded-md text-sm" />
                            </div>
                        </div>

                        <div class="bg-white shadow-sm rounded-lg p-5">
                            <h3 class="font-semibold text-sm text-gray-700 mb-3">Customer Credit</h3>
                            <div class="flex items-center gap-2">
                                <label class="block text-xs text-gray-500 w-12">Score</label>
                                <input v-model.number="credit.score" type="number" min="300" max="850" placeholder="722" class="block w-32 border-gray-300 rounded-md text-sm" />
                                <span v-if="credit.score >= 700" class="text-emerald-600 text-sm">✓</span>
                                <span v-else-if="credit.score >= 600" class="text-amber-600 text-sm">⚠</span>
                                <span v-else-if="credit.score" class="text-red-600 text-sm">✗</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer actions -->
                <div class="bg-white shadow-sm rounded-lg p-4 flex items-center justify-between">
                    <Link :href="route('leasing.deals.show', deal.id)"
                          class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md text-sm hover:bg-gray-200">Cancel</Link>
                    <div class="flex items-center gap-2">
                        <span v-if="lease.terms.length === 0" class="text-xs text-amber-600">Pick at least one term →</span>
                        <button type="button" @click="saveAndContinue" :disabled="form.processing || lease.terms.length === 0"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700 disabled:opacity-50">
                            {{ form.processing ? 'Saving…' : 'Save Drafts → (Lender Selection coming next PR)' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
