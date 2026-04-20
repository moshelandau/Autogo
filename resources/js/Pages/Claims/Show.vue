<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ claim: Object });
const c = props.claim;
const fmt = (v) => v ? '$' + parseFloat(v).toLocaleString(undefined, {minimumFractionDigits: 2}) : '-';
const activeTab = ref('steps');

const commentForm = useForm({ body: '' });
const addComment = () => { commentForm.post(route('claims.comment', c.id), { onSuccess: () => commentForm.reset() }); };

const insuranceForm = useForm({ insurance_company: '', claim_number: '', policy_number: '' });
const addInsurance = () => { insuranceForm.post(route('claims.add-insurance', c.id), { onSuccess: () => insuranceForm.reset() }); };

const supplementForm = useForm({ amount: '', requested_date: new Date().toISOString().split('T')[0], description: '' });
const addSupplement = () => { supplementForm.post(route('claims.add-supplement', c.id), { onSuccess: () => supplementForm.reset() }); };

const toggleStep = (stepId, isCompleted) => {
    if (isCompleted) {
        router.post(route('claims.uncomplete-step', { claim: c.id, step: stepId }));
    } else {
        router.post(route('claims.complete-step', { claim: c.id, step: stepId }));
    }
};

const statusColors = { new: 'bg-blue-100 text-blue-800', filed: 'bg-yellow-100 text-yellow-800', in_progress: 'bg-orange-100 text-orange-800', completed: 'bg-green-100 text-green-800' };
</script>

