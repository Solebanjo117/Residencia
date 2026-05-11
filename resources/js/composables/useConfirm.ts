import { ref } from 'vue';

interface ConfirmOptions {
    title: string;
    description?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: 'destructive' | 'warning' | 'default';
    onConfirm: () => void | Promise<void>;
}

const isOpen = ref(false);
const currentOptions = ref<ConfirmOptions>({
    title: '',
    onConfirm: () => {},
});

export function useConfirm() {
    const confirm = (options: ConfirmOptions) => {
        currentOptions.value = {
            confirmLabel: 'Confirmar',
            cancelLabel: 'Cancelar',
            variant: 'destructive',
            description: '',
            ...options,
        };
        isOpen.value = true;
    };

    const handleConfirm = () => {
        currentOptions.value.onConfirm();
        isOpen.value = false;
    };

    const handleCancel = () => {
        isOpen.value = false;
    };

    return {
        isOpen,
        options: currentOptions,
        confirm,
        handleConfirm,
        handleCancel,
    };
}
