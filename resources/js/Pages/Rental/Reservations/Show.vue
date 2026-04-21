<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';

import { ref, computed } from 'vue';

const props = defineProps({
    reservation: Object,
    availableVehicles: Array,
    inspectionAreas: { type: Array, default: () => ['front','rear','left_side','right_side','interior','odometer'] },
    inspectionStatus: { type: Object, default: () => ({ pickup_missing: [], return_missing: [] }) },
});
const r = props.reservation;
const fmt = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v || 0);

const pickupForm = useForm({
    vehicle_id: r.vehicle_id || '',
    odometer_out: '', fuel_out: 'full', pickup_notes: '',
    insurance_source: 'own_policy',
    insurance_company_seen: r.customer?.insurance_company || '',
    insurance_policy_seen:  r.customer?.insurance_policy || '',
    // Hold card info (tokenized in production via Sola):
    hold_card_brand:  '', hold_card_last4: '', hold_card_exp: '',
    hold_amount: 250,
});
const returnForm = useForm({ odometer_in: '', fuel_in: 'full', return_notes: '' });

// POS-style payment form
const posForm = useForm({
    payment_method: 'cash',           // cash | card_on_file | new_card
    amount: r.outstanding_balance || 0,
    tendered: r.outstanding_balance || 0, // cash tendered (for change calc)
    card_brand: '', card_last4: '', card_exp: '', card_cvc: '',
    use_hold_card: true,
    sola_account: '',                 // 'autogo' | 'high_rental' (required for card payments)
    reference: '',
});

// Return completion → claim prompt
const showClaimPrompt = ref(false);
const claimForm = useForm({
    damage_description: '',
    priority: 'medium',
    insurance_company: r.customer?.insurance_company || '',
    insurance_claim_number: '',
});
const openClaimFromReturn = () => {
    claimForm.post(route('rental.reservations.openClaim', r.id), {
        onSuccess: () => { showClaimPrompt.value = false; },
    });
};

const posChange = computed(() =>
    posForm.payment_method === 'cash'
        ? Math.max(0, (parseFloat(posForm.tendered) || 0) - (parseFloat(posForm.amount) || 0))
        : 0
);

const doPickup = () => pickupForm.post(route('rental.reservations.pickup', r.id), { forceFormData: true });
const doReturn = () => returnForm.post(route('rental.reservations.return', r.id), {
    onSuccess: () => { showClaimPrompt.value = true; },
});
const doPayment = () => posForm.post(route('rental.reservations.payment', r.id));
const doCancel = () => { if (confirm('Cancel this reservation?')) router.post(route('rental.reservations.cancel', r.id)); };
const releaseHold = (holdId) => { if (confirm('Release the $250 security hold?')) router.post(route('rental.holds.release', holdId)); };
const captureHold = (holdId) => { if (confirm('CAPTURE the security hold as a charge? (use only for damages)')) router.post(route('rental.holds.capture', holdId)); };

const statusColors = { open: 'bg-blue-100 text-blue-800', rental: 'bg-green-100 text-green-800', completed: 'bg-gray-100 text-gray-800', cancelled: 'bg-red-100 text-red-800' };

// ── Inspection photo upload ──────────────────────────────
const areaLabels = {
    front: 'Front', rear: 'Rear', left_side: 'Left Side', right_side: 'Right Side',
    interior: 'Interior', odometer: 'Odometer',
};
const areaIcons = {
    front: '🚗', rear: '🔙', left_side: '⬅️', right_side: '➡️',
    interior: '🪑', odometer: '🔢',
};

const inspectionsByType = computed(() => {
    const grouped = { pickup: {}, return: {} };
    (r.inspections || []).forEach(i => {
        if (!grouped[i.type]) grouped[i.type] = {};
        grouped[i.type][i.area] = i;
    });
    return grouped;
});

const uploadInspection = (type, area, fileInput) => {
    const file = fileInput.files?.[0];
    if (!file) return;
    const form = useForm({ type, area, image: file, has_damage: false });
    form.post(route('inspection.upload', r.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { fileInput.value = ''; },
    });
};

