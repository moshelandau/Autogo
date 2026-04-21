<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';

const props = defineProps({ current: Object, history: Array });

const form = useForm({ bucket: '', region: 'us-east-1', access_key: '', secret_key: '' });
const save = () => form.post(route('settings.s3.save'));
const restore = (h) => { if (confirm(`Restore S3 config from ${new Date(h.created_at).toLocaleString()}?`)) router.post(route('settings.s3.restore', h.id)); };
</script>

<template>
    <AppLayout title="S3 Storage Settings">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('settings')" class="text-gray-500 hover:text-gray-700">&larr; Settings</Link>
                <h2 class="font-bold text-xl text-gray-900">📦 S3 Storage — Locked-down</h2>
            </div>
        </template>

        <div class="p-6 max-w-4xl mx-auto space-y-5">
            <div class="bg-amber-50 border border-amber-300 rounded-xl p-4 text-sm text-amber-900">
                🛡 <strong>Safety lock:</strong> S3 keys can never be deleted. To rotate, enter a new set below — the test runs first; if it fails, the previous keys stay active.
            </div>

            <!-- Current active -->
            <div v-if="current" class="bg-emerald-50 border border-emerald-300 rounded-xl p-5">
                <div class="font-semibold text-emerald-900">Currently active</div>
                <dl class="text-sm mt-2 space-y-1">
                    <div><strong>Bucket:</strong> {{ current.bucket }} ({{ current.region }})</div>
                    <div><strong>Access key:</strong> {{ current.access_key }}</div>
                    <div class="text-xs text-emerald-800">Activated {{ new Date(current.created_at).toLocaleString() }}</div>
                </dl>
            </div>
            <div v-else class="bg-gray-50 border rounded-xl p-5 text-sm text-gray-600">No S3 keys saved yet.</div>

            <!-- New config form (test-before-save) -->
            <form @submit.prevent="save" class="bg-white border rounded-xl p-5 space-y-3">
                <h3 class="font-semibold">Save NEW S3 config (test runs first)</h3>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-xs font-semibold">Bucket *</label><input v-model="form.bucket" required class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
                    <div><label class="block text-xs font-semibold">Region *</label><input v-model="form.region" required placeholder="us-east-1" class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
                    <div><label class="block text-xs font-semibold">Access Key *</label><input v-model="form.access_key" required class="mt-1 w-full border-gray-300 rounded-lg text-sm font-mono" /></div>
                    <div><label class="block text-xs font-semibold">Secret Key *</label><input v-model="form.secret_key" required type="password" class="mt-1 w-full border-gray-300 rounded-lg text-sm font-mono" /></div>
                </div>
                <button type="submit" :disabled="form.processing"
                        class="px-5 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50">
                    {{ form.processing ? 'Testing & saving…' : '🔐 Test & Activate' }}
                </button>
                <p class="text-xs text-gray-500">Performs a real put → get → delete on the bucket. Only activates if successful.</p>
            </form>

            <!-- History -->
            <div class="bg-white border rounded-xl overflow-hidden">
                <header class="p-4 border-b"><h3 class="font-semibold">History (no deletions allowed)</h3></header>
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>
                        <th class="px-3 py-2 text-left">Bucket</th>
                        <th class="px-3 py-2 text-left">Access Key</th>
                        <th class="px-3 py-2 text-left">Test</th>
                        <th class="px-3 py-2 text-left">Saved</th>
                        <th class="px-3 py-2"></th>
                    </tr></thead>
                    <tbody class="divide-y">
                        <tr v-for="h in history" :key="h.id" :class="h.is_active && 'bg-emerald-50'">
                            <td class="px-3 py-2">{{ h.bucket }} <span class="text-xs text-gray-400">({{ h.region }})</span></td>
                            <td class="px-3 py-2 font-mono text-xs">{{ h.access_key }}</td>
                            <td class="px-3 py-2">
                                <span v-if="h.test_passed" class="text-xs text-emerald-700">✓ Pass</span>
                                <span v-else class="text-xs text-red-700">✗ Fail</span>
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-500">{{ new Date(h.created_at).toLocaleString() }}</td>
                            <td class="px-3 py-2 text-right">
                                <span v-if="h.is_active" class="text-xs text-emerald-700 font-semibold">ACTIVE</span>
                                <button v-else-if="h.test_passed" @click="restore(h)" class="text-xs text-indigo-600 hover:text-indigo-800">Restore</button>
                            </td>
                        </tr>
                        <tr v-if="!history.length"><td colspan="5" class="px-4 py-6 text-center text-gray-400">No history yet</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
