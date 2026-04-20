<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({ lifts: Array, workers: Array, phases: Array, recentClaims: Array });

const showAssign = ref(null); // lift being assigned to
const assignForm = useForm({
    bodyshop_worker_id: '', claim_id: '', vehicle_label: '', vehicle_plate: '',
    repair_phase: 'disassembly', estimated_completion: '', notes: '',
});

const openAssign = (lift) => {
    assignForm.reset();
    assignForm.repair_phase = 'disassembly';
    showAssign.value = lift;
};
const submitAssign = () => {
    assignForm.post(route('bodyshop.assign', showAssign.value.id), {
        preserveScroll: true,
        onSuccess: () => showAssign.value = null,
    });
};

const release = (lift) => {
    if (!confirm(`Release ${lift.name}? The current vehicle will be marked completed.`)) return;
    router.post(route('bodyshop.release', lift.id), {}, { preserveScroll: true });
};

const setPhase = (slot, phase) => {
    router.put(route('bodyshop.slots.update', slot.id), { repair_phase: phase, status: slot.status }, { preserveScroll: true });
};

const liftIcon = { lift: '🛗', bay: '🅿️', spray_booth: '🎨', frame_machine: '🔧', detail_bay: '✨' };
const phaseColor = {
    disassembly: 'bg-amber-500',
    body:        'bg-orange-500',
    paint:       'bg-violet-500',
    reassembly:  'bg-blue-500',
    detail:      'bg-cyan-500',
    ready:       'bg-emerald-500',
};

const occupied = computed(() => props.lifts.filter(l => l.active_slot).length);
const total = computed(() => props.lifts.length);
</script>

