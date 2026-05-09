<script setup lang="ts">
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import {
    CalendarPlus,
    Edit2,
    Trash2,
    CalendarHeart,
    CalendarOff,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import AdminTable from '@/components/AdminTable.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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

defineProps<{
    semesters: {
        data: any[];
        links: any[];
    };
    academicPeriods: any[];
}>();

const page = usePage();
const isModalOpen = ref(false);
const isConfirmOpen = ref(false);
const semesterToDelete = ref<number | null>(null);
const editingSemester = ref<any | null>(null);

watch(() => page.props.flash?.success, (val) => {
    if (val) toast.success(val as string);
});

const form = useForm({
    name: '',
    start_date: '',
    end_date: '',
    status: 'OPEN',
    academic_period_id: '' as string | null,
});

const openCreateModal = () => {
    editingSemester.value = null;
    form.reset();
    form.clearErrors();
    isModalOpen.value = true;
};

const openEditModal = (semester: any) => {
    editingSemester.value = semester;
    form.clearErrors();
    form.name = semester.name;
    form.start_date = semester.start_date;
    form.end_date = semester.end_date;
    form.status = semester.status;
    form.academic_period_id = semester.academic_period_id;
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
    form.clearErrors();
};

const submitForm = () => {
    if (editingSemester.value) {
        form.put(`/admin/semesters/${editingSemester.value.id}`, {
            onSuccess: () => {
                closeModal();
                toast.success('Semestre actualizado correctamente.');
            },
        });
    } else {
        form.post('/admin/semesters', {
            onSuccess: () => {
                closeModal();
                toast.success('Semestre creado correctamente.');
            },
        });
    }
};

const requestDelete = (id: number) => {
    semesterToDelete.value = id;
    isConfirmOpen.value = true;
};

const confirmDelete = () => {
    if (semesterToDelete.value === null) return;
    router.delete(`/admin/semesters/${semesterToDelete.value}`, {
        onSuccess: () => {
            toast.success('Semestre eliminado correctamente.');
        },
    });
    semesterToDelete.value = null;
};

const formatDate = (dateString: string) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('es-ES', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <Head title="Administrar Semestres" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Admin', href: '#' },
            { title: 'Semestres', href: '/admin/semesters' },
        ]"
    >
        <div class="mx-auto max-w-7xl px-6 py-8">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-foreground">
                        Semestres Académicos
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Configura los semestres globales y su vigencia institucional.
                    </p>
                </div>
                <Button @click="openCreateModal">
                    <CalendarPlus class="mr-2 h-4 w-4" />
                    Nuevo Semestre
                </Button>
            </div>

            <div
                v-if="$page.props.errors.error"
                class="mb-4 rounded-lg border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive"
            >
                {{ $page.props.errors.error }}
            </div>

            <AdminTable>
                <template #default>
                    <table class="min-w-full divide-y divide-border">
                        <thead class="bg-muted/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Nombre</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Periodo Académico</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Vigencia</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">Estado</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-muted-foreground">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border bg-background">
                            <tr
                                v-for="semester in semesters.data"
                                :key="semester.id"
                                class="transition-colors hover:bg-muted/50"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-foreground">{{ semester.name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-muted-foreground">
                                        {{ semester.academic_period ? semester.academic_period.name : 'Sin asignar' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-foreground">
                                        {{ formatDate(semester.start_date) }} -
                                        {{ formatDate(semester.end_date) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <Badge v-if="semester.status === 'OPEN'" variant="success">
                                        <CalendarHeart class="mr-1 h-3 w-3" /> ABIERTO
                                    </Badge>
                                    <Badge v-else variant="destructive">
                                        <CalendarOff class="mr-1 h-3 w-3" /> CERRADO
                                    </Badge>
                                </td>
                                <td class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap">
                                    <div class="flex justify-end gap-2">
                                        <Button variant="ghost" size="icon-sm" aria-label="Editar semestre" @click="openEditModal(semester)">
                                            <Edit2 class="h-4 w-4" />
                                        </Button>
                                        <Button variant="ghost" size="icon-sm" aria-label="Eliminar semestre" class="text-destructive hover:text-destructive/80" @click="requestDelete(semester.id)">
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="semesters.data.length === 0">
                                <td colspan="5" class="bg-muted/30 px-6 py-12 text-center text-muted-foreground">
                                    Aún no hay semestres configurados.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </template>
            </AdminTable>

            <Pagination :links="semesters.links" />
        </div>
    </AppLayout>

    <Dialog :open="isModalOpen" @update:open="(val: boolean) => { if (!val) closeModal() }">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ editingSemester ? 'Editar Semestre' : 'Crear Semestre' }}</DialogTitle>
                <DialogDescription v-if="editingSemester">
                    Modifica los datos del semestre existente.
                </DialogDescription>
                <DialogDescription v-else>
                    Define un nuevo periodo semestral para la institución.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitForm" class="space-y-4">
                <div>
                    <Label for="semester-name">Nombre (ej. Ago-Dic 2026)</Label>
                    <Input id="semester-name" v-model="form.name" type="text" class="mt-1" required />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-destructive">{{ form.errors.name }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="semester-start">Fecha de Inicio</Label>
                        <Input id="semester-start" type="date" v-model="form.start_date" class="mt-1" required />
                        <p v-if="form.errors.start_date" class="mt-1 text-xs text-destructive">{{ form.errors.start_date }}</p>
                    </div>
                    <div>
                        <Label for="semester-end">Fecha de Fin</Label>
                        <Input id="semester-end" type="date" v-model="form.end_date" class="mt-1" required />
                        <p v-if="form.errors.end_date" class="mt-1 text-xs text-destructive">{{ form.errors.end_date }}</p>
                    </div>
                </div>

                <div>
                    <Label for="semester-period">Periodo Académico (Opcional)</Label>
                    <select
                        id="semester-period"
                        v-model="form.academic_period_id"
                        class="mt-1 block w-full rounded-md border-input bg-background py-2 pl-3 pr-10 text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:outline-none"
                    >
                        <option value="">-- Sin periodo asignado --</option>
                        <option v-for="period in academicPeriods" :key="period.id" :value="period.id">
                            {{ period.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.academic_period_id" class="mt-1 text-xs text-destructive">{{ form.errors.academic_period_id }}</p>
                </div>

                <div>
                    <Label for="semester-status">Estado</Label>
                    <select
                        id="semester-status"
                        v-model="form.status"
                        class="mt-1 block w-full rounded-md border-input bg-background py-2 pl-3 pr-10 text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:outline-none"
                    >
                        <option value="OPEN">ABIERTO (Activo y visible)</option>
                        <option value="CLOSED">CERRADO (Archivado)</option>
                    </select>
                    <p v-if="form.errors.status" class="mt-1 text-xs text-destructive">{{ form.errors.status }}</p>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Solo puede haber un semestre activo a la vez. Si marcas este semestre como abierto,
                        el sistema cerrará automáticamente cualquier otro semestre activo.
                    </p>
                </div>
            </form>

            <DialogFooter>
                <Button variant="outline" @click="closeModal">Cancelar</Button>
                <Button type="submit" :disabled="form.processing" @click="submitForm">
                    {{ editingSemester ? 'Guardar Cambios' : 'Crear' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        :open="isConfirmOpen"
        title="Eliminar semestre"
        description="¿Seguro que deseas eliminar este semestre? Esta acción no se puede deshacer si tiene cargas activas asociadas."
        confirm-label="Eliminar"
        cancel-label="Cancelar"
        variant="destructive"
        @update:open="isConfirmOpen = $event"
        @confirm="confirmDelete"
    />
</template>