const removeInspection = (insp) => {
    if (!confirm('Remove this photo?')) return;
    router.delete(route('inspection.destroy', [r.id, insp.id]), { preserveScroll: true });
};

const analyzingId = ref(null);
const analyzeImage = async (insp) => {
    analyzingId.value = insp.id;
    try {
        await fetch(route('inspection.analyze', insp.id), { method: 'POST', headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content }});
        router.reload({ only: ['reservation'] });
    } finally { analyzingId.value = null; }
};
</script>

<template>
    <AppLayout title="Reservation Details">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('rental.reservations.index')" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Reservation #{{ r.reservation_number }}</h2>
                    <span class="px-3 py-1 text-sm rounded-full capitalize" :class="statusColors[r.status]">{{ r.status }}</span>
                </div>
                <button v-if="r.status === 'open'" @click="doCancel" class="text-sm text-red-600 hover:text-red-800">Cancel Reservation</button>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Summary -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <h3 class="text-sm font-medium text-gray-500 mb-3">Customer</h3>
                        <p class="font-bold text-lg">{{ r.customer?.first_name }} {{ r.customer?.last_name }}</p>
                        <p class="text-sm text-gray-600">{{ r.customer?.phone || '' }}</p>
                        <p class="text-sm text-gray-600">{{ r.customer?.email || '' }}</p>
                    </div>
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <h3 class="text-sm font-medium text-gray-500 mb-3">Vehicle</h3>
                        <p v-if="r.vehicle" class="font-bold">{{ r.vehicle.year }} {{ r.vehicle.make }} {{ r.vehicle.model }}</p>
                        <p v-else class="text-gray-500">Not assigned ({{ r.vehicle_class }})</p>
                        <p v-if="r.vehicle" class="text-sm text-gray-600">Plate: {{ r.vehicle.license_plate || '-' }}</p>
                    </div>
                    <div class="bg-white shadow-sm rounded-lg p-6">
                        <h3 class="text-sm font-medium text-gray-500 mb-3">Financial</h3>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between"><span>{{ r.total_days }} days @ {{ fmt(r.daily_rate) }}/day</span><span>{{ fmt(r.subtotal) }}</span></div>
                            <div v-if="r.discount_amount > 0" class="flex justify-between text-green-600"><span>Discount</span><span>-{{ fmt(r.discount_amount) }}</span></div>
                            <div class="flex justify-between font-bold border-t pt-1"><span>Total</span><span>{{ fmt(r.total_price) }}</span></div>
                            <div class="flex justify-between"><span>Paid</span><span class="text-green-600">{{ fmt(r.total_paid) }}</span></div>
                            <div class="flex justify-between font-bold" :class="r.outstanding_balance > 0 ? 'text-red-600' : 'text-green-600'"><span>Balance</span><span>{{ fmt(r.outstanding_balance) }}</span></div>
                        </div>
                    </div>
                </div>

                <!-- Dates -->
                <div class="bg-white shadow-sm rounded-lg p-6 grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div><div class="text-xs text-gray-500">Pickup</div><div class="font-medium">{{ r.pickup_date?.split('T')[0] }}</div><div class="text-sm text-gray-500">{{ r.pickup_location?.name || '-' }}</div></div>
                    <div><div class="text-xs text-gray-500">Return</div><div class="font-medium">{{ r.return_date?.split('T')[0] }}</div><div class="text-sm text-gray-500">{{ r.return_location?.name || '-' }}</div></div>
                    <div><div class="text-xs text-gray-500">Odometer Out / In</div><div class="font-medium">{{ r.odometer_out || '-' }} / {{ r.odometer_in || '-' }}</div></div>
                    <div><div class="text-xs text-gray-500">Fuel Out / In</div><div class="font-medium">{{ r.fuel_out || '-' }} / {{ r.fuel_in || '-' }}</div></div>
                </div>

                <!-- Pickup Action -->
                <div v-if="r.status === 'open'" class="bg-yellow-50 border-2 border-yellow-300 rounded-lg p-6">
                    <h3 class="font-bold text-yellow-900 text-lg mb-1">🔑 Start Rental — Pickup Vehicle</h3>
                    <p class="text-xs text-yellow-800 mb-4">Verify DL, insurance, and authorize the $250 security deposit hold. Inspection photos are handled below.</p>
                    <form @submit.prevent="doPickup" class="space-y-5">
                        <!-- Vehicle + condition -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700">Vehicle</label>
                                <select v-model="pickupForm.vehicle_id" class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                                    <option v-if="r.vehicle" :value="r.vehicle.id">{{ r.vehicle.year }} {{ r.vehicle.make }} {{ r.vehicle.model }}</option>
                                    <option v-for="v in availableVehicles" :key="v.id" :value="v.id">{{ v.year }} {{ v.make }} {{ v.model }} — {{ v.license_plate }}</option>
                                </select>
                            </div>
                            <div><label class="block text-xs font-semibold text-gray-700">Odometer Out</label><input v-model="pickupForm.odometer_out" type="number" class="mt-1 block w-full border-gray-300 rounded-md text-sm" /></div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700">Fuel Out</label>
                                <select v-model="pickupForm.fuel_out" class="mt-1 block w-full border-gray-300 rounded-md text-sm">
                                    <option value="full">Full</option><option value="3/4">3/4</option><option value="1/2">1/2</option><option value="1/4">1/4</option>
                                </select>
                            </div>
                        </div>

                        <!-- Insurance source -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-2">Insurance Coverage *</label>
                            <div class="grid grid-cols-3 gap-3">
                                <label class="flex items-start gap-2 p-3 border-2 rounded-lg cursor-pointer transition"
                                       :class="pickupForm.insurance_source === 'own_policy' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300'">
                                    <input v-model="pickupForm.insurance_source" value="own_policy" type="radio" />
                                    <div><div class="text-sm font-semibold">🛡 Own Policy</div><div class="text-[10px] text-gray-500">Customer's auto insurance</div></div>
                                </label>
                                <label class="flex items-start gap-2 p-3 border-2 rounded-lg cursor-pointer transition"
                                       :class="pickupForm.insurance_source === 'credit_card' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300'">
                                    <input v-model="pickupForm.insurance_source" value="credit_card" type="radio" />
                                    <div><div class="text-sm font-semibold">💳 Credit Card</div><div class="text-[10px] text-gray-500">CC provides rental coverage</div></div>
                                </label>
                                <label class="flex items-start gap-2 p-3 border-2 rounded-lg cursor-pointer transition"
                                       :class="pickupForm.insurance_source === 'none' ? 'border-red-500 bg-red-50' : 'border-gray-200 hover:border-gray-300'">
                                    <input v-model="pickupForm.insurance_source" value="none" type="radio" />
                                    <div><div class="text-sm font-semibold">⚠️ None</div><div class="text-[10px] text-gray-500">Not recommended</div></div>
                                </label>
                            </div>

                            <div v-if="pickupForm.insurance_source === 'own_policy'" class="mt-3 grid grid-cols-2 gap-3">
                                <div><label class="block text-[10px] font-semibold">Insurance Company</label><input v-model="pickupForm.insurance_company_seen" type="text" class="mt-1 block w-full border-gray-300 rounded-md text-sm" placeholder="Progressive, Geico, etc." /></div>
                                <div><label class="block text-[10px] font-semibold">Policy #</label><input v-model="pickupForm.insurance_policy_seen" type="text" class="mt-1 block w-full border-gray-300 rounded-md text-sm" /></div>
                            </div>
                        </div>

                        <!-- $250 Security Deposit Hold -->
                        <div class="bg-white border-2 border-amber-300 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <div class="font-bold text-amber-900">🔒 Security Deposit Hold</div>
                                    <div class="text-[10px] text-gray-600">Authorize (not charge) this amount on customer's card. Auto-released at return unless damage.</div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs">$</span>
                                    <input v-model.number="pickupForm.hold_amount" type="number" class="w-20 border-gray-300 rounded text-sm text-right font-bold" />
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <div>
                                    <label class="block text-[10px] font-semibold">Card Brand</label>
                                    <select v-model="pickupForm.hold_card_brand" class="mt-1 w-full border-gray-300 rounded text-sm">
                                        <option value="">—</option>
                                        <option value="visa">Visa</option>
                                        <option value="mc">Mastercard</option>
                                        <option value="amex">Amex</option>
                                        <option value="discover">Discover</option>
                                    </select>
                                </div>
                                <div><label class="block text-[10px] font-semibold">Last 4</label><input v-model="pickupForm.hold_card_last4" maxlength="4" class="mt-1 w-full border-gray-300 rounded text-sm font-mono" /></div>
                                <div><label class="block text-[10px] font-semibold">Exp MM/YY</label><input v-model="pickupForm.hold_card_exp" maxlength="5" placeholder="12/28" class="mt-1 w-full border-gray-300 rounded text-sm font-mono" /></div>
                            </div>
                            <p class="text-[10px] text-gray-500 mt-2">💡 For security, only last 4 is stored. Sola Payments handles the authorization in the background.</p>
                        </div>

                        <div class="flex items-center justify-between pt-2 border-t">
                            <div class="text-xs text-gray-600">
                                <strong>Before clicking Pickup:</strong>
                                <ul class="list-disc ml-4 mt-1">
                                    <li>Upload driver's license (see inspection grid below)</li>
                                    <li>Upload all 6 pickup inspection photos</li>
                                </ul>
                            </div>
                            <button type="submit" :disabled="pickupForm.processing" class="px-6 py-3 bg-emerald-600 text-white rounded-lg font-semibold hover:bg-emerald-700 disabled:opacity-50">
                                {{ pickupForm.processing ? 'Processing…' : '🔑 Start Rental' }}
                            </button>
                        </div>
                        <p v-for="(err, k) in pickupForm.errors" :key="k" class="text-xs text-red-600">{{ err }}</p>
                    </form>
                </div>

                <!-- Active security hold display -->
                <div v-if="r.active_hold" class="bg-amber-50 border-2 border-amber-300 rounded-lg p-4 flex items-center justify-between">
                    <div>
                        <div class="font-bold text-amber-900 text-lg">🔒 ${{ Number(r.active_hold.amount).toFixed(2) }} held on High Car Rental</div>
                        <div class="text-sm text-amber-800">
                            {{ r.active_hold.card_brand?.toUpperCase() }} •••• {{ r.active_hold.card_last4 }} ·
                            authorized {{ r.active_hold.placed_at?.split('T')[0] }}
                        </div>
                        <div class="text-xs text-amber-700 mt-1">
                            ⏳ Auto-expires after 30 days. <strong>Hold is intentionally kept after return</strong> to allow capture if damage discovered later.
                        </div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <button @click="releaseHold(r.active_hold.id)" class="text-xs px-3 py-1.5 bg-white border border-emerald-300 text-emerald-700 rounded hover:bg-emerald-50">
                            ✓ Release Now (clean)
                        </button>
                        <button @click="captureHold(r.active_hold.id)" class="text-xs px-3 py-1.5 bg-red-600 text-white rounded hover:bg-red-700">
                            ⚠ Capture (damage)
                        </button>
                    </div>
                </div>

                <!-- Claim prompt (after successful return) -->
                <div v-if="showClaimPrompt" @click.self="showClaimPrompt = false"
                     class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
                    <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full">
                        <header class="p-5 border-b">
                            <h3 class="font-bold text-lg">🚨 Any damage or incident?</h3>
                            <p class="text-xs text-gray-500 mt-1">Now is the time to open a rental claim so we can track repairs and insurance recovery.</p>
                        </header>
                        <div class="p-5 space-y-3">
                            <textarea v-model="claimForm.damage_description" rows="3"
                                      placeholder="Describe any damage (dent on right rear, scratch on bumper, interior stain, etc.)"
                                      class="w-full border-gray-300 rounded-lg text-sm"></textarea>
                            <div class="grid grid-cols-2 gap-3">
                                <select v-model="claimForm.priority" class="border-gray-300 rounded-lg text-sm">
                                    <option value="low">Low priority</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                                <input v-model="claimForm.insurance_company" placeholder="Insurance company" class="border-gray-300 rounded-lg text-sm" />
                            </div>
                            <input v-model="claimForm.insurance_claim_number" placeholder="Insurance claim # (if known)" class="w-full border-gray-300 rounded-lg text-sm" />
                        </div>
                        <div class="p-5 border-t flex justify-between">
                            <button @click="showClaimPrompt = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">
                                No damage · skip
                            </button>
                            <button @click="openClaimFromReturn"
                                    :disabled="!claimForm.damage_description || claimForm.processing"
                                    class="px-5 py-2 bg-rose-600 text-white rounded-lg text-sm font-bold hover:bg-rose-700 disabled:opacity-50">
                                {{ claimForm.processing ? 'Opening…' : '🚨 Open Rental Claim' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Return Action -->
                <div v-if="r.status === 'rental'" class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                    <h3 class="font-semibold text-blue-800 mb-4">Return Vehicle</h3>
                    <form @submit.prevent="doReturn" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div><label class="block text-sm font-medium text-gray-700">Odometer</label><input v-model="returnForm.odometer_in" type="number" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" /></div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Fuel</label>
                            <select v-model="returnForm.fuel_in" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="full">Full</option><option value="3/4">3/4</option><option value="1/2">1/2</option><option value="1/4">1/4</option><option value="empty">Empty</option>
                            </select>
                        </div>
                        <div><label class="block text-sm font-medium text-gray-700">Notes</label><input v-model="returnForm.return_notes" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" /></div>
                        <div class="flex items-end">
                            <button type="submit" :disabled="returnForm.processing" class="w-full px-4 py-2 bg-blue-600 text-white rounded-md text-sm hover:bg-blue-700">Return</button>
                        </div>
                    </form>
                </div>

                <!-- Vehicle Inspection Photos (Pickup & Return) -->
                <div v-if="['open','rental','completed'].includes(r.status)" class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="p-5 border-b bg-gradient-to-r from-indigo-50 to-purple-50">
                        <h3 class="font-bold text-lg text-gray-900">📸 Vehicle Inspection</h3>
                        <p class="text-xs text-gray-600 mt-1">Capture or upload photos at pickup & return. Tap any tile to scan with your phone camera or upload from disk. AI damage analysis runs automatically.</p>
                    </div>

                    <div class="grid md:grid-cols-2 divide-y md:divide-y-0 md:divide-x">
                        <!-- Pickup grid -->
                        <div v-for="type in ['pickup','return']" :key="type" class="p-5">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-semibold text-sm" :class="type === 'pickup' ? 'text-yellow-700' : 'text-blue-700'">
                                    {{ type === 'pickup' ? '🟡 Pickup Photos' : '🔵 Return Photos' }}
                                </h4>
                                <span class="text-xs px-2 py-0.5 rounded-full"
                                      :class="(inspectionStatus[type+'_missing']?.length ?? inspectionAreas.length) === 0
                                              ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                                    {{ inspectionAreas.length - (inspectionStatus[type+'_missing']?.length ?? inspectionAreas.length) }} / {{ inspectionAreas.length }}
                                </span>
                            </div>

                            <div class="grid grid-cols-3 gap-2">
                                <div v-for="area in inspectionAreas" :key="area"
                                     class="relative aspect-square border-2 border-dashed rounded-lg overflow-hidden group"
                                     :class="inspectionsByType[type]?.[area] ? 'border-emerald-400' : 'border-gray-300 hover:border-indigo-400 bg-gray-50'">
                                    <!-- Already uploaded -->
                                    <template v-if="inspectionsByType[type]?.[area]">
                                        <img :src="`/storage/${inspectionsByType[type][area].image_path}`"
                                             :alt="areaLabels[area]"
                                             class="w-full h-full object-cover" />
                                        <div v-if="inspectionsByType[type][area].has_damage"
                                             class="absolute top-1 right-1 bg-red-600 text-white text-[10px] px-1.5 py-0.5 rounded font-bold">⚠ DAMAGE</div>
                                        <div class="absolute inset-x-0 bottom-0 bg-black/60 text-white px-1.5 py-1 flex items-center justify-between text-[10px]">
                                            <span>{{ areaIcons[area] }} {{ areaLabels[area] }}</span>
                                            <button @click="removeInspection(inspectionsByType[type][area])"
                                                    class="opacity-0 group-hover:opacity-100 text-red-300 hover:text-white" title="Remove">×</button>
                                        </div>
                                    </template>

                                    <!-- Empty slot — file picker (uses capture=environment to open camera on mobile) -->
                                    <label v-else class="absolute inset-0 flex flex-col items-center justify-center cursor-pointer p-2 text-center">
                                        <span class="text-2xl">{{ areaIcons[area] }}</span>
                                        <span class="text-[10px] text-gray-500 mt-1 font-medium">{{ areaLabels[area] }}</span>
                                        <span class="text-[9px] text-indigo-600 mt-1">📷 Scan / Upload</span>
                                        <input type="file" accept="image/*" capture="environment"
                                               @change="uploadInspection(type, area, $event.target)"
                                               class="absolute inset-0 opacity-0 cursor-pointer" />
                                    </label>
                                </div>
                            </div>

                            <p v-if="inspectionStatus[type+'_missing']?.length"
                               class="mt-3 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1.5">
                                ⚠ Still missing: {{ inspectionStatus[type+'_missing'].join(', ') }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- POS: Collect Final Payment -->
                <div v-if="r.outstanding_balance > 0 && r.status !== 'cancelled'" class="bg-white shadow-lg rounded-xl border-2 border-emerald-200 overflow-hidden">
                    <header class="bg-gradient-to-r from-emerald-50 to-teal-50 p-5 border-b">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-bold text-lg text-emerald-900">💰 Collect Final Payment</h3>
                                <p class="text-xs text-emerald-800">Outstanding balance · pick method · tender · done</p>
                            </div>
                            <div class="text-right">
                                <div class="text-[10px] text-gray-500 uppercase">Amount Due</div>
                                <div class="text-3xl font-black text-emerald-700">{{ fmt(r.outstanding_balance) }}</div>
                            </div>
                        </div>
                    </header>

                    <form @submit.prevent="doPayment" class="p-5 space-y-5">
                        <!-- Method picker -->
                        <div class="grid grid-cols-3 gap-3">
                            <label class="cursor-pointer border-2 rounded-xl p-4 text-center transition"
                                   :class="posForm.payment_method === 'cash' ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200 hover:border-gray-300'">
                                <input v-model="posForm.payment_method" value="cash" type="radio" class="hidden" />
                                <div class="text-3xl mb-1">💵</div>
                                <div class="font-semibold text-sm">Cash</div>
                            </label>
                            <label class="cursor-pointer border-2 rounded-xl p-4 text-center transition"
                                   :class="posForm.payment_method === 'card_on_file' ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200 hover:border-gray-300'">
                                <input v-model="posForm.payment_method" value="card_on_file" type="radio" class="hidden" />
                                <div class="text-3xl mb-1">💳</div>
                                <div class="font-semibold text-sm">Card on File</div>
                                <div v-if="r.active_hold" class="text-[10px] text-gray-500">{{ r.active_hold.card_brand?.toUpperCase() }} •••• {{ r.active_hold.card_last4 }}</div>
                                <div v-else class="text-[10px] text-red-500">No card on file</div>
                            </label>
                            <label class="cursor-pointer border-2 rounded-xl p-4 text-center transition"
                                   :class="posForm.payment_method === 'new_card' ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200 hover:border-gray-300'">
                                <input v-model="posForm.payment_method" value="new_card" type="radio" class="hidden" />
                                <div class="text-3xl mb-1">🆕</div>
                                <div class="font-semibold text-sm">New Card</div>
                            </label>
                        </div>

                        <!-- Amount -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700">Charge Amount</label>
                                <div class="mt-1 relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">$</span>
                                    <input v-model.number="posForm.amount" type="number" step="0.01"
                                           class="block w-full pl-6 border-gray-300 rounded-lg text-lg font-bold" />
                                </div>
                            </div>
                            <div v-if="posForm.payment_method === 'cash'">
                                <label class="block text-xs font-semibold text-gray-700">Cash Tendered</label>
                                <div class="mt-1 relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">$</span>
                                    <input v-model.number="posForm.tendered" type="number" step="0.01"
                                           class="block w-full pl-6 border-gray-300 rounded-lg text-lg font-bold" />
                                </div>
                                <div v-if="posChange > 0" class="mt-1 text-xs text-emerald-700 font-bold">Change: {{ fmt(posChange) }}</div>
                            </div>
                        </div>

                        <!-- Card-specific fields -->
                        <div v-if="posForm.payment_method === 'new_card'" class="bg-gray-50 border rounded-lg p-4 grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs">Card brand</label>
                                <select v-model="posForm.card_brand" class="mt-1 w-full border-gray-300 rounded text-sm">
                                    <option value="">—</option>
                                    <option value="visa">Visa</option><option value="mc">Mastercard</option>
                                    <option value="amex">Amex</option><option value="discover">Discover</option>
                                </select>
                            </div>
                            <div><label class="block text-xs">Last 4</label><input v-model="posForm.card_last4" maxlength="4" class="mt-1 w-full border-gray-300 rounded text-sm font-mono" /></div>
                            <div><label class="block text-xs">Exp MM/YY</label><input v-model="posForm.card_exp" maxlength="5" placeholder="12/28" class="mt-1 w-full border-gray-300 rounded text-sm font-mono" /></div>
                            <div><label class="block text-xs">CVC</label><input v-model="posForm.card_cvc" maxlength="4" class="mt-1 w-full border-gray-300 rounded text-sm font-mono" /></div>
                        </div>

                        <!-- Sola Account Selector (card only) -->
                        <div v-if="posForm.payment_method !== 'cash'" class="bg-amber-50 border-2 border-amber-300 rounded-lg p-4">
                            <label class="block text-xs font-bold text-amber-900 mb-2">Which Sola account receives this charge? *</label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="cursor-pointer border-2 rounded-lg p-3 text-center transition bg-white"
                                       :class="posForm.sola_account === 'autogo' ? 'border-indigo-500 ring-2 ring-indigo-200' : 'border-gray-200'">
                                    <input v-model="posForm.sola_account" value="autogo" type="radio" class="hidden" />
                                    <div class="font-bold text-sm">AutoGo</div>
                                    <div class="text-[10px] text-gray-500">Lease / finance / body / tow</div>
                                </label>
                                <label class="cursor-pointer border-2 rounded-lg p-3 text-center transition bg-white"
                                       :class="posForm.sola_account === 'high_rental' ? 'border-indigo-500 ring-2 ring-indigo-200' : 'border-gray-200'">
                                    <input v-model="posForm.sola_account" value="high_rental" type="radio" class="hidden" />
                                    <div class="font-bold text-sm">High Car Rental</div>
                                    <div class="text-[10px] text-gray-500">Rentals + security holds</div>
                                </label>
                            </div>
                        </div>

                        <!-- Reference / memo -->
                        <div>
                            <label class="block text-xs">Reference / memo</label>
                            <input v-model="posForm.reference" type="text" class="mt-1 w-full border-gray-300 rounded text-sm" placeholder="Check #, transaction ID, notes…" />
                        </div>

                        <button type="submit" :disabled="posForm.processing || (posForm.payment_method !== 'cash' && !posForm.sola_account)"
                                class="w-full px-6 py-4 bg-emerald-600 text-white rounded-xl font-bold text-lg hover:bg-emerald-700 disabled:opacity-50">
                            {{ posForm.processing ? 'Processing…' : `✓ Charge ${fmt(posForm.amount)}` }}
                        </button>
                    </form>
                </div>

                <!-- Payment History -->
                <div v-if="r.payments?.length" class="bg-white shadow-sm rounded-lg">
                    <div class="p-4 border-b"><h3 class="font-semibold">Payment History</h3></div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Date</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Method</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Amount</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Reference</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="p in r.payments" :key="p.id">
                                <td class="px-4 py-2 text-sm">{{ p.paid_at?.split('T')[0] }}</td>
                                <td class="px-4 py-2 text-sm capitalize">{{ p.payment_method?.replace('_', ' ') }}</td>
                                <td class="px-4 py-2 text-sm text-right font-medium text-green-600">{{ fmt(p.amount) }}</td>
                                <td class="px-4 py-2 text-sm text-gray-500">{{ p.reference || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
