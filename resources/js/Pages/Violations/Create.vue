<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ types: Object, statuses: Object });

const form = useForm({
    plate: '', plate_state: 'NY',
    type: 'parking', jurisdiction: 'NY', issuing_agency: '',
    summons_number: '', citation_number: '',
    issued_at: '', due_date: '',
    location: '', borough_or_county: '',
    fine_amount: '', late_fee: 0, admin_fee: 25,
    notes: '',
    photo: null, document: null,
});

const onPhoto = (e) => form.photo = e.target.files?.[0];
const onDoc   = (e) => form.document = e.target.files?.[0];

const submit = () => form.post(route('violations.store'), { forceFormData: true });
</script>

<template>
    <AppLayout title="Log Violation">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('violations.index')" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                <h2 class="font-bold text-xl text-gray-900">🚓 Log Violation</h2>
            </div>
        </template>

        <form @submit.prevent="submit" class="p-6 max-w-3xl mx-auto space-y-5">
            <!-- Type + jurisdiction -->
            <div class="bg-white rounded-xl border p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold">Violation Type *</label>
                    <select v-model="form.type" required class="mt-1 w-full border-gray-300 rounded-lg text-sm">
                        <option v-for="(label, val) in types" :key="val" :value="val">{{ label }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold">Jurisdiction</label>
                    <select v-model="form.jurisdiction" class="mt-1 w-full border-gray-300 rounded-lg text-sm">
                        <option>NY</option><option>NJ</option><option>CT</option><option>PA</option>
                        <option>MA</option><option>MD</option><option>VA</option><option>DC</option><option>OTHER</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold">Issuing Agency</label>
                    <input v-model="form.issuing_agency" placeholder="NYC DOF, NYC DOT, NYPD, NYS DMV, School District XYZ…" class="mt-1 w-full border-gray-300 rounded-lg text-sm" />
                </div>
            </div>

            <!-- Vehicle + dates -->
            <div class="bg-white rounded-xl border p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold">Plate *</label>
                    <input v-model="form.plate" required class="mt-1 w-full border-gray-300 rounded-lg text-sm uppercase font-mono" />
                </div>
                <div>
                    <label class="block text-xs font-semibold">Plate State</label>
                    <input v-model="form.plate_state" maxlength="2" class="mt-1 w-full border-gray-300 rounded-lg text-sm uppercase" />
                </div>
                <div></div>
                <div>
                    <label class="block text-xs font-semibold">Issued Date/Time *</label>
                    <input v-model="form.issued_at" type="datetime-local" required class="mt-1 w-full border-gray-300 rounded-lg text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-semibold">Due Date</label>
                    <input v-model="form.due_date" type="date" class="mt-1 w-full border-gray-300 rounded-lg text-sm" />
                </div>
                <div></div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold">Location</label>
                    <input v-model="form.location" placeholder="e.g. 5th Ave & 42nd St" class="mt-1 w-full border-gray-300 rounded-lg text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-semibold">Borough / County</label>
                    <input v-model="form.borough_or_county" placeholder="Manhattan, Bronx, Rockland…" class="mt-1 w-full border-gray-300 rounded-lg text-sm" />
                </div>
            </div>

            <!-- Identifiers -->
            <div class="bg-white rounded-xl border p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold">Summons #</label>
                    <input v-model="form.summons_number" class="mt-1 w-full border-gray-300 rounded-lg text-sm font-mono" />
                </div>
                <div>
                    <label class="block text-xs font-semibold">Citation #</label>
                    <input v-model="form.citation_number" class="mt-1 w-full border-gray-300 rounded-lg text-sm font-mono" />
                </div>
                <div>
                    <label class="block text-xs font-semibold">Other ID</label>
                    <input v-model="form.issue_number" class="mt-1 w-full border-gray-300 rounded-lg text-sm font-mono" />
                </div>
            </div>

            <!-- Amounts -->
            <div class="bg-white rounded-xl border p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold">Fine $ *</label>
                    <input v-model.number="form.fine_amount" type="number" step="0.01" required class="mt-1 w-full border-gray-300 rounded-lg text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-semibold">Late Fee $</label>
                    <input v-model.number="form.late_fee" type="number" step="0.01" class="mt-1 w-full border-gray-300 rounded-lg text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-semibold">Admin Fee $</label>
                    <input v-model.number="form.admin_fee" type="number" step="0.01" class="mt-1 w-full border-gray-300 rounded-lg text-sm" />
                    <p class="text-[10px] text-gray-500 mt-0.5">Default $25 pass-through admin charge.</p>
                </div>
            </div>

            <!-- Evidence -->
            <div class="bg-white rounded-xl border p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold">Photo (image of ticket / camera shot)</label>
                    <input type="file" accept="image/*" capture="environment" @change="onPhoto"
                           class="mt-1 block w-full text-sm file:mr-2 file:px-3 file:py-1.5 file:rounded file:border-0 file:bg-indigo-600 file:text-white" />
                </div>
                <div>
                    <label class="block text-xs font-semibold">Document (PDF of summons)</label>
                    <input type="file" accept=".pdf,image/*" @change="onDoc"
                           class="mt-1 block w-full text-sm file:mr-2 file:px-3 file:py-1.5 file:rounded file:border-0 file:bg-indigo-600 file:text-white" />
                </div>
            </div>

            <div class="bg-white rounded-xl border p-5">
                <label class="block text-xs font-semibold">Notes</label>
                <textarea v-model="form.notes" rows="3" class="mt-1 w-full border-gray-300 rounded-lg text-sm"></textarea>
            </div>

            <div class="flex justify-end gap-3">
                <Link :href="route('violations.index')" class="px-4 py-2 text-sm bg-gray-100 rounded-lg">Cancel</Link>
                <button type="submit" :disabled="form.processing" class="bg-indigo-600 text-white px-6 py-2 rounded-lg text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50">
                    {{ form.processing ? 'Saving…' : 'Save & auto-link to rental' }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
