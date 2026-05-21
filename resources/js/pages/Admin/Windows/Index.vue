<script setup lang="ts">
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import {
    CalendarClock,
    Edit2,
    Trash2,
    Filter,
    AlertCircle,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import AdminTable from '@/components/AdminTable.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    windows: {
        data: any[];
        links: any[];
    };
    semesters: any[];
    evidenceItems: any[];
    modalities: Array<{ value: string; label: string }>;
    selectedSemester: string | null;
    selectedStatus?: string | null;
}>();

const page = usePage();
const isModalOpen = ref(false);
const isConfirmOpen = ref(false);
const windowToDelete = ref<number | null>(null);
const editingWindow = ref<any | null>(null);
const filterSemester = ref(
    props.selectedSemester ||
        (props.semesters.length > 0 ? props.semesters[0].id : ''),
);
const filterStatus = ref(props.selectedStatus || '');

watch([filterSemester, filterStatus], ([semesterId, status]) => {
    router.get('/admin/windows', {
        semester_id: semesterId,
        status: status || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
});

watch(
    () => page.props.flash?.success,
    (val) => {
        if (val) toast.success(val as string);
    },
);

const form = useForm({
    semester_id: filterSemester.value,
    evidence_item_id: '',
    evidence_item_ids: [] as number[],
    modality: '',
    opens_at: '',
    closes_at: '',
    status: 'ACTIVE',
});

const groupedEvidenceItems = computed(() => {
    const groups = new Map<string, any[]>();

    props.evidenceItems.forEach((item) => {
        const label = item.stage_label || 'Sin etapa';
        groups.set(label, [...(groups.get(label) || []), item]);
    });

    return Array.from(groups.entries()).map(([label, items]) => ({
        label,
        items,
    }));
});

const selectedEvidenceCount = computed(() => form.evidence_item_ids.length);

const formatForInput = (dateString: string) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return new Date(date.getTime() - date.getTimezoneOffset() * 60000)
        .toISOString()
        .slice(0, 10);
};

const openCreateModal = () => {
    editingWindow.value = null;
    form.reset();
    form.semester_id = filterSemester.value;
    form.evidence_item_id = '';
    form.evidence_item_ids = [];
    form.status = 'ACTIVE';
    isModalOpen.value = true;
};

const openEditModal = (win: any) => {
    editingWindow.value = win;
    form.clearErrors();
    form.semester_id = win.semester_id;
    form.evidence_item_id = win.evidence_item_id;
    form.evidence_item_ids = [win.evidence_item_id];
    form.modality = win.modality || '';
    form.opens_at = formatForInput(win.opens_at);
    form.closes_at = formatForInput(win.closes_at);
    form.status = win.status;
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
    form.clearErrors();
};

const handleStatusChange = (checked: boolean) => {
    form.status = checked ? 'ACTIVE' : 'INACTIVE';
};

const isEvidenceSelected = (id: number) => {
    return form.evidence_item_ids.includes(Number(id));
};

const toggleEvidenceSelection = (id: number, checked: boolean) => {
    const itemId = Number(id);

    if (checked) {
        form.evidence_item_ids = [
            ...new Set([...form.evidence_item_ids, itemId]),
        ];
        return;
    }

    form.evidence_item_ids = form.evidence_item_ids.filter(
        (selectedId) => selectedId !== itemId,
    );
};

const submitForm = () => {
    if (editingWindow.value) {
        form.put(`/admin/windows/${editingWindow.value.id}`, {
            onSuccess: () => {
                closeModal();
                toast.success('Ventana actualizada correctamente.');
            },
        });
    } else {
        form.post('/admin/windows', {
            onSuccess: () => {
                closeModal();
                toast.success(
                    selectedEvidenceCount.value > 1
                        ? 'Ventanas creadas correctamente.'
                        : 'Ventana creada correctamente.',
                );
            },
        });
    }
};

const requestDelete = (id: number) => {
    windowToDelete.value = id;
    isConfirmOpen.value = true;
};

const confirmDelete = () => {
    if (windowToDelete.value === null) return;
    router.delete(`/admin/windows/${windowToDelete.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Ventana eliminada correctamente.');
        },
    });
    windowToDelete.value = null;
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('es-ES', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getStatusVariant = (status: string, closesAt: string) => {
    if (status !== 'ACTIVE') return 'outline';
    if (new Date(closesAt) < new Date()) return 'destructive';
    return 'success';
};

const getStatusText = (status: string, opensAt: string, closesAt: string) => {
    if (status !== 'ACTIVE') return 'INACTIVO';
    const now = new Date();
    if (now < new Date(opensAt)) return 'PROGRAMADA';
    if (now > new Date(closesAt)) return 'CERRADA (Vencida)';
    return 'ABIERTA';
};
</script>

<template>
    <Head title="Ventanas de Entrega" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Admin', href: '#' },
            { title: 'Ventanas de Entrega', href: '/admin/windows' },
        ]"
    >
        <div class="mx-auto max-w-7xl px-6 py-8">
            <div
                class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-bold text-foreground">
                        Ventanas de Entrega
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Configura las fechas limite para que los docentes suban
                        sus archivos por evidencia.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <select
                            v-model="filterSemester"
                            aria-label="Seleccionar semestre"
                            class="appearance-none rounded-lg border border-input bg-background py-2 pr-10 pl-4 text-sm shadow-sm focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            <option value="">Selecciona semestre...</option>
                            <option
                                v-for="sem in semesters"
                                :key="sem.id"
                                :value="sem.id"
                            >
                                {{ sem.name }}
                            </option>
                        </select>
                        <div
                            class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-muted-foreground"
                        >
                            <Filter class="h-4 w-4" />
                        </div>
                    </div>
                    <div class="relative">
                        <select
                            v-model="filterStatus"
                            aria-label="Filtrar estado de ventana"
                            class="appearance-none rounded-lg border border-input bg-background py-2 pr-10 pl-4 text-sm shadow-sm focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            <option value="">Todos los estados</option>
                            <option value="OPEN">Abiertas</option>
                            <option value="UPCOMING">Programadas</option>
                            <option value="EXPIRED">Vencidas</option>
                            <option value="INACTIVE">Inactivas</option>
                        </select>
                        <div
                            class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-muted-foreground"
                        >
                            <Filter class="h-4 w-4" />
                        </div>
                    </div>

                    <Button @click="openCreateModal">
                        <CalendarClock class="mr-2 h-4 w-4" />
                        Crear en lote
                    </Button>
                </div>
            </div>

            <AdminTable>
                <template #default>
                    <table class="min-w-full divide-y divide-border">
                        <thead class="bg-muted/50">
                            <tr>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >
                                    Evidencia
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >
                                    Modalidad
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >
                                    Apertura
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >
                                    Cierre
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >
                                    Estado
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-right text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border bg-background">
                            <tr
                                v-for="win in windows.data"
                                :key="win.id"
                                class="transition-colors hover:bg-muted/50"
                            >
                                <td class="px-6 py-4">
                                    <div
                                        class="text-sm font-semibold text-foreground"
                                    >
                                        {{ win.evidence_item.name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        Semestre: {{ win.semester.name }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <Badge
                                        :variant="
                                            win.modality === 'EN_LINEA'
                                                ? 'info'
                                                : 'secondary'
                                        "
                                    >
                                        {{
                                            win.modality === 'EN_LINEA'
                                                ? 'Materia en línea'
                                                : win.modality === 'PRESENCIAL'
                                                  ? 'Presencial'
                                                  : 'General'
                                        }}
                                    </Badge>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-muted-foreground">
                                        {{ formatDate(win.opens_at) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        {{ formatDate(win.closes_at) }}
                                    </div>
                                    <div
                                        v-if="
                                            new Date(win.closes_at) < new Date()
                                        "
                                        class="mt-0.5 text-[10px] font-bold text-destructive uppercase"
                                    >
                                        Vencido
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <Badge
                                        :variant="
                                            getStatusVariant(
                                                win.status,
                                                win.closes_at,
                                            )
                                        "
                                    >
                                        {{
                                            getStatusText(
                                                win.status,
                                                win.opens_at,
                                                win.closes_at,
                                            )
                                        }}
                                    </Badge>
                                </td>
                                <td
                                    class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                                >
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            aria-label="Editar ventana"
                                            @click="openEditModal(win)"
                                        >
                                            <Edit2 class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            aria-label="Eliminar ventana"
                                            class="text-destructive hover:text-destructive/80"
                                            @click="requestDelete(win.id)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="windows.data.length === 0">
                                <td
                                    colspan="6"
                                    class="bg-muted/30 px-6 py-12 text-center text-muted-foreground"
                                >
                                    No se han configurado ventanas de entrega
                                    para este semestre.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </template>
            </AdminTable>

            <Pagination :links="windows.links" />
        </div>
    </AppLayout>

    <Dialog
        :open="isModalOpen"
        @update:open="
            (val: boolean) => {
                if (!val) closeModal();
            }
        "
    >
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{
                    editingWindow
                        ? 'Editar Ventana de Entrega'
                        : 'Crear Ventanas de Entrega'
                }}</DialogTitle>
                <DialogDescription>
                    {{
                        editingWindow
                            ? 'Modifica las fechas y datos de esta ventana.'
                            : 'Selecciona una o varias evidencias y aplica el mismo plazo.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <div
                class="mb-4 flex items-start gap-3 rounded-lg border border-info/50 bg-info/10 px-3 py-2 text-sm text-primary"
            >
                <AlertCircle class="h-5 w-5 shrink-0 text-info" />
                <span>
                    Las ventanas definen cuándo los docentes pueden subir
                    archivos para una evidencia en especifico. La fecha de
                    inicio abre a las 00:00 y la fecha de cierre termina a las
                    23:59.
                </span>
            </div>

            <form @submit.prevent="submitForm" class="space-y-4">
                <div>
                    <Label for="window-semester">Semestre</Label>
                    <select
                        id="window-semester"
                        v-model="form.semester_id"
                        class="mt-1 block w-full rounded-md border-input bg-background py-2 pr-10 pl-3 text-sm focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        required
                    >
                        <option
                            v-for="sem in semesters"
                            :key="sem.id"
                            :value="sem.id"
                        >
                            {{ sem.name }}
                        </option>
                    </select>
                    <p
                        v-if="form.errors.semester_id"
                        class="mt-1 text-xs text-destructive"
                    >
                        {{ form.errors.semester_id }}
                    </p>
                </div>

                <div v-if="editingWindow">
                    <Label for="window-evidence">Evidencia</Label>
                    <select
                        id="window-evidence"
                        v-model="form.evidence_item_id"
                        class="mt-1 block w-full rounded-md border-input bg-background py-2 pr-10 pl-3 text-sm focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        required
                    >
                        <option value="" disabled>
                            Selecciona una evidencia...
                        </option>
                        <option
                            v-for="item in evidenceItems"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.stage_label }} - {{ item.name }}
                        </option>
                    </select>
                    <p
                        v-if="form.errors.evidence_item_id"
                        class="mt-1 text-xs text-destructive"
                    >
                        {{ form.errors.evidence_item_id }}
                    </p>
                </div>

                <div v-else>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <Label>Evidencias</Label>
                        <span class="text-xs font-semibold text-muted-foreground">
                            {{ selectedEvidenceCount }} seleccionada{{
                                selectedEvidenceCount === 1 ? '' : 's'
                            }}
                        </span>
                    </div>
                    <div
                        class="max-h-72 space-y-3 overflow-y-auto rounded-md border border-input bg-background p-3"
                    >
                        <div
                            v-for="group in groupedEvidenceItems"
                            :key="group.label"
                            class="space-y-2"
                        >
                            <div
                                class="text-xs font-bold tracking-wide text-muted-foreground uppercase"
                            >
                                {{ group.label }}
                            </div>
                            <label
                                v-for="item in group.items"
                                :key="item.id"
                                class="flex cursor-pointer items-start gap-3 rounded-md px-2 py-1.5 text-sm hover:bg-muted/60"
                            >
                                <Checkbox
                                    :checked="isEvidenceSelected(item.id)"
                                    @update:checked="
                                        (checked) =>
                                            toggleEvidenceSelection(
                                                item.id,
                                                Boolean(checked),
                                            )
                                    "
                                />
                                <span class="leading-5">{{ item.name }}</span>
                            </label>
                        </div>
                    </div>
                    <p
                        v-if="form.errors.evidence_item_ids"
                        class="mt-1 text-xs text-destructive"
                    >
                        {{ form.errors.evidence_item_ids }}
                    </p>
                    <p
                        v-if="form.errors['evidence_item_ids.0']"
                        class="mt-1 text-xs text-destructive"
                    >
                        {{ form.errors['evidence_item_ids.0'] }}
                    </p>
                </div>

                <div>
                    <Label for="window-modality">Modalidad</Label>
                    <select
                        id="window-modality"
                        v-model="form.modality"
                        class="mt-1 block w-full rounded-md border-input bg-background py-2 pr-10 pl-3 text-sm focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    >
                        <option
                            v-for="mod in modalities"
                            :key="mod.value"
                            :value="mod.value"
                        >
                            {{ mod.label }}
                        </option>
                    </select>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Usa "Materia en linea" o "Presencial" solo si esa
                        evidencia tendra fechas distintas por modalidad. Si no,
                        se usara la ventana general.
                    </p>
                    <p
                        v-if="form.errors.modality"
                        class="mt-1 text-xs text-destructive"
                    >
                        {{ form.errors.modality }}
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="window-opens">Apertura</Label>
                        <Input
                            id="window-opens"
                            type="date"
                            v-model="form.opens_at"
                            class="mt-1"
                            required
                        />
                        <p
                            v-if="form.errors.opens_at"
                            class="mt-1 text-xs text-destructive"
                        >
                            {{ form.errors.opens_at }}
                        </p>
                    </div>
                    <div>
                        <Label for="window-closes">Cierre Limite</Label>
                        <Input
                            id="window-closes"
                            type="date"
                            v-model="form.closes_at"
                            class="mt-1"
                            required
                        />
                        <p
                            v-if="form.errors.closes_at"
                            class="mt-1 text-xs text-destructive"
                        >
                            {{ form.errors.closes_at }}
                        </p>
                    </div>
                </div>

                <div v-if="editingWindow" class="pt-2">
                    <label
                        class="flex items-center gap-2 text-sm text-foreground"
                    >
                        <Checkbox
                            :checked="form.status === 'ACTIVE'"
                            @update:checked="handleStatusChange"
                        />
                        Ventana Activa (Forzar cierre desmarcando)
                    </label>
                </div>
            </form>

            <DialogFooter>
                <Button variant="outline" @click="closeModal">Cancelar</Button>
                <Button
                    type="submit"
                    :disabled="
                        form.processing ||
                        (!editingWindow && selectedEvidenceCount === 0)
                    "
                    @click="submitForm"
                >
                    {{
                        editingWindow
                            ? 'Guardar Cambios'
                            : selectedEvidenceCount > 1
                              ? 'Crear ventanas'
                              : 'Crear ventana'
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="isConfirmOpen"
        title="Eliminar ventana de entrega"
        description="¿Seguro que deseas eliminar esta ventana de entrega? Si estaba activa, los docentes perderán acceso de inmediato."
        confirm-label="Eliminar"
        cancel-label="Cancelar"
        variant="destructive"
        @update:open="isConfirmOpen = $event"
        @confirm="confirmDelete"
    />
</template>
