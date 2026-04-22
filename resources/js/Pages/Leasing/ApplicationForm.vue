<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref, reactive } from 'vue';
import axios from 'axios';

const props = defineProps({
    deal:    { type: Object, required: true },
    session: { type: Object, default: null },
    fields:  { type: Object, default: () => ({}) },
});

const local = reactive({ ...props.fields });
const savingKey = ref('');
const savedKey  = ref('');

const onChange = async (key) => {
    savingKey.value = key;
    try {
        await axios.put(route('leasing.deals.application.update', props.deal.id), { key, value: local[key] ?? '' });
        savedKey.value = key;
        setTimeout(() => { if (savedKey.value === key) savedKey.value = ''; }, 1500);
    } catch (e) {
        console.error(e);
        alert('Save failed: ' + (e.response?.data?.message || e.message));
    } finally {
        savingKey.value = '';
    }
};

const emailOpen = ref(false);
const emailForm = useForm({ to: '', subject: `AutoGo Leasing Application — Deal #${props.deal.deal_number}`, message: '' });
const sendEmail = () => emailForm.post(route('leasing.deals.application.email', props.deal.id), {
    preserveScroll: true,
    onSuccess: () => { emailOpen.value = false; emailForm.reset('to', 'message'); },
});

const openPdf = () => window.open(route('leasing.deals.application.pdf', props.deal.id), '_blank');
</script>

