<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    token:      { type: String, required: true },
    phone_hint: { type: String, required: true },
});

const sendForm = useForm({});
const verifyForm = useForm({ code: '' });

const sendCode  = () => sendForm.post('/apply/' + props.token + '/send-otp');
const verifyCode = () => verifyForm.post('/apply/' + props.token + '/verify-otp');

const flash = computed(() => usePage().props.flash || {});
</script>

<template>
    <Head>
        <title>AutoGo — Verify</title>
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    </Head>

    <div class="min-h-screen bg-gray-50 flex items-center justify-center px-4 py-10">
        <div class="bg-white rounded-2xl shadow-sm w-full max-w-md overflow-hidden">
            <header class="bg-indigo-600 text-white px-6 py-5">
                <h1 class="text-lg font-bold">Quick verify</h1>
                <p class="text-indigo-100 text-xs mt-1">Confirming this is you before we show your application.</p>
            </header>

            <div class="p-6 space-y-4">
                <p class="text-sm text-gray-700">
                    We'll text a 6-digit code to <span class="font-mono">{{ phone_hint }}</span>.
                </p>

                <button @click="sendCode" :disabled="sendForm.processing"
                        class="w-full px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 disabled:opacity-50">
                    {{ sendForm.processing ? 'Sending…' : 'Text me a code' }}
                </button>

                <p v-if="flash.success" class="text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 rounded p-2">{{ flash.success }}</p>
                <p v-if="flash.error" class="text-xs text-red-700 bg-red-50 border border-red-200 rounded p-2">{{ flash.error }}</p>

                <form @submit.prevent="verifyCode" class="pt-2 border-t space-y-3">
                    <label class="block">
                        <span class="block text-xs font-medium text-gray-700 mb-1">Enter the 6-digit code</span>
                        <input v-model="verifyForm.code" inputmode="numeric" pattern="\d{6}" maxlength="6"
                               autocomplete="one-time-code"
                               class="w-full text-center text-xl tracking-widest font-mono border-gray-300 rounded-md py-2" />
                    </label>
                    <p v-if="verifyForm.errors.code" class="text-xs text-red-600">{{ verifyForm.errors.code }}</p>
                    <button type="submit" :disabled="verifyForm.processing || verifyForm.code.length !== 6"
                            class="w-full px-4 py-2.5 bg-gray-900 text-white text-sm font-medium rounded-md hover:bg-gray-800 disabled:opacity-50">
                        {{ verifyForm.processing ? 'Verifying…' : 'Verify' }}
                    </button>
                </form>

                <p class="text-[11px] text-gray-500 text-center pt-1">Code expires in 10 minutes. Single use.</p>
            </div>
        </div>
    </div>
</template>
