<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { Bell, CreditCard, Box } from 'lucide-vue-next';
import axios from 'axios';

// Interfaces
interface ExternalNotification {
    id: number;
    type: string;
    title: string;
    message: string;
    is_read: boolean;
    created_at: string;
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
    } catch (e) {
        console.error('Error fetching notifications API');
    }
}

async function markAsRead(id: number | null = null) {
    try {
        const url = id ? `/api/notifications/read/${id}` : '/api/notifications/read';
        await axios.post(url);
        await fetchNotifications();
    } catch (e) {
        console.error('Error marking read');
    }
}

function handleClickOutside(event: MouseEvent) {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target as Node)) {
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
    return d.toLocaleDateString('es-MX', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <div class="relative" ref="dropdownRef">
        <button type="button" @click.stop="isOpen = !isOpen"
            class="relative p-2 text-gray-500 rounded-full hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
        >
            <Bell class="w-5 h-5" />
            <span 
                v-if="unreadCount > 0"
                class="absolute top-0 right-0 inline-flex items-center justify-center p-1 text-[10px] font-bold leading-none text-white transform translate-x-1/4 -translate-y-1/4 bg-red-600 rounded-full"
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
                class="absolute right-0 z-50 mt-2 w-80 lg:w-96 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
            >
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-900">Notificaciones</h3>
                    <button type="button" v-if="unreadCount > 0"
                        @click="markAsRead()"
                        class="text-xs text-indigo-600 hover:text-indigo-800"
                    >
                        Marcar todo
                    </button>
                </div>

                <div class="max-h-96 overflow-y-auto">
                    <div v-if="notifications.length === 0" class="p-6 text-center text-sm text-gray-500">
                        No tienes notificaciones pendientes.
                    </div>
                    <ul v-else class="divide-y divide-gray-100">
                        <li v-for="notif in notifications" :key="notif.id" class="p-4 hover:bg-gray-50 cursor-default group">
                            <div class="flex gap-x-3">
                                <div class="flex-1 mt-1">
                                    <p class="text-sm font-medium text-gray-900">{{ notif.title }}</p>
                                    <p class="text-xs text-gray-600 mt-0.5 line-clamp-2" :title="notif.message">{{ notif.message }}</p>
                                    <p class="text-[10px] text-gray-400 mt-1">{{ formatDate(notif.created_at) }}</p>
                                </div>
                                <button type="button" @click="markAsRead(notif.id)"
                                    class="text-indigo-600 opacity-0 group-hover:opacity-100 transition-opacity p-1"
                                    title="Marcar leído"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
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
