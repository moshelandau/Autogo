<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';

const props = defineProps({ type: Object, pagesGrouped: Object, pagePermissions: Object });

// Build form data from current permissions
const buildPagesArray = () => {
    const pages = [];
    for (const [group, groupPages] of Object.entries(props.pagesGrouped)) {
        for (const page of groupPages) {
            const perms = props.pagePermissions[page.key] || {};
            pages.push({
                page_key: page.key,
                can_view: perms.can_view || false,
                can_create: perms.can_create || false,
                can_edit: perms.can_edit || false,
                can_delete: perms.can_delete || false,
            });
        }
    }
    return pages;
};

const form = useForm({
    name: props.type.name,
    description: props.type.description || '',
    pages: buildPagesArray(),
});

const getPageForm = (key) => form.pages.find(p => p.page_key === key);

const toggleView = (key) => {
    const p = getPageForm(key);
    if (p) {
        p.can_view = !p.can_view;
        // If unchecking view, uncheck all others
        if (!p.can_view) { p.can_create = false; p.can_edit = false; p.can_delete = false; }
    }
};

const toggleAction = (key, action) => {
    const p = getPageForm(key);
    if (p) {
        p[action] = !p[action];
        // If checking any action, ensure view is checked
        if (p[action]) p.can_view = true;
    }
};

const selectAllForGroup = (groupPages) => {
    for (const page of groupPages) {
        const p = getPageForm(page.key);
        if (p) {
            p.can_view = true;
            if (page.actions.includes('create')) p.can_create = true;
            if (page.actions.includes('edit')) p.can_edit = true;
            if (page.actions.includes('delete')) p.can_delete = true;
        }
    }
};

const clearAllForGroup = (groupPages) => {
    for (const page of groupPages) {
        const p = getPageForm(page.key);
        if (p) { p.can_view = false; p.can_create = false; p.can_edit = false; p.can_delete = false; }
    }
};

const submit = () => form.put(route('permission-types.update', props.type.id));

const deletePT = () => {
    if (confirm('Delete this permission type? Users must be unassigned first.')) {
        router.delete(route('permission-types.destroy', props.type.id));
    }
};
</script>

<template>
    <AppLayout title="Edit Permission Type">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('permission-types.index')" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                    <div>
                        <h2 class="font-bold text-xl text-gray-900">{{ type.name }}</h2>
                        <p class="text-sm text-gray-500">Set page access and permissions</p>
                    </div>
                </div>
                <button @click="deletePT" class="text-sm text-red-500 hover:text-red-700">Delete Type</button>
            </div>
        </template>

        <div class="p-6">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Name & Description -->
                <div class="bg-white rounded-xl border p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Type Name *</label>
                            <input v-model="form.name" type="text" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Description</label>
                            <input v-model="form.description" type="text" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" />
                        </div>
                    </div>
                </div>

                <!-- Page Permissions Grid -->
                <div v-for="(groupPages, groupName) in pagesGrouped" :key="groupName" class="bg-white rounded-xl border overflow-hidden">
                    <div class="px-6 py-3 bg-gray-50 border-b flex justify-between items-center">
                        <h3 class="font-semibold text-sm text-gray-700">{{ groupName }}</h3>
                        <div class="flex gap-2">
                            <button type="button" @click="selectAllForGroup(groupPages)" class="text-xs text-indigo-600 hover:text-indigo-800">Select All</button>
                            <span class="text-gray-300">|</span>
                            <button type="button" @click="clearAllForGroup(groupPages)" class="text-xs text-gray-500 hover:text-gray-700">Clear All</button>
                        </div>
                    </div>
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b">
                                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase w-64">Page</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase w-24">View</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase w-24">Create</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase w-24">Edit</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase w-24">Delete</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="page in groupPages" :key="page.key" class="hover:bg-gray-50">
                                <td class="px-6 py-3">
                                    <span class="text-sm font-medium text-gray-900">{{ page.label }}</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <label v-if="page.actions.includes('view')" class="inline-flex items-center justify-center cursor-pointer">
                                        <input type="checkbox" :checked="getPageForm(page.key)?.can_view" @change="toggleView(page.key)"
                                               class="w-5 h-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer" />
                                    </label>
                                    <span v-else class="text-gray-300">—</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <label v-if="page.actions.includes('create')" class="inline-flex items-center justify-center cursor-pointer">
                                        <input type="checkbox" :checked="getPageForm(page.key)?.can_create" @change="toggleAction(page.key, 'can_create')"
                                               class="w-5 h-5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer" />
                                    </label>
                                    <span v-else class="text-gray-300">—</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <label v-if="page.actions.includes('edit')" class="inline-flex items-center justify-center cursor-pointer">
                                        <input type="checkbox" :checked="getPageForm(page.key)?.can_edit" @change="toggleAction(page.key, 'can_edit')"
                                               class="w-5 h-5 rounded border-gray-300 text-amber-600 focus:ring-amber-500 cursor-pointer" />
                                    </label>
                                    <span v-else class="text-gray-300">—</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <label v-if="page.actions.includes('delete')" class="inline-flex items-center justify-center cursor-pointer">
                                        <input type="checkbox" :checked="getPageForm(page.key)?.can_delete" @change="toggleAction(page.key, 'can_delete')"
                                               class="w-5 h-5 rounded border-gray-300 text-red-600 focus:ring-red-500 cursor-pointer" />
                                    </label>
                                    <span v-else class="text-gray-300">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Save -->
                <div class="flex justify-end gap-3">
                    <Link :href="route('permission-types.index')" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</Link>
                    <button type="submit" :disabled="form.processing" class="px-8 py-2.5 text-sm text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 font-medium">
                        {{ form.processing ? 'Saving...' : 'Save Permission Type' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
