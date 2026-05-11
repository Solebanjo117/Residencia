<script setup lang="ts">
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import {
    Save,
    Filter,
    Info,
    AlertTriangle,
    ChevronDown,
} from 'lucide-vue-next';
import { ref, watch, computed, onMounted, onBeforeUnmount } from 'vue';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    semesters: any[];
    departments: any[];
    categories: any[];
    requirements: any[];
    selectedSemester: string | null;
}>();

const page = usePage();

const filterSemester = ref(
    props.selectedSemester ||
        (props.semesters.length > 0 ? props.semesters[0].id : ''),
);

const form = useForm({
    semester_id: filterSemester.value,
    requirements: [] as any[],
});

const initialRequirementsJson = ref('');

watch(
    () => page.props.flash?.success,
    (val) => {
        if (val) toast.success(val as string);
    },
);

const pendingSemesterChange = ref<string | null>(null);
const showSemesterConfirm = ref(false);

watch(filterSemester, (newValue) => {
    if (hasUnsavedChanges.value && newValue !== props.selectedSemester) {
        pendingSemesterChange.value = newValue;
        showSemesterConfirm.value = true;
        return;
    }
    if (newValue && newValue !== props.selectedSemester) {
        performSemesterChange(newValue);
    }
});

const performSemesterChange = (semesterId: string) => {
    router.get(
        '/admin/requirements',
        { semester_id: semesterId },
        { preserveState: true, replace: true },
    );
};

const confirmSemesterChange = () => {
    if (pendingSemesterChange.value) {
        performSemesterChange(pendingSemesterChange.value);
        pendingSemesterChange.value = null;
    }
    showSemesterConfirm.value = false;
    initialRequirementsJson.value = normalizeReqs(form.requirements);
};

const cancelSemesterChange = () => {
    filterSemester.value = props.selectedSemester || '';
    pendingSemesterChange.value = null;
    showSemesterConfirm.value = false;
};

const normalizeReqs = (reqs: any[]) =>
    JSON.stringify(
        reqs
            .map((r: any) => ({
                department_id: r.department_id,
                evidence_item_id: r.evidence_item_id,
                is_mandatory: r.is_mandatory,
            }))
            .sort(
                (a: any, b: any) =>
                    String(a.evidence_item_id).localeCompare(
                        String(b.evidence_item_id),
                    ) ||
                    String(a.department_id ?? 'null').localeCompare(
                        String(b.department_id ?? 'null'),
                    ),
            ),
    );

const hasUnsavedChanges = computed(() => {
    return normalizeReqs(form.requirements) !== initialRequirementsJson.value;
});

const beforeUnloadHandler = (e: BeforeUnloadEvent) => {
    if (hasUnsavedChanges.value) {
        e.preventDefault();
    }
};

onMounted(() => {
    window.addEventListener('beforeunload', beforeUnloadHandler);
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', beforeUnloadHandler);
});

const initializeRequirements = () => {
    form.semester_id = filterSemester.value;
    form.requirements = [];

    const getExisting = (itemId: number, deptId: number | null) => {
        return props.requirements.find(
            (r) =>
                r.evidence_item_id === itemId &&
                (deptId === null
                    ? r.department_id === null
                    : r.department_id === deptId),
        );
    };

    props.categories.forEach((category) => {
        category.items.forEach((item: any) => {
            const globalReq = getExisting(item.id, null);
            if (globalReq) {
                form.requirements.push({
                    department_id: null,
                    evidence_item_id: item.id,
                    is_mandatory: globalReq.is_mandatory,
                });
            }

            props.departments.forEach((dept) => {
                const deptReq = getExisting(item.id, dept.id);
                if (deptReq) {
                    form.requirements.push({
                        department_id: dept.id,
                        evidence_item_id: item.id,
                        is_mandatory: deptReq.is_mandatory,
                    });
                }
            });
        });
    });

    initialRequirementsJson.value = normalizeReqs(form.requirements);
};

watch(
    () => props.requirements,
    () => {
        initializeRequirements();
    },
    { immediate: true },
);

const isChecked = (itemId: number, deptId: number | null) => {
    return form.requirements.some(
        (r) => r.evidence_item_id === itemId && r.department_id === deptId,
    );
};

const isMandatory = (itemId: number, deptId: number | null) => {
    const req = form.requirements.find(
        (r) => r.evidence_item_id === itemId && r.department_id === deptId,
    );
    return req ? req.is_mandatory : true;
};

const toggleRequirement = (itemId: number, deptId: number | null) => {
    const index = form.requirements.findIndex(
        (r) => r.evidence_item_id === itemId && r.department_id === deptId,
    );

    if (index >= 0) {
        form.requirements.splice(index, 1);
    } else {
        form.requirements.push({
            department_id: deptId,
            evidence_item_id: itemId,
            is_mandatory: true,
        });
    }
};

