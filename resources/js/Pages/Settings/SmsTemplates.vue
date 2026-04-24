<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ templates: { type: Array, default: () => [] } });

const newForm = useForm({ label: '', body: '', category: 'general' });
const showAdd = ref(false);
const submit = () => newForm.post(route('settings.sms-templates.store'), {
    preserveScroll: true,
    onSuccess: () => { showAdd.value = false; newForm.reset(); },
});
const toggleActive = (t) => router.put(route('settings.sms-templates.update', t.id), { is_active: !t.is_active }, { preserveScroll: true });
const editTpl = (t) => {
    const newLabel = prompt('Label', t.label); if (newLabel === null) return;
    const newBody  = prompt('Body (use {first_name} / {last_name} placeholders)', t.body); if (newBody === null) return;
    const newCat   = prompt('Category', t.category || ''); if (newCat === null) return;
    router.put(route('settings.sms-templates.update', t.id), { label: newLabel, body: newBody, category: newCat }, { preserveScroll: true });
};
const removeTpl = (t) => { if (!confirm(`Delete "${t.label}"?`)) return; router.delete(route('settings.sms-templates.destroy', t.id), { preserveScroll: true }); };
</script>

<template>
    <AppLayout title="SMS Templates">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('settings')" class="text-gray-500 hover:text-gray-700">&larr; Settings</Link>
                <h2 class="font-bold text-xl text-gray-900">📋 SMS Templates</h2>
            </div>
        </template>

        <div class="py-6 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-900">
                Pre-saved messages staff can pick from the 📋 button in any SMS thread. Use
                <code class="bg-white border px-1.5 py-0.5 rounded text-xs">{first_name}</code> and
                <code class="bg-white border px-1.5 py-0.5 rounded text-xs">{last_name}</code> placeholders — they get
                auto-filled from the customer record.
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-5 py-3 border-b flex items-center justify-between">
                    <h3 class="font-semibold text-sm">{{ templates.length }} template{{ templates.length === 1 ? '' : 's' }}</h3>
                    <button @click="showAdd = !showAdd" class="text-sm bg-indigo-600 text-white px-3 py-1.5 rounded-md hover:bg-indigo-700">
                        {{ showAdd ? 'Cancel' : '+ Add template' }}
                    </button>
                </div>

                <form v-if="showAdd" @submit.prevent="submit" class="p-5 border-b bg-indigo-50/30 space-y-3">
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div><label class="text-xs font-semibold">Label *</label>
                            <input v-model="newForm.label" required class="mt-1 w-full border-gray-300 rounded-md text-sm" placeholder="Vehicle ready for pickup" />
                        </div>
                        <div><label class="text-xs font-semibold">Category</label>
                            <select v-model="newForm.category" class="mt-1 w-full border-gray-300 rounded-md text-sm">
                                <option>general</option><option>rental</option><option>intake</option>
                                <option>bodyshop</option><option>towing</option><option>leasing</option>
                            </select>
                        </div>
                    </div>
                    <div><label class="text-xs font-semibold">Body *</label>
                        <textarea v-model="newForm.body" required rows="3" class="mt-1 w-full border-gray-300 rounded-md text-sm font-mono"
                                  placeholder="Hi {first_name} — your vehicle is ready..."></textarea>
                    </div>
                    <button type="submit" :disabled="newForm.processing"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700 disabled:opacity-50">
                        {{ newForm.processing ? 'Saving…' : 'Save template' }}
                    </button>
                </form>

                <table class="min-w-full divide-y text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>
                        <th class="px-4 py-2 text-left">Label</th>
                        <th class="px-4 py-2 text-left">Body</th>
                        <th class="px-4 py-2 text-left">Category</th>
                        <th class="px-4 py-2 text-left">On</th>
                        <th class="px-4 py-2 text-right">Actions</th>
                    </tr></thead>
                    <tbody class="divide-y">
                        <tr v-for="t in templates" :key="t.id" :class="!t.is_active && 'opacity-50'">
                            <td class="px-4 py-2 font-medium">{{ t.label }}</td>
                            <td class="px-4 py-2 text-xs text-gray-600 max-w-md truncate">{{ t.body }}</td>
                            <td class="px-4 py-2 text-xs"><span class="px-2 py-0.5 bg-gray-100 rounded">{{ t.category || '—' }}</span></td>
                            <td class="px-4 py-2">
                                <button @click="toggleActive(t)" class="text-xs underline" :class="t.is_active ? 'text-emerald-700' : 'text-gray-400'">
                                    {{ t.is_active ? 'On' : 'Off' }}
                                </button>
                            </td>
                            <td class="px-4 py-2 text-right text-xs space-x-2">
                                <button @click="editTpl(t)" class="text-indigo-600 hover:text-indigo-800">Edit</button>
                                <button @click="removeTpl(t)" class="text-red-600 hover:text-red-800">Delete</button>
                            </td>
                        </tr>
                        <tr v-if="!templates.length"><td colspan="5" class="px-4 py-8 text-center text-gray-400">No templates yet.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
