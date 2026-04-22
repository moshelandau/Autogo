<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    deal:    { type: Object, required: true },
    session: { type: Object, default: null },
    fields:  { type: Object, default: () => ({}) },
    approvals: { type: Object, default: () => ({}) },
    approvalRequired: { type: Array, default: () => [] },
});

const localApprovals = ref({ ...props.approvals });
const allApproved = computed(() => props.approvalRequired.every(k => localApprovals.value[k]));
const toggleApprove = (key) => {
    const wasApproved = !!localApprovals.value[key];
    if (wasApproved) {
        delete localApprovals.value[key];
    } else {
        localApprovals.value[key] = { by: 'you', at: new Date().toISOString() };
    }
    axios.post(route('leasing.deals.application.approve', props.deal.id), { key, approve: !wasApproved });
};

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

// ── Signature pad ─────────────────────────────────────
const sigCanvas = ref(null);
const sigCtx = ref(null);
const drawing = ref(false);
const signatureSaved = ref(false);
const signatureSavedAt = ref('');
onMounted(() => {
    const canvas = sigCanvas.value;
    if (!canvas) return;
    sigCtx.value = canvas.getContext('2d');
    sigCtx.value.strokeStyle = '#111';
    sigCtx.value.lineWidth = 2;
    sigCtx.value.lineCap = 'round';
});
const sigPos = (e) => {
    const r = sigCanvas.value.getBoundingClientRect();
    const t = e.touches?.[0] || e;
    return { x: (t.clientX - r.left) * (sigCanvas.value.width / r.width),
             y: (t.clientY - r.top) * (sigCanvas.value.height / r.height) };
};
const sigStart = (e) => { drawing.value = true; const p = sigPos(e); sigCtx.value.beginPath(); sigCtx.value.moveTo(p.x, p.y); };
const sigMove = (e) => { if (!drawing.value) return; const p = sigPos(e); sigCtx.value.lineTo(p.x, p.y); sigCtx.value.stroke(); };
const sigEnd = () => { drawing.value = false; };
const sigClear = () => {
    sigCtx.value.clearRect(0, 0, sigCanvas.value.width, sigCanvas.value.height);
    signatureSaved.value = false;
};
const sigSave = async () => {
    const dataUrl = sigCanvas.value.toDataURL('image/png');
    try {
        await axios.put(route('leasing.deals.application.update', props.deal.id), { key: 'signature_data_url', value: dataUrl });
        signatureSaved.value = true;
        signatureSavedAt.value = new Date().toLocaleString();
    } catch (e) { alert('Save signature failed: ' + (e.response?.data?.message || e.message)); }
};
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
                    <button @click="emailOpen = true" :disabled="!allApproved || !signatureSaved"
                        :title="!allApproved ? 'Approve income / housing / years first' : (!signatureSaved ? 'Capture signature first' : 'Email to dealer')"
                        class="px-4 py-2 text-sm bg-emerald-600 text-white rounded-md hover:bg-emerald-700 disabled:bg-gray-300 disabled:cursor-not-allowed">
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
                        <div class="col-span-4">
                            <label class="text-xs text-gray-500 flex items-center justify-between">
                                Monthly payment / rent
                                <button type="button" @click="toggleApprove('applicant_monthly_housing')"
                                    class="ml-2 px-2 py-0.5 text-[10px] rounded-full font-bold transition"
                                    :class="localApprovals.applicant_monthly_housing ? 'bg-emerald-600 text-white' : 'bg-orange-100 text-orange-700 border border-orange-300'">
                                    {{ localApprovals.applicant_monthly_housing ? '✓ approved' : 'approve' }}
                                </button>
                            </label>
                            <input v-model="local.applicant_monthly_housing" @blur="onChange('applicant_monthly_housing')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" />
                        </div>
                        <div class="col-span-4">
                            <label class="text-xs text-gray-500 flex items-center justify-between">
                                Years at address
                                <button type="button" @click="toggleApprove('applicant_years_at_addr')"
                                    class="ml-2 px-2 py-0.5 text-[10px] rounded-full font-bold transition"
                                    :class="localApprovals.applicant_years_at_addr ? 'bg-emerald-600 text-white' : 'bg-orange-100 text-orange-700 border border-orange-300'">
                                    {{ localApprovals.applicant_years_at_addr ? '✓ approved' : 'approve' }}
                                </button>
                            </label>
                            <input v-model="local.applicant_years_at_addr" @blur="onChange('applicant_years_at_addr')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" />
                        </div>
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
                        <div class="col-span-6">
                            <label class="text-xs text-gray-500 flex items-center justify-between">
                                Annual income
                                <button type="button" @click="toggleApprove('applicant_annual_income')"
                                    class="ml-2 px-2 py-0.5 text-[10px] rounded-full font-bold transition"
                                    :class="localApprovals.applicant_annual_income ? 'bg-emerald-600 text-white' : 'bg-orange-100 text-orange-700 border border-orange-300'">
                                    {{ localApprovals.applicant_annual_income ? '✓ approved' : 'approve' }}
                                </button>
                            </label>
                            <input v-model="local.applicant_annual_income" @blur="onChange('applicant_annual_income')" class="w-full border-b border-gray-300 focus:border-indigo-500 outline-none py-1" />
                        </div>
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

                <!-- Signature -->
                <div>
                    <h3 class="bg-gray-100 px-3 py-1.5 text-sm font-bold uppercase tracking-wider text-gray-700">Customer Signature</h3>
                    <div class="mt-3 grid md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Sign in the box below (mouse or touch):</p>
                            <canvas ref="sigCanvas" width="500" height="160"
                                class="w-full border-2 border-dashed border-gray-300 rounded-md bg-white touch-none"
                                @mousedown="sigStart" @mousemove="sigMove" @mouseup="sigEnd" @mouseleave="sigEnd"
                                @touchstart.prevent="sigStart" @touchmove.prevent="sigMove" @touchend.prevent="sigEnd"></canvas>
                            <div class="flex gap-2 mt-2">
                                <button type="button" @click="sigClear" class="text-xs text-gray-600 hover:text-gray-900 underline">Clear</button>
                                <button type="button" @click="sigSave" class="text-xs text-emerald-600 hover:text-emerald-800 underline font-semibold">Save signature</button>
                            </div>
                            <p v-if="signatureSaved" class="text-xs text-emerald-700 mt-1">✓ Signature captured at {{ signatureSavedAt }}</p>
                        </div>
                        <div class="text-xs text-gray-600 leading-relaxed">
                            <p class="mb-2">By signing, the applicant electronically authorizes AutoGo and the dealer to submit this application to financial institutions for the proposed transaction.</p>
                            <p>The signature image is attached as part of the PDF when emailed to the dealer.</p>
                        </div>
                    </div>
                </div>

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
