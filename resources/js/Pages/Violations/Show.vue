<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';

const props = defineProps({ violation: Object, types: Object, statuses: Object });
const v = props.violation;
const fmt = (x) => '$' + Number(x || 0).toFixed(2);

const form = useForm({
    fine_amount: v.fine_amount, late_fee: v.late_fee, admin_fee: v.admin_fee,
    paid_amount: v.paid_amount, status: v.status, notes: v.notes,
});
const save = () => form.put(route('violations.update', v.id));

const bill = () => {
    if (!confirm(`Charge customer's card on file for ${fmt(v.total_due)}? This will create a payment record and communication log.`)) return;
    router.post(route('violations.bill-renter', v.id));
};

const statusColor = {
    new: 'bg-amber-100 text-amber-800', received: 'bg-blue-100 text-blue-800',
    renter_notified: 'bg-violet-100 text-violet-800', renter_billed: 'bg-orange-100 text-orange-800',
    paid_by_renter: 'bg-emerald-100 text-emerald-800', paid_by_us: 'bg-gray-100 text-gray-800',
    disputed: 'bg-rose-100 text-rose-800', dismissed: 'bg-slate-100 text-slate-700',
};
</script>

<template>
    <AppLayout title="Violation Details">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('violations.index')" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                <h2 class="font-bold text-xl text-gray-900">{{ types[v.type] }}</h2>
                <span class="text-xs px-2 py-0.5 rounded-full" :class="statusColor[v.status]">{{ statuses[v.status] }}</span>
            </div>
        </template>

        <div class="p-6 max-w-5xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div class="lg:col-span-2 space-y-5">
                <div class="bg-white rounded-xl border p-5">
                    <h3 class="font-semibold mb-3">Violation Info</h3>
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div><dt class="text-xs text-gray-500">Jurisdiction</dt><dd>{{ v.jurisdiction }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Agency</dt><dd>{{ v.issuing_agency || '—' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Summons #</dt><dd class="font-mono">{{ v.summons_number || v.citation_number || '—' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Issued</dt><dd>{{ new Date(v.issued_at).toLocaleString() }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Due</dt><dd>{{ v.due_date || '—' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Location</dt><dd>{{ v.location || '—' }}</dd></div>
                    </dl>
                </div>

                <div class="bg-white rounded-xl border p-5">
                    <h3 class="font-semibold mb-3">Vehicle & Renter</h3>
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div><dt class="text-xs text-gray-500">Plate</dt><dd class="font-mono">{{ v.plate }} {{ v.plate_state ? `(${v.plate_state})` : '' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Vehicle</dt><dd>{{ v.vehicle ? `${v.vehicle.year} ${v.vehicle.make} ${v.vehicle.model}` : '— not matched' }}</dd></div>
                        <div v-if="v.customer" class="col-span-2">
                            <dt class="text-xs text-gray-500">Renter</dt>
                            <dd>
                                <Link :href="route('customers.show', v.customer.id)" class="text-indigo-600 hover:text-indigo-800 font-semibold">
                                    {{ v.customer.first_name }} {{ v.customer.last_name }}
                                </Link>
                                <span class="ml-2 text-xs text-gray-500">{{ v.customer.phone }}</span>
                            </dd>
                        </div>
                        <div v-if="v.reservation" class="col-span-2">
                            <dt class="text-xs text-gray-500">Rental</dt>
                            <dd><Link :href="route('rental.reservations.show', v.reservation.id)" class="text-indigo-600 hover:text-indigo-800">RA#{{ v.reservation.reservation_number }}</Link></dd>
                        </div>
                    </dl>
                    <p v-if="!v.reservation_id" class="text-xs text-amber-700 bg-amber-50 border border-amber-200 p-2 rounded mt-2">
                        ⚠ No rental was matched for this plate on that date. Enter the reservation ID manually in the update form below.
                    </p>
                </div>

                <div v-if="v.photo_path || v.document_path" class="bg-white rounded-xl border p-5">
                    <h3 class="font-semibold mb-3">Evidence</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <a v-if="v.photo_path" :href="`/storage/${v.photo_path}`" target="_blank">
                            <img :src="`/storage/${v.photo_path}`" class="w-full h-48 object-cover rounded-lg border" />
                        </a>
                        <a v-if="v.document_path" :href="`/storage/${v.document_path}`" target="_blank"
                           class="flex items-center justify-center h-48 bg-gray-50 border rounded-lg text-sm text-indigo-600 hover:text-indigo-800">
                            📄 View document
                        </a>
                    </div>
                </div>

                <div v-if="v.notes" class="bg-white rounded-xl border p-5">
                    <h3 class="font-semibold mb-2">Notes</h3>
                    <p class="text-sm whitespace-pre-wrap">{{ v.notes }}</p>
                </div>
            </div>

            <div class="space-y-5">
                <div class="bg-white rounded-xl border p-5">
                    <h3 class="font-semibold mb-3">Amounts</h3>
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between"><span class="text-gray-500">Fine</span><span>{{ fmt(v.fine_amount) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Late Fee</span><span>{{ fmt(v.late_fee) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Admin Fee</span><span>{{ fmt(v.admin_fee) }}</span></div>
                        <div class="flex justify-between border-t pt-1 font-bold"><span>Total</span><span>{{ fmt(Number(v.fine_amount)+Number(v.late_fee)+Number(v.admin_fee)) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Paid</span><span class="text-emerald-600">{{ fmt(v.paid_amount) }}</span></div>
                        <div class="flex justify-between font-bold" :class="v.total_due > 0 ? 'text-red-600' : 'text-emerald-600'">
                            <span>Due</span><span>{{ fmt(v.total_due) }}</span>
                        </div>
                    </div>

                    <button v-if="v.total_due > 0 && v.reservation_id"
                            @click="bill"
                            class="mt-4 w-full py-2 bg-rose-600 text-white rounded-lg text-sm font-semibold hover:bg-rose-700">
                        💳 Bill Renter (charge card on file)
                    </button>
                </div>

                <div class="bg-white rounded-xl border p-5">
                    <h3 class="font-semibold mb-3">Manual Update</h3>
                    <form @submit.prevent="save" class="space-y-2 text-sm">
                        <div class="grid grid-cols-3 gap-2">
                            <div><label class="text-[10px] block">Fine</label><input v-model.number="form.fine_amount" type="number" step="0.01" class="w-full border-gray-300 rounded text-sm" /></div>
                            <div><label class="text-[10px] block">Late</label><input v-model.number="form.late_fee" type="number" step="0.01" class="w-full border-gray-300 rounded text-sm" /></div>
                            <div><label class="text-[10px] block">Admin</label><input v-model.number="form.admin_fee" type="number" step="0.01" class="w-full border-gray-300 rounded text-sm" /></div>
                        </div>
                        <div>
                            <label class="text-[10px] block">Paid so far</label>
                            <input v-model.number="form.paid_amount" type="number" step="0.01" class="w-full border-gray-300 rounded text-sm" />
                        </div>
                        <div>
                            <label class="text-[10px] block">Status</label>
                            <select v-model="form.status" class="w-full border-gray-300 rounded text-sm">
                                <option v-for="(label, val) in statuses" :key="val" :value="val">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] block">Notes</label>
                            <textarea v-model="form.notes" rows="3" class="w-full border-gray-300 rounded text-sm"></textarea>
                        </div>
                        <button type="submit" :disabled="form.processing" class="w-full py-2 bg-indigo-600 text-white rounded text-sm">Save</button>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
