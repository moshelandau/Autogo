<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    session:   { type: Object, required: true },
    collected: { type: Object, default: () => ({}) },
});

const c = (k) => props.collected?.[k] ?? '';
const has = (k) => c(k) !== '' && c(k) !== null && c(k) !== undefined;

const maskSsn = (v) => {
    const d = String(v || '').replace(/\D/g, '');
    return d.length === 9 ? `***-**-${d.slice(-4)}` : (v || '');
};

const status = computed(() => {
    if (props.session.completed_at) return { label: '✓ Completed', color: 'bg-emerald-100 text-emerald-800 border-emerald-300' };
    if (props.session.aborted_at)   return { label: 'Aborted',     color: 'bg-gray-200 text-gray-700 border-gray-300' };
    return { label: 'In progress',  color: 'bg-blue-100 text-blue-800 border-blue-300' };
});

const finalizeNow = () => {
    if (!confirm('Force-finalize this intake now? Creates the Deal/Reservation/Task with whatever data we have.')) return;
    router.post(route('bot-sessions.finalize', props.session.id), {}, { preserveScroll: true });
};
const abortNow = () => {
    if (!confirm('Mark this intake as aborted? It will move out of the in-progress list.')) return;
    router.post(route('bot-sessions.abort', props.session.id), {}, { preserveScroll: true });
};

const flowTitle = computed(() => ({
    lease: 'Leasing Application',
    finance: 'Finance Application',
    rental: 'Rental Reservation',
    towing: 'Towing Request',
    bodyshop: 'Bodyshop Estimate',
}[props.session.flow] || 'SMS Intake'));
</script>

