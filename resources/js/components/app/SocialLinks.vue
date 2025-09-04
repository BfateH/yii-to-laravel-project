<template>
    <div class="social-auth-wrapper">
        <div class="social-auth-title">Регистрация с помощью социальных сервисов:</div>
        <div class="social-auth-container">
            <a
                v-for="provider in providers"
                :key="provider.name"
                :href="provider.route"
                :class="['social-btn', provider.class]"
            >
                <span class="social-icon">
                    <component :is="provider.icon" />
                </span>
            </a>
        </div>
    </div>
</template>

<script lang="ts">
import { route } from 'ziggy-js';
import { markRaw } from 'vue';
import GoogleIcon from '@/components/app/icons/GoogleIcon.vue';
import YandexIcon from '@/components/app/icons/YandexIcon.vue';
import VkIcon from '@/components/app/icons/VkIcon.vue';
import MailRuIcon from '@/components/app/icons/MailRuIcon.vue';

export default {
    name: 'SocialLinks',
    data() {
        return {
            providers: [
                {
                    name: 'google',
                    class: 'google-btn',
                    route: route("oauth.redirect", {"provider": "google"}),
                    icon: markRaw(GoogleIcon)
                },
                {
                    name: 'yandex',
                    class: 'yandex-btn',
                    route: route("oauth.redirect", {"provider": "yandex"}),
                    icon: markRaw(YandexIcon)
                },
                {
                    name: 'vkontakte',
                    class: 'vk-btn',
                    route: route("oauth.redirect", {"provider": "vkontakte"}),
                    icon: markRaw(VkIcon)
                },
                {
                    name: 'mailru',
                    class: 'mailru-btn',
                    route: route("oauth.redirect", {"provider": "mailru"}),
                    icon: markRaw(MailRuIcon)
                }
            ]
        }
    }
}
</script>

<style scoped>
.social-auth-wrapper {
    display: flex;
    flex-direction: column;
    gap: 16px;
    max-width: 100%;
    margin: 0 auto;
}

.social-auth-title {
    text-align: left;
    font-size: 16px;
    color: white;
    font-weight: 500;
    margin-bottom: 8px;
}

.social-auth-container {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}

.social-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    border-radius: 12px;
    text-decoration: none;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid transparent;
    min-height: 56px;
}

.social-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
}

.social-icon {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 24px;
    height: 24px;
}

/* Google */
.google-btn {
    background-color: #ffffff;
    border-color: #dadce0;
}

.google-btn:hover {
    background-color: #f8f9fa;
}

/* Яндекс */
.yandex-btn {
    background-color: #ffcc00;
}

.yandex-btn:hover {
    background-color: #f2c200;
}

/* VK */
.vk-btn {
    background-color: #0077ff;
}

.vk-btn:hover {
    background-color: #0066cc;
}

/* Mail.ru */
.mailru-btn {
    background-color: #005ff9;
}

.mailru-btn:hover {
    background-color: #004ec7;
}

@media (max-width: 768px) {
    .social-auth-container {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }

    .social-btn {
        padding: 14px;
        min-height: 52px;
    }

    .social-icon {
        width: 20px;
        height: 20px;
    }
}

@media (max-width: 480px) {
    .social-auth-container {
        grid-template-columns: repeat(2, 1fr);
    }

    .social-btn {
        padding: 12px;
        min-height: 48px;
    }
}
</style>
