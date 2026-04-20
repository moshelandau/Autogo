<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ workers: Array, roles: Array });

const editing = ref(null);
const form = useForm({
    name:'', phone:'', email:'', role:'tech', color:'#6366f1', hourly_rate:'', hire_date:'', is_active:true,
});

const open = (w = null) => {
    editing.value = w;
    if (w) Object.assign(form, w);
    else form.reset();
};

const save = () => {
    if (editing.value) form.put(route('bodyshop.workers.update', editing.value.id), { onSuccess: () => editing.value = null });
    else form.post(route('bodyshop.workers.store'), { onSuccess: () => { form.reset(); editing.value = null; } });
};
const del = (w) => { if (confirm(`Remove ${w.name}?`)) router.delete(route('bodyshop.workers.destroy', w.id)); };

const roleEmoji = { tech: '🔧', painter: '🎨', detailer: '✨', estimator: '📋', manager: '🧑‍💼', helper: '🙋' };
</script>

<template>
    <AppLayout title="Bodyshop Workers">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link :href="route('bodyshop.floor')" class="text-gray-500 hover:text-gray-700">&larr; Floor</Link>
                    <h2 class="font-bold text-xl text-gray-900">👷 Bodyshop Workers</h2>
                </div>
                <button @click="open()" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">+ Add Worker</button>
            </div>
        </template>

        <div class="p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <div v-for="w in workers" :key="w.id"
                     class="bg-white rounded-xl border p-4 flex items-center gap-3"
                     :class="!w.is_active && 'opacity-50'">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center text-white font-bold text-lg flex-shrink-0"
                         :style="{ backgroundColor: w.color }">
                        {{ w.name?.charAt(0) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold truncate">{{ w.name }}</div>
                        <div class="text-xs text-gray-500 capitalize">{{ roleEmoji[w.role] }} {{ w.role }}</div>
                        <div v-if="w.phone" class="text-[11px] text-gray-500">📞 {{ w.phone }}</div>
                    </div>
                    <div class="flex flex-col gap-1">
                        <button @click="open(w)" class="text-xs text-indigo-600 hover:text-indigo-800">Edit</button>
                        <button @click="del(w)" class="text-xs text-red-600 hover:text-red-800">Delete</button>
                    </div>
                </div>
                <div v-if="!workers.length" class="col-span-full text-center text-gray-400 py-12">No workers yet — click + Add Worker</div>
            </div>
        </div>

        <div v-if="editing !== null" @click.self="editing = null"
             class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-xl max-w-md w-full">
                <header class="flex items-center justify-between p-5 border-b">
                    <h3 class="font-semibold">{{ editing ? 'Edit Worker' : 'Add Worker' }}</h3>
                    <button @click="editing = null" class="text-gray-400 text-2xl">×</button>
                </header>
                <form @submit.prevent="save" class="p-5 space-y-3 text-sm">
                    <div><label class="block text-xs">Name *</label><input v-model="form.name" required class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="block text-xs">Phone</label><input v-model="form.phone" class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
                        <div><label class="block text-xs">Email</label><input v-model="form.email" type="email" class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs">Role</label>
                            <select v-model="form.role" class="mt-1 w-full border-gray-300 rounded-lg text-sm">
                                <option v-for="r in roles" :key="r" :value="r">{{ roleEmoji[r] }} {{ r }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs">Color</label>
                            <input v-model="form.color" type="color" class="mt-1 h-9 w-full border-gray-300 rounded-lg" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="block text-xs">Hourly $</label><input v-model="form.hourly_rate" type="number" step="0.01" class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
                        <div><label class="block text-xs">Hire Date</label><input v-model="form.hire_date" type="date" class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
                    </div>
                    <label class="flex items-center gap-2 text-xs"><input type="checkbox" v-model="form.is_active" /> Active</label>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="editing = null" class="px-4 py-2 text-sm bg-gray-100 rounded-lg">Cancel</button>
                        <button type="submit" :disabled="form.processing" class="bg-indigo-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-indigo-700">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
