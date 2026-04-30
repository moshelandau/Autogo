<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    token: { type: String, required: true },
    has_front: { type: Boolean, default: false },
    has_back:  { type: Boolean, default: false },
});

const form = useForm({
    license_front: null,
    license_back:  null,
});

const onPickFront = (e) => { form.license_front = e.target.files[0] || null; };
const onPickBack  = (e) => { form.license_back  = e.target.files[0] || null; };

const submit = () => {
    form.post(`/apply/${props.token}/license`, { forceFormData: true });
};
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
                <p class="text-indigo-100 text-xs mt-0.5">Upload both sides of your driver's license.</p>
            </header>

            <form @submit.prevent="submit" class="p-6 space-y-5">
                <p class="text-xs text-gray-500">
                    Tap each box to pick a photo from your camera or library.
                    All four corners visible, nothing covering the card.
                </p>

                <label class="block">
                    <span class="block text-xs font-medium text-gray-700 mb-1.5">
                        Front of license
                        <span v-if="has_front" class="text-emerald-600 text-[10px] ml-1">✓ uploaded</span>
                    </span>
                    <input type="file" accept="image/*" capture="environment" @change="onPickFront"
                           class="block w-full text-sm border border-gray-300 rounded-md p-3 bg-gray-50" />
                    <span v-if="form.license_front" class="text-[11px] text-emerald-600 mt-1 block">
                        Selected: {{ form.license_front.name }}
                    </span>
                    <p v-if="form.errors.license_front" class="text-xs text-red-600 mt-1">{{ form.errors.license_front }}</p>
                </label>

                <label class="block">
                    <span class="block text-xs font-medium text-gray-700 mb-1.5">
                        Back of license
                        <span v-if="has_back" class="text-emerald-600 text-[10px] ml-1">✓ uploaded</span>
                    </span>
                    <input type="file" accept="image/*" capture="environment" @change="onPickBack"
                           class="block w-full text-sm border border-gray-300 rounded-md p-3 bg-gray-50" />
                    <span v-if="form.license_back" class="text-[11px] text-emerald-600 mt-1 block">
                        Selected: {{ form.license_back.name }}
                    </span>
                    <p v-if="form.errors.license_back" class="text-xs text-red-600 mt-1">{{ form.errors.license_back }}</p>
                </label>

                <p class="text-[11px] text-gray-500">
                    🔒 Encrypted in transit and at rest. Used only to verify your identity for the application.
                </p>

                <button type="submit" :disabled="form.processing || (!form.license_front && !form.license_back)"
                        class="w-full px-4 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                    {{ form.processing ? 'Uploading…' : 'Submit' }}
                </button>
            </form>
        </div>
    </div>
</template>