const toggleMandatory = (itemId: number, deptId: number | null) => {
    const req = form.requirements.find(
        (r) => r.evidence_item_id === itemId && r.department_id === deptId,
    );
    if (req) {
        req.is_mandatory = !req.is_mandatory;
    }
};

const submitForm = () => {
    form.semester_id = filterSemester.value;

    form.post('/admin/requirements', {
        preserveScroll: true,
        onSuccess: () => {
            initialRequirementsJson.value = normalizeReqs(form.requirements);
            toast.success('Matriz guardada correctamente.');
        },
        onError: () => {
            toast.error('Error al guardar la matriz. Revisa los datos.');
        },
    });
};

const applyGlobalCategory = (category: any) => {
    category.items.forEach((item: any) => {
        if (!isChecked(item.id, null)) {
            form.requirements.push({
                department_id: null,
                evidence_item_id: item.id,
                is_mandatory: true,
            });
        }
        form.requirements = form.requirements.filter(
            (r) =>
                !(r.evidence_item_id === item.id && r.department_id !== null),
        );
    });
};

const clearCategory = (category: any) => {
    const itemIds = category.items.map((i: any) => i.id);
    form.requirements = form.requirements.filter(
        (r) => !itemIds.includes(r.evidence_item_id),
    );
};

const allMandatoryCategory = (category: any) => {
    category.items.forEach((item: any) => {
        form.requirements.forEach((r) => {
            if (r.evidence_item_id === item.id) {
                r.is_mandatory = true;
            }
        });
    });
};

const categoryToClear = ref<any>(null);
const showClearConfirm = ref(false);

const requestClearCategory = (category: any) => {
    categoryToClear.value = category;
    showClearConfirm.value = true;
};

const confirmClearCategory = () => {
    if (categoryToClear.value) {
        clearCategory(categoryToClear.value);
        categoryToClear.value = null;
    }
    showClearConfirm.value = false;
};

const semesterSummary = computed(() => {
    const reqs = form.requirements;
    const globalReqs = reqs.filter((r) => r.department_id === null);
    const deptReqs = reqs.filter((r) => r.department_id !== null);
    const mandatoryCount = reqs.filter((r) => r.is_mandatory).length;
    const optionalCount = reqs.filter((r) => !r.is_mandatory).length;

    return {
        total: reqs.length,
        global: globalReqs.length,
        byDepartments: deptReqs.length,
        mandatory: mandatoryCount,
        optional: optionalCount,
    };
});
</script>

