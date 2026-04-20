<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';

const props = defineProps({ lenders: Array });

const form = useForm({
    lender_id: '',
    program_type: 'lease',
    make: '',
    model: '',
    year: new Date().getFullYear(),
    term: 36,
    annual_mileage: 10000,
    residual_pct: '',
    money_factor: '',
    apr: '',
    acquisition_fee: 595,
    min_credit_score: '',
    valid_from: new Date().toISOString().split('T')[0],
    valid_until: '',
    notes: '',
});

const submit = () => form.post(route('lender-programs.store'));
</script>

<template>
    <AppLayout title="Add Lender Program">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('lender-programs.index')" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                <h2 class="font-bold text-xl text-gray-900">Add Lender Program</h2>
            </div>
        </template>

        <div class="p-6">
            <div class="max-w-3xl mx-auto">
                <form @submit.prevent="submit" class="bg-white rounded-xl border p-6 space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Lender *</label>
                            <select v-model="form.lender_id" class="mt-1 block w-full border-gray-300 rounded-lg text-sm">
                                <option value="">Select lender</option>
                                <option v-for="l in lenders" :key="l.id" :value="l.id">{{ l.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Type *</label>
                            <select v-model="form.program_type" class="mt-1 block w-full border-gray-300 rounded-lg text-sm">
                                <option value="lease">Lease</option><option value="finance">Finance</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <h3 class="font-semibold text-sm mb-2">Vehicle Scope (leave blank for "All")</h3>
                        <div class="grid grid-cols-3 gap-3">
                            <input v-model="form.make" type="text" placeholder="Make (e.g. Honda)" class="border-gray-300 rounded-lg text-sm" />
                            <input v-model="form.model" type="text" placeholder="Model (e.g. Odyssey)" class="border-gray-300 rounded-lg text-sm" />
                            <input v-model="form.year" type="number" placeholder="Year" class="border-gray-300 rounded-lg text-sm" />
                        </div>
                    </div>

                    <div v-if="form.program_type === 'lease'">
                        <h3 class="font-semibold text-sm mb-2">Lease Terms</h3>
                        <div class="grid grid-cols-4 gap-3">
                            <div>
                                <label class="text-xs text-gray-500">Term (mo)</label>
                                <select v-model="form.term" class="mt-1 block w-full border-gray-300 rounded-lg text-sm">
                                    <option value="24">24</option><option value="36">36</option><option value="39">39</option><option value="42">42</option><option value="48">48</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-gray-500">Annual Mileage</label>
                                <select v-model="form.annual_mileage" class="mt-1 block w-full border-gray-300 rounded-lg text-sm">
                                    <option value="7500">7,500</option><option value="10000">10,000</option><option value="12000">12,000</option><option value="15000">15,000</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-gray-500">Residual %</label>
                                <input v-model="form.residual_pct" type="number" step="0.01" placeholder="60.50" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" />
                            </div>
                            <div>
                                <label class="text-xs text-gray-500">Money Factor</label>
                                <input v-model="form.money_factor" type="number" step="0.000001" placeholder="0.00185" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" />
                            </div>
                        </div>
                    </div>

                    <div v-if="form.program_type === 'finance'">
                        <h3 class="font-semibold text-sm mb-2">Finance Terms</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs text-gray-500">Term (mo)</label>
                                <select v-model="form.term" class="mt-1 block w-full border-gray-300 rounded-lg text-sm">
                                    <option value="36">36</option><option value="48">48</option><option value="60">60</option><option value="72">72</option><option value="84">84</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-gray-500">APR %</label>
                                <input v-model="form.apr" type="number" step="0.001" placeholder="5.99" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" />
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div><label class="text-xs text-gray-500">Acquisition Fee</label><input v-model="form.acquisition_fee" type="number" step="0.01" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                        <div><label class="text-xs text-gray-500">Min Credit Score</label><input v-model="form.min_credit_score" type="number" placeholder="700" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="text-xs text-gray-500">Valid From</label><input v-model="form.valid_from" type="date" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                        <div><label class="text-xs text-gray-500">Valid Until *</label><input v-model="form.valid_until" type="date" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" /></div>
                    </div>

                    <div><label class="text-xs text-gray-500">Notes</label><textarea v-model="form.notes" rows="2" placeholder="From Honda Financial October 2026 bulletin..." class="mt-1 block w-full border-gray-300 rounded-lg text-sm"></textarea></div>

                    <div class="flex justify-end gap-3">
                        <Link :href="route('lender-programs.index')" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg">Cancel</Link>
                        <button type="submit" :disabled="form.processing" class="px-6 py-2 text-sm text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">Save Program</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
