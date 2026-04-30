<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({ phone: { type: String, required: true } });
</script>

<template>
    <Head>
        <title>AutoGo — Submitted</title>
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    </Head>
    <div class="min-h-screen bg-gray-50 flex items-center justify-center px-4 py-10">
        <div class="bg-white rounded-2xl shadow-sm w-full max-w-md overflow-hidden text-center">
            <div class="bg-emerald-600 text-white px-6 py-6">
                <div class="text-4xl mb-2">✓</div>
                <h1 class="text-lg font-bold">Application submitted</h1>
            </div>
            <div class="p-6 space-y-3 text-sm text-gray-700">
                <p>Thanks — your application is in.</p>
                <p>A team member will text <span class="font-mono">{{ phone }}</span> shortly with next steps.</p>
            </div>
        </div>
    </div>
</template>
