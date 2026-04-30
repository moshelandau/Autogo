<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import CustomerSelect from '@/Components/CustomerSelect.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { VEHICLE_MAKES, VEHICLE_COLORS } from '@/Components/vehicleOptions.js';

const props = defineProps({ customers: Array, lenders: Array, salespeople: Array, prefill: { type: Object, default: () => ({}) } });

const STAGES = [
    { value: 'lead', label: 'Lead' },
    { value: 'quote', label: 'Quote' },
    { value: 'application', label: 'Application' },
    { value: 'submission', label: 'Submission' },
    { value: 'pending', label: 'Pending' },
    { value: 'finalize', label: 'Finalize' },
    { value: 'outstanding', label: 'Outstanding' },
    { value: 'complete', label: 'Complete' },
];

const STYLE_OPTIONS = ['Sedan', 'SUV', 'Coupe', 'Truck', 'Van', 'Convertible', 'Hatchback', 'Wagon', 'Crossover'];
const MILES_OPTIONS = [7500, 10000, 12000, 15000, 18000, 20000];

const form = useForm({
    customer_id: props.prefill.customer_id || '', payment_type: 'lease', priority: 'low',
    stage: 'lead',
    preferences: { style: '', budget: '', miles_per_year: '', passengers: '', color: '', brand: '' },
    vehicle_vin: '', vehicle_year: '', vehicle_make: '', vehicle_model: '', vehicle_trim: '',
    msrp: '', sell_price: '', credit_score: '', customer_zip: '', notes: '',
});

const decoding = ref(false);
const decodeVin = async () => {
    if (!form.vehicle_vin || form.vehicle_vin.length !== 17) return;
    decoding.value = true;
    try {
        const response = await fetch(route('leasing.vin-decode'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
            body: JSON.stringify({ vin: form.vehicle_vin }),
        });
        const result = await response.json();
        if (result.success && result.data) {
            form.vehicle_year = result.data.year || form.vehicle_year;
            form.vehicle_make = result.data.make || form.vehicle_make;
            form.vehicle_model = result.data.model || form.vehicle_model;
            form.vehicle_trim = result.data.trim || form.vehicle_trim;
            if (result.data.msrp) form.msrp = result.data.msrp;
        }
    } catch (e) { console.error(e); }
    decoding.value = false;
};

const submit = () => form.post(route('leasing.deals.store'));
</script>

<template>
    <AppLayout title="New Deal">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('leasing.deals.index')" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">New Deal</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="bg-white shadow-sm rounded-lg p-6 space-y-6">
                    <!-- Customer & Type -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Customer *</label>
                            <CustomerSelect v-model="form.customer_id" class="mt-1" />
                            <p v-if="form.errors.customer_id" class="mt-1 text-sm text-red-600">{{ form.errors.customer_id }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Payment Type *</label>
                            <select v-model="form.payment_type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="lease">Lease</option><option value="finance">Finance</option>
                                <option value="one_pay">One-Pay</option><option value="balloon">Balloon</option><option value="cash">Cash</option>
                            </select>
                        </div>
                    </div>

                    <!-- Initial stage (which Kanban column the new deal lands in) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Starting stage *</label>
                        <select v-model="form.stage" class="mt-1 block w-full md:w-64 border-gray-300 rounded-md shadow-sm text-sm">
                            <option v-for="s in STAGES" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                        <p class="mt-1 text-[11px] text-gray-500">Defaults to Lead. Pick a later stage if you're entering an in-progress deal — initial tasks for that stage get auto-generated.</p>
                        <p v-if="form.errors.stage" class="mt-1 text-sm text-red-600">{{ form.errors.stage }}</p>
                    </div>

                    <!-- Lead-stage preferences: what kind of car the customer is shopping for. -->
                    <div>
                        <h3 class="text-lg font-medium mb-3">Customer Preferences</h3>
                        <p class="text-xs text-gray-500 mb-3">Captured at the lead stage so we know what to match. All fields optional — fill what you know.</p>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700">Style</label>
                                <select v-model="form.preferences.style" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                    <option value="">—</option>
                                    <option v-for="s in STYLE_OPTIONS" :key="s">{{ s }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700">Brand</label>
                                <SearchableSelect v-model="form.preferences.brand" :options="VEHICLE_MAKES" placeholder="Kia, Honda, …" input-class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700">Color</label>
                                <SearchableSelect v-model="form.preferences.color" :options="VEHICLE_COLORS" placeholder="White, Black, …" input-class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700">Budget ($/mo)</label>
                                <input v-model="form.preferences.budget" type="number" step="0.01" min="0" placeholder="e.g. 450" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700">Miles / year</label>
                                <select v-model.number="form.preferences.miles_per_year" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                    <option value="">—</option>
                                    <option v-for="m in MILES_OPTIONS" :key="m" :value="m">{{ m.toLocaleString() }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700">Passengers</label>
                                <input v-model.number="form.preferences.passengers" type="number" min="1" max="12" placeholder="e.g. 5" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                            </div>
                        </div>
                    </div>

                    <!-- VIN Decode -->
                    <div>
                        <h3 class="text-lg font-medium mb-3">Vehicle</h3>
                        <div class="flex gap-3 mb-4">
                            <input v-model="form.vehicle_vin" type="text" maxlength="17" placeholder="Enter VIN to auto-fill"
                                   class="flex-1 border-gray-300 rounded-md shadow-sm text-sm font-mono" />
                            <button type="button" @click="decodeVin" :disabled="decoding || !form.vehicle_vin"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm hover:bg-blue-700 disabled:opacity-50">
                                {{ decoding ? 'Decoding...' : 'Decode VIN' }}
                            </button>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div><label class="block text-sm font-medium text-gray-700">Year</label><input v-model="form.vehicle_year" type="number" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" /></div>
                            <div><label class="block text-sm font-medium text-gray-700">Make</label><SearchableSelect v-model="form.vehicle_make" :options="VEHICLE_MAKES" placeholder="Honda, Toyota, …" input-class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" /></div>
                            <div><label class="block text-sm font-medium text-gray-700">Model</label><input v-model="form.vehicle_model" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" /></div>
                            <div><label class="block text-sm font-medium text-gray-700">Trim</label><input v-model="form.vehicle_trim" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" /></div>
                        </div>
                    </div>

                    <!-- Pricing -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div><label class="block text-sm font-medium text-gray-700">MSRP</label><input v-model="form.msrp" type="number" step="0.01" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" /></div>
                        <div><label class="block text-sm font-medium text-gray-700">Sell Price</label><input v-model="form.sell_price" type="number" step="0.01" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" /></div>
                        <div><label class="block text-sm font-medium text-gray-700">Credit Score</label><input v-model="form.credit_score" type="number" min="300" max="850" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" /></div>
                        <div><label class="block text-sm font-medium text-gray-700">ZIP</label><input v-model="form.customer_zip" type="text" maxlength="5" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" /></div>
                    </div>

                    <div><label class="block text-sm font-medium text-gray-700">Notes</label><textarea v-model="form.notes" rows="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></textarea></div>

                    <div class="flex justify-end gap-3">
                        <Link :href="route('leasing.deals.index')" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">Cancel</Link>
                        <button type="submit" :disabled="form.processing" class="px-4 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50">
                            {{ form.processing ? 'Creating...' : 'Create Deal' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
