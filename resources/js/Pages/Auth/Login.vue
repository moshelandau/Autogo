<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Log in" />

    <div class="min-h-screen flex bg-slate-900">
        <!-- Left Panel - Branding -->
        <div class="hidden lg:flex lg:w-1/2 xl:w-2/5 bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-900 items-center justify-center relative overflow-hidden">
            <div class="absolute inset-0 opacity-10">
                <div class="absolute top-20 left-20 w-72 h-72 bg-blue-500 rounded-full blur-3xl"></div>
                <div class="absolute bottom-20 right-20 w-96 h-96 bg-indigo-500 rounded-full blur-3xl"></div>
            </div>
            <div class="relative z-10 text-center px-12">
                <div class="w-24 h-24 rounded-3xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-4xl font-bold mx-auto mb-8 shadow-2xl shadow-blue-500/30">
                    AG
                </div>
                <h1 class="text-4xl font-bold text-white mb-3">AutoGo</h1>
                <p class="text-lg text-slate-400 mb-8">Management System</p>
                <div class="space-y-3 text-sm text-slate-500">
                    <div class="flex items-center justify-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-blue-400"></div>
                        <span>Car Rental & Fleet Management</span>
                    </div>
                    <div class="flex items-center justify-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-emerald-400"></div>
                        <span>Leasing & Financing CRM</span>
                    </div>
                    <div class="flex items-center justify-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-orange-400"></div>
                        <span>Bodyshop & Insurance Claims</span>
                    </div>
                    <div class="flex items-center justify-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-purple-400"></div>
                        <span>Accounting & Reports</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Panel - Login Form -->
        <div class="flex-1 flex items-center justify-center p-8">
            <div class="w-full max-w-md">
                <!-- Mobile Logo -->
                <div class="lg:hidden text-center mb-8">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-2xl font-bold mx-auto mb-4 shadow-lg shadow-blue-500/30">
                        AG
                    </div>
                    <h1 class="text-2xl font-bold text-white">AutoGo</h1>
                    <p class="text-sm text-slate-500">Management System</p>
                </div>

                <div class="bg-white rounded-2xl shadow-2xl p-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-1">Welcome back</h2>
                    <p class="text-sm text-gray-500 mb-6">Sign in to your account</p>

                    <div v-if="status" class="mb-4 p-3 bg-green-50 border border-green-200 rounded-xl text-sm text-green-700">
                        {{ status }}
                    </div>

                    <form @submit.prevent="submit" class="space-y-5">
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input id="email" v-model="form.email" type="email" required autofocus autocomplete="username"
                                   class="block w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-gray-50 focus:bg-white transition-colors" />
                            <InputError class="mt-1" :message="form.errors.email" />
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <input id="password" v-model="form.password" type="password" required autocomplete="current-password"
                                   class="block w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-gray-50 focus:bg-white transition-colors" />
                            <InputError class="mt-1" :message="form.errors.password" />
                        </div>

                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <Checkbox v-model:checked="form.remember" name="remember" />
                                <span class="text-sm text-gray-600">Remember me</span>
                            </label>
                            <Link v-if="canResetPassword" :href="route('password.request')" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                                Forgot password?
                            </Link>
                        </div>

                        <button type="submit" :disabled="form.processing"
                                class="w-full py-3 px-4 bg-gradient-to-r from-indigo-600 to-blue-600 text-white font-semibold rounded-xl hover:from-indigo-700 hover:to-blue-700 focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 transition-all shadow-lg shadow-indigo-500/25">
                            {{ form.processing ? 'Signing in...' : 'Sign In' }}
                        </button>
                    </form>
                </div>

                <p class="text-center text-xs text-slate-600 mt-6">AutoGo Management System v1.0</p>
            </div>
        </div>
    </div>
</template>
