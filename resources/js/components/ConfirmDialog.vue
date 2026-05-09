<script setup lang="ts">
import { AlertTriangle, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type ConfirmVariant = 'destructive' | 'warning' | 'default';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        description?: string;
        confirmLabel?: string;
        cancelLabel?: string;
        variant?: ConfirmVariant;
    }>(),
    {
        confirmLabel: 'Confirmar',
        cancelLabel: 'Cancelar',
        variant: 'destructive',
        description: '',
    },
);

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'confirm'): void;
    (e: 'cancel'): void;
}>();

const isLoading = ref(false);

const handleConfirm = () => {
    emit('confirm');
};

const handleCancel = () => {
    emit('update:open', false);
    emit('cancel');
};

const variantConfig: Record<ConfirmVariant, { icon: typeof AlertTriangle; buttonVariant: 'destructive' | 'default' | 'secondary' }> = {
    destructive: { icon: Trash2, buttonVariant: 'destructive' },
    warning: { icon: AlertTriangle, buttonVariant: 'secondary' },
    default: { icon: AlertTriangle, buttonVariant: 'default' },
};

const config = variantConfig[props.variant];
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <component :is="config.icon" class="h-5 w-5" :class="variant === 'destructive' ? 'text-destructive' : 'text-warning'" />
                    {{ title }}
                </DialogTitle>
                <DialogDescription v-if="description">
                    {{ description }}
                </DialogDescription>
            </DialogHeader>

            <slot />

            <DialogFooter>
                <Button variant="outline" @click="handleCancel">
                    {{ cancelLabel }}
                </Button>
                <Button
                    :variant="config.buttonVariant"
                    :disabled="isLoading"
                    @click="handleConfirm"
                >
                    {{ confirmLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>