<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, nextTick } from 'vue';

const props = defineProps({
    session: { type: Object, required: true },
});

const isClosed = !!(props.session.completed_at || props.session.aborted_at);
const c = props.session.collected || {};

const form = useForm({
    first_name:        c.first_name        || '',
    last_name:         c.last_name         || '',
    date_of_birth:     c.date_of_birth     || '',
    ssn:               c.ssn               || '',
    address:           c.address           || '',
    city:              c.city              || '',
    state:             c.state             || '',
    zip:               c.zip               || '',
    own_or_rent:       c.own_or_rent       || '',
    monthly_housing:   c.monthly_housing   || '',
    years_at_address:  c.years_at_address  || '',
    email:             c.email             || '',
    employer:          c.employer          || '',
    employer_address:  c.employer_address  || '',
    employer_city:     c.employer_city     || '',
    employer_state:    c.employer_state    || '',
    employer_zip:      c.employer_zip      || '',
    employer_phone:    c.employer_phone    || '',
    position:          c.position          || '',
    years_employed:    c.years_employed    || '',
    annual_income:     c.annual_income     || '',
    has_coapplicant:   c.has_coapplicant   || 'no',
    vehicle_interest:  c.vehicle_interest  || '',
    license_front: null,
    license_back:  null,
});

const hasFrontUploaded = !!c.license_image_front_path;
const hasBackUploaded  = !!c.license_image_back_path;

// Inline preview state — file is checked as soon as it's picked, NOT
// just at submit time. Bad photos surface immediately with a reason.
const frontCheck = ref({ state: 'idle', reason: '', preview: '' }); // idle|checking|ok|bad
const backCheck  = ref({ state: 'idle', reason: '', preview: '' });
const session_token = window.location.pathname.split('/')[2];

const previewLicense = async (side, file, target) => {
    target.value = { state: 'checking', reason: '', preview: '' };
    const fd = new FormData();
    fd.append('side', side);
    fd.append('file', file);
    try {
        const r = await axios.post(`/apply/${session_token}/preview-license`, fd);
        target.value = {
            state:   r.data.valid ? 'ok' : 'bad',
            reason:  r.data.reason || '',
            preview: r.data.preview || '',
        };
    } catch (e) {
        target.value = {
            state:  'bad',
            reason: e.response?.data?.reason || 'Could not check this photo.',
            preview: '',
        };
    }
};

const onPickFront = (e) => {
    const f = e.target.files[0] || null;
    form.license_front = f;
    if (f) previewLicense('front', f, frontCheck);
};
const onPickBack = (e) => {
    const f = e.target.files[0] || null;
    form.license_back = f;
    if (f) previewLicense('back', f, backCheck);
};

const checkBadge = (check) => {
    if (check.value.state === 'checking') return { label: 'Checking…', cls: 'text-amber-700' };
    if (check.value.state === 'ok')       return { label: '✓ ' + (check.value.preview || 'Looks good'), cls: 'text-emerald-700' };
    if (check.value.state === 'bad')      return { label: '✗ ' + check.value.reason + ' — pick a new photo', cls: 'text-red-700' };
    return null;
};
const frontBadge = computed(() => checkBadge(frontCheck));
const backBadge  = computed(() => checkBadge(backCheck));

const submit = () => {
    form.post(window.location.pathname, { forceFormData: true });
};

const flash = computed(() => usePage().props.flash || {});
const errorCount = computed(() => Object.keys(form.errors).length);
const errorList  = computed(() => Object.entries(form.errors).map(([field, msg]) => ({ field, msg })));

const fieldCls = (field) => [
    'mt-0.5 block w-full rounded-md text-sm',
    form.errors[field]
        ? 'border-red-400 focus:border-red-500 focus:ring-red-500 bg-red-50'
        : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500',
];

const hasPrefill = ['first_name','last_name','address','city','state','zip','date_of_birth','email']
    .some(k => !!c[k]);

