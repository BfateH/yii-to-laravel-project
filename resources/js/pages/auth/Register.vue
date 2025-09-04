<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { route } from 'ziggy-js';
import SocialLinks from '../../components/app/SocialLinks.vue'
import { Form, Head, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const page = usePage()
</script>

<template>
    <AuthBase title="Создание аккаунта" description="Заполните данные для регистрации">
        <Head title="Регистрация" />

        <Form
            :action="route('register.store')"
            method="post"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <input type="hidden" name="_token" :value="page.props.csrf_token">

            <div class="grid gap-5">
                <!-- Имя -->
                <div class="grid gap-2">
                    <Label for="name" class="text-sm font-medium text-gray-700 mb-1">Имя</Label>
                    <Input
                        id="name"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="name"
                        name="name"
                        placeholder="Ваше полное имя"
                        class="border-gray-200 focus:border-purple-400 focus:ring-2 focus:ring-purple-100 rounded-lg py-3 px-4 transition-all duration-200"
                        :class="{ 'border-red-300 focus:border-red-400 focus:ring-red-100': errors.name }"
                    />
                    <InputError :message="errors.name" class="text-red-600" />
                </div>

                <!-- Email -->
                <div class="grid gap-2">
                    <Label for="email" class="text-sm font-medium text-gray-700 mb-1">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        :tabindex="2"
                        autocomplete="email"
                        name="email"
                        placeholder="email@example.com"
                        class="border-gray-200 focus:border-purple-400 focus:ring-2 focus:ring-purple-100 rounded-lg py-3 px-4 transition-all duration-200"
                        :class="{ 'border-red-300 focus:border-red-400 focus:ring-red-100': errors.email }"
                    />
                    <InputError :message="errors.email" class="text-red-600" />
                </div>

                <!-- Пароль -->
                <div class="grid gap-2">
                    <Label for="password" class="text-sm font-medium text-gray-700 mb-1">Пароль</Label>
                    <Input
                        id="password"
                        type="password"
                        required
                        :tabindex="3"
                        autocomplete="new-password"
                        name="password"
                        placeholder="Придумайте надежный пароль"
                        class="border-gray-200 focus:border-purple-400 focus:ring-2 focus:ring-purple-100 rounded-lg py-3 px-4 transition-all duration-200"
                        :class="{ 'border-red-300 focus:border-red-400 focus:ring-red-100': errors.password }"
                    />
                    <InputError :message="errors.password" class="text-red-600" />
                </div>

                <!-- Подтверждение пароля -->
                <div class="grid gap-2">
                    <Label for="password_confirmation" class="text-sm font-medium text-gray-700 mb-1">Подтвердите пароль</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        name="password_confirmation"
                        placeholder="Повторите ваш пароль"
                        class="border-gray-200 focus:border-purple-400 focus:ring-2 focus:ring-purple-100 rounded-lg py-3 px-4 transition-all duration-200"
                        :class="{ 'border-red-300 focus:border-red-400 focus:ring-red-100': errors.password_confirmation }"
                    />
                    <InputError :message="errors.password_confirmation" class="text-red-600" />
                </div>

                <!-- Кнопка регистрации -->
                <Button
                    type="submit"
                    class="w-full bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white font-medium py-3.5 rounded-lg transition-all duration-300 shadow-lg hover:shadow-xl mt-2 cursor-pointer"
                    tabindex="5"
                    :disabled="processing"
                >
                    <LoaderCircle v-if="processing" class="h-5 w-5 animate-spin mr-2" />
                    {{ processing ? 'Регистрация...' : 'Создать аккаунт' }}
                </Button>
            </div>

            <!-- Социальные сети -->
            <SocialLinks class="mt-4" />

            <!-- Ссылка на вход -->
            <div class="text-center text-sm text-gray-600 pt-4 border-t border-gray-100 mt-4">
                Уже есть аккаунт?
                <a
                    :href="route('moonshine.login')"
                    class="text-purple-600 hover:text-purple-700 font-medium underline underline-offset-4 transition-colors duration-200 ml-1 cursor-pointer"
                    :tabindex="6"
                >
                    Войти в систему
                </a>
            </div>
        </Form>
    </AuthBase>
</template>

<style scoped>
/* Плавные анимации для всех элементов */
:deep(*) {
    transition: all 0.2s ease-in-out;
}

/* Стили для инпутов */
:deep(.border-gray-200) {
    border: 1.5px solid #E5E7EB;
}

:deep(.focus\:border-purple-400:focus) {
    border-color: #A78BFA;
    box-shadow: 0 0 0 3px rgba(167, 139, 250, 0.1);
}

:deep(.focus\:ring-purple-100:focus) {
    --tw-ring-color: rgba(245, 243, 255, 0.5);
}

/* Стили для ошибок */
:deep(.border-red-300) {
    border-color: #FCA5A5;
}

:deep(.focus\:border-red-400:focus) {
    border-color: #F87171;
    box-shadow: 0 0 0 3px rgba(248, 113, 113, 0.1);
}

:deep(.focus\:ring-red-100:focus) {
    --tw-ring-color: rgba(254, 226, 226, 0.5);
}

/* Градиент для кнопки */
:deep(.bg-gradient-to-r) {
    background-size: 200% 100%;
    background-position: 100% 0;
}

:deep(.bg-gradient-to-r:hover) {
    background-position: 0 0;
    transform: translateY(-2px);
}

:deep(.bg-gradient-to-r:disabled) {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
    background-position: 100% 0;
}

/* Тень для кнопки */
:deep(.shadow-lg) {
    box-shadow: 0 10px 25px -5px rgba(139, 92, 246, 0.15);
}

:deep(.shadow-xl:hover) {
    box-shadow: 0 20px 25px -5px rgba(139, 92, 246, 0.2);
}

/* Курсоры */
:deep(.cursor-pointer) {
    cursor: pointer;
}
</style>
