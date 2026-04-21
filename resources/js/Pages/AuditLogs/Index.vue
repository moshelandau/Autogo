<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({ logs: Object, filters: Object });

const q       = ref(props.filters?.q || '');
const method  = ref(props.filters?.method || '');
const source  = ref(props.filters?.source || '');

const apply = () => router.get(route('audit-logs.index'), { q: q.value, method: method.value, source: source.value }, { preserveState: true, replace: true });
watch([q, method, source], apply);

const expanded = ref(null);

const sourceColor = { web: 'bg-blue-100 text-blue-800', mobile_app: 'bg-purple-100 text-purple-800', api: 'bg-amber-100 text-amber-800', internal_job: 'bg-gray-100 text-gray-800' };
const methodColor = { POST: 'bg-emerald-100 text-emerald-800', PUT: 'bg-blue-100 text-blue-800', PATCH: 'bg-blue-100 text-blue-800', DELETE: 'bg-red-100 text-red-800' };
</script>

<template>
    <AppLayout title="Audit Logs">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-xl text-gray-900">📋 Audit Logs</h2>
                    <p class="text-sm text-gray-500">Immutable record of every state-changing action. Append-only — cannot be edited or deleted.</p>
                </div>
            </div>
        </template>

        <div class="p-6 space-y-4">
            <div class="bg-white rounded-xl border p-3 grid grid-cols-1 md:grid-cols-4 gap-3">
                <input v-model="q" type="text" placeholder="Search path / user / action…" class="border-gray-300 rounded-lg text-sm" />
                <select v-model="method" class="border-gray-300 rounded-lg text-sm">
                    <option value="">All methods</option>
                    <option>POST</option><option>PUT</option><option>PATCH</option><option>DELETE</option>
                </select>
                <select v-model="source" class="border-gray-300 rounded-lg text-sm">
                    <option value="">All sources</option>
                    <option value="web">Web</option><option value="mobile_app">Mobile App</option>
                    <option value="api">API</option><option value="internal_job">Internal Job</option>
                </select>
            </div>

            <div class="bg-white rounded-xl border overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>
                        <th class="px-3 py-2 text-left">#</th>
                        <th class="px-3 py-2 text-left">User</th>
                        <th class="px-3 py-2 text-left">Method</th>
                        <th class="px-3 py-2 text-left">Path</th>
                        <th class="px-3 py-2 text-left">Source</th>
                        <th class="px-3 py-2 text-left">IP</th>
                        <th class="px-3 py-2 text-right">Status</th>
                        <th class="px-3 py-2 text-right">ms</th>
                        <th class="px-3 py-2 text-left">When</th>
                    </tr></thead>
                    <tbody class="divide-y">
                        <template v-for="l in logs.data" :key="l.id">
                            <tr class="hover:bg-gray-50 cursor-pointer" @click="expanded = expanded === l.id ? null : l.id">
                                <td class="px-3 py-2 text-xs text-gray-500">{{ l.id }}</td>
                                <td class="px-3 py-2 text-xs">{{ l.user_name || '—' }}</td>
                                <td class="px-3 py-2"><span class="text-[10px] px-1.5 py-0.5 rounded font-mono" :class="methodColor[l.method]">{{ l.method }}</span></td>
                                <td class="px-3 py-2 text-xs font-mono">{{ l.path }}</td>
                                <td class="px-3 py-2"><span class="text-[10px] px-2 py-0.5 rounded-full" :class="sourceColor[l.source]">{{ l.source }}</span></td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ l.ip_address }}</td>
                                <td class="px-3 py-2 text-right text-xs" :class="l.status_code >= 400 ? 'text-red-600 font-bold' : 'text-gray-500'">{{ l.status_code }}</td>
                                <td class="px-3 py-2 text-right text-xs text-gray-500">{{ l.duration_ms }}</td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ new Date(l.created_at).toLocaleString() }}</td>
                            </tr>
                            <tr v-if="expanded === l.id" class="bg-gray-50">
                                <td colspan="9" class="px-4 py-3 text-xs">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <div class="font-semibold mb-1">Params</div>
                                            <pre class="bg-white border rounded p-2 overflow-x-auto text-[10px]">{{ JSON.stringify(l.params, null, 2) }}</pre>
                                        </div>
                                        <div>
                                            <div class="font-semibold mb-1">User Agent</div>
                                            <p class="text-[10px] break-all">{{ l.user_agent }}</p>
                                            <div v-if="l.changes" class="mt-2 font-semibold">Changes</div>
                                            <pre v-if="l.changes" class="bg-white border rounded p-2 overflow-x-auto text-[10px]">{{ JSON.stringify(l.changes, null, 2) }}</pre>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="!logs.data?.length"><td colspan="9" class="px-4 py-8 text-center text-gray-400">No logs in this range.</td></tr>
                    </tbody>
                </table>
            </div>

            <div v-if="logs.links?.length > 3" class="flex justify-center gap-1">
                <Link v-for="link in logs.links" :key="link.label" :href="link.url || '#'"
                      class="px-3 py-1 text-sm rounded" :class="link.active ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100'" v-html="link.label" />
            </div>
        </div>
    </AppLayout>
</template>
