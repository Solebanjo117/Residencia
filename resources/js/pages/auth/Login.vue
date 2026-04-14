<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
    socialAuthError?: string;
    socialProviders?: Array<{
        name: string;
        label: string;
        login_url: string;
    }>;
}>();
</script>

<template>
    <AuthBase
        title="Log in to your account"
        description="Enter your email and password below to log in"
    >
        <Head title="Log in" />

        <div
            v-if="status"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <div
            v-if="socialAuthError"
            class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
        >
            {{ socialAuthError }}
        </div>

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div v-if="socialProviders?.length" class="grid gap-3">
                <Button
                    v-for="provider in socialProviders"
                    :key="provider.name"
                    as="a"
                    :href="provider.login_url"
                    variant="outline"
                    class="w-full justify-center"
                >
                    <svg
                        v-if="provider.name === 'google'"
                        class="h-4 w-4"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            fill="#EA4335"
                            d="M12.24 10.285v3.964h5.518c-.24 1.285-.96 2.374-2.044 3.102l3.305 2.565c1.924-1.773 3.037-4.382 3.037-7.49 0-.728-.066-1.429-.188-2.141z"
                        />
                        <path
                            fill="#34A853"
                            d="M12 24c2.76 0 5.078-.916 6.771-2.484l-3.305-2.565c-.916.614-2.088.977-3.466.977-2.663 0-4.92-1.8-5.728-4.22H2.86v2.65A11.997 11.997 0 0012 24z"
                        />
                        <path
                            fill="#4A90E2"
                            d="M6.272 15.708A7.214 7.214 0 015.96 12c0-1.289.223-2.54.612-3.708V5.642H2.86A11.998 11.998 0 000 12c0 1.936.463 3.769 1.286 5.358z"
                        />
                        <path
                            fill="#FBBC05"
                            d="M12 4.77c1.5 0 2.847.516 3.908 1.53l2.928-2.928C17.073 1.748 14.757.77 12 .77A11.997 11.997 0 002.86 5.642l3.712 2.65C7.08 6.57 9.337 4.77 12 4.77z"
                        />
                    </svg>
                    Continuar con {{ provider.label }}
                </Button>

                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <span class="w-full border-t" />
                    </div>
                    <div class="relative flex justify-center text-xs uppercase">
                        <span class="bg-background px-2 text-muted-foreground">O usa tu correo</span>
                    </div>
                </div>
            </div>

            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">Password</Label>
                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            class="text-sm"
                            :tabindex="5"
                        >
                            Forgot password?
                        </TextLink>
                    </div>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Password"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" name="remember" :tabindex="3" />
                        <span>Remember me</span>
                    </Label>
                </div>

                <Button
                    type="submit"
                    class="mt-4 w-full"
                    :tabindex="4"
                    :disabled="processing"
                    data-test="login-button"
                >
                    <Spinner v-if="processing" />
                    Log in
                </Button>
            </div>

            <div
                class="text-center text-sm text-muted-foreground"
                v-if="canRegister"
            >
                Don't have an account?
                <TextLink :href="register()" :tabindex="5">Sign up</TextLink>
            </div>
        </Form>
    </AuthBase>
</template>
