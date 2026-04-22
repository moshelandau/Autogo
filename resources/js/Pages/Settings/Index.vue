<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref, reactive } from 'vue';
import axios from 'axios';

const props = defineProps({
    settings: { type: Object, default: () => ({}) },
    secrets:  { type: Object, default: () => ({}) },
    env: { type: Object, default: () => ({}) },
});

// All known setting keys, grouped. Empty defaults pulled from props.settings.
const sections = [
    {
        id: 'company',
        title: 'Company / General',
        icon: '🏢',
        desc: 'Company info, sales tax, default state, business hours.',
        fields: [
            { key: 'company_name',     label: 'Company name',          type: 'text' },
            { key: 'company_ein',      label: 'EIN',                   type: 'text' },
            { key: 'company_address',  label: 'Address',               type: 'text' },
            { key: 'company_phone',    label: 'Main phone',            type: 'text' },
            { key: 'company_email',    label: 'Main email',            type: 'email' },
            { key: 'ny_dealer_license',label: 'NY dealer license #',   type: 'text' },
            { key: 'default_state',    label: 'Default state',         type: 'text', placeholder: 'NY' },
            { key: 'sales_tax_pct',    label: 'Sales tax %',           type: 'text', placeholder: '8.875' },
        ],
    },
    {
        id: 'telebroad',
        title: 'Telebroad — Phone System',
        icon: '☎️',
        desc: 'Click-to-call, voicemail, call logs. AutoGo logs every call against the matching customer/deal.',
        envFlag: 'telebroad',
        testKey: 'telebroad',
        fields: [
            { key: 'telebroad_username',     label: 'Username',     type: 'text' },
            { key: 'telebroad_password',     label: 'Password',     type: 'password' },
            { key: 'telebroad_phone_number', label: 'Caller-ID #',  type: 'text', placeholder: '+18453255387' },
            { key: 'telebroad_api_url',      label: 'API URL',      type: 'text', placeholder: 'https://webserv.telebroad.com/api/teleconsole/rest' },
        ],
    },
    {
        id: 'sms_bot',
        title: 'SMS Application Bot',
        icon: '🤖',
        desc: 'Auto-reply bot that walks customers through lease/rental/towing/bodyshop intake by text. Triggers ONLY on exact words (help / new / car / lease / rental / tow / bodyshop). Auto-suppresses on detected loops (3+ identical replies in 10 min) and on auto-responder fingerprints (auto-reply, do-not-reply, out-of-office, msg&data rates, etc.).',
        fields: [
            { key: 'bot_disabled', label: 'Kill switch — set to "1" to silence the bot system-wide', type: 'text', placeholder: '0' },
        ],
    },
    {
        id: 'ai',
        title: 'AI (Anthropic Claude)',
        icon: '🧠',
        desc: 'Powers SMS routing (respond/silent/escalate), per-answer validation, license/insurance/credit-card OCR, and document classification. Get a key at console.anthropic.com.',
        envFlag: 'anthropic',
        fields: [
            { key: 'anthropic_api_key',     label: 'Anthropic API key (sk-ant-…)',  type: 'password' },
            { key: 'ai_router_model',       label: 'SMS router model',              type: 'text', placeholder: 'claude-3-5-sonnet-latest' },
            { key: 'ai_validator_model',    label: 'Per-answer validator model',    type: 'text', placeholder: 'claude-3-5-haiku-latest' },
            { key: 'ai_router_disabled',    label: 'Kill switch for AI router (set "1" to bypass; rule-based safeguards still apply)', type: 'text', placeholder: '0' },
            { key: 'ai_validator_disabled', label: 'Kill switch for per-answer validator (set "1" to skip)', type: 'text', placeholder: '0' },
        ],
    },
    {
        id: 'twilio',
        title: 'Twilio (Backup SMS)',
        icon: '💬',
        desc: 'Optional backup for outbound SMS reminders if Telebroad SMS is unavailable.',
        envFlag: 'twilio',
        testKey: 'twilio',
        fields: [
            { key: 'twilio_sid',   label: 'Account SID',  type: 'text' },
            { key: 'twilio_token', label: 'Auth token',   type: 'password' },
            { key: 'twilio_from',  label: 'From number',  type: 'text', placeholder: '+15551234567' },
        ],
    },
    {
        id: 'sola',
        title: 'Sola Payments / Cardknox — Two Merchants',
        icon: '💳',
        desc: 'Sola Payments is powered by Cardknox. Each merchant has its own xKey. Endpoint: https://x1.cardknox.com/gatewayjson. PCI compliant — card numbers are tokenized, never stored locally. Holds always go to High Car Rental; final charges prompt operator to choose.',
        envFlag: 'sola',
        fields: [],
        subTests: [
            { id: 'sola_autogo',     label: 'Test AutoGo xKey',         keyField: 'sola_api_key',        labelText: 'AutoGo xKey (from Cardknox merchant account)' },
            { id: 'sola_high_rental',label: 'Test High Car Rental xKey',keyField: 'sola_webhook_secret', labelText: 'High Car Rental xKey (from Cardknox merchant account)' },
        ],
    },
    {
        id: 'credit700',
        title: '700Credit (Soft Pulls)',
        icon: '📊',
        desc: 'Pre-qualification soft pulls only. AutoGo never runs hard pulls — those are done by the dealer/lender.',
        envFlag: 'credit700',
        testKey: 'credit700',
        fields: [
            { key: 'credit700_api_key', label: 'API key',  type: 'password' },
            { key: 'credit700_api_url', label: 'API URL',  type: 'text', placeholder: 'https://api.700credit.com/v1' },
            { key: 'credit700_account', label: 'Account #', type: 'text' },
        ],
    },
    {
        id: 'asana',
        title: 'Asana (Task Sync)',
        icon: '✅',
        desc: 'Read-only sync of tasks from Asana into AutoGo Office Tasks during the migration period.',
        envFlag: 'asana',
        testKey: 'asana',
        fields: [
            { key: 'asana_token',        label: 'Personal Access Token', type: 'password' },
            { key: 'asana_workspace_id', label: 'Workspace ID',          type: 'text' },
        ],
    },
    {
        id: 'hq_rentals',
        title: 'HQ Rentals (Migration)',
        icon: '🚗',
        desc: 'Pull historical reservations + customers from HQ Rentals during migration. Will be disabled once cutover is complete.',
        envFlag: 'hq_rentals',
        testKey: 'hq_rentals',
        fields: [
            { key: 'hq_rentals_api_key',     label: 'API key',     type: 'password' },
            { key: 'hq_rentals_subdomain',   label: 'Subdomain',   type: 'text', placeholder: 'highrental' },
            { key: 'hq_rentals_location_id', label: 'Location ID', type: 'text' },
        ],
    },
    {
        id: 'ccc_one',
        title: 'CCC ONE (Estimates)',
        icon: '🔧',
        desc: 'Bodyshop estimate / claim integration. CCC ONE has no public API — credentials used by scraper.',
        envFlag: 'ccc_one',
        testKey: 'ccc_one',
        fields: [
            { key: 'ccc_one_username', label: 'Username', type: 'text' },
            { key: 'ccc_one_password', label: 'Password', type: 'password' },
        ],
    },
    {
        id: 'towbook',
        title: 'TowBook (Dispatch)',
        icon: '🚛',
        desc: 'Tow dispatch system — OAuth2 API integration. Email support@towbook.com asking for "API client credentials for AutoGo integration" (your account: TB2254354 / AG312995). They\'ll send a client_id + client_secret. Once added, the scheduler syncs hourly automatically.',
        envFlag: 'towbook',
        testKey: 'towbook',
        fields: [
            { key: 'towbook_client_id',     label: 'Client ID (from TowBook)',     type: 'text' },
            { key: 'towbook_client_secret', label: 'Client Secret (from TowBook)', type: 'password' },
            { key: 'towbook_company_id',    label: 'Company ID',                   type: 'text', placeholder: 'AG312995' },
            { key: 'towbook_username',      label: 'Username (scrape fallback)',   type: 'text', placeholder: '8455008085' },
            { key: 'towbook_password',      label: 'Password (scrape fallback)',   type: 'password' },
        ],
    },
    {
        id: 'swoop',
        title: 'Agero / Swoop',
        icon: '🪝',
        desc: 'Direct integration with Agero (Swoop) tow dispatch network. Requires partner approval from Agero.',
        envFlag: 'swoop',
        testKey: 'swoop',
        fields: [
            { key: 'swoop_env',        label: 'Environment',  type: 'select', options: ['sandbox','live'] },
            { key: 'swoop_api_key',    label: 'API key',      type: 'password' },
            { key: 'swoop_partner_id', label: 'Partner ID',   type: 'text' },
        ],
    },
    {
        id: 'allstate_roadside',
        title: 'Allstate Roadside',
        icon: '🛡️',
        desc: 'Direct integration with Allstate Roadside Services. Requires partner enrollment with Allstate.',
        envFlag: 'allstate_roadside',
        testKey: 'allstate_roadside',
        fields: [
            { key: 'allstate_roadside_username', label: 'Username', type: 'text' },
            { key: 'allstate_roadside_password', label: 'Password', type: 'password' },
            { key: 'allstate_roadside_api_key',  label: 'API key',  type: 'password' },
        ],
    },
    {
        id: 'mail',
        title: 'Email (SMTP)',
        icon: '✉️',
        desc: 'Outgoing email — quotes, invoices, password resets.',
        envFlag: 'mail',
        testKey: 'mail',
        fields: [
            { key: 'mail_from_address', label: 'From address', type: 'email' },
            { key: 'mail_from_name',    label: 'From name',    type: 'text' },
            { key: 'mail_host',         label: 'SMTP host',    type: 'text' },
            { key: 'mail_port',         label: 'SMTP port',    type: 'text', placeholder: '587' },
            { key: 'mail_username',     label: 'SMTP username',type: 'text' },
            { key: 'mail_password',     label: 'SMTP password',type: 'password' },
            { key: 'mail_encryption',   label: 'Encryption',   type: 'select', options: ['tls','ssl','none'] },
        ],
    },
    {
        id: 's3',
        title: 'Photo / File Storage (S3)',
        icon: '📦',
        desc: 'Vehicle photos, damage photos, signed documents.',
        envFlag: 's3',
        testKey: 's3',
        fields: [
            { key: 's3_bucket', label: 'Bucket',       type: 'text' },
            { key: 's3_region', label: 'Region',       type: 'text', placeholder: 'us-east-1' },
            { key: 's3_key',    label: 'Access key',   type: 'text' },
            { key: 's3_secret', label: 'Secret key',   type: 'password' },
        ],
    },
    {
        id: 'lease_defaults',
        title: 'Lease Defaults',
        icon: '🧮',
        desc: 'Defaults applied when calculator/quote starts.',
        fields: [
            { key: 'default_lease_term',     label: 'Default term (months)',  type: 'text', placeholder: '36' },
            { key: 'default_annual_mileage', label: 'Default annual mileage', type: 'text', placeholder: '10000' },
            { key: 'default_acq_fee',        label: 'Acquisition fee',        type: 'text', placeholder: '795' },
            { key: 'default_doc_fee',        label: 'Doc fee',                type: 'text', placeholder: '175' },
            { key: 'default_dealer_reserve_pct', label: 'Dealer reserve %',   type: 'text', placeholder: '2' },
        ],
    },
];

