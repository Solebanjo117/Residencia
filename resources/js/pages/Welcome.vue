<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { dashboard, login, register } from '@/routes';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);
</script>

<template>
    <Head title="Seguimiento Docente" />

    <main class="min-h-screen bg-slate-50 text-slate-950">
        <section class="mx-auto flex min-h-screen max-w-6xl flex-col px-6 py-8 lg:px-8">
            <header class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-full bg-white shadow-sm ring-1 ring-slate-200">
                        <AppLogoIcon class="h-14 w-14 object-contain" />
                    </div>
                    <div>
                        <p class="text-base font-bold tracking-wide">Seguimiento Docente</p>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                            Tecnológico de Piedras Negras
                        </p>
                    </div>
                </div>

                <nav class="flex items-center gap-3 text-sm">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white transition hover:bg-slate-700"
                    >
                        Ir al panel
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-lg border border-slate-300 px-4 py-2 font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-white"
                        >
                            Iniciar sesión
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="hidden rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white transition hover:bg-slate-700 sm:inline-block"
                        >
                            Crear cuenta
                        </Link>
                    </template>
                </nav>
            </header>

            <div class="flex flex-1 items-center justify-center py-16">
                <div class="w-full max-w-3xl text-center">
                    <div class="mx-auto mb-8 flex h-32 w-32 items-center justify-center overflow-hidden rounded-full bg-white shadow-sm ring-1 ring-slate-200">
                        <AppLogoIcon class="h-32 w-32 object-contain" />
                    </div>

                    <p class="mb-4 text-sm font-bold uppercase tracking-[0.24em] text-blue-700">
                        Instituto Tecnológico de Piedras Negras
                    </p>

                    <h1 class="text-4xl font-black tracking-tight text-slate-950 sm:text-6xl">
                        Sistema de seguimiento docente
                    </h1>

                    <p class="mx-auto mt-6 max-w-2xl text-lg leading-8 text-slate-600">
                        Plataforma institucional para gestionar evidencias, asesorías,
                        archivos, ventanas de entrega y revisión académica por roles.
                    </p>

                    <div class="mt-10 flex justify-center">
                        <Link
                            :href="$page.props.auth.user ? dashboard() : login()"
                            class="rounded-xl bg-blue-700 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-blue-800"
                        >
                            Entrar al sistema
                        </Link>
                    </div>
                </div>
            </div>

            <footer class="pb-4 text-center text-xs font-medium text-slate-500">
                Seguimiento Docente · Tecnológico de Piedras Negras
            </footer>
        </section>
    </main>
</template>