<template>
    <AppLayout :title="`Application — Deal #${deal.deal_number}`">
        <template #header>
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-3">
                    <Link :href="route('leasing.deals.show', deal.id)" class="text-indigo-600 hover:text-indigo-800 text-sm">← Deal #{{ deal.deal_number }}</Link>
                    <h2 class="font-semibold text-xl text-gray-800">📄 Leasing Application — editable</h2>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="openPdf" class="px-4 py-2 text-sm bg-gray-700 text-white rounded-md hover:bg-gray-800">
                        🖨 Preview / Download PDF
                    </button>
                    <button @click="emailOpen = true" class="px-4 py-2 text-sm bg-emerald-600 text-white rounded-md hover:bg-emerald-700">
                        ✉️ Email to Dealer
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-8 space-y-6">
                <!-- Header -->
                <div class="border-b pb-4 flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-red-600">AutoGo</h1>
                        <p class="text-xs text-gray-600">279 route 32 · Central Valley NY 10917 · (845)-751-1133</p>
                    </div>
                    <p class="text-xs text-gray-600">Hershy@autogoco.com</p>
                </div>

                <!-- APPLICANT -->
                <div>
                    <h3 class="bg-gray-100 px-3 py-1.5 text-sm font-bold uppercase tracking-wider text-gray-700">Applicant Information</h3>
                    <div class="grid grid-cols-12 gap-3 mt-3 text-sm">
                        <div class="col-span-12"><label class="text-xs text-gray-500">Name</label><input v-model="local.applicant_name" @blur="onChange('applicant_name')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                        <div class="col-span-4"><label class="text-xs text-gray-500">Date of birth</label><input v-model="local.applicant_dob" @blur="onChange('applicant_dob')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                        <div class="col-span-4"><label class="text-xs text-gray-500">SSN</label><input v-model="local.applicant_ssn" @blur="onChange('applicant_ssn')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1 font-mono" /></div>
                        <div class="col-span-4"><label class="text-xs text-gray-500">Phone</label><input v-model="local.applicant_phone" @blur="onChange('applicant_phone')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                        <div class="col-span-12"><label class="text-xs text-gray-500">Current address</label><input v-model="local.applicant_address" @blur="onChange('applicant_address')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                        <div class="col-span-5"><label class="text-xs text-gray-500">City</label><input v-model="local.applicant_city" @blur="onChange('applicant_city')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                        <div class="col-span-3"><label class="text-xs text-gray-500">State</label><input v-model="local.applicant_state" @blur="onChange('applicant_state')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1 uppercase" maxlength="2" /></div>
                        <div class="col-span-4"><label class="text-xs text-gray-500">ZIP</label><input v-model="local.applicant_zip" @blur="onChange('applicant_zip')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                        <div class="col-span-4"><label class="text-xs text-gray-500">Own / Rent</label><input v-model="local.applicant_own_or_rent" @blur="onChange('applicant_own_or_rent')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                        <div class="col-span-4"><label class="text-xs text-gray-500">Monthly payment / rent</label><input v-model="local.applicant_monthly_housing" @blur="onChange('applicant_monthly_housing')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                        <div class="col-span-4"><label class="text-xs text-gray-500">Years at address</label><input v-model="local.applicant_years_at_addr" @blur="onChange('applicant_years_at_addr')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                    </div>
                </div>

                <!-- EMPLOYMENT -->
                <div>
                    <h3 class="bg-gray-100 px-3 py-1.5 text-sm font-bold uppercase tracking-wider text-gray-700">Employment Information</h3>
                    <div class="grid grid-cols-12 gap-3 mt-3 text-sm">
                        <div class="col-span-12"><label class="text-xs text-gray-500">Current employer</label><input v-model="local.applicant_employer" @blur="onChange('applicant_employer')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                        <div class="col-span-8"><label class="text-xs text-gray-500">Employer address</label><input v-model="local.applicant_employer_address" @blur="onChange('applicant_employer_address')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                        <div class="col-span-4"><label class="text-xs text-gray-500">Years employed</label><input v-model="local.applicant_years_employed" @blur="onChange('applicant_years_employed')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                        <div class="col-span-5"><label class="text-xs text-gray-500">City</label><input v-model="local.applicant_employer_city" @blur="onChange('applicant_employer_city')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                        <div class="col-span-3"><label class="text-xs text-gray-500">State</label><input v-model="local.applicant_employer_state" @blur="onChange('applicant_employer_state')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1 uppercase" maxlength="2" /></div>
                        <div class="col-span-4"><label class="text-xs text-gray-500">ZIP</label><input v-model="local.applicant_employer_zip" @blur="onChange('applicant_employer_zip')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                        <div class="col-span-6"><label class="text-xs text-gray-500">Phone</label><input v-model="local.applicant_employer_phone" @blur="onChange('applicant_employer_phone')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                        <div class="col-span-6"><label class="text-xs text-gray-500">E-mail</label><input v-model="local.applicant_employer_email" @blur="onChange('applicant_employer_email')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                        <div class="col-span-6"><label class="text-xs text-gray-500">Position</label><input v-model="local.applicant_position" @blur="onChange('applicant_position')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                        <div class="col-span-6"><label class="text-xs text-gray-500">Annual income</label><input v-model="local.applicant_annual_income" @blur="onChange('applicant_annual_income')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                    </div>
                </div>

                <!-- CO-APPLICANT -->
                <div>
                    <h3 class="bg-gray-100 px-3 py-1.5 text-sm font-bold uppercase tracking-wider text-gray-700">Co-Applicant Information <span class="text-xs font-normal lowercase text-gray-500">— if for a joint account</span></h3>
                    <div class="grid grid-cols-12 gap-3 mt-3 text-sm">
                        <div class="col-span-12"><label class="text-xs text-gray-500">Name</label><input v-model="local.co_name" @blur="onChange('co_name')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                        <div class="col-span-4"><label class="text-xs text-gray-500">Date of birth</label><input v-model="local.co_dob" @blur="onChange('co_dob')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                        <div class="col-span-4"><label class="text-xs text-gray-500">SSN</label><input v-model="local.co_ssn" @blur="onChange('co_ssn')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1 font-mono" /></div>
                        <div class="col-span-4"><label class="text-xs text-gray-500">Phone</label><input v-model="local.co_phone" @blur="onChange('co_phone')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                        <div class="col-span-12"><label class="text-xs text-gray-500">Current address</label><input v-model="local.co_address" @blur="onChange('co_address')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                        <div class="col-span-5"><label class="text-xs text-gray-500">City</label><input v-model="local.co_city" @blur="onChange('co_city')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                        <div class="col-span-3"><label class="text-xs text-gray-500">State</label><input v-model="local.co_state" @blur="onChange('co_state')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1 uppercase" maxlength="2" /></div>
                        <div class="col-span-4"><label class="text-xs text-gray-500">ZIP</label><input v-model="local.co_zip" @blur="onChange('co_zip')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                        <div class="col-span-4"><label class="text-xs text-gray-500">Own / Rent</label><input v-model="local.co_own_or_rent" @blur="onChange('co_own_or_rent')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                        <div class="col-span-4"><label class="text-xs text-gray-500">Monthly housing</label><input v-model="local.co_monthly_housing" @blur="onChange('co_monthly_housing')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                        <div class="col-span-4"><label class="text-xs text-gray-500">Years at address</label><input v-model="local.co_years_at_addr" @blur="onChange('co_years_at_addr')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>

                        <div class="col-span-12 mt-2"><label class="text-xs text-gray-500">Co-applicant employer</label><input v-model="local.co_employer" @blur="onChange('co_employer')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                        <div class="col-span-6"><label class="text-xs text-gray-500">Position</label><input v-model="local.co_position" @blur="onChange('co_position')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                        <div class="col-span-6"><label class="text-xs text-gray-500">Annual income</label><input v-model="local.co_annual_income" @blur="onChange('co_annual_income')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                    </div>
                </div>

                <!-- VEHICLE -->
                <div>
                    <h3 class="bg-gray-100 px-3 py-1.5 text-sm font-bold uppercase tracking-wider text-gray-700">Vehicle of Interest</h3>
                    <div class="mt-3"><input v-model="local.vehicle_interest" @blur="onChange('vehicle_interest')" placeholder="Year / make / model" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" /></div>
                </div>

                <p class="text-xs text-red-600 italic pt-3 border-t">
                    <strong>I authorize AutoGo and Dealer to submit this application or any other application in connection with the proposed transaction to the financial institutions disclose.</strong>
                </p>

                <p v-if="savedKey" class="text-xs text-emerald-600">✓ {{ savedKey }} saved</p>
                <p v-if="savingKey" class="text-xs text-gray-500">saving {{ savingKey }}…</p>
            </div>
        </div>

        <!-- Email modal -->
        <Teleport to="body">
            <div v-if="emailOpen" @click.self="emailOpen = false" class="fixed inset-0 bg-black/40 flex items-center justify-center z-[100] p-4">
                <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full">
                    <header class="p-5 border-b">
                        <h3 class="font-bold text-lg">✉️ Email Application to Dealer</h3>
                        <p class="text-xs text-gray-500 mt-1">Generates a PDF and sends as attachment.</p>
                    </header>
                    <form @submit.prevent="sendEmail" class="p-5 space-y-3 text-sm">
                        <div>
                            <label class="block text-xs font-semibold mb-1">Dealer email *</label>
                            <input v-model="emailForm.to" type="email" required class="w-full border-gray-300 rounded-lg text-sm" placeholder="dealer@example.com" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1">Subject</label>
                            <input v-model="emailForm.subject" class="w-full border-gray-300 rounded-lg text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1">Message (optional)</label>
                            <textarea v-model="emailForm.message" rows="4" class="w-full border-gray-300 rounded-lg text-sm" placeholder="Hi, please review the attached lease application…" />
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="emailOpen = false" class="px-4 py-2 text-sm bg-gray-100 rounded-lg">Cancel</button>
                            <button type="submit" :disabled="emailForm.processing" class="px-5 py-2 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700 disabled:opacity-50">
                                {{ emailForm.processing ? 'Sending…' : 'Send' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