// Build form state from props.settings
const buildModel = () => {
    const m = {};
    sections.forEach(s => s.fields.forEach(f => { m[f.key] = props.settings?.[f.key] ?? ''; }));
    return m;
};

const model = reactive(buildModel());
const saving = ref(false);
const flash = ref(null);
const testResults = reactive({});
const testing = reactive({});

const save = () => {
    saving.value = true;
    flash.value = null;
    const payload = {
        settings: Object.entries(model).map(([key, value]) => {
            const section = sections.find(s => s.fields.some(f => f.key === key));
            return { key, value: value ?? '', group: section?.id ?? 'general' };
        }),
    };
    router.post(route('settings.update'), payload, {
        preserveScroll: true,
        onSuccess: () => { flash.value = { ok: true,  msg: 'Settings saved.' }; },
        onError:   () => { flash.value = { ok: false, msg: 'Save failed — check fields.' }; },
        onFinish:  () => { saving.value = false; setTimeout(() => flash.value = null, 4000); },
    });
};

const runTest = async (section) => {
    if (!section.testKey) return;
    testing[section.id] = true;
    testResults[section.id] = null;
    const overrides = {};
    section.fields.forEach(f => { overrides[f.key.replace(`${section.id}_`, '')] = model[f.key]; });
    try {
        const { data } = await axios.post(route('settings.test', section.testKey), overrides);
        testResults[section.id] = data;
    } catch (e) {
        testResults[section.id] = { ok: false, message: e.response?.data?.message || e.message };
    } finally {
        testing[section.id] = false;
    }
};

