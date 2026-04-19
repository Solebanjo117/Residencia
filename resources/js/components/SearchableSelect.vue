<script setup lang="ts">
import { useVModel } from '@vueuse/core';
import { Check, ChevronsUpDown, Search } from 'lucide-vue-next';
import type { HTMLAttributes } from 'vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { cn } from '@/lib/utils';

type SearchableSelectValue = string | number | null;

type SearchableSelectOption = {
    value: string | number;
    label: string;
    disabled?: boolean;
    keywords?: string | string[];
};

const props = withDefaults(
    defineProps<{
        options: SearchableSelectOption[];
        defaultValue?: SearchableSelectValue;
        modelValue?: SearchableSelectValue;
        placeholder?: string;
        searchPlaceholder?: string;
        emptyText?: string;
        disabled?: boolean;
        closeOnSelect?: boolean;
        class?: HTMLAttributes['class'];
        triggerClass?: HTMLAttributes['class'];
        dropdownClass?: HTMLAttributes['class'];
    }>(),
    {
        defaultValue: null,
        modelValue: null,
        placeholder: 'Selecciona una opcion',
        searchPlaceholder: 'Buscar...',
        emptyText: 'Sin resultados',
        disabled: false,
        closeOnSelect: true,
    },
);

const emits = defineEmits<{
    (e: 'update:modelValue', payload: SearchableSelectValue): void;
    (e: 'change', payload: { value: SearchableSelectValue; option: SearchableSelectOption | null }): void;
}>();

const modelValue = useVModel(props, 'modelValue', emits, {
    passive: true,
    defaultValue: props.defaultValue,
});

const rootRef = ref<HTMLElement | null>(null);
const searchInputRef = ref<HTMLInputElement | null>(null);
const isOpen = ref(false);
const searchTerm = ref('');

const selectedOption = computed(() => {
    if (modelValue.value === null || modelValue.value === undefined) {
        return null;
    }

    const normalizedValue = String(modelValue.value);

    return props.options.find((option) => String(option.value) === normalizedValue) ?? null;
});

const filteredOptions = computed(() => {
    const query = searchTerm.value.trim().toLocaleLowerCase();

    if (!query) {
        return props.options;
    }

    return props.options.filter((option) => {
        const keywords = Array.isArray(option.keywords)
            ? option.keywords
            : option.keywords
              ? [option.keywords]
              : [];

        const searchable = [option.label, ...keywords].join(' ').toLocaleLowerCase();

        return searchable.includes(query);
    });
});

const isSelected = (option: SearchableSelectOption) => {
    if (modelValue.value === null || modelValue.value === undefined) {
        return false;
    }

    return String(option.value) === String(modelValue.value);
};

const closeDropdown = () => {
    isOpen.value = false;
    searchTerm.value = '';
};

const openDropdown = async () => {
    if (props.disabled) {
        return;
    }

    isOpen.value = true;
    await nextTick();
    searchInputRef.value?.focus();
};

const toggleDropdown = () => {
    if (isOpen.value) {
        closeDropdown();
        return;
    }

    void openDropdown();
};

const selectOption = (option: SearchableSelectOption) => {
    if (option.disabled) {
        return;
    }

    modelValue.value = option.value;
    emits('change', { value: option.value, option });

    if (props.closeOnSelect) {
        closeDropdown();
    }
};

const handleDocumentPointerDown = (event: PointerEvent) => {
    if (!isOpen.value || !rootRef.value) {
        return;
    }

    const target = event.target as Node | null;

    if (target && !rootRef.value.contains(target)) {
        closeDropdown();
    }
};

const onTriggerKeydown = (event: KeyboardEvent) => {
    if (props.disabled) {
        return;
    }

    if (event.key === 'Enter' || event.key === ' ' || event.key === 'ArrowDown') {
        event.preventDefault();
        void openDropdown();
    }
};

watch(
    () => props.disabled,
    (disabled) => {
        if (disabled && isOpen.value) {
            closeDropdown();
        }
    },
);

onMounted(() => {
    window.addEventListener('pointerdown', handleDocumentPointerDown);
});

onBeforeUnmount(() => {
    window.removeEventListener('pointerdown', handleDocumentPointerDown);
});
</script>

<template>
    <div ref="rootRef" :class="cn('relative', props.class)">
        <button
            type="button"
            :disabled="disabled"
            :aria-expanded="isOpen"
            :aria-haspopup="'listbox'"
            :class="
                cn(
                    'flex h-10 w-full items-center justify-between gap-2 rounded-md border border-gray-300 bg-white px-3 text-sm text-gray-700 shadow-sm transition-colors',
                    'focus-visible:ring-2 focus-visible:ring-blue-200 focus-visible:outline-none',
                    'disabled:cursor-not-allowed disabled:opacity-60',
                    'hover:border-gray-400',
                    triggerClass,
                )
            "
            @click="toggleDropdown"
            @keydown="onTriggerKeydown"
        >
            <span
                class="truncate"
                :class="selectedOption ? 'text-gray-900' : 'text-gray-400'"
            >
                {{ selectedOption ? selectedOption.label : placeholder }}
            </span>

            <ChevronsUpDown class="h-4 w-4 shrink-0 text-gray-400" />
        </button>

        <div
            v-if="isOpen"
            :class="
                cn(
                    'absolute z-50 mt-2 w-full rounded-md border border-gray-200 bg-white shadow-lg',
                    dropdownClass,
                )
            "
        >
            <div class="border-b border-gray-100 p-2">
                <div class="relative">
                    <Search class="pointer-events-none absolute top-1/2 left-2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input
                        ref="searchInputRef"
                        v-model="searchTerm"
                        type="text"
                        :placeholder="searchPlaceholder"
                        class="h-9 w-full rounded-md border border-gray-200 bg-white pr-3 pl-8 text-sm text-gray-800 outline-none focus:border-blue-300 focus:ring-2 focus:ring-blue-100"
                        @keydown.esc.prevent="closeDropdown"
                    />
                </div>
            </div>

            <ul
                role="listbox"
                class="max-h-64 overflow-y-auto py-1"
            >
                <li
                    v-if="filteredOptions.length === 0"
                    class="px-3 py-2 text-sm text-gray-500"
                >
                    {{ emptyText }}
                </li>

                <li
                    v-for="option in filteredOptions"
                    :key="String(option.value)"
                >
                    <button
                        type="button"
                        role="option"
                        :aria-selected="isSelected(option)"
                        :disabled="option.disabled"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition-colors"
                        :class="[
                            isSelected(option) ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-50',
                            option.disabled ? 'cursor-not-allowed opacity-50' : '',
                        ]"
                        @click="selectOption(option)"
                    >
                        <Check
                            class="h-4 w-4 shrink-0"
                            :class="isSelected(option) ? 'opacity-100' : 'opacity-0'"
                        />
                        <span class="truncate">{{ option.label }}</span>
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
