<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    token: { type: String, required: true },
    has_front: { type: Boolean, default: false },
    has_back:  { type: Boolean, default: false },
});

// Per-side state machine: idle → checking → ok | bad
const frontState   = ref(props.has_front ? 'ok' : 'idle');
const frontReason  = ref('');
const frontPreview = ref(props.has_front ? '✓ on file from earlier' : '');
const frontPath    = ref(null);
const frontExtracted = ref({});       // OCR extract for diff resolution
const frontDiffs   = ref({});          // { field: { license, file } }
const diffChoices  = ref({});          // { field: 'license' | 'file' | '<custom>' }
const customInputs = ref({});          // text inputs when user picks Custom

const backState  = ref(props.has_back ? 'ok' : 'idle');
const backReason = ref('');
const backPreview = ref(props.has_back ? '✓ on file from earlier' : '');
const backPath   = ref(null);

const submitting = ref(false);

const previewFile = async (side, file) => {
    if (!file) return;
    if (side === 'front') {
        frontState.value = 'checking'; frontReason.value = ''; frontPreview.value = '';
        frontDiffs.value = {}; diffChoices.value = {}; customInputs.value = {};
    } else {
        backState.value  = 'checking'; backReason.value  = ''; backPreview.value  = '';
    }

    const fd = new FormData();
    fd.append('side', side);
    fd.append('file', file);

    try {
        const r = await axios.post(`/apply/${props.token}/preview-license`, fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        const data = r.data;
        if (side === 'front') {
            frontState.value     = data.valid ? 'ok' : 'bad';
            frontReason.value    = data.reason || '';
            frontPreview.value   = data.preview || '';
            frontPath.value      = data.path;
            frontExtracted.value = data.extracted || {};
            frontDiffs.value     = data.diffs || {};
        } else {
            backState.value   = data.valid ? 'ok' : 'bad';
            backReason.value  = data.reason || '';
            backPreview.value = data.preview || '';
            backPath.value    = data.path;
        }
    } catch (e) {
        const msg = e.response?.data?.reason || e.response?.data?.message || 'Could not check this photo. Try again.';
        if (side === 'front') { frontState.value = 'bad'; frontReason.value = msg; }
        else                  { backState.value  = 'bad'; backReason.value  = msg; }
    }
};

const onPickFront = (e) => previewFile('front', e.target.files[0]);
const onPickBack  = (e) => previewFile('back',  e.target.files[0]);

const diffFields = computed(() => Object.keys(frontDiffs.value || {}));
const hasUnresolvedDiffs = computed(() =>
    diffFields.value.some(f => {
        const c = diffChoices.value[f];
        if (!c) return true;
        if (c === 'custom') return !customInputs.value[f] || !customInputs.value[f].trim();
        return false;
    }));

const FIELD_LABEL = {
    date_of_birth: 'DATE OF BIRTH',
    address:       'ADDRESS',
    first_name:    'FIRST NAME',
    last_name:     'LAST NAME',
};

const fmtDob = (v) => {
    if (!v) return '—';
    if (typeof v === 'string' && v.match(/^\d{4}-\d{2}-\d{2}/)) {
        const [y, m, d] = v.split('-');
        return `${m}/${d}/${y}`;
    }
    return v;
};
const fieldDisplay = (field, val) => field === 'date_of_birth' ? fmtDob(val) : (val || '—');

const canSubmit = computed(() =>
    frontState.value === 'ok' &&
    backState.value  === 'ok' &&
    !hasUnresolvedDiffs.value
);

const submit = () => {
    submitting.value = true;
    const payload = {
        extracted: frontExtracted.value,
        diff_choices: {},
    };
    for (const f of diffFields.value) {
        const c = diffChoices.value[f];
        payload.diff_choices[f] = c === 'custom' ? customInputs.value[f] : c;
    }
    if (frontPath.value) payload.front_path = frontPath.value;
    if (backPath.value)  payload.back_path  = backPath.value;
    router.post(`/apply/${props.token}/license`, payload, {
        preserveScroll: true,
        onFinish: () => { submitting.value = false; },
    });
};

const stateClass = (state) => state === 'ok'
    ? 'border-emerald-400 bg-emerald-50'
    : state === 'bad'
    ? 'border-red-400 bg-red-50'
    : state === 'checking'
    ? 'border-amber-400 bg-amber-50'
    : 'border-gray-300 bg-gray-50';
</script>

<template>
    <Head>
        <title>AutoGo — License upload</title>
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    </Head>

    <div class="min-h-screen bg-gray-50 flex items-center justify-center px-4 py-10">
        <div class="bg-white rounded-2xl shadow-sm w-full max-w-md overflow-hidden">
            <header class="bg-indigo-600 text-white px-6 py-5">
                <h1 class="text-base font-semibold">AutoGo — License upload</h1>
                <p class="text-indigo-100 text-xs mt-0.5">We check each photo as soon as you pick it.</p>
            </header>

            <div class="p-6 space-y-5">
                <!-- FRONT -->
                <div :class="['rounded-md p-3 border', stateClass(frontState)]">
                    <label class="block">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-xs font-medium text-gray-700">Front of license</span>
                            <span v-if="frontState === 'checking'" class="text-[11px] text-amber-700">Checking…</span>
                            <span v-else-if="frontState === 'ok'" class="text-[11px] text-emerald-700 font-semibold">✓ Looks good</span>
                            <span v-else-if="frontState === 'bad'" class="text-[11px] text-red-700 font-semibold">✗ Re-take needed</span>
                        </div>
                        <input type="file" accept="image/*" capture="environment" @change="onPickFront"
                               :disabled="frontState === 'checking'"
                               class="block w-full text-sm" />
                    </label>
                    <p v-if="frontState === 'ok' && frontPreview" class="text-[11px] text-emerald-700 mt-1.5">{{ frontPreview }}</p>
                    <p v-if="frontState === 'bad' && frontReason" class="text-[11px] text-red-700 mt-1.5">{{ frontReason }} — please pick a new photo.</p>
                </div>

                <!-- DIFF CONFIRMATION (only shown if front read OK and there are diffs) -->
                <div v-if="frontState === 'ok' && diffFields.length"
                     class="rounded-md p-3 border border-amber-300 bg-amber-50 space-y-3">
                    <div class="text-xs font-semibold text-amber-900">
                        Quick confirm — your license info doesn't match what we have on file:
                    </div>
                    <div v-for="field in diffFields" :key="field" class="space-y-1.5 pb-2 border-b border-amber-200 last:border-0">
                        <div class="text-[10px] font-bold uppercase tracking-wide text-gray-700">
                            {{ FIELD_LABEL[field] || field.replace('_', ' ').toUpperCase() }}
                        </div>
                        <label class="block text-xs">
                            <input type="radio" :name="`diff_${field}`" value="license" v-model="diffChoices[field]" class="mr-1.5" />
                            <span class="font-medium">License:</span>
                            <span class="font-mono ml-1">{{ fieldDisplay(field, frontDiffs[field].license) }}</span>
                        </label>
                        <label class="block text-xs">
                            <input type="radio" :name="`diff_${field}`" value="file" v-model="diffChoices[field]" class="mr-1.5" />
                            <span class="font-medium">On file:</span>
                            <span class="font-mono ml-1">{{ fieldDisplay(field, frontDiffs[field].file) }}</span>
                        </label>
                        <label class="block text-xs">
                            <input type="radio" :name="`diff_${field}`" value="custom" v-model="diffChoices[field]" class="mr-1.5" />
                            <span class="font-medium">Type the correct value:</span>
                        </label>
                        <input v-if="diffChoices[field] === 'custom'"
                               v-model="customInputs[field]"
                               :placeholder="field === 'date_of_birth' ? 'MM/DD/YYYY' : 'corrected value'"
                               class="block w-full text-sm border-gray-300 rounded-md ml-5" />
                    </div>
                </div>

                <!-- BACK -->
                <div :class="['rounded-md p-3 border', stateClass(backState)]">
                    <label class="block">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-xs font-medium text-gray-700">Back of license</span>
                            <span v-if="backState === 'checking'" class="text-[11px] text-amber-700">Checking…</span>
                            <span v-else-if="backState === 'ok'" class="text-[11px] text-emerald-700 font-semibold">✓ Looks good</span>
                            <span v-else-if="backState === 'bad'" class="text-[11px] text-red-700 font-semibold">✗ Re-take needed</span>
                        </div>
                        <input type="file" accept="image/*" capture="environment" @change="onPickBack"
                               :disabled="backState === 'checking'"
                               class="block w-full text-sm" />
                    </label>
                    <p v-if="backState === 'ok' && backPreview" class="text-[11px] text-emerald-700 mt-1.5">{{ backPreview }}</p>
                    <p v-if="backState === 'bad' && backReason" class="text-[11px] text-red-700 mt-1.5">{{ backReason }} — please pick a new photo.</p>
                </div>

                <p class="text-[11px] text-gray-500">
                    🔒 Encrypted in transit and at rest. Used only to verify your identity.
                </p>

                <button type="button" @click="submit" :disabled="!canSubmit || submitting"
                        class="w-full px-4 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                    {{ submitting ? 'Submitting…' :
                       (!canSubmit && hasUnresolvedDiffs ? 'Pick which value is correct above' :
                       (!canSubmit ? 'Both sides need a green ✓ first' : 'Submit')) }}
                </button>
            </div>
        </div>
    </div>
</template>