<template>
    <AppLayout title="Bodyshop Floor">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-xl text-gray-900">🔧 Bodyshop Floor</h2>
                    <p class="text-sm text-gray-500">{{ occupied }} / {{ total }} lifts in use · {{ workers.length }} workers</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('bodyshop.workers')" class="px-3 py-1.5 text-sm rounded-lg border bg-white hover:bg-gray-50">👷 Workers</Link>
                    <Link :href="route('bodyshop.lifts')" class="px-3 py-1.5 text-sm rounded-lg border bg-white hover:bg-gray-50">🛠 Lifts</Link>
                </div>
            </div>
        </template>

        <div class="p-6">
            <div v-if="!lifts.length" class="bg-white border rounded-xl p-10 text-center">
                <p class="text-gray-500 mb-4">No lifts configured yet.</p>
                <Link :href="route('bodyshop.lifts')" class="inline-block bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm">+ Add your first lift</Link>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <div v-for="lift in lifts" :key="lift.id"
                     class="bg-white rounded-2xl border-2 overflow-hidden transition hover:shadow-lg"
                     :class="lift.active_slot ? 'border-emerald-300' : 'border-dashed border-gray-300'">

                    <!-- Lift header -->
                    <header class="p-4 flex items-center justify-between text-white"
                            :style="{ backgroundColor: lift.color || '#0ea5e9' }">
                        <div class="flex items-center gap-2">
                            <span class="text-2xl">{{ liftIcon[lift.type] || '🛗' }}</span>
                            <div>
                                <h3 class="font-bold leading-tight">{{ lift.name }}</h3>
                                <span class="text-[10px] opacity-90 uppercase">{{ lift.type?.replace('_', ' ') }}</span>
                            </div>
                        </div>
                        <span class="text-[10px] px-2 py-1 rounded-full"
                              :class="lift.active_slot ? 'bg-emerald-500/40' : 'bg-white/30'">
                            {{ lift.active_slot ? 'OCCUPIED' : 'FREE' }}
                        </span>
                    </header>

                    <!-- Active slot -->
                    <div v-if="lift.active_slot" class="p-4 space-y-3">
                        <div>
                            <div class="font-semibold text-gray-900">{{ lift.active_slot.vehicle_label || 'Vehicle' }}</div>
                            <div v-if="lift.active_slot.vehicle_plate" class="text-xs text-gray-500">🪪 {{ lift.active_slot.vehicle_plate }}</div>
                            <div v-if="lift.active_slot.customer" class="text-xs text-gray-600 mt-1">
                                👤 {{ lift.active_slot.customer.first_name }} {{ lift.active_slot.customer.last_name }}
                            </div>
                        </div>

                        <!-- Worker chip -->
                        <div v-if="lift.active_slot.worker" class="flex items-center gap-2 text-xs bg-gray-50 rounded-lg p-2">
                            <span class="w-6 h-6 rounded-full flex items-center justify-center text-white font-bold text-[10px]"
                                  :style="{ backgroundColor: lift.active_slot.worker.color }">
                                {{ lift.active_slot.worker.name?.charAt(0) }}
                            </span>
                            <div class="flex-1">
                                <div class="font-medium">{{ lift.active_slot.worker.name }}</div>
                                <div class="text-[10px] text-gray-500 capitalize">{{ lift.active_slot.worker.role }}</div>
                            </div>
                        </div>

                        <!-- Phase pill row -->
                        <div>
                            <div class="text-[10px] text-gray-500 mb-1.5">Phase</div>
                            <div class="flex flex-wrap gap-1">
                                <button v-for="p in phases" :key="p"
                                        @click="setPhase(lift.active_slot, p)"
                                        class="text-[10px] px-2 py-0.5 rounded-full transition"
                                        :class="lift.active_slot.repair_phase === p
                                            ? `text-white ${phaseColor[p]}`
                                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
                                    {{ p }}
                                </button>
                            </div>
                        </div>

                        <div v-if="lift.active_slot.estimated_completion" class="text-[10px] text-gray-500">
                            🎯 Est. done: {{ lift.active_slot.estimated_completion }}
                        </div>

                        <button @click="release(lift)" class="w-full text-xs py-1.5 border border-rose-300 text-rose-700 rounded-lg hover:bg-rose-50">
                            ✓ Release Lift
                        </button>
                    </div>

                    <!-- Empty -->
                    <div v-else class="p-6 text-center">
                        <button @click="openAssign(lift)" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-indigo-700">
                            + Assign Vehicle
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assign modal -->
        <div v-if="showAssign" @click.self="showAssign = null"
             class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full">
                <header class="flex items-center justify-between p-5 border-b">
                    <h3 class="font-semibold">Assign Vehicle to {{ showAssign.name }}</h3>
                    <button @click="showAssign = null" class="text-gray-400 hover:text-gray-700 text-2xl leading-none">×</button>
                </header>
                <form @submit.prevent="submitAssign" class="p-5 space-y-4 text-sm">
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Vehicle (year/make/model)</label>
                        <input v-model="assignForm.vehicle_label" placeholder="2024 Honda Civic" class="mt-1 w-full border-gray-300 rounded-lg text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Plate</label>
                        <input v-model="assignForm.vehicle_plate" class="mt-1 w-full border-gray-300 rounded-lg text-sm uppercase" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Worker</label>
                        <select v-model="assignForm.bodyshop_worker_id" class="mt-1 w-full border-gray-300 rounded-lg text-sm">
                            <option value="">— None —</option>
                            <option v-for="w in workers" :key="w.id" :value="w.id">{{ w.name }} ({{ w.role }})</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700">Linked claim (optional)</label>
                        <select v-model="assignForm.claim_id" class="mt-1 w-full border-gray-300 rounded-lg text-sm">
                            <option value="">— None —</option>
                            <option v-for="c in recentClaims" :key="c.id" :value="c.id">
                                #{{ c.id }} — {{ c.customer?.first_name }} {{ c.customer?.last_name }} · {{ c.vehicle_year }} {{ c.vehicle_make }} {{ c.vehicle_model }}
                            </option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs">Phase</label>
                            <select v-model="assignForm.repair_phase" class="mt-1 w-full border-gray-300 rounded-lg text-sm capitalize">
                                <option v-for="p in phases" :key="p" :value="p">{{ p }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs">Est. Completion</label>
                            <input v-model="assignForm.estimated_completion" type="date" class="mt-1 w-full border-gray-300 rounded-lg text-sm" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs">Notes</label>
                        <textarea v-model="assignForm.notes" rows="2" class="mt-1 w-full border-gray-300 rounded-lg text-sm"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showAssign = null" class="px-4 py-2 text-sm bg-gray-100 rounded-lg">Cancel</button>
                        <button type="submit" :disabled="assignForm.processing" class="bg-indigo-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-indigo-700">
                            {{ assignForm.processing ? 'Assigning…' : 'Assign' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
