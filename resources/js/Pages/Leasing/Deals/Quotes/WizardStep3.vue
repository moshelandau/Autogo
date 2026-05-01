<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { reactive, ref, computed, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    deal:   Object,
    drafts: Array,
    sheets: Object, // { draftId: { inputs: {...}, result: {...} } }
});

// One reactive sheet per draft
const sheets = reactive(
    props.drafts.map(d => ({
        draft_id: d.id,
        term:     d.term,
        lender:   d.lender?.name,
        inputs:   { ...props.sheets[d.id].inputs, fees: [...props.sheets[d.id].inputs.fees].map(f => ({ ...f })) },
        result:   { ...props.sheets[d.id].result },
        recomputing: false,
    }))
);

// Debounced recompute on input change
const recompute = async (sheet) => {
    sheet.recomputing = true;
    try {
        const { data } = await axios.post(
            route('leasing.deals.quotes.wizard.step3.compute', [props.deal.id, sheet.draft_id]),
            { worksheet: sheet.inputs }
        );
        sheet.result = data;
    } catch (e) { /* keep last result on error */ }
    sheet.recomputing = false;
};

// Watch every sheet's inputs and debounce recompute
sheets.forEach(sheet => {
    let timer = null;
    watch(() => sheet.inputs, () => {
        clearTimeout(timer);
        timer = setTimeout(() => recompute(sheet), 250);
    }, { deep: true });
});

const addFee = (sheet) => {
    sheet.inputs.fees.push({ name: 'Fee', amount: 0, paid_as: 'capped' });
};
const removeFee = (sheet, i) => sheet.inputs.fees.splice(i, 1);

const form = useForm({});
const save = () => {
    form
        .transform(() => ({
            sheets: sheets.map(s => ({ draft_id: s.draft_id, inputs: s.inputs })),
        }))
        .post(route('leasing.deals.quotes.wizard.step3.save', props.deal.id));
};

const fmt = (v) => v != null && v !== '' ? '$' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—';
const pct = (v) => v != null && v !== '' ? Number(v).toFixed(2) + '%' : '—';
</script>