const focused = computed(() => new URLSearchParams(window.location.search).get('focus') || '');
const FIELD_FOR_STEP = {
    license_image_front: 'license_front',
    license_image_back:  'license_back',
};
const focusFieldName = computed(() => FIELD_FOR_STEP[focused.value] || focused.value);

onMounted(() => {
    if (!focusFieldName.value) return;
    nextTick(() => {
        const el = document.querySelector(`[name="${focusFieldName.value}"]`);
        if (!el) return;
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.classList.add('ring-4', 'ring-amber-300');
        setTimeout(() => el.classList.remove('ring-4', 'ring-amber-300'), 3500);
        if (el.focus) el.focus({ preventScroll: true });
    });
});
</script>

<template>
    <Head>
        <title>AutoGo — Lease Application</title>
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    </Head>

    <div class="min-h-screen bg-gray-50">
        <div class="max-w-2xl mx-auto px-4 py-6">
            <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <header class="bg-indigo-600 text-white px-6 py-5">
                    <h1 class="text-xl font-bold">AutoGo Lease Application</h1>
                    <p class="text-indigo-100 text-sm mt-1">Save progress as you go — you can submit partial info and finish later.</p>
                </header>

                <div v-if="isClosed" class="p-6 text-center text-gray-700">
                    <div class="text-3xl mb-2">✓</div>
                    <p class="text-lg font-medium">This application has already been submitted.</p>
                    <p class="text-sm text-gray-500 mt-1">A team member will reach out shortly.</p>
                </div>

                <form v-else @submit.prevent="submit" class="p-6 space-y-6">
                    <p v-if="hasPrefill" class="text-xs bg-indigo-50 text-indigo-800 border border-indigo-200 rounded-md px-3 py-2">
                        Some fields are pre-filled from your texts — review and correct anything that's off.
                    </p>

                    <div v-if="flash.success" class="text-xs bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-md px-3 py-2">
                        ✓ {{ flash.success }}
                    </div>

                    <div v-if="errorCount" class="text-xs bg-red-50 text-red-800 border border-red-300 rounded-md px-3 py-2 space-y-1">
                        <div class="font-semibold">Couldn't submit — {{ errorCount }} field{{ errorCount === 1 ? '' : 's' }} need attention:</div>
                        <ul class="list-disc list-inside space-y-0.5">
                            <li v-for="e in errorList" :key="e.field">
                                <span class="font-mono text-[10px]">{{ e.field }}</span>: {{ e.msg }}
                            </li>
                        </ul>
                    </div>

                    <section>
                        <h2 class="text-sm font-semibold text-gray-700 mb-3">Identity</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="block">
                                <span class="block text-[11px] font-medium text-gray-700 mb-0.5">First name *</span>
                                <input v-model="form.first_name" name="first_name" required :class="fieldCls('first_name')" />
                                <p v-if="form.errors.first_name" class="text-[10px] text-red-600 mt-0.5">{{ form.errors.first_name }}</p>
                            </label>
                            <label class="block">
                                <span class="block text-[11px] font-medium text-gray-700 mb-0.5">Last name *</span>
                                <input v-model="form.last_name" name="last_name" required :class="fieldCls('last_name')" />
                                <p v-if="form.errors.last_name" class="text-[10px] text-red-600 mt-0.5">{{ form.errors.last_name }}</p>
                            </label>
                            <label class="block">
                                <span class="block text-[11px] font-medium text-gray-700 mb-0.5">Date of birth *</span>
                                <input v-model="form.date_of_birth" name="date_of_birth" type="date" required :class="fieldCls('date_of_birth')" />
                                <p v-if="form.errors.date_of_birth" class="text-[10px] text-red-600 mt-0.5">{{ form.errors.date_of_birth }}</p>
                            </label>
                            <label class="block">
                                <span class="block text-[11px] font-medium text-gray-700 mb-0.5">SSN (XXX-XX-XXXX)</span>
                                <input v-model="form.ssn" name="ssn" type="password" placeholder="encrypted" :class="fieldCls('ssn')" />
                                <p v-if="form.errors.ssn" class="text-[10px] text-red-600 mt-0.5">{{ form.errors.ssn }}</p>
                            </label>
                            <label class="block sm:col-span-2">
                                <span class="block text-[11px] font-medium text-gray-700 mb-0.5">Email</span>
                                <input v-model="form.email" name="email" type="email" :class="fieldCls('email')" />
                                <p v-if="form.errors.email" class="text-[10px] text-red-600 mt-0.5">{{ form.errors.email }}</p>
                            </label>
                        </div>
                    </section>

                    <section>
                        <h2 class="text-sm font-semibold text-gray-700 mb-1">Driver's License</h2>
                        <p class="text-xs text-gray-500 mb-3">Upload a fresh photo to replace what we have, or leave blank.</p>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block">
                                <span class="block text-[11px] font-medium text-gray-700 mb-0.5">
                                    License — front
                                    <span v-if="hasFrontUploaded && !form.license_front" class="text-emerald-600 text-[10px] ml-1">✓ on file</span>
                                </span>
                                <input name="license_front" type="file" accept="image/*" capture="environment" @change="onPickFront" class="w-full text-xs" />
                                <p v-if="frontBadge" :class="['text-[11px] mt-1', frontBadge.cls]">{{ frontBadge.label }}</p>
                                <p v-if="form.errors.license_front" class="text-[10px] text-red-600 mt-0.5">{{ form.errors.license_front }}</p>
                            </label>
                            <label class="block">
                                <span class="block text-[11px] font-medium text-gray-700 mb-0.5">
                                    License — back
                                    <span v-if="hasBackUploaded && !form.license_back" class="text-emerald-600 text-[10px] ml-1">✓ on file</span>
                                </span>
                                <input name="license_back" type="file" accept="image/*" capture="environment" @change="onPickBack" class="w-full text-xs" />
                                <p v-if="backBadge" :class="['text-[11px] mt-1', backBadge.cls]">{{ backBadge.label }}</p>
                                <p v-if="form.errors.license_back" class="text-[10px] text-red-600 mt-0.5">{{ form.errors.license_back }}</p>
                            </label>
                        </div>
                    </section>

                    <section>
                        <h2 class="text-sm font-semibold text-gray-700 mb-3">Home Address</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="block sm:col-span-2">
                                <span class="block text-[11px] font-medium text-gray-700 mb-0.5">Street</span>
                                <input v-model="form.address" name="address" :class="fieldCls('address')" />
                                <p v-if="form.errors.address" class="text-[10px] text-red-600 mt-0.5">{{ form.errors.address }}</p>
                            </label>
                            <label class="block">
                                <span class="block text-[11px] font-medium text-gray-700 mb-0.5">City</span>
                                <input v-model="form.city" name="city" :class="fieldCls('city')" />
                            </label>
                            <label class="block">
                                <span class="block text-[11px] font-medium text-gray-700 mb-0.5">State</span>
                                <input v-model="form.state" name="state" maxlength="2" :class="fieldCls('state')" />
                            </label>
                            <label class="block">
                                <span class="block text-[11px] font-medium text-gray-700 mb-0.5">ZIP</span>
                                <input v-model="form.zip" name="zip" maxlength="10" :class="fieldCls('zip')" />
                            </label>
                            <label class="block">
                                <span class="block text-[11px] font-medium text-gray-700 mb-0.5">Own or rent</span>
                                <select v-model="form.own_or_rent" name="own_or_rent" :class="fieldCls('own_or_rent')">
                                    <option value="">—</option>
                                    <option value="own">Own</option>
                                    <option value="rent">Rent</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="block text-[11px] font-medium text-gray-700 mb-0.5">Monthly housing ($)</span>
                                <input v-model="form.monthly_housing" name="monthly_housing" type="number" step="0.01" :class="fieldCls('monthly_housing')" />
                            </label>
                            <label class="block">
                                <span class="block text-[11px] font-medium text-gray-700 mb-0.5">Years at address</span>
                                <input v-model="form.years_at_address" name="years_at_address" type="number" step="0.5" :class="fieldCls('years_at_address')" />
                            </label>
                        </div>
                    </section>

                    <section>
                        <h2 class="text-sm font-semibold text-gray-700 mb-3">Employment</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="block">
                                <span class="block text-[11px] font-medium text-gray-700 mb-0.5">Employer name</span>
                                <input v-model="form.employer" name="employer" :class="fieldCls('employer')" />
                            </label>
                            <label class="block">
                                <span class="block text-[11px] font-medium text-gray-700 mb-0.5">Position / job title</span>
                                <input v-model="form.position" name="position" :class="fieldCls('position')" />
                            </label>
                            <label class="block">
                                <span class="block text-[11px] font-medium text-gray-700 mb-0.5">Years employed</span>
                                <input v-model="form.years_employed" name="years_employed" type="number" step="0.5" :class="fieldCls('years_employed')" />
                            </label>
                            <label class="block">
                                <span class="block text-[11px] font-medium text-gray-700 mb-0.5">Annual income ($)</span>
                                <input v-model="form.annual_income" name="annual_income" type="number" step="0.01" :class="fieldCls('annual_income')" />
                            </label>
                            <label class="block">
                                <span class="block text-[11px] font-medium text-gray-700 mb-0.5">Employer phone</span>
                                <input v-model="form.employer_phone" name="employer_phone" type="tel" :class="fieldCls('employer_phone')" />
                            </label>
                            <label class="block sm:col-span-2">
                                <span class="block text-[11px] font-medium text-gray-700 mb-0.5">Employer address</span>
                                <input v-model="form.employer_address" name="employer_address" :class="fieldCls('employer_address')" />
                            </label>
                            <label class="block">
                                <span class="block text-[11px] font-medium text-gray-700 mb-0.5">City</span>
                                <input v-model="form.employer_city" name="employer_city" :class="fieldCls('employer_city')" />
                            </label>
                            <label class="block">
                                <span class="block text-[11px] font-medium text-gray-700 mb-0.5">State</span>
                                <input v-model="form.employer_state" name="employer_state" maxlength="2" :class="fieldCls('employer_state')" />
                            </label>
                            <label class="block">
                                <span class="block text-[11px] font-medium text-gray-700 mb-0.5">ZIP</span>
                                <input v-model="form.employer_zip" name="employer_zip" maxlength="10" :class="fieldCls('employer_zip')" />
                            </label>
                        </div>
                    </section>

                    <section>
                        <h2 class="text-sm font-semibold text-gray-700 mb-3">Vehicle Interest</h2>
                        <label class="block">
                            <span class="block text-[11px] font-medium text-gray-700 mb-0.5">What vehicle are you interested in?</span>
                            <input v-model="form.vehicle_interest" name="vehicle_interest" placeholder="e.g. 2026 Kia Sportage EX" :class="fieldCls('vehicle_interest')" />
                        </label>
                    </section>

                    <p class="text-[11px] text-gray-500">
                        By submitting you authorize a credit application. Final terms subject to lender approval.
                    </p>

                    <p v-if="(form.license_front && frontCheck.state !== 'ok') || (form.license_back && backCheck.state !== 'ok')"
                       class="text-xs text-amber-700 bg-amber-50 border border-amber-300 rounded p-2">
                        License photos need a green ✓ before you can submit. Pick a new file if a side shows an issue.
                    </p>

                    <button type="submit"
                            :disabled="form.processing
                                       || (form.license_front && frontCheck.state !== 'ok')
                                       || (form.license_back && backCheck.state !== 'ok')"
                            class="w-full px-4 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                        {{ form.processing ? 'Submitting…' : 'Submit Application' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