<template>
    <Head title="Matriz de evidencias" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Admin', href: '#' },
            { title: 'Matriz de evidencias', href: '/admin/requirements' },
        ]"
    >
        <div class="mx-auto max-w-full px-6 py-8">
            <div
                class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-bold text-foreground">
                        Matriz de evidencias
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Configura los documentos requeridos por departamento y
                        semestre.
                    </p>
                </div>
                <div class="flex items-center gap-4">
                    <Badge v-if="hasUnsavedChanges" variant="warning">
                        <AlertTriangle class="mr-1 h-3 w-3" />
                        Cambios sin guardar
                    </Badge>
                    <div class="relative">
                        <Label for="semester-select" class="sr-only"
                            >Semestre</Label
                        >
                        <select
                            id="semester-select"
                            v-model="filterSemester"
                            class="appearance-none rounded-lg border border-input bg-background py-2 pr-10 pl-4 text-sm shadow-sm focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            <option value="" disabled>
                                Selecciona un semestre...
                            </option>
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
                </div>
            </div>

            <div
                class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-info/50 bg-info/10 px-4 py-3 text-sm text-primary"
            >
                <Info class="h-4 w-4 shrink-0" />
                <span>
                    Los rubros globales aplican a todos los departamentos y
                    reemplazan selecciones individuales.
                </span>
            </div>

            <div
                v-if="filterSemester && form.requirements.length > 0"
                class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-border bg-card px-4 py-3 text-sm text-muted-foreground shadow-sm"
            >
                <Badge variant="default">
                    {{ semesterSummary.total }} selecciones
                </Badge>
                <Badge variant="info">
                    {{ semesterSummary.global }} globales
                </Badge>
                <Badge variant="secondary">
                    {{ semesterSummary.byDepartments }} por departamento
                </Badge>
                <Badge variant="warning">
                    {{ semesterSummary.mandatory }} obligatorios
                </Badge>
                <Badge variant="outline">
                    {{ semesterSummary.optional }} opcionales
                </Badge>
            </div>

            <div
                v-if="!filterSemester"
                class="rounded-xl border border-border bg-card p-12 text-center text-muted-foreground shadow-sm"
            >
                Selecciona un semestre del menú desplegable para configurar la
                matriz de evidencias.
            </div>

            <div
                v-else-if="categories.length === 0"
                class="rounded-xl border border-border bg-card p-12 text-center shadow-sm"
            >
                <p class="text-muted-foreground">
                    No hay rubros de evidencia configurados.
                </p>
                <p class="mt-1 text-sm text-muted-foreground/70">
                    Agrega rubros de evidencia primero antes de configurar la
                    matriz.
                </p>
            </div>

            <div
                v-else
                class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
            >
                <form @submit.prevent="submitForm">
                    <div class="overflow-x-auto">
                        <table
                            class="min-w-full divide-y divide-border border-b"
                        >
                            <thead class="bg-muted/50">
                                <tr>
                                    <th
                                        scope="col"
                                        class="sticky left-0 z-10 w-1/3 bg-muted/50 px-6 py-4 text-left text-xs font-bold tracking-wider text-foreground uppercase shadow-[inset_-1px_0_0_var(--color-border)]"
                                    >
                                        Rubro de evidencia
                                    </th>
                                    <th
                                        scope="col"
                                        class="bg-info/10 px-4 py-4 text-center text-xs font-bold tracking-wider text-info uppercase"
                                    >
                                        Todos<br /><span
                                            class="text-[10px] font-normal text-muted-foreground"
                                            >(Aplica a todos los
                                            departamentos)</span
                                        >
                                    </th>
                                    <th
                                        v-for="dept in departments"
                                        :key="dept.id"
                                        scope="col"
                                        class="min-w-[120px] px-4 py-4 text-center text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                    >
                                        {{ dept.name }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border bg-background">
                                <template
                                    v-for="category in categories"
                                    :key="'cat-' + category.id"
                                >
                                    <tr class="bg-muted/70">
                                        <td
                                            :colspan="2 + departments.length"
                                            class="sticky left-0 z-[5] bg-muted/70 px-6 py-2 text-sm font-semibold text-foreground shadow-[inset_-1px_0_0_var(--color-border)]"
                                        >
                                            <div
                                                class="flex flex-wrap items-center justify-between gap-2"
                                            >
                                                <span>{{ category.name }}</span>
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        as-child
                                                    >
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                        >
                                                            Acciones
                                                            <ChevronDown
                                                                class="ml-1 h-3 w-3"
                                                            />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent
                                                        align="end"
                                                    >
                                                        <DropdownMenuItem
                                                            @click="
                                                                applyGlobalCategory(
                                                                    category,
                                                                )
                                                            "
                                                        >
                                                            Aplicar global
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            @click="
                                                                allMandatoryCategory(
                                                                    category,
                                                                )
                                                            "
                                                        >
                                                            Todo obligatorio
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            class="text-destructive focus:text-destructive"
                                                            @click="
                                                                requestClearCategory(
                                                                    category,
                                                                )
                                                            "
                                                        >
                                                            Limpiar
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="item in category.items"
                                        :key="'item-' + item.id"
                                        class="transition-colors hover:bg-muted/30"
                                    >
                                        <td
                                            class="sticky left-0 z-[2] bg-background px-6 py-3 text-sm text-foreground shadow-[inset_-1px_0_0_var(--color-border)]"
                                        >
                                            <div class="font-medium">
                                                {{ item.name }}
                                            </div>
                                            <div
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ item.description }}
                                            </div>
                                        </td>

                                        <td
                                            class="bg-info/5 px-4 py-3 text-center"
                                        >
                                            <div
                                                class="flex flex-col items-center gap-1"
                                            >
                                                <Checkbox
                                                    :model-value="
                                                        isChecked(item.id, null)
                                                    "
                                                    @update:model-value="
                                                        toggleRequirement(
                                                            item.id,
                                                            null,
                                                        )
                                                    "
                                                />
                                                <button
                                                    v-if="
                                                        isChecked(item.id, null)
                                                    "
                                                    type="button"
                                                    class="mt-1 cursor-pointer rounded-full border px-2 py-0.5 text-[10px] font-semibold transition-colors focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                                    :class="
                                                        isMandatory(
                                                            item.id,
                                                            null,
                                                        )
                                                            ? 'border-amber-300 bg-amber-100 text-amber-800 hover:bg-amber-200'
                                                            : 'border-blue-300 bg-blue-100 text-blue-800 hover:bg-blue-200'
                                                    "
                                                    :aria-label="
                                                        isMandatory(
                                                            item.id,
                                                            null,
                                                        )
                                                            ? 'Cambiar a opcional'
                                                            : 'Cambiar a obligatorio'
                                                    "
                                                    :title="
                                                        isMandatory(
                                                            item.id,
                                                            null,
                                                        )
                                                            ? 'Clic para cambiar a Opcional'
                                                            : 'Clic para cambiar a Obligatorio'
                                                    "
                                                    @click="
                                                        toggleMandatory(
                                                            item.id,
                                                            null,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        isMandatory(
                                                            item.id,
                                                            null,
                                                        )
                                                            ? 'Obligatorio'
                                                            : 'Opcional'
                                                    }}
                                                </button>
                                            </div>
                                        </td>

                                        <td
                                            v-for="dept in departments"
                                            :key="'dept-' + dept.id"
                                            class="px-4 py-3 text-center"
                                        >
                                            <div
                                                class="flex flex-col items-center gap-1"
                                            >
                                                <TooltipProvider
                                                    :delay-duration="300"
                                                >
                                                    <Tooltip
                                                        v-if="
                                                            isChecked(
                                                                item.id,
                                                                null,
                                                            )
                                                        "
                                                    >
                                                        <TooltipTrigger
                                                            as-child
                                                        >
                                                            <span>
                                                                <Checkbox
                                                                    :model-value="
                                                                        isChecked(
                                                                            item.id,
                                                                            dept.id,
                                                                        )
                                                                    "
                                                                    :disabled="
                                                                        isChecked(
                                                                            item.id,
                                                                            null,
                                                                        )
                                                                    "
                                                                    @update:model-value="
                                                                        toggleRequirement(
                                                                            item.id,
                                                                            dept.id,
                                                                        )
                                                                    "
                                                                />
                                                            </span>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            Desactiva la
                                                            selección global
                                                            primero
                                                        </TooltipContent>
                                                    </Tooltip>
                                                    <Checkbox
                                                        v-else
                                                        :model-value="
                                                            isChecked(
                                                                item.id,
                                                                dept.id,
                                                            )
                                                        "
                                                        @update:model-value="
                                                            toggleRequirement(
                                                                item.id,
                                                                dept.id,
                                                            )
                                                        "
                                                    />
                                                </TooltipProvider>
                                                <button
                                                    v-if="
                                                        isChecked(
                                                            item.id,
                                                            dept.id,
                                                        ) &&
                                                        !isChecked(
                                                            item.id,
                                                            null,
                                                        )
                                                    "
                                                    type="button"
                                                    class="mt-1 cursor-pointer rounded-full border px-2 py-0.5 text-[10px] font-semibold transition-colors focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                                    :class="
                                                        isMandatory(
                                                            item.id,
                                                            dept.id,
                                                        )
                                                            ? 'border-amber-300 bg-amber-100 text-amber-800 hover:bg-amber-200'
                                                            : 'border-blue-300 bg-blue-100 text-blue-800 hover:bg-blue-200'
                                                    "
                                                    :aria-label="
                                                        isMandatory(
                                                            item.id,
                                                            dept.id,
                                                        )
                                                            ? 'Cambiar a opcional'
                                                            : 'Cambiar a obligatorio'
                                                    "
                                                    :title="
                                                        isMandatory(
                                                            item.id,
                                                            dept.id,
                                                        )
                                                            ? 'Clic para cambiar a Opcional'
                                                            : 'Clic para cambiar a Obligatorio'
                                                    "
                                                    @click="
                                                        toggleMandatory(
                                                            item.id,
                                                            dept.id,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        isMandatory(
                                                            item.id,
                                                            dept.id,
                                                        )
                                                            ? 'Obligatorio'
                                                            : 'Opcional'
                                                    }}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div
                        class="flex items-center justify-between border-t border-border bg-muted/30 px-6 py-4"
                    >
                        <div class="text-xs text-muted-foreground">
                            Los rubros globales reemplazan las selecciones
                            departamentales individuales.
                        </div>
                        <button
                            type="submit"
                            :disabled="form.processing || !filterSemester"
                            class="inline-flex items-center justify-center rounded-md border border-transparent bg-primary px-6 py-2.5 text-sm font-bold text-primary-foreground shadow-sm hover:bg-primary/90 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50"
                        >
                            <Save class="mr-2 h-4 w-4" />
                            Guardar matriz
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>

    <ConfirmDialog
        :open="showSemesterConfirm"
        title="Cambios sin guardar"
        description="Tienes cambios sin guardar en la matriz. Si cambias de semestre, se perderán los cambios actuales. ¿Deseas continuar?"
        confirm-label="Continuar sin guardar"
        cancel-label="Permanecer"
        variant="warning"
        @update:open="
            (val: boolean) => {
                if (!val) cancelSemesterChange();
            }
        "
        @confirm="confirmSemesterChange"
    />

    <ConfirmDialog
        :open="showClearConfirm"
        title="Limpiar categoría"
        description="Se eliminarán todas las selecciones de esta categoría. Esta acción no se puede deshacer."
        confirm-label="Limpiar"
        cancel-label="Cancelar"
        variant="destructive"
        @update:open="
            (val: boolean) => {
                if (!val) {
                    showClearConfirm = false;
                    categoryToClear = null;
                }
            }
        "
        @confirm="confirmClearCategory"
    />
</template>