<template>
    <AppLayout title="Quote Wizard — Worksheet">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800">Worksheet — Deal #{{ deal.deal_number }}</h2>
                    <p class="text-sm text-gray-500">{{ deal.customer?.first_name }} {{ deal.customer?.last_name }} · {{ deal.vehicle_year }} {{ deal.vehicle_make }} {{ deal.vehicle_model }}</p>
                </div>
                <Link :href="route('leasing.deals.show', deal.id)" class="text-sm text-gray-600 hover:underline">← Back to deal</Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

                <!-- 3-step nav -->
                <div class="bg-white shadow-sm rounded-lg p-3 flex items-center gap-2 text-sm">
                    <Link :href="route('leasing.deals.quotes.wizard', deal.id)" class="px-3 py-1 bg-emerald-100 text-emerald-800 rounded hover:bg-emerald-200">✓ 1. Quote Structure</Link>
                    <span class="text-gray-300">›</span>
                    <Link :href="route('leasing.deals.quotes.wizard.step2', deal.id)" class="px-3 py-1 bg-emerald-100 text-emerald-800 rounded hover:bg-emerald-200">✓ 2. Lender Selection</Link>
                    <span class="text-gray-300">›</span>
                    <span class="px-3 py-1 bg-indigo-600 text-white rounded font-semibold">3. Worksheet</span>
                </div>

                <!-- One worksheet per draft (one per term) -->
                <div v-for="sheet in sheets" :key="sheet.draft_id" class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <header class="px-5 py-3 border-b bg-gray-50 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-800">Draft — {{ sheet.term }}-month {{ deal.payment_type }} · <span class="text-indigo-600">{{ sheet.lender }}</span></h3>
                        <span v-if="sheet.recomputing" class="text-xs text-indigo-600 animate-pulse">recomputing…</span>
                    </header>

                    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 p-5">

                        <!-- LEFT: Vehicle Price + Detail Table + Rate + Residual -->
                        <div class="space-y-4">
                            <!-- Vehicle Price + editable Profit (back-solves sell_price) -->
                            <div class="border rounded-xl p-3">
                                <div class="flex items-baseline justify-between border-b pb-2 mb-2">
                                    <h4 class="font-semibold text-sm">Vehicle Price</h4>
                                    <span class="text-sm font-bold">{{ fmt(sheet.result.sell_price) }}</span>
                                </div>
                                <div class="grid grid-cols-3 gap-2 text-xs">
                                    <div>
                                        <label class="text-gray-500">Cost</label>
                                        <input v-model.number="sheet.inputs.cost" type="number" step="0.01" min="0" class="block w-full border-gray-300 rounded text-xs" />
                                    </div>
                                    <div>
                                        <label class="text-gray-500">Sell Price</label>
                                        <input v-model.number="sheet.inputs.sell_price" type="number" step="0.01" min="0"
                                               :disabled="sheet.inputs.vehicle_profit_target != null && sheet.inputs.vehicle_profit_target !== ''"
                                               :placeholder="fmt(sheet.result.sell_price)"
                                               class="block w-full border-gray-300 rounded text-xs disabled:bg-gray-100" />
                                    </div>
                                    <div>
                                        <label class="text-gray-500 flex items-center gap-1">
                                            Target Profit
                                            <button type="button" v-if="sheet.inputs.vehicle_profit_target != null && sheet.inputs.vehicle_profit_target !== ''"
                                                    @click="sheet.inputs.vehicle_profit_target = ''"
                                                    class="text-[10px] text-red-500">clear</button>
                                        </label>
                                        <input v-model.number="sheet.inputs.vehicle_profit_target" type="number" step="50" min="0"
                                               :placeholder="fmt(sheet.result.profit?.vehicle)"
                                               class="block w-full border-gray-300 rounded text-xs" />
                                        <p class="text-[10px] text-emerald-700 mt-0.5 font-semibold">Current: {{ fmt(sheet.result.profit?.vehicle) }}</p>
                                    </div>
                                </div>
                                <p class="text-[10px] text-gray-500 mt-1">Set Target Profit to back-solve sell price (cost + target). Or set Sell Price directly.</p>
                            </div>

                            <!-- Applied Rebates (from Step 1's Available Rebates picker) -->
                            <div v-if="sheet.inputs.applied_rebates?.length" class="border rounded-xl p-3 bg-emerald-50/40">
                                <div class="flex items-baseline justify-between border-b pb-2 mb-2">
                                    <h4 class="font-semibold text-sm">Applied Rebates</h4>
                                    <span class="text-sm font-bold text-emerald-700">−{{ fmt(sheet.inputs.rebates) }}</span>
                                </div>
                                <ul class="space-y-1 text-xs">
                                    <li v-for="r in sheet.inputs.applied_rebates" :key="r.id" class="flex items-center justify-between gap-2">
                                        <span class="flex-1 truncate">
                                            <span class="font-bold text-emerald-700">${{ Number(r.cashback || 0).toLocaleString() }}</span>
                                            ·
                                            <span class="px-1 py-0.5 rounded text-[9px] font-semibold"
                                                  :class="r.source === 'dealer_markdown' ? 'bg-amber-200 text-amber-900' : 'bg-blue-200 text-blue-900'">
                                                {{ r.source === 'dealer_markdown' ? 'DEALER' : 'OEM' }}
                                            </span>
                                            {{ r.title }}
                                        </span>
                                    </li>
                                </ul>
                                <input v-model.number="sheet.inputs.rebates" type="number" step="50" min="0"
                                       class="mt-2 block w-full border-gray-300 rounded text-xs" placeholder="Adjust total" />
                            </div>

                            <!-- Detail table — Upfront / Capped / Total per line -->
                            <!-- overflow-x-auto so the 4-column table can scroll
                                 inside the card on narrow screens instead of clipping -->
                            <div class="border rounded-xl overflow-x-auto">
                                <table class="min-w-full text-xs whitespace-nowrap">
                                    <thead class="bg-gray-50 text-gray-500">
                                        <tr>
                                            <th class="px-2 py-1 text-left"></th>
                                            <th class="px-2 py-1 text-right">Upfront</th>
                                            <th class="px-2 py-1 text-right">Capped</th>
                                            <th class="px-2 py-1 text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr>
                                            <td class="px-2 py-1.5">First Month Payment</td>
                                            <td class="px-2 py-1.5 text-right text-blue-600">{{ fmt(sheet.result.total_monthly) }}</td>
                                            <td class="px-2 py-1.5 text-right">—</td>
                                            <td class="px-2 py-1.5 text-right font-semibold">{{ fmt(sheet.result.total_monthly) }}</td>
                                        </tr>
                                        <tr v-for="(f, i) in sheet.inputs.fees" :key="i">
                                            <td class="px-2 py-1.5">
                                                <div class="flex items-center gap-1">
                                                    <input v-model="f.name" type="text" class="border-0 p-0 text-xs bg-transparent w-24" />
                                                    <button @click="removeFee(sheet, i)" type="button" class="text-red-500 text-xs">×</button>
                                                </div>
                                            </td>
                                            <td class="px-2 py-1.5 text-right">
                                                <input v-model.number="f.amount" v-if="f.paid_as === 'upfront'" type="number" step="0.01" min="0" class="border-gray-200 rounded text-xs w-20 text-right" />
                                                <span v-else class="text-gray-300">$0.00</span>
                                            </td>
                                            <td class="px-2 py-1.5 text-right">
                                                <input v-model.number="f.amount" v-if="f.paid_as === 'capped'" type="number" step="0.01" min="0" class="border-gray-200 rounded text-xs w-20 text-right" />
                                                <span v-else class="text-gray-300">$0.00</span>
                                            </td>
                                            <td class="px-2 py-1.5 text-right">
                                                <select v-model="f.paid_as" class="border-gray-200 rounded text-[10px] py-0">
                                                    <option value="upfront">↑ Up</option>
                                                    <option value="capped">⤓ Cap</option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr class="bg-gray-50 font-semibold">
                                            <td class="px-2 py-1.5">Total Charges</td>
                                            <td class="px-2 py-1.5 text-right">{{ fmt(sheet.result.upfront_fees + sheet.result.upfront_tax + sheet.result.total_monthly) }}</td>
                                            <td class="px-2 py-1.5 text-right">{{ fmt(sheet.result.capped_fees) }}</td>
                                            <td class="px-2 py-1.5 text-right">{{ fmt(sheet.result.upfront_fees + sheet.result.upfront_tax + sheet.result.total_monthly + sheet.result.capped_fees) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="px-2 py-1.5 text-gray-500">Applied Rebates</td>
                                            <td colspan="2" class="px-2 py-1.5 text-right">
                                                <input v-model.number="sheet.inputs.rebates" type="number" step="0.01" min="0" class="border-gray-200 rounded text-xs w-24 text-right" />
                                            </td>
                                            <td class="px-2 py-1.5 text-right">−{{ fmt(sheet.inputs.rebates) }}</td>
                                        </tr>
                                        <tr class="bg-indigo-50 font-bold">
                                            <td class="px-2 py-2">Due at Signing</td>
                                            <td colspan="2"></td>
                                            <td class="px-2 py-2 text-right text-indigo-700 text-sm">{{ fmt(sheet.result.due_at_signing) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div class="px-2 py-1.5 border-t flex justify-end">
                                    <button type="button" @click="addFee(sheet)" class="text-xs text-indigo-600 hover:underline">+ Add Fee</button>
                                </div>
                            </div>

                            <!-- Rate -->
                            <div class="border rounded-xl p-3">
                                <div class="flex items-baseline justify-between border-b pb-2 mb-2">
                                    <h4 class="font-semibold text-sm">Rate</h4>
                                    <span class="text-sm font-mono">{{ Number(sheet.inputs.sell_money_factor || 0).toFixed(5) }}</span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div>
                                        <label class="text-gray-500">Buy Rate (MF)</label>
                                        <input v-model.number="sheet.inputs.buy_money_factor" type="number" step="0.000001" min="0" class="block w-full border-gray-300 rounded text-xs" />
                                    </div>
                                    <div>
                                        <label class="text-gray-500">Sell Rate (MF)</label>
                                        <input v-model.number="sheet.inputs.sell_money_factor" type="number" step="0.000001" min="0" class="block w-full border-gray-300 rounded text-xs" />
                                    </div>
                                    <div class="col-span-2 text-gray-600">
                                        Reserve spread: {{ Number((sheet.result.reserve_spread || 0) * 10000).toFixed(2) }} bps · Reserve Profit: <span class="font-bold text-emerald-700">{{ fmt(sheet.result.profit?.reserve) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Residual -->
                            <div class="border rounded-xl p-3">
                                <div class="flex items-baseline justify-between border-b pb-2 mb-2">
                                    <h4 class="font-semibold text-sm">Residual</h4>
                                    <span class="text-sm font-bold">{{ fmt(sheet.result.residual_value) }}</span>
                                </div>
                                <div class="grid grid-cols-3 gap-2 text-xs">
                                    <div>
                                        <label class="text-gray-500">Base %</label>
                                        <input v-model.number="sheet.inputs.base_residual_pct" type="number" step="0.01" min="0" max="100" class="block w-full border-gray-300 rounded text-xs" />
                                    </div>
                                    <div>
                                        <label class="text-gray-500">Adjust %</label>
                                        <input v-model.number="sheet.inputs.adj_residual_pct" type="number" step="0.01" class="block w-full border-gray-300 rounded text-xs" />
                                    </div>
                                    <div>
                                        <label class="text-gray-500">Total %</label>
                                        <div class="font-semibold mt-1">{{ pct(sheet.result.total_residual_pct) }}</div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-xs mt-2 pt-2 border-t">
                                    <div>
                                        <label class="text-gray-500">Purchase Option Fee</label>
                                        <input v-model.number="sheet.inputs.purchase_option_fee" type="number" step="1" min="0" placeholder="0" class="block w-full border-gray-300 rounded text-xs" />
                                    </div>
                                    <div v-if="sheet.inputs.purchase_option_fee > 0">
                                        <label class="text-gray-500">Buyout (residual + POF)</label>
                                        <div class="text-xs mt-1">{{ fmt(sheet.result.residual_gross) }}</div>
                                    </div>
                                </div>
                                <p class="text-[10px] text-gray-500 mt-1">Most OEMs leave POF at $0 (residual = lease-end buyout). Set when the lender adds a buyout fee — true residual used in monthly math = buyout − POF.</p>
                            </div>
                        </div>

                        <!-- MIDDLE: Payment + Profit + Cap Cost & Max Advance -->
                        <div class="space-y-4">
                            <!-- Payment -->
                            <div class="border rounded-xl p-3">
                                <div class="flex items-baseline justify-between border-b pb-2 mb-2">
                                    <h4 class="font-semibold text-sm">Payment</h4>
                                    <span class="text-2xl font-bold text-indigo-700">{{ fmt(sheet.result.total_monthly) }}/mo</span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div>Term:</div>             <div class="text-right">{{ sheet.result.term }} mo</div>
                                    <div>Depreciation:</div>    <div class="text-right">{{ fmt(sheet.result.depreciation) }}</div>
                                    <div>Rent Charge:</div>    <div class="text-right">{{ fmt(sheet.result.rent_charge) }}</div>
                                    <div>Monthly Tax:</div>    <div class="text-right">{{ fmt(sheet.result.monthly_tax) }}</div>
                                    <div>APR equivalent:</div> <div class="text-right">{{ sheet.result.apr_equivalent }}%</div>
                                </div>
                            </div>

                            <!-- Profit -->
                            <div class="border rounded-xl p-3">
                                <div class="flex items-baseline justify-between border-b pb-2 mb-2">
                                    <h4 class="font-semibold text-sm">Profit</h4>
                                    <span class="text-lg font-bold text-emerald-700">{{ fmt(sheet.result.profit?.total) }}</span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div>Vehicle Profit:</div>     <div class="text-right">{{ fmt(sheet.result.profit?.vehicle) }}</div>
                                    <div>Trade Profit:</div>       <div class="text-right">{{ fmt(sheet.result.profit?.trade) }}</div>
                                    <div>Reserve Profit:</div>     <div class="text-right">{{ fmt(sheet.result.profit?.reserve) }}</div>
                                </div>
                            </div>

                            <!-- Vehicle info -->
                            <div class="border rounded-xl p-3">
                                <div class="flex items-baseline justify-between border-b pb-2 mb-2">
                                    <h4 class="font-semibold text-sm">Vehicle Info</h4>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div>MSRP:</div>    <div class="text-right">{{ fmt(sheet.result.msrp) }}</div>
                                    <div v-if="deal.mrm">MRM:</div>     <div v-if="deal.mrm" class="text-right">{{ fmt(deal.mrm) }}</div>
                                    <div v-if="deal.invoice_price">Invoice:</div> <div v-if="deal.invoice_price" class="text-right">{{ fmt(deal.invoice_price) }}</div>
                                    <div>Sell Price:</div><div class="text-right">{{ fmt(sheet.result.sell_price) }}</div>
                                </div>
                            </div>

                            <!-- Cap Cost & Max Advance -->
                            <div class="border rounded-xl p-3">
                                <div class="flex items-baseline justify-between border-b pb-2 mb-2">
                                    <h4 class="font-semibold text-sm">Capitalized Cost & Max Advance</h4>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div>Gross Cap:</div>     <div class="text-right">{{ fmt(sheet.result.gross_cap_cost) }}</div>
                                    <div>Net Cap:</div>       <div class="text-right font-semibold">{{ fmt(sheet.result.net_cap_cost) }}</div>
                                    <div>Max Advance %:</div>
                                    <div class="text-right">
                                        <input v-model.number="sheet.inputs.max_advance_pct" type="number" step="0.5" min="80" max="200" class="border-gray-200 rounded text-xs w-16 text-right" />
                                    </div>
                                    <div>Max Advance $:</div> <div class="text-right">{{ fmt(sheet.result.max_advance_amount) }}</div>
                                </div>
                                <div v-if="!sheet.result.within_max_advance" class="mt-2 p-2 bg-red-50 border border-red-300 rounded text-xs text-red-800">
                                    ⚠ Net cap exceeds max advance by <strong>{{ fmt(sheet.result.max_advance_over) }}</strong>. Reduce cap cost or increase Max Advance %.
                                </div>
                                <div v-else class="mt-2 p-1 text-[11px] text-emerald-700">✓ Within max advance.</div>
                            </div>
                        </div>

                        <!-- RIGHT: Taxes & Fees + Trade-in -->
                        <div class="space-y-4">
                            <div class="border rounded-xl p-3">
                                <div class="flex items-baseline justify-between border-b pb-2 mb-2">
                                    <h4 class="font-semibold text-sm">Taxes</h4>
                                    <div class="flex gap-1">
                                        <button type="button" @click="sheet.inputs.taxes_paid_as = 'upfront'"
                                                :class="['px-2 py-0.5 rounded text-[10px]', sheet.inputs.taxes_paid_as === 'upfront' ? 'bg-gray-900 text-white' : 'bg-gray-100']">Upfront All</button>
                                        <button type="button" @click="sheet.inputs.taxes_paid_as = 'capped'"
                                                :class="['px-2 py-0.5 rounded text-[10px]', sheet.inputs.taxes_paid_as === 'capped' ? 'bg-gray-900 text-white' : 'bg-gray-100']">Cap All</button>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div>
                                        <label class="text-gray-500">Tax Rate</label>
                                        <input v-model.number="sheet.inputs.tax_rate" type="number" step="0.0001" min="0" max="0.2" class="block w-full border-gray-300 rounded text-xs" />
                                    </div>
                                    <div>
                                        <label class="text-gray-500">Calculated</label>
                                        <div class="mt-1 font-semibold">{{ pct(sheet.inputs.tax_rate * 100) }}</div>
                                    </div>
                                    <div class="col-span-2 text-gray-500 mt-1">Monthly Tax: <strong>{{ fmt(sheet.result.monthly_tax) }}</strong> · Total over term: <strong>{{ fmt(sheet.result.monthly_tax * sheet.result.term + sheet.result.upfront_tax) }}</strong></div>
                                </div>
                            </div>

                            <!-- Trade summary -->
                            <div class="border rounded-xl p-3">
                                <div class="flex items-baseline justify-between border-b pb-2 mb-2">
                                    <h4 class="font-semibold text-sm">Trade-In</h4>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div>Allowance:</div>     <div class="text-right"><input v-model.number="sheet.inputs.trade_allowance" type="number" step="0.01" min="0" class="border-gray-200 rounded text-xs w-24 text-right" /></div>
                                    <div>Payoff:</div>        <div class="text-right"><input v-model.number="sheet.inputs.trade_payoff" type="number" step="0.01" min="0" class="border-gray-200 rounded text-xs w-24 text-right" /></div>
                                    <div>ACV:</div>           <div class="text-right"><input v-model.number="sheet.inputs.trade_acv" type="number" step="0.01" min="0" class="border-gray-200 rounded text-xs w-24 text-right" /></div>
                                    <div class="text-emerald-700">Credit:</div>     <div class="text-right text-emerald-700">{{ fmt(sheet.result.trade_credit) }}</div>
                                    <div v-if="sheet.result.negative_equity > 0" class="text-red-700">Neg Equity (rolled):</div>
                                    <div v-if="sheet.result.negative_equity > 0" class="text-right text-red-700">+{{ fmt(sheet.result.negative_equity) }}</div>
                                </div>
                            </div>

                            <!-- Cap cost reduction -->
                            <div class="border rounded-xl p-3">
                                <div class="flex items-baseline justify-between border-b pb-2 mb-2">
                                    <h4 class="font-semibold text-sm">Cap Cost Reduction</h4>
                                </div>
                                <input v-model.number="sheet.inputs.cap_cost_reduction" type="number" step="0.01" min="0" placeholder="$ 0.00" class="block w-full border-gray-300 rounded text-xs" />
                                <p class="text-[11px] text-gray-500 mt-1">Customer cash that reduces the lease cap (lowers monthly).</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-white shadow-sm rounded-lg p-4 flex items-center justify-between">
                    <Link :href="route('leasing.deals.quotes.wizard.step2', deal.id)"
                          class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md text-sm hover:bg-gray-200">← Back to Lender Selection</Link>
                    <button type="button" @click="save" :disabled="form.processing"
                            class="px-4 py-2 bg-emerald-600 text-white rounded-md text-sm hover:bg-emerald-700 disabled:opacity-50">
                        {{ form.processing ? 'Saving…' : '✓ Finalize Quotes' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
