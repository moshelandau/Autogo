<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CustomerSelect from '@/Components/CustomerSelect.vue';

const props = defineProps({
    customers: Array, vehicles: Array, locations: Array,
    vehicleClasses: { type: Array, default: () => [] },
    prefill: { type: Object, default: () => ({}) },
});

const selectedCustomer = ref(null); // populated from CustomerSelect's @select event

const form = useForm({
    customer_id: props.prefill.customer_id || '',
    vehicle_id:  props.prefill.vehicle_id || '',
    vehicle_class: props.prefill.vehicle_class || 'suv',
    pickup_location_id: '', return_location_id: '',
    pickup_date: props.prefill.pickup_date || '',
    return_date: '',
    daily_rate: 65, security_deposit: 0, discount_amount: 0, notes: '',
});

// If pre-filled with customer, hydrate selectedCustomer
if (props.prefill.customer_id) {
    const c = props.customers?.find(x => x.id === props.prefill.customer_id);
    if (c) selectedCustomer.value = { id: c.id, label: `${c.first_name} ${c.last_name}`, outstanding_balance: 0 };
}

const selectedVehicle = computed(() => props.vehicles?.find(v => v.id == form.vehicle_id));
const totalDays = computed(() => {
    if (!form.pickup_date || !form.return_date) return 0;
    return Math.max(1, Math.ceil((new Date(form.return_date) - new Date(form.pickup_date)) / 86400000));
});
const estimatedTotal = computed(() => (form.daily_rate * totalDays.value) - form.discount_amount);

const onVehicleSelect = () => {
    if (selectedVehicle.value) {
        form.daily_rate = selectedVehicle.value.daily_rate;
        form.vehicle_class = selectedVehicle.value.vehicle_class;
    }
};

const submit = () => form.post(route('rental.reservations.store'));
</script>

<template>
    <AppLayout title="New Reservation">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('rental.reservations.index')" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">New Reservation</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="bg-white shadow-sm rounded-lg p-6 space-y-6">
                    <!-- Customer -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Customer *</label>
                        <CustomerSelect v-model="form.customer_id" @select="selectedCustomer = $event" class="mt-1" />
                        <p v-if="form.errors.customer_id" class="mt-1 text-sm text-red-600">{{ form.errors.customer_id }}</p>

                        <!-- Outstanding balance warning -->
                        <div v-if="selectedCustomer && selectedCustomer.outstanding_balance > 0"
                             class="mt-3 bg-red-50 border-2 border-red-300 rounded-lg p-3 flex items-start gap-2">
                            <span class="text-2xl">⚠️</span>
                            <div class="flex-1">
                                <div class="font-bold text-red-900 text-sm">Customer owes ${{ Number(selectedCustomer.outstanding_balance).toFixed(2) }} from past rentals.</div>
                                <div class="text-xs text-red-800">Collect outstanding balance before starting a new rental, or get manager approval.</div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Vehicle -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Vehicle</label>
                            <select v-model="form.vehicle_id" @change="onVehicleSelect" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="">Assign later</option>
                                <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.year }} {{ v.make }} {{ v.model }} - {{ v.license_plate || 'No plate' }} (${{ v.daily_rate }}/day)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Vehicle Class</label>
                            <select v-model="form.vehicle_class" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <!-- Live inventory counts from fleet: "5 Pass SUV — 3 of 6 available" -->
                                <option v-for="c in vehicleClasses" :key="c.class" :value="c.class"
                                        :disabled="c.available === 0"
                                        :class="c.available === 0 ? 'text-red-500' : ''">
                                    {{ c.class }} — {{ c.available }} of {{ c.total }} available{{ c.available === 0 ? ' (all rented)' : '' }}
                                </option>
                                <!-- Fallback static list when fleet is empty -->
                                <template v-if="!vehicleClasses?.length">
                                    <option value="car">Car</option><option value="suv">SUV (5 Pass)</option><option value="minivan">Minivan</option><option value="truck">Truck</option>
                                </template>
                            </select>
                            <p class="mt-1 text-[10px] text-gray-500">Class count is live across the fleet. Disabled = nothing free now.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Pickup Location *</label>
                            <select v-model="form.pickup_location_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="">Select</option>
                                <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
                            </select>
                            <p v-if="form.errors.pickup_location_id" class="mt-1 text-sm text-red-600">{{ form.errors.pickup_location_id }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Return Location</label>
                            <select v-model="form.return_location_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="">Same as pickup</option>
                                <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Pickup Date/Time *</label>
                            <input v-model="form.pickup_date" type="datetime-local" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                            <p v-if="form.errors.pickup_date" class="mt-1 text-sm text-red-600">{{ form.errors.pickup_date }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Return Date/Time *</label>
                            <input v-model="form.return_date" type="datetime-local" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                            <p v-if="form.errors.return_date" class="mt-1 text-sm text-red-600">{{ form.errors.return_date }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Daily Rate *</label>
                            <input v-model="form.daily_rate" type="number" step="0.01" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Security Deposit</label>
                            <input v-model="form.security_deposit" type="number" step="0.01" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Discount</label>
                            <input v-model="form.discount_amount" type="number" step="0.01" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                        </div>
                        <div class="flex flex-col justify-end">
                            <div class="bg-gray-50 rounded-md p-3 text-center">
                                <div class="text-xs text-gray-500">{{ totalDays }} days</div>
                                <div class="text-xl font-bold text-green-700">${{ estimatedTotal.toFixed(2) }}</div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Notes</label>
                        <textarea v-model="form.notes" rows="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm"></textarea>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Link :href="route('rental.reservations.index')" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">Cancel</Link>
                        <button type="submit" :disabled="form.processing" class="px-4 py-2 text-sm text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50">
                            {{ form.processing ? 'Creating...' : 'Create Reservation' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
