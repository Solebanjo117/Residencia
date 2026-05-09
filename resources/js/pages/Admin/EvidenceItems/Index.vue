<script setup lang="ts">
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { Edit2, Plus, Trash2, Search } from 'lucide-vue-next';
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

type EvidenceCategory = {
    id: number;
    name: string;
    description: string | null;
    items_count: number;
};

type EvidenceItem = {
    id: number;
    category_id: number;
    name: string;
    description: string | null;
    requires_subject: boolean;
    active: boolean;
    category: EvidenceCategory;
    requirements_count: number;
    submissions_count: number;
};

const props = defineProps<{
    categories: EvidenceCategory[];
    items: {
        data: EvidenceItem[];
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
    filters: {
        search: string;
        category_id: string;
        status: string;
        usage: string;
    };
}>();

const page = usePage();

const isModalOpen = ref(false);
const isConfirmOpen = ref(false);
const itemToDelete = ref<EvidenceItem | null>(null);
const editingItem = ref<EvidenceItem | null>(null);

const filterSearch = ref(props.filters.search);
const filterCategory = ref(props.filters.category_id);
const filterStatus = ref(props.filters.status);
const filterUsage = ref(props.filters.usage);

let debounceTimeout: ReturnType<typeof setTimeout>;

const applyFilters = () => {
    router.get('/admin/evidence-items', {
        search: filterSearch.value || undefined,
        category_id: filterCategory.value || undefined,
        status: filterStatus.value !== 'all' ? filterStatus.value : undefined,
        usage: filterUsage.value !== 'all' ? filterUsage.value : undefined,
    }, { preserveState: true, replace: true });
};

watch(filterSearch, () => {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(applyFilters, 300);
});

watch([filterCategory, filterStatus, filterUsage], () => {
    applyFilters();
});

const form = useForm({
    category_id: props.categories[0]?.id ?? '',
    name: '',
    description: '',
    requires_subject: true,
    active: true,
});

const canSubmit = computed(() => props.categories.length > 0);

const flashSuccess = computed(() => page.props.flash?.success as string | undefined);
watch(flashSuccess, (val) => {
    if (val) toast.success(val);
});

const openCreateModal = () => {
    editingItem.value = null;
    form.reset();
    form.clearErrors();
    form.category_id = props.categories[0]?.id ?? '';
    form.requires_subject = true;
    form.active = true;
    isModalOpen.value = true;
};

const openEditModal = (item: EvidenceItem) => {
    editingItem.value = item;
    form.clearErrors();
    form.category_id = item.category_id;
    form.name = item.name;
    form.description = item.description ?? '';
    form.requires_subject = item.requires_subject;
    form.active = item.active;
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    editingItem.value = null;
    form.reset();
    form.clearErrors();
};

const submitForm = () => {
    if (editingItem.value) {
        form.put(`/admin/evidence-items/${editingItem.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                toast.success('Rubro actualizado correctamente.');
            },
        });
        return;
    }

    form.post('/admin/evidence-items', {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            toast.success('Rubro creado correctamente.');
        },
    });
};

const requestDelete = (item: EvidenceItem) => {
    if (isUsed(item)) return;
    itemToDelete.value = item;
    isConfirmOpen.value = true;
};

const confirmDelete = () => {
    if (!itemToDelete.value) return;
    useForm({}).delete(`/admin/evidence-items/${itemToDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Rubro eliminado correctamente.');
        },
    });
    itemToDelete.value = null;
};

const isUsed = (item: EvidenceItem) =>
    item.requirements_count > 0 || item.submissions_count > 0;

const editingItemUsageWarning = computed(() => {
    if (!editingItem.value || !isUsed(editingItem.value)) return null;
    return `Este rubro está usado en ${editingItem.value.requirements_count} matriz${editingItem.value.requirements_count !== 1 ? 'ces' : ''} y ${editingItem.value.submissions_count} evidencia${editingItem.value.submissions_count !== 1 ? 's' : ''}.`;
});

const modalTitle = computed(() =>
    editingItem.value ? 'Editar rubro' : 'Agregar rubro',
);
</script>

<template>
    <Head title="Rubros de evidencia" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Admin', href: '#' },
            { title: 'Rubros de evidencia', href: '/admin/evidence-items' },
        ]"
    >
        <div class="mx-auto max-w-7xl px-6 py-8">
            <div class="mb-6 flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">
                        Rubros de evidencia
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Alta y control de los documentos que después se activan
                        en la matriz por semestre y departamento.
                    </p>
                </div>

                <Button
                    :disabled="!canSubmit"
                    @click="openCreateModal"
                >
                    <Plus class="mr-2 h-4 w-4" />
                    Agregar rubro
                </Button>
            </div>

            <div
                v-if="props.categories.length === 0"
                class="mb-4 rounded-lg border border-warning/50 bg-warning/10 px-4 py-3 text-sm font-medium text-warning-foreground"
            >
                No existen categorías de evidencia. Configura categorías de
                evidencia antes de crear rubros.
            </div>

            <div
                v-if="$page.props.errors.error"
                class="mb-4 rounded-lg border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive"
            >
                {{ $page.props.errors.error }}
            </div>

            <div
                class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-border bg-card px-4 py-3 shadow-sm"
            >
                <div class="flex-1 min-w-[200px]">
                    <Label for="filter-search">Buscar</Label>
                    <div class="relative mt-1">
                        <Search
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            id="filter-search"
                            v-model="filterSearch"
                            type="text"
                            placeholder="Nombre o descripción..."
                            class="pl-9"
                        />
                    </div>
                </div>
                <div>
                    <Label for="filter-category">Categoría</Label>
                    <select
                        id="filter-category"
                        v-model="filterCategory"
                        class="mt-1 rounded-md border-input bg-background py-2 pl-3 pr-8 text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:outline-none"
                    >
                        <option value="">Todas</option>
                        <option
                            v-for="cat in props.categories"
                            :key="cat.id"
                            :value="cat.id"
                        >
                            {{ cat.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <Label for="filter-status">Estado</Label>
                    <select
                        id="filter-status"
                        v-model="filterStatus"
                        class="mt-1 rounded-md border-input bg-background py-2 pl-3 pr-8 text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:outline-none"
                    >
                        <option value="all">Todos</option>
                        <option value="active">Activo</option>
                        <option value="inactive">Inactivo</option>
                    </select>
                </div>
                <div>
                    <Label for="filter-usage">Uso</Label>
                    <select
                        id="filter-usage"
                        v-model="filterUsage"
                        class="mt-1 rounded-md border-input bg-background py-2 pl-3 pr-8 text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:outline-none"
                    >
                        <option value="all">Todos</option>
                        <option value="used">En uso</option>
                        <option value="unused">Sin uso</option>
                    </select>
                </div>
            </div>

            <AdminTable>
                <template #default>
                    <table class="min-w-full divide-y divide-border">
                        <thead class="bg-muted/50">
                            <tr>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground"
                                >
                                    Rubro
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground"
                                >
                                    Categoría
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground"
                                >
                                    Alcance
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground"
                                >
                                    Estado
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground"
                                >
                                    Uso
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-muted-foreground"
                                >
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border bg-background">
                            <tr
                                v-for="item in props.items.data"
                                :key="item.id"
                                class="transition-colors hover:bg-muted/50"
                            >
                                <td class="px-6 py-4 text-sm text-foreground">
                                    <div class="font-semibold">
                                        {{ item.name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{
                                            item.description || 'Sin descripción'
                                        }}
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-muted-foreground">
                                    {{ item.category?.name }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <Badge
                                        :variant="
                                            item.requires_subject
                                                ? 'info'
                                                : 'secondary'
                                        "
                                    >
                                        {{
                                            item.requires_subject
                                                ? 'Por carga/materia'
                                                : 'General del docente'
                                        }}
                                    </Badge>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <Badge
                                        :variant="
                                            item.active ? 'success' : 'warning'
                                        "
                                    >
                                        {{ item.active ? 'Activo' : 'Inactivo' }}
                                    </Badge>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-muted-foreground">
                                    {{ item.requirements_count }} matriz /
                                    {{ item.submissions_count }} evidencias
                                </td>
                                <td
                                    class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium"
                                >
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            aria-label="Editar rubro"
                                            @click="openEditModal(item)"
                                        >
                                            <Edit2 class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            :disabled="isUsed(item)"
                                            :aria-label="
                                                isUsed(item)
                                                    ? 'No se puede eliminar: rubro en uso'
                                                    : 'Eliminar rubro'
                                            "
                                            :title="
                                                isUsed(item)
                                                    ? 'No se puede eliminar porque está usado en matriz o evidencias'
                                                    : 'Eliminar'
                                            "
                                            class="text-destructive hover:text-destructive/80 disabled:text-muted-foreground"
                                            @click="requestDelete(item)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="props.items.data.length === 0">
                                <td
                                    colspan="6"
                                    class="bg-muted/30 px-6 py-12 text-center text-muted-foreground"
                                >
                                    No hay rubros que coincidan con los filtros.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </template>
            </AdminTable>

            <Pagination :links="props.items.links" />
        </div>
    </AppLayout>

    <Dialog :open="isModalOpen" @update:open="(val: boolean) => { if (!val) closeModal() }">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ modalTitle }}</DialogTitle>
                <DialogDescription v-if="editingItem">
                    Modifica los datos del rubro de evidencia.
                </DialogDescription>
                <DialogDescription v-else>
                    Completa los datos para crear un nuevo rubro de evidencia.
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="editingItemUsageWarning"
                class="flex items-center gap-2 rounded-lg border border-warning/50 bg-warning/10 px-3 py-2 text-sm text-warning-foreground"
            >
                <Badge variant="warning">
                    {{ editingItemUsageWarning }}
                </Badge>
            </div>

            <form @submit.prevent="submitForm" class="space-y-4">
                <div>
                    <Label for="evidence-category">Categoría</Label>
                    <select
                        id="evidence-category"
                        v-model="form.category_id"
                        class="mt-1 block w-full rounded-md border-input bg-background py-2 pl-3 pr-10 text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:outline-none"
                        required
                    >
                        <option
                            v-for="category in props.categories"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.category_id" class="mt-1 text-xs text-destructive">
                        {{ form.errors.category_id }}
                    </p>
                </div>

                <div>
                    <Label for="evidence-name">Nombre del rubro</Label>
                    <Input
                        id="evidence-name"
                        v-model="form.name"
                        type="text"
                        class="mt-1"
                        required
                    />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-destructive">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div>
                    <Label for="evidence-description">Descripción</Label>
                    <textarea
                        id="evidence-description"
                        v-model="form.description"
                        rows="3"
                        class="mt-1 block w-full rounded-md border-input bg-background px-3 py-2 text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:outline-none"
                    ></textarea>
                    <p v-if="form.errors.description" class="mt-1 text-xs text-destructive">
                        {{ form.errors.description }}
                    </p>
                </div>

                <label class="flex items-center gap-2 text-sm text-foreground">
                    <Checkbox v-model="form.requires_subject" />
                    Requiere materia/carga académica
                </label>

                <label class="flex items-center gap-2 text-sm text-foreground">
                    <Checkbox v-model="form.active" />
                    Activo para matrices y ventanas
                </label>

                <p
                    v-if="
                        editingItem &&
                        isUsed(editingItem) &&
                        editingItem.active
                    "
                    class="text-xs text-muted-foreground"
                >
                    Para desactivar este rubro, desmarca la casilla "Activo" y
                    guarda los cambios. No se puede eliminar porque está en uso.
                </p>
            </form>

            <DialogFooter>
                <Button variant="outline" @click="closeModal">
                    Cancelar
                </Button>
                <Button
                    :disabled="form.processing"
                    @click="submitForm"
                >
                    {{ editingItem ? 'Guardar cambios' : 'Agregar' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

<ConfirmDialog
        :open="isConfirmOpen"
        title="Eliminar rubro"
        :description="'¿Deseas eliminar el rubro? Esta acción no se puede deshacer.'"
        confirm-label="Eliminar"
        cancel-label="Cancelar"
        variant="destructive"
        @update:open="isConfirmOpen = $event"
        @confirm="confirmDelete"
    />
</template>