<template>
    <AppLayout :title="`Bot intake #${session.id}`">
        <template #header>
            <div class="flex items-center gap-3 flex-wrap">
                <Link :href="route('bot-sessions.index')" class="text-indigo-600 hover:text-indigo-800 text-sm">← All intakes</Link>
                <h2 class="font-semibold text-xl text-gray-800">{{ flowTitle }} #{{ session.id }}</h2>
                <span class="px-2 py-0.5 text-xs font-semibold rounded-full border" :class="status.color">{{ status.label }}</span>
                <div class="ml-auto flex items-center gap-2">
                    <button v-if="!session.completed_at && !session.aborted_at" @click="finalizeNow"
                            class="text-sm bg-emerald-600 text-white px-3 py-1.5 rounded-md hover:bg-emerald-700">
                        ✓ Finalize now
                    </button>
                    <button v-if="!session.completed_at && !session.aborted_at" @click="abortNow"
                            class="text-sm bg-gray-200 text-gray-700 px-3 py-1.5 rounded-md hover:bg-gray-300">
                        ✗ Abort
                    </button>
                    <Link v-if="session.deal_id" :href="route('leasing.deals.show', session.deal_id)"
                          class="text-sm text-indigo-600 hover:text-indigo-800">Deal →</Link>
                    <Link v-if="session.deal_id" :href="route('leasing.deals.application.show', session.deal_id)"
                          class="text-sm bg-emerald-600 text-white px-3 py-1.5 rounded-md hover:bg-emerald-700">📄 Application Form</Link>
                    <Link :href="route('sms.show', session.phone)"
                          class="text-sm bg-indigo-600 text-white px-3 py-1.5 rounded-md hover:bg-indigo-700">SMS Thread</Link>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- "Paper form" container -->
            <div class="bg-white shadow-md rounded-lg p-8 space-y-6">
                <!-- Header band -->
                <div class="border-b pb-4 flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-red-600">AutoGo</h1>
                        <p class="text-xs text-gray-500">279 route 32 · Central Valley NY 10917 · (845) 751-1133</p>
                    </div>
                    <div class="text-right text-xs text-gray-500">
                        <div>From: <span class="font-mono">{{ session.phone }}</span></div>
                        <div>Started: {{ new Date(session.created_at).toLocaleString() }}</div>
                    </div>
                </div>

                <!-- LEASE / FINANCE FLOW -->
                <template v-if="['lease','finance'].includes(session.flow)">
                    <div>
                        <h3 class="bg-gray-100 px-3 py-1.5 text-sm font-bold uppercase tracking-wider text-gray-700">Applicant</h3>
                        <table class="w-full text-sm mt-2 border-collapse">
                            <tr><td colspan="3" class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Name</div>{{ c('first_name') }} {{ c('last_name') }}</td></tr>
                            <tr>
                                <td class="border p-2 w-1/3"><div class="text-[10px] text-gray-500 uppercase">Date of birth</div>{{ c('date_of_birth') }}</td>
                                <td class="border p-2 w-1/3"><div class="text-[10px] text-gray-500 uppercase">SSN</div><span class="font-mono">{{ maskSsn(c('ssn')) }}</span></td>
                                <td class="border p-2 w-1/3"><div class="text-[10px] text-gray-500 uppercase">Email</div>{{ c('email') }}</td>
                            </tr>
                            <tr><td colspan="3" class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Address</div>{{ c('address') }}</td></tr>
                            <tr>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">City</div>{{ c('city') }}</td>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">State</div>{{ c('state') }}</td>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">ZIP</div>{{ c('zip') }}</td>
                            </tr>
                            <tr>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Own / Rent</div>{{ c('own_or_rent') }}</td>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Monthly housing</div>{{ c('monthly_housing') ? '$' + c('monthly_housing') : '' }}</td>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Years at addr</div>{{ c('years_at_address') }}</td>
                            </tr>
                        </table>
                    </div>
                    <div v-if="has('employer') || has('annual_income')">
                        <h3 class="bg-gray-100 px-3 py-1.5 text-sm font-bold uppercase tracking-wider text-gray-700">Employment</h3>
                        <table class="w-full text-sm mt-2 border-collapse">
                            <tr><td colspan="3" class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Employer</div>{{ c('employer') }}</td></tr>
                            <tr>
                                <td colspan="2" class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Address</div>{{ c('employer_address') }}</td>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Years employed</div>{{ c('years_employed') }}</td>
                            </tr>
                            <tr>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">City</div>{{ c('employer_city') }}</td>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">State</div>{{ c('employer_state') }}</td>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">ZIP</div>{{ c('employer_zip') }}</td>
                            </tr>
                            <tr>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Phone</div>{{ c('employer_phone') }}</td>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Position</div>{{ c('position') }}</td>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Annual income</div>{{ c('annual_income') ? '$' + Number(c('annual_income')).toLocaleString() : '' }}</td>
                            </tr>
                        </table>
                    </div>
                    <div v-if="String(c('has_coapplicant')).toLowerCase() === 'yes'">
                        <h3 class="bg-gray-100 px-3 py-1.5 text-sm font-bold uppercase tracking-wider text-gray-700">Co-Applicant</h3>
                        <table class="w-full text-sm mt-2 border-collapse">
                            <tr><td colspan="3" class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Name</div>{{ c('co_first_name') }} {{ c('co_last_name') }}</td></tr>
                            <tr>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Date of birth</div>{{ c('co_date_of_birth') }}</td>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">SSN</div><span class="font-mono">{{ maskSsn(c('co_ssn')) }}</span></td>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Phone</div>{{ c('co_phone') }}</td>
                            </tr>
                            <tr><td colspan="3" class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Address</div>{{ c('co_address') }}, {{ c('co_city') }}, {{ c('co_state') }} {{ c('co_zip') }}</td></tr>
                            <tr>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Employer</div>{{ c('co_employer') }}</td>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Position</div>{{ c('co_position') }}</td>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Annual income</div>{{ c('co_annual_income') ? '$' + Number(c('co_annual_income')).toLocaleString() : '' }}</td>
                            </tr>
                        </table>
                    </div>
                    <div v-if="has('vehicle_interest')">
                        <h3 class="bg-gray-100 px-3 py-1.5 text-sm font-bold uppercase tracking-wider text-gray-700">Vehicle of Interest</h3>
                        <table class="w-full text-sm mt-2 border-collapse"><tr><td class="border p-2">{{ c('vehicle_interest') }}</td></tr></table>
                    </div>
                </template>

                <!-- RENTAL FLOW -->
                <template v-else-if="session.flow === 'rental'">
                    <div>
                        <h3 class="bg-gray-100 px-3 py-1.5 text-sm font-bold uppercase tracking-wider text-gray-700">Renter</h3>
                        <table class="w-full text-sm mt-2 border-collapse">
                            <tr><td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Name</div>{{ c('first_name') }} {{ c('last_name') }}</td>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">DOB</div>{{ c('date_of_birth') }}</td>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Email</div>{{ c('email') }}</td></tr>
                            <tr><td colspan="3" class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Address</div>{{ c('address') }}, {{ c('city') }}, {{ c('state') }} {{ c('zip') }}</td></tr>
                            <tr>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Driver's License #</div>{{ c('drivers_license_number') }}</td>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">DL State</div>{{ c('dl_state') }}</td>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">DL Expiration</div>{{ c('dl_expiration') }}</td>
                            </tr>
                            <tr v-if="has('license_image_url')"><td colspan="3" class="border p-2">
                                <div class="text-[10px] text-gray-500 uppercase mb-1">License image</div>
                                <a :href="c('license_image_url')" target="_blank"><img :src="c('license_image_url')" class="max-h-48 rounded border" /></a>
                            </td></tr>
                        </table>
                    </div>
                    <div>
                        <h3 class="bg-gray-100 px-3 py-1.5 text-sm font-bold uppercase tracking-wider text-gray-700">Insurance</h3>
                        <table class="w-full text-sm mt-2 border-collapse">
                            <tr>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Has insurance?</div>{{ c('has_insurance') || '—' }}</td>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Company</div>{{ c('insurance_company') || '—' }}</td>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Policy #</div>{{ c('insurance_policy') || '—' }}</td>
                            </tr>
                        </table>
                    </div>
                    <div>
                        <h3 class="bg-gray-100 px-3 py-1.5 text-sm font-bold uppercase tracking-wider text-gray-700">Reservation</h3>
                        <table class="w-full text-sm mt-2 border-collapse">
                            <tr>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Pickup</div>{{ c('pickup_date') }}</td>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Return</div>{{ c('return_date') }}</td>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Location</div>{{ c('pickup_location') }}</td>
                            </tr>
                            <tr><td colspan="3" class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Vehicle preference</div>{{ c('vehicle_preference') }}</td></tr>
                        </table>
                    </div>
                </template>

                <!-- TOWING -->
                <template v-else-if="session.flow === 'towing'">
                    <div>
                        <h3 class="bg-gray-100 px-3 py-1.5 text-sm font-bold uppercase tracking-wider text-gray-700">Towing Request</h3>
                        <table class="w-full text-sm mt-2 border-collapse">
                            <tr><td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Name</div>{{ c('first_name') }} {{ c('last_name') }}</td>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Urgency</div><span class="font-bold uppercase">{{ c('urgency') }}</span></td></tr>
                            <tr><td colspan="2" class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Pickup location</div>{{ c('pickup_location') }}</td></tr>
                            <tr><td colspan="2" class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Drop-off location</div>{{ c('dropoff_location') }}</td></tr>
                            <tr><td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Vehicle</div>{{ c('vehicle') }}</td>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">All wheels turn?</div>{{ c('wheels_turn') }}</td></tr>
                            <tr><td colspan="2" class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Situation</div>{{ c('situation') }}</td></tr>
                        </table>
                    </div>
                </template>

                <!-- BODYSHOP -->
                <template v-else-if="session.flow === 'bodyshop'">
                    <div>
                        <h3 class="bg-gray-100 px-3 py-1.5 text-sm font-bold uppercase tracking-wider text-gray-700">Bodyshop Estimate</h3>
                        <table class="w-full text-sm mt-2 border-collapse">
                            <tr><td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Name</div>{{ c('first_name') }} {{ c('last_name') }}</td>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Vehicle</div>{{ c('vehicle') }}</td></tr>
                            <tr><td colspan="2" class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Damage area</div>{{ c('damage_area') }}</td></tr>
                            <tr>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Photos sent?</div>{{ c('has_photos') || '—' }}</td>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Insurance claim?</div>{{ c('is_insurance_claim') || 'no' }}</td>
                            </tr>
                            <tr v-if="String(c('is_insurance_claim')).toLowerCase() === 'yes'">
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Insurance company</div>{{ c('insurance_company') }}</td>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Claim #</div>{{ c('claim_number') }}</td>
                            </tr>
                            <tr>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Preferred drop-off</div>{{ c('preferred_drop_off') }}</td>
                                <td class="border p-2"><div class="text-[10px] text-gray-500 uppercase">Needs rental?</div>{{ c('rental_needed') || 'no' }}</td>
                            </tr>
                        </table>
                    </div>
                </template>

                <p v-if="session.completed_at" class="text-xs text-emerald-700 italic pt-3 border-t">
                    ✓ Application completed on {{ new Date(session.completed_at).toLocaleString() }}
                </p>
                <p v-else-if="session.aborted_at" class="text-xs text-gray-500 italic pt-3 border-t">
                    Aborted on {{ new Date(session.aborted_at).toLocaleString() }}
                </p>
                <p v-else class="text-xs text-blue-700 italic pt-3 border-t">
                    🟡 In progress — current step: <span class="font-mono">{{ session.current_step }}</span>
                </p>
            </div>
        </div>
    </AppLayout>
</template>
