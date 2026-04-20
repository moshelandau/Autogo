<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ lifts: Array, types: Array });

const editing = ref(null);
const form = useForm({ name:'', type:'lift', position:0, color:'#0ea5e9', is_active:true, notes:'' });

const open = (l = null) => {
    editing.value = l;
    if (l) Object.assign(form, l);
    else form.reset();
};
const save = () => {
    if (editing.value) form.put(route('bodyshop.lifts.update', editing.value.id), { onSuccess: () => editing.value = null });
    else form.post(route('bodyshop.lifts.store'), { onSuccess: () => { form.reset(); editing.value = null; } });
};
const del = (l) => { if (confirm(`Remove ${l.name}?`)) router.delete(route('bodyshop.lifts.destroy', l.id)); };

const typeIcon = { lift: '🛗', bay: '🅿️', spray_booth: '🎨', frame_machine: '🔧', detail_bay: '✨' };
</script>

<template>
    <AppLayout title="Bodyshop Lifts & Bays">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link :href="route('bodyshop.floor')" class="text-gray-500 hover:text-gray-700">&larr; Floor</Link>
                    <h2 class="font-bold text-xl text-gray-900">🛠 Bodyshop Lifts &amp; Bays</h2>
                </div>
                <button @click="open()" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">+ Add Lift / Bay</button>
            </div>
        </template>

        <div class="p-6">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                <div v-for="l in lifts" :key="l.id"
                     class="bg-white rounded-2xl border-2 overflow-hidden"
                     :class="!l.is_active && 'opacity-50'">
                    <header class="p-4 text-white text-center" :style="{ backgroundColor: l.color }">
                        <div class="text-3xl mb-1">{{ typeIcon[l.type] }}</div>
                        <div class="font-bold">{{ l.name }}</div>
                        <div class="text-[10px] opacity-90 uppercase">{{ l.type?.replace('_', ' ') }}</div>
                    </header>
                    <div class="p-3 flex justify-between text-xs">
                        <button @click="open(l)" class="text-indigo-600 hover:text-indigo-800">Edit</button>
                        <button @click="del(l)" class="text-red-600 hover:text-red-800">Delete</button>
                    </div>
                </div>
                <div v-if="!lifts.length" class="col-span-full text-center text-gray-400 py-12">No lifts yet — click + Add Lift / Bay</div>
            </div>
        </div>

        <div v-if="editing !== null" @click.self="editing = null" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-xl max-w-md w-full">
                <header class="flex items-center justify-between p-5 border-b">
                    <h3 class="font-semibold">{{ editing ? 'Edit' : 'Add' }} Lift / Bay</h3>
                    <button @click="editing = null" class="text-gray-400 text-2xl">×</button>
                </header>
                <form @submit.prevent="save" class="p-5 space-y-3 text-sm">
                    <div><label class="block text-xs">Name *</label><input v-model="form.name" required placeholder="Lift 1, Bay A, Spray Booth" class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
                    <div>
                        <label class="block text-xs">Type</label>
                        <select v-model="form.type" class="mt-1 w-full border-gray-300 rounded-lg text-sm">
                            <option v-for="t in types" :key="t" :value="t">{{ typeIcon[t] }} {{ t.replace('_',' ') }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="block text-xs">Position (sort order)</label><input v-model="form.position" type="number" class="mt-1 w-full border-gray-300 rounded-lg text-sm" /></div>
                        <div><label class="block text-xs">Color</label><input v-model="form.color" type="color" class="mt-1 h-9 w-full border-gray-300 rounded-lg" /></div>
                    </div>
                    <div><label class="block text-xs">Notes</label><textarea v-model="form.notes" rows="2" class="mt-1 w-full border-gray-300 rounded-lg text-sm"></textarea></div>
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
