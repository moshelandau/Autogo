<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    token:    { type: String, required: true },
    step_key: { type: String, required: true },
    label:    { type: String, required: true },
    type:     { type: String, default: 'text' },        // text, password, file
    accept:   { type: String, default: '' },
    capture:  { type: String, default: '' },
    placeholder: { type: String, default: '' },
});

const form = useForm({ value: '', file: null });
const onPickFile = (e) => { form.file = e.target.files[0] || null; };

const submit = () => {
    form.post(`/apply/${props.token}/step/${props.step_key}`, { forceFormData: true });
};
</script>

<template>
    <Head>
        <title>AutoGo — {{ label }}</title>
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    </Head>

    <div class="min-h-screen bg-gray-50 flex items-center justify-center px-4 py-10">
        <div class="bg-white rounded-2xl shadow-sm w-full max-w-md overflow-hidden">
            <header class="bg-indigo-600 text-white px-6 py-5">
                <h1 class="text-base font-semibold">AutoGo — secure entry</h1>
                <p class="text-indigo-100 text-xs mt-0.5">{{ label }}</p>
            </header>

            <form @submit.prevent="submit" class="p-6 space-y-4">
                <label v-if="type !== 'file'" class="block">
                    <span class="block text-xs font-medium text-gray-700 mb-1">{{ label }}</span>
                    <input v-model="form.value" :type="type" :placeholder="placeholder"
                           required autocomplete="off"
                           class="w-full text-base font-mono border-gray-300 rounded-md px-3 py-2.5" />
                </label>

                <label v-else class="block">
                    <span class="block text-xs font-medium text-gray-700 mb-1">{{ label }}</span>
                    <input type="file" :accept="accept" :capture="capture" required @change="onPickFile"
                           class="block w-full text-sm" />
                    <span v-if="form.file" class="text-[11px] text-emerald-600 mt-1 block">{{ form.file.name }}</span>
                </label>

                <p v-if="form.errors.value" class="text-xs text-red-600">{{ form.errors.value }}</p>
                <p v-if="form.errors.file" class="text-xs text-red-600">{{ form.errors.file }}</p>

                <p class="text-[11px] text-gray-500">
                    🔒 Submitted directly to AutoGo. Encrypted in transit and at rest. We never share with third parties.
                </p>

                <button type="submit" :disabled="form.processing"
                        class="w-full px-4 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                    {{ form.processing ? 'Submitting…' : 'Submit' }}
                </button>
            </form>
        </div>
    </div>
</template>
