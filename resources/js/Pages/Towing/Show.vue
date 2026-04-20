<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';

const props = defineProps({ job: Object, trucks: Array, drivers: Array });
const j = props.job;

const form = useForm({
    status: j.status,
    tow_truck_id: j.tow_truck_id || '',
    tow_driver_id: j.tow_driver_id || '',
    priority: j.priority,
    quoted_amount: j.quoted_amount,
    billed_amount: j.billed_amount,
    paid_amount: j.paid_amount,
    notes: j.notes,
});

const update = () => form.put(route('towing.update', j.id));

const statusColor = {
    pending:'bg-amber-100 text-amber-800', dispatched:'bg-blue-100 text-blue-800', en_route:'bg-indigo-100 text-indigo-800',
    on_scene:'bg-violet-100 text-violet-800', in_transit:'bg-cyan-100 text-cyan-800', completed:'bg-emerald-100 text-emerald-800', cancelled:'bg-rose-100 text-rose-800',
};
</script>

<template>
    <AppLayout title="Tow Job Details">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('towing.index')" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                    <h2 class="font-bold text-xl text-gray-900">🚛 {{ j.job_number }}</h2>
                    <span class="text-xs px-2 py-1 rounded-full capitalize" :class="statusColor[j.status]">{{ j.status?.replace('_',' ') }}</span>
                </div>
            </div>
        </template>

        <div class="p-6 max-w-5xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div class="lg:col-span-2 space-y-5">
                <div class="bg-white rounded-xl border p-5">
                    <h3 class="font-semibold mb-3">Job Info</h3>
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div><dt class="text-xs text-gray-500">Caller</dt><dd>{{ j.customer ? `${j.customer.first_name} ${j.customer.last_name}` : (j.caller_name || '—') }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Phone</dt><dd>{{ j.caller_phone || j.customer?.phone || '—' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Insurance</dt><dd>{{ j.insurance_company || '—' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Reference #</dt><dd>{{ j.reference_number || '—' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Reason</dt><dd class="capitalize">{{ j.reason?.replace('_',' ') }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Requested</dt><dd>{{ j.requested_at?.split('T')[0] }}</dd></div>
                    </dl>
                </div>
                <div class="bg-white rounded-xl border p-5">
                    <h3 class="font-semibold mb-3">🚗 Vehicle</h3>
                    <dl class="grid grid-cols-3 gap-3 text-sm">
                        <div><dt class="text-xs text-gray-500">Year/Make/Model</dt><dd>{{ j.vehicle_year }} {{ j.vehicle_make }} {{ j.vehicle_model }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Color</dt><dd>{{ j.vehicle_color || '—' }}</dd></div>
                        <div><dt class="text-xs text-gray-500">Plate</dt><dd>{{ j.vehicle_plate || '—' }}</dd></div>
                        <div class="col-span-3"><dt class="text-xs text-gray-500">VIN</dt><dd class="font-mono text-xs">{{ j.vehicle_vin || '—' }}</dd></div>
                    </dl>
                </div>
                <div class="bg-white rounded-xl border p-5 grid grid-cols-2 gap-4">
                    <div>
                        <h3 class="font-semibold mb-2">📍 Pickup</h3>
                        <div class="text-sm">{{ j.pickup_address }}</div>
                        <div class="text-xs text-gray-500">{{ j.pickup_city }} {{ j.pickup_state }} {{ j.pickup_zip }}</div>
                    </div>
                    <div>
                        <h3 class="font-semibold mb-2">🏁 Drop-off</h3>
                        <div class="text-sm">{{ j.dropoff_address }}</div>
                        <div class="text-xs text-gray-500">{{ j.dropoff_city }} {{ j.dropoff_state }} {{ j.dropoff_zip }}</div>
                    </div>
                </div>
                <div v-if="j.notes" class="bg-white rounded-xl border p-5">
                    <h3 class="font-semibold mb-2">Notes</h3>
                    <p class="text-sm whitespace-pre-wrap">{{ j.notes }}</p>
                </div>
            </div>

            <div class="space-y-5">
                <div class="bg-white rounded-xl border p-5">
                    <h3 class="font-semibold mb-3">Update</h3>
                    <form @submit.prevent="update" class="space-y-3 text-sm">
                        <div>
                            <label class="block text-xs">Status</label>
                            <select v-model="form.status" class="mt-1 w-full border-gray-300 rounded-lg text-sm">
                                <option v-for="s in ['pending','dispatched','en_route','on_scene','in_transit','completed','cancelled']" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs">Priority</label>
                            <select v-model="form.priority" class="mt-1 w-full border-gray-300 rounded-lg text-sm">
                                <option v-for="p in ['low','normal','high','urgent']" :key="p" :value="p">{{ p }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs">Truck</label>
                            <select v-model="form.tow_truck_id" class="mt-1 w-full border-gray-300 rounded-lg text-sm">
                                <option value="">— None —</option>
                                <option v-for="t in trucks" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs">Driver</label>
                            <select v-model="form.tow_driver_id" class="mt-1 w-full border-gray-300 rounded-lg text-sm">
                                <option value="">— None —</option>
                                <option v-for="d in drivers" :key="d.id" :value="d.id">{{ d.name }}</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div><label class="block text-xs">Quoted</label><input v-model="form.quoted_amount" type="number" step="0.01" class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
                            <div><label class="block text-xs">Billed</label><input v-model="form.billed_amount" type="number" step="0.01" class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
                            <div><label class="block text-xs">Paid</label><input v-model="form.paid_amount" type="number" step="0.01" class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
                        </div>
                        <button type="submit" :disabled="form.processing" class="w-full bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-indigo-700">
                            {{ form.processing ? 'Saving…' : 'Save' }}
                        </button>
                    </form>
                </div>
                <div class="bg-white rounded-xl border p-5 text-sm space-y-1">
                    <div class="text-xs text-gray-500">Timeline</div>
                    <div v-if="j.requested_at">📥 Requested {{ new Date(j.requested_at).toLocaleString() }}</div>
                    <div v-if="j.dispatched_at">🚀 Dispatched {{ new Date(j.dispatched_at).toLocaleString() }}</div>
                    <div v-if="j.on_scene_at">📍 On Scene {{ new Date(j.on_scene_at).toLocaleString() }}</div>
                    <div v-if="j.completed_at">✅ Completed {{ new Date(j.completed_at).toLocaleString() }}</div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
