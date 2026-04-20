<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ types: Array });

const form = useForm({ name: '', description: '' });
const submit = () => form.post(route('permission-types.store'), { onSuccess: () => form.reset() });
</script>

<template>
    <AppLayout title="Permission Types">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('users.index')" class="text-gray-500 hover:text-gray-700">&larr; Users</Link>
                <div>
                    <h2 class="font-bold text-xl text-gray-900">Permission Types</h2>
                    <p class="text-sm text-gray-500">Define access levels — assign to users via dropdown</p>
                </div>
            </div>
        </template>

        <div class="p-6 space-y-6">
            <!-- Create New -->
            <div class="bg-white rounded-xl border p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Create Permission Type</h3>
                <form @submit.prevent="submit" class="flex gap-4">
                    <div class="flex-1">
                        <input v-model="form.name" type="text" placeholder="Type name (e.g. Office Manager, Sales Rep)" class="block w-full border-gray-300 rounded-lg text-sm" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div class="flex-1">
                        <input v-model="form.description" type="text" placeholder="Description (optional)" class="block w-full border-gray-300 rounded-lg text-sm" />
                    </div>
                    <button type="submit" :disabled="form.processing || !form.name" class="px-6 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700 disabled:opacity-50 whitespace-nowrap">Create Type</button>
                </form>
            </div>

            <!-- Types List -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <Link v-for="t in types" :key="t.id" :href="route('permission-types.edit', t.id)"
                      class="bg-white rounded-xl border p-6 hover:shadow-lg hover:-translate-y-0.5 transition-all group">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <h3 class="font-bold text-gray-900 text-lg">{{ t.name }}</h3>
                            <p v-if="t.description" class="text-xs text-gray-500 mt-0.5">{{ t.description }}</p>
                        </div>
                        <svg class="w-5 h-5 text-gray-300 group-hover:text-indigo-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </div>
                    <div class="flex gap-4 text-sm">
                        <div>
                            <span class="text-2xl font-bold text-indigo-600">{{ t.pages_count }}</span>
                            <span class="text-xs text-gray-500 ml-1">pages</span>
                        </div>
                        <div>
                            <span class="text-2xl font-bold text-emerald-600">{{ t.users_count }}</span>
                            <span class="text-xs text-gray-500 ml-1">users</span>
                        </div>
                    </div>
                </Link>

                <div v-if="!types?.length" class="col-span-full bg-white rounded-xl border p-12 text-center text-gray-400">
                    No permission types yet. Create one above to get started.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
