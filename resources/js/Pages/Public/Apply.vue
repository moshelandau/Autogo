<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

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

const licenseFrontInput = ref(null);
const licenseBackInput  = ref(null);
const onPickFront = (e) => { form.license_front = e.target.files[0] || null; };
const onPickBack  = (e) => { form.license_back  = e.target.files[0] || null; };

const submit = () => {
    form.post('/apply/' + window.location.pathname.split('/')[2], {
        forceFormData: true,
    });
};

// Convenience — prefilled flag so we can hint to the user when fields
// are pre-populated from the SMS conversation.
const hasPrefill = Object.values(c).some(v => v !== null && v !== '' && typeof v !== 'object');
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
                    <p class="text-indigo-100 text-sm mt-1">Complete the form below — takes about 2 minutes.</p>
                </header>

                <div v-if="isClosed" class="p-6 text-center text-gray-700">
                    <div class="text-3xl mb-2">✓</div>
                    <p class="text-lg font-medium">This application has already been submitted.</p>
                    <p class="text-sm text-gray-500 mt-1">A team member will reach out shortly.</p>
                </div>

                <form v-else @submit.prevent="submit" class="p-6 space-y-6">
                    <p v-if="hasPrefill" class="text-xs bg-indigo-50 text-indigo-800 border border-indigo-200 rounded-md px-3 py-2">
                        Some fields are pre-filled from your texts — please review and correct anything that's off.
                    </p>

                    <!-- Identity -->
                    <section>
                        <h2 class="text-sm font-semibold text-gray-700 mb-3">Identity</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <Field label="First name *">
                                <input v-model="form.first_name" required class="w-full border-gray-300 rounded-md text-sm" />
                            </Field>
                            <Field label="Last name *">
                                <input v-model="form.last_name" required class="w-full border-gray-300 rounded-md text-sm" />
                            </Field>
                            <Field label="Date of birth *">
                                <input v-model="form.date_of_birth" type="date" required class="w-full border-gray-300 rounded-md text-sm" />
                            </Field>
                            <Field label="SSN (XXX-XX-XXXX)">
                                <input v-model="form.ssn" type="password" placeholder="encrypted" class="w-full border-gray-300 rounded-md text-sm" />
                            </Field>
                            <Field label="Email">
                                <input v-model="form.email" type="email" class="w-full border-gray-300 rounded-md text-sm" />
                            </Field>
                        </div>
                    </section>

                    <!-- Driver's license uploads -->
                    <section>
                        <h2 class="text-sm font-semibold text-gray-700 mb-3">Driver's License</h2>
                        <p class="text-xs text-gray-500 mb-3">Upload clear photos — all four corners visible, no fingers covering anything.</p>
                        <div class="grid grid-cols-2 gap-3">
                            <Field label="License — front">
                                <input ref="licenseFrontInput" type="file" accept="image/*" capture="environment" @change="onPickFront" class="w-full text-xs" />
                                <span v-if="form.license_front" class="text-[11px] text-emerald-600">{{ form.license_front.name }}</span>
                            </Field>
                            <Field label="License — back">
                                <input ref="licenseBackInput" type="file" accept="image/*" capture="environment" @change="onPickBack" class="w-full text-xs" />
                                <span v-if="form.license_back" class="text-[11px] text-emerald-600">{{ form.license_back.name }}</span>
                            </Field>
                        </div>
                    </section>

                    <!-- Address -->
                    <section>
                        <h2 class="text-sm font-semibold text-gray-700 mb-3">Home Address</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <Field label="Street" class="sm:col-span-2">
                                <input v-model="form.address" class="w-full border-gray-300 rounded-md text-sm" />
                            </Field>
                            <Field label="City">
                                <input v-model="form.city" class="w-full border-gray-300 rounded-md text-sm" />
                            </Field>
                            <Field label="State">
                                <input v-model="form.state" maxlength="2" class="w-full border-gray-300 rounded-md text-sm" />
                            </Field>
                            <Field label="ZIP">
                                <input v-model="form.zip" maxlength="10" class="w-full border-gray-300 rounded-md text-sm" />
                            </Field>
                            <Field label="Own or rent">
                                <select v-model="form.own_or_rent" class="w-full border-gray-300 rounded-md text-sm">
                                    <option value="">—</option>
                                    <option value="own">Own</option>
                                    <option value="rent">Rent</option>
                                </select>
                            </Field>
                            <Field label="Monthly housing ($)">
                                <input v-model="form.monthly_housing" type="number" step="0.01" class="w-full border-gray-300 rounded-md text-sm" />
                            </Field>
                            <Field label="Years at address">
                                <input v-model="form.years_at_address" type="number" step="0.5" class="w-full border-gray-300 rounded-md text-sm" />
                            </Field>
                        </div>
                    </section>

                    <!-- Employment -->
                    <section>
                        <h2 class="text-sm font-semibold text-gray-700 mb-3">Employment</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <Field label="Employer name">
                                <input v-model="form.employer" class="w-full border-gray-300 rounded-md text-sm" />
                            </Field>
                            <Field label="Position / job title">
                                <input v-model="form.position" class="w-full border-gray-300 rounded-md text-sm" />
                            </Field>
                            <Field label="Years employed">
                                <input v-model="form.years_employed" type="number" step="0.5" class="w-full border-gray-300 rounded-md text-sm" />
                            </Field>
                            <Field label="Annual income ($)">
                                <input v-model="form.annual_income" type="number" step="0.01" class="w-full border-gray-300 rounded-md text-sm" />
                            </Field>
                            <Field label="Employer phone">
                                <input v-model="form.employer_phone" type="tel" class="w-full border-gray-300 rounded-md text-sm" />
                            </Field>
                            <Field label="Employer address" class="sm:col-span-2">
                                <input v-model="form.employer_address" class="w-full border-gray-300 rounded-md text-sm" />
                            </Field>
                            <Field label="City">
                                <input v-model="form.employer_city" class="w-full border-gray-300 rounded-md text-sm" />
                            </Field>
                            <Field label="State">
                                <input v-model="form.employer_state" maxlength="2" class="w-full border-gray-300 rounded-md text-sm" />
                            </Field>
                            <Field label="ZIP">
                                <input v-model="form.employer_zip" maxlength="10" class="w-full border-gray-300 rounded-md text-sm" />
                            </Field>
                        </div>
                    </section>

                    <!-- Vehicle -->
                    <section>
                        <h2 class="text-sm font-semibold text-gray-700 mb-3">Vehicle Interest</h2>
                        <Field label="What vehicle are you interested in?">
                            <input v-model="form.vehicle_interest" placeholder="e.g. 2026 Kia Sportage EX" class="w-full border-gray-300 rounded-md text-sm" />
                        </Field>
                    </section>

                    <p v-if="Object.keys(form.errors).length" class="text-xs text-red-600">
                        Please fix the highlighted fields above.
                    </p>
                    <p class="text-[11px] text-gray-500">
                        By submitting you authorize a credit application. Final terms subject to lender approval.
                    </p>

                    <button type="submit" :disabled="form.processing"
                            class="w-full px-4 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                        {{ form.processing ? 'Submitting…' : 'Submit Application' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
// Lightweight Field wrapper kept in same SFC to avoid a separate component import.
import { defineComponent, h } from 'vue';
export default defineComponent({
    name: 'Field',
    props: ['label'],
    setup(props, { slots, attrs }) {
        return () => h('label', { class: ['block', attrs.class] }, [
            h('span', { class: 'block text-[11px] font-medium text-gray-700 mb-0.5' }, props.label),
            slots.default && slots.default(),
        ]);
    },
});
</script>