// Per-account sub-test (e.g. Sola AutoGo / Sola High Rental)
const runSubTest = async (subTestId, keyField) => {
    testing[subTestId] = true;
    testResults[subTestId] = null;
    try {
        const { data } = await axios.post(route('settings.test', subTestId), {
            api_key: model[keyField],
            env:     model['sola_env'],
            api_base: model['sola_api_base'],
        });
        testResults[subTestId] = data;
    } catch (e) {
        testResults[subTestId] = { ok: false, message: e.response?.data?.message || e.message };
    } finally {
        testing[subTestId] = false;
    }
};

const activeSection = ref('company');
const scrollTo = (id) => {
    activeSection.value = id;
    document.getElementById('section-' + id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};
</script>

<template>
    <AppLayout title="Settings">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-xl text-gray-900">Settings & Integrations</h2>
                    <p class="text-sm text-gray-500">Phone, payments, credit, email, storage and more.</p>
                </div>
                <button @click="save" :disabled="saving"
                    class="bg-indigo-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-indigo-700 disabled:opacity-50">
                    {{ saving ? 'Saving…' : 'Save all changes' }}
                </button>
            </div>
        </template>

        <div class="p-6">
            <div v-if="flash" class="mb-4 px-4 py-2 rounded-lg text-sm"
                :class="flash.ok ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200'">
                {{ flash.msg }}
            </div>

            <div class="grid grid-cols-12 gap-6">
                <!-- Sidebar nav -->
                <aside class="col-span-12 md:col-span-3 lg:col-span-2">
                    <nav class="bg-white border rounded-xl p-2 sticky top-4">
                        <button v-for="s in sections" :key="s.id" @click="scrollTo(s.id)"
                            class="w-full flex items-center gap-2 px-3 py-2 text-left text-sm rounded-lg transition"
                            :class="activeSection === s.id ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-700 hover:bg-gray-50'">
                            <span>{{ s.icon }}</span>
                            <span class="truncate">{{ s.title }}</span>
                            <span v-if="env[s.envFlag]" class="ml-auto text-xs text-emerald-600">●</span>
                        </button>
                    </nav>
                </aside>

                <!-- Sections -->
                <main class="col-span-12 md:col-span-9 lg:col-span-10 space-y-5">
                    <section v-for="s in sections" :key="s.id" :id="'section-' + s.id"
                        class="bg-white border rounded-xl p-5">
                        <header class="flex items-start justify-between mb-4 pb-3 border-b">
                            <div>
                                <h3 class="font-semibold text-lg flex items-center gap-2">
                                    <span>{{ s.icon }}</span>{{ s.title }}
                                    <span v-if="env[s.envFlag]" class="text-xs px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded-full">configured in .env</span>
                                </h3>
                                <p class="text-sm text-gray-500 mt-1">{{ s.desc }}</p>
                            </div>
                            <button v-if="s.testKey" @click="runTest(s)" :disabled="testing[s.id]"
                                class="text-sm px-3 py-1.5 border border-indigo-300 text-indigo-700 rounded-lg hover:bg-indigo-50 disabled:opacity-50">
                                {{ testing[s.id] ? 'Testing…' : 'Test connection' }}
                            </button>
                        </header>

                        <div v-if="testResults[s.id]" class="mb-4 px-3 py-2 rounded-lg text-sm"
                            :class="testResults[s.id].ok ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800'">
                            <strong>{{ testResults[s.id].ok ? '✓' : '✗' }}</strong>
                            {{ testResults[s.id].message }}
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div v-for="f in s.fields" :key="f.key">
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-xs font-medium text-gray-700">{{ f.label }}</label>
                                    <span v-if="secrets[f.key]?.has"
                                        class="text-[10px] font-mono text-emerald-700 bg-emerald-50 border border-emerald-200 rounded px-1.5 py-0.5">
                                        ✓ saved · {{ secrets[f.key].masked }}
                                    </span>
                                </div>
                                <select v-if="f.type === 'select'" v-model="model[f.key]"
                                    class="w-full border-gray-300 rounded-lg text-sm">
                                    <option v-for="o in f.options" :key="o" :value="o">{{ o }}</option>
                                </select>
                                <input v-else v-model="model[f.key]" :type="f.type"
                                    :placeholder="secrets[f.key]?.has ? 'leave blank to keep saved value' : (f.placeholder || '')"
                                    class="w-full border-gray-300 rounded-lg text-sm"
                                    autocomplete="off" />
                            </div>
                        </div>

                        <!-- Per-account sub-tests (e.g. Sola has AutoGo + High Rental) -->
                        <div v-if="s.subTests?.length" class="mt-5 space-y-3">
                            <div v-for="sub in s.subTests" :key="sub.id" class="border-2 border-gray-200 rounded-lg p-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between mb-1">
                                            <label class="block text-xs font-semibold text-gray-700">{{ sub.labelText }}</label>
                                            <span v-if="secrets[sub.keyField]?.has"
                                                class="text-[10px] font-mono text-emerald-700 bg-emerald-50 border border-emerald-200 rounded px-1.5 py-0.5">
                                                ✓ saved · {{ secrets[sub.keyField].masked }}
                                            </span>
                                        </div>
                                        <input v-model="model[sub.keyField]" type="password"
                                            :placeholder="secrets[sub.keyField]?.has ? 'leave blank to use saved key' : 'API key'"
                                            class="w-full border-gray-300 rounded-lg text-sm font-mono" autocomplete="off" />
                                    </div>
                                    <button @click="runSubTest(sub.id, sub.keyField)" :disabled="testing[sub.id]"
                                            class="self-end px-3 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 whitespace-nowrap">
                                        {{ testing[sub.id] ? 'Testing…' : sub.label }}
                                    </button>
                                </div>
                                <div v-if="testResults[sub.id]" class="mt-2 px-3 py-2 rounded-lg text-xs"
                                     :class="testResults[sub.id].ok ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800'">
                                    <strong>{{ testResults[sub.id].ok ? '✓' : '✗' }}</strong>
                                    {{ testResults[sub.id].message }}
                                </div>
                            </div>
                        </div>
                    </section>

                    <div class="flex justify-end pt-2">
                        <button @click="save" :disabled="saving"
                            class="bg-indigo-600 text-white px-6 py-2.5 rounded-lg text-sm hover:bg-indigo-700 disabled:opacity-50">
                            {{ saving ? 'Saving…' : 'Save all changes' }}
                        </button>
                    </div>
                </main>
            </div>
        </div>
    </AppLayout>
</template>