<template>
    <AppLayout title="Claim Detail">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('claims.index')" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                    <h2 class="font-bold text-xl text-gray-900">{{ c.customer?.first_name }} {{ c.customer?.last_name }}</h2>
                    <span class="px-3 py-1 text-sm rounded-full capitalize" :class="statusColors[c.status]">{{ c.status?.replace('_', ' ') }}</span>
                </div>
            </div>
        </template>

        <div class="p-6 space-y-6">
            <!-- Top Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <!-- Insurance Entries -->
                <div class="bg-white rounded-xl border p-5">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">Insurance</h3>
                    <div v-for="ie in c.insurance_entries" :key="ie.id" class="mb-3 last:mb-0">
                        <div class="font-bold text-sm">{{ ie.insurance_company }}</div>
                        <div class="text-xs text-gray-500 font-mono">#{{ ie.claim_number }}</div>
                    </div>
                    <form v-if="activeTab === 'insurance'" @submit.prevent="addInsurance" class="mt-3 pt-3 border-t space-y-2">
                        <input v-model="insuranceForm.insurance_company" type="text" placeholder="Company" class="block w-full border-gray-300 rounded-lg text-xs" />
                        <input v-model="insuranceForm.claim_number" type="text" placeholder="Claim #" class="block w-full border-gray-300 rounded-lg text-xs" />
                        <button type="submit" class="text-xs text-indigo-600 font-medium">Add</button>
                    </form>
                    <button v-else @click="activeTab = 'insurance'" class="mt-2 text-xs text-indigo-600">+ Add Insurance</button>
                </div>

                <!-- Adjuster / Appraiser -->
                <div class="bg-white rounded-xl border p-5">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">Adjuster</h3>
                    <div class="text-sm space-y-1">
                        <div><span class="text-gray-500">Name:</span> {{ c.adjuster_name || '-' }}</div>
                        <div><span class="text-gray-500">Phone:</span> {{ c.adjuster_phone || '-' }}</div>
                        <div><span class="text-gray-500">Email:</span> {{ c.adjuster_email || '-' }}</div>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mt-4 mb-3">Appraiser</h3>
                    <div class="text-sm space-y-1">
                        <div><span class="text-gray-500">Name:</span> {{ c.appraiser_name || '-' }}</div>
                        <div><span class="text-gray-500">Phone:</span> {{ c.appraiser_phone || '-' }}</div>
                        <div><span class="text-gray-500">Email:</span> {{ c.appraiser_email || '-' }}</div>
                    </div>
                </div>

                <!-- Financial -->
                <div class="bg-white rounded-xl border p-5">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">Financial</h3>
                    <div class="text-sm space-y-2">
                        <div class="flex justify-between"><span class="text-gray-500">Estimate</span><span class="font-medium">{{ fmt(c.estimate_amount) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Supplements</span><span class="font-medium">{{ fmt(c.supplement_amount) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Approved</span><span class="font-bold text-green-700">{{ fmt(c.approved_amount) }}</span></div>
                        <div class="flex justify-between border-t pt-2"><span class="text-gray-500">Paid</span><span class="font-bold text-green-600">{{ fmt(c.paid_amount) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Towing</span><span>{{ fmt(c.towing_amount) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Rental</span><span>{{ fmt(c.rental_amount) }}</span></div>
                    </div>
                </div>
            </div>

            <!-- Accident & Vehicle Info -->
            <div class="bg-white rounded-xl border p-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Accident</h3>
                        <div class="text-sm space-y-1">
                            <div><span class="text-gray-500">Date:</span> {{ c.accident_date || '-' }}</div>
                            <div><span class="text-gray-500">Location:</span> {{ c.accident_location || '-' }}</div>
                            <div v-if="c.story"><span class="text-gray-500">Story:</span> <span class="whitespace-pre-wrap">{{ c.story }}</span></div>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Vehicle</h3>
                        <div class="text-sm space-y-1">
                            <div class="font-medium">{{ c.vehicle_year }} {{ c.vehicle_make }} {{ c.vehicle_model }}</div>
                            <div v-if="c.vehicle_vin"><span class="text-gray-500">VIN:</span> <span class="font-mono text-xs">{{ c.vehicle_vin }}</span></div>
                            <div v-if="c.vehicle_plate"><span class="text-gray-500">Plate:</span> {{ c.vehicle_plate }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 9-Step Checklist -->
            <div class="bg-white rounded-xl border p-5">
                <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">
                    Claim Steps
                    <span class="ml-2 text-xs font-mono" :class="c.steps?.filter(s => s.is_completed).length === c.steps?.length ? 'text-green-600' : 'text-gray-400'">
                        {{ c.steps?.filter(s => s.is_completed).length }}/{{ c.steps?.length }}
                    </span>
                </h3>
                <div class="space-y-1">
                    <div v-for="step in c.steps" :key="step.id"
                         class="flex items-center gap-3 py-2.5 px-3 rounded-xl hover:bg-gray-50 transition-colors cursor-pointer"
                         @click="toggleStep(step.id, step.is_completed)">
                        <div class="w-6 h-6 rounded-full border-2 flex items-center justify-center flex-shrink-0 transition-colors"
                             :class="step.is_completed ? 'bg-green-500 border-green-500 text-white' : 'border-gray-300 hover:border-green-400'">
                            <svg v-if="step.is_completed" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                        </div>
                        <span class="text-sm flex-1" :class="step.is_completed ? 'line-through text-gray-400' : 'text-gray-900 font-medium'">
                            {{ step.name }}
                        </span>
                        <span v-if="step.completed_at" class="text-xs text-gray-400">{{ new Date(step.completed_at).toLocaleDateString() }}</span>
                    </div>
                </div>
            </div>

            <!-- Supplements -->
            <div v-if="c.supplements?.length || true" class="bg-white rounded-xl border p-5">
                <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">Supplements</h3>
                <div v-for="s in c.supplements" :key="s.id" class="flex justify-between items-center py-2 border-b last:border-0 text-sm">
                    <div>
                        <span class="font-medium">{{ fmt(s.amount) }}</span>
                        <span class="text-gray-500 ml-2">{{ s.description || '' }}</span>
                    </div>
                    <div class="flex gap-3 text-xs text-gray-400">
                        <span>Requested: {{ s.requested_date }}</span>
                        <span class="px-2 py-0.5 rounded-full capitalize" :class="s.status === 'approved' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'">{{ s.status }}</span>
                    </div>
                </div>
                <form @submit.prevent="addSupplement" class="mt-3 pt-3 border-t grid grid-cols-4 gap-3">
                    <input v-model="supplementForm.amount" type="number" step="0.01" placeholder="Amount" class="border-gray-300 rounded-lg text-xs" />
                    <input v-model="supplementForm.requested_date" type="date" class="border-gray-300 rounded-lg text-xs" />
                    <input v-model="supplementForm.description" type="text" placeholder="Description" class="border-gray-300 rounded-lg text-xs" />
                    <button type="submit" :disabled="supplementForm.processing" class="bg-indigo-600 text-white rounded-lg text-xs hover:bg-indigo-700">Add Supplement</button>
                </form>
            </div>

            <!-- Comments -->
            <div class="bg-white rounded-xl border p-5">
                <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">Comments & Activity</h3>
                <form @submit.prevent="addComment" class="flex gap-3 mb-4">
                    <input v-model="commentForm.body" type="text" placeholder="Add a comment..." class="flex-1 border-gray-300 rounded-lg text-sm" />
                    <button type="submit" :disabled="commentForm.processing || !commentForm.body" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700 disabled:opacity-50">Post</button>
                </form>
                <div v-for="comment in c.comments" :key="comment.id" class="border-b last:border-0 py-3">
                    <div class="flex justify-between text-xs text-gray-400 mb-1">
                        <span class="font-medium text-gray-600">{{ comment.user?.name || 'System' }}</span>
                        <span>{{ new Date(comment.created_at).toLocaleString() }}</span>
                    </div>
                    <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ comment.body }}</p>
                </div>
                <p v-if="!c.comments?.length" class="text-sm text-gray-400">No comments yet.</p>
            </div>
        </div>
    </AppLayout>
</template>
