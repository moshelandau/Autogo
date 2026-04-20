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

const pickupForm = useForm({ vehicle_id: r.vehicle_id || '', odometer_out: '', fuel_out: 'full', pickup_notes: '' });
const returnForm = useForm({ odometer_in: '', fuel_in: '', return_notes: '' });
const paymentForm = useForm({ payment_method: 'credit_card', amount: r.outstanding_balance || 0, reference: '' });

const doPickup = () => pickupForm.post(route('rental.reservations.pickup', r.id));
const doReturn = () => returnForm.post(route('rental.reservations.return', r.id));
const doPayment = () => paymentForm.post(route('rental.reservations.payment', r.id));
const doCancel = () => { if (confirm('Cancel this reservation?')) router.post(route('rental.reservations.cancel', r.id)); };

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
                <div v-if="r.status === 'open'" class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
                    <h3 class="font-semibold text-yellow-800 mb-4">Pickup Vehicle</h3>
                    <form @submit.prevent="doPickup" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Vehicle</label>
                            <select v-model="pickupForm.vehicle_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <option v-if="r.vehicle" :value="r.vehicle.id">{{ r.vehicle.year }} {{ r.vehicle.make }} {{ r.vehicle.model }}</option>
                                <option v-for="v in availableVehicles" :key="v.id" :value="v.id">{{ v.year }} {{ v.make }} {{ v.model }} - {{ v.license_plate }}</option>
                            </select>
                        </div>
                        <div><label class="block text-sm font-medium text-gray-700">Odometer</label><input v-model="pickupForm.odometer_out" type="number" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" /></div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Fuel</label>
                            <select v-model="pickupForm.fuel_out" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="full">Full</option><option value="3/4">3/4</option><option value="1/2">1/2</option><option value="1/4">1/4</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" :disabled="pickupForm.processing" class="w-full px-4 py-2 bg-green-600 text-white rounded-md text-sm hover:bg-green-700">Pickup</button>
                        </div>
                    </form>
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

                <!-- Record Payment -->
                <div v-if="r.outstanding_balance > 0 && r.status !== 'cancelled'" class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold mb-4">Record Payment</h3>
                    <form @submit.prevent="doPayment" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Method</label>
                            <select v-model="paymentForm.payment_method" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="credit_card">Credit Card</option><option value="cash">Cash</option><option value="check">Check</option><option value="transfer">Transfer</option>
                            </select>
                        </div>
                        <div><label class="block text-sm font-medium text-gray-700">Amount</label><input v-model="paymentForm.amount" type="number" step="0.01" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" /></div>
                        <div><label class="block text-sm font-medium text-gray-700">Reference</label><input v-model="paymentForm.reference" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" /></div>
                        <div class="flex items-end">
                            <button type="submit" :disabled="paymentForm.processing" class="w-full px-4 py-2 bg-green-600 text-white rounded-md text-sm hover:bg-green-700">Record Payment</button>
                        </div>
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
