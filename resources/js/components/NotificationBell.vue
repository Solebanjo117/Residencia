<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { Bell } from 'lucide-vue-next';
import { ref, onMounted, onUnmounted } from 'vue';

// Interfaces
interface ExternalNotification {
    id: number;
    type: string;
    title: string;
    message: string;
    is_read: boolean;
    created_at: string;
    action_url: string | null;
    action_label: string | null;
}

const notifications = ref<ExternalNotification[]>([]);
const unreadCount = ref(0);
const isOpen = ref(false);
const dropdownRef = ref<HTMLElement | null>(null);

let pollInterval: ReturnType<typeof setInterval>;

async function fetchNotifications() {
    try {
        const response = await axios.get('/api/notifications');
        unreadCount.value = response.data.count;
        notifications.value = response.data.notifications;
    } catch {
        console.error('Error fetching notifications API');
    }
}

async function markAsRead(id: number | null = null) {
    try {
        const url = id
            ? `/api/notifications/read/${id}`
            : '/api/notifications/read';
        await axios.post(url);
        await fetchNotifications();
    } catch {
        console.error('Error marking read');
    }
}

async function openNotification(notification: ExternalNotification) {
    await markAsRead(notification.id);
    isOpen.value = false;

    if (notification.action_url) {
        router.visit(notification.action_url);
    }
}

function handleClickOutside(event: MouseEvent) {
    if (
        dropdownRef.value &&
        !dropdownRef.value.contains(event.target as Node)
    ) {
        isOpen.value = false;
    }
}

onMounted(() => {
    fetchNotifications();
    pollInterval = setInterval(fetchNotifications, 60000); // Poll every minute
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    clearInterval(pollInterval);
    document.removeEventListener('click', handleClickOutside);
});

function formatDate(dateStr: string) {
    // Basic relative time
    const d = new Date(dateStr);
    return d.toLocaleDateString('es-MX', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <div class="relative" ref="dropdownRef">
        <button
            type="button"
            @click.stop="isOpen = !isOpen"
            class="relative rounded-full p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none"
            aria-label="Notificaciones"
        >
            <Bell class="h-5 w-5" />
            <span
                v-if="unreadCount > 0"
                class="absolute top-0 right-0 inline-flex translate-x-1/4 -translate-y-1/4 transform items-center justify-center rounded-full bg-red-600 p-1 text-[10px] leading-none font-bold text-white"
            >
                {{ unreadCount > 9 ? '9+' : unreadCount }}
            </span>
        </button>

        <transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div
                v-if="isOpen"
                class="ring-opacity-5 absolute right-0 z-50 mt-2 w-80 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black focus:outline-none lg:w-96"
            >
                <div
                    class="flex items-center justify-between border-b border-gray-100 px-4 py-3"
                >
                    <h3 class="text-sm font-semibold text-gray-900">
                        Notificaciones
                    </h3>
                    <button
                        type="button"
                        v-if="unreadCount > 0"
                        @click="markAsRead()"
                        class="text-xs text-indigo-600 hover:text-indigo-800"
                    >
                        Marcar todo
                    </button>
                </div>

                <div class="max-h-96 overflow-y-auto">
                    <div
                        v-if="notifications.length === 0"
                        class="p-6 text-center text-sm text-gray-500"
                    >
                        No tienes notificaciones pendientes.
                    </div>
                    <ul v-else class="divide-y divide-gray-100">
                        <li
                            v-for="notif in notifications"
                            :key="notif.id"
                            class="group p-4 hover:bg-gray-50"
                            :class="
                                notif.action_url
                                    ? 'cursor-pointer'
                                    : 'cursor-default'
                            "
                            @click="openNotification(notif)"
                        >
                            <div class="flex gap-x-3">
                                <div class="mt-1 flex-1">
                                    <p
                                        class="text-sm font-medium text-gray-900"
                                    >
                                        {{ notif.title }}
                                    </p>
                                    <p
                                        class="mt-0.5 line-clamp-2 text-xs text-gray-600"
                                        :title="notif.message"
                                    >
                                        {{ notif.message }}
                                    </p>
                                    <p class="mt-1 text-[10px] text-gray-400">
                                        {{ formatDate(notif.created_at) }}
                                    </p>
                                    <p
                                        v-if="notif.action_label"
                                        class="mt-2 text-xs font-semibold text-indigo-600"
                                    >
                                        {{ notif.action_label }}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    @click.stop="markAsRead(notif.id)"
                                    class="p-1 text-indigo-600 opacity-0 transition-opacity group-hover:opacity-100"
                                    title="Marcar leído"
                                    aria-label="Marcar leído"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M5 13l4 4L19 7"
                                        />
                                    </svg>
                                </button>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </transition>
    </div>
</template>
