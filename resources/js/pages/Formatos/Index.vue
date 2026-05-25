<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Archive,
    Download,
    FileText,
    Filter,
    Pencil,
    RefreshCw,
    RotateCcw,
    Search,
    Upload,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import AppLayout from '@/layouts/AppLayout.vue';
import type { EvidenceItem, FormatPublication } from '@/types/models';

const props = defineProps<{
    publications: FormatPublication[];
    evidenceItems: Pick<EvidenceItem, 'id' | 'name'>[];
    canManageFormats: boolean;
    allowedExtensions: string[];
    maxUploadKb: number;
    focusedPublicationId: number | null;
    filters: {
        search: string;
        evidence_item_id: number | null;
    };
}>();

const page = usePage();
const search = ref(props.filters.search ?? '');
const selectedEvidenceItemId = ref<number | ''>(
    props.filters.evidence_item_id ?? '',
);
const editingPublication = ref<FormatPublication | null>(null);
const replacingPublicationId = ref<number | null>(null);

const publicationForm = useForm<{
    title: string;
    body: string;
    evidence_item_id: number | '';
    file: File | null;
}>({
    title: '',
    body: '',
    evidence_item_id: props.evidenceItems[0]?.id ?? '',
    file: null,
});

const replaceForm = useForm<{
    file: File | null;
}>({
    file: null,
});

const acceptedFileTypes = computed(() =>
    props.allowedExtensions.map((extension) => `.${extension}`).join(','),
);

const flashSuccess = computed(
    () => page.props.flash?.success as string | undefined,
);

watch(flashSuccess, (message) => {
    if (message) toast.success(message);
});

let filterTimeout: ReturnType<typeof setTimeout>;
watch([search, selectedEvidenceItemId], () => {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(() => {
        router.get(
            '/formatos',
            {
                search: search.value || undefined,
                evidence_item_id: selectedEvidenceItemId.value || undefined,
            },
            { preserveState: true, replace: true },
        );
    }, 250);
});

const resetPublicationForm = () => {
    editingPublication.value = null;
    publicationForm.reset();
    publicationForm.clearErrors();
    publicationForm.evidence_item_id = props.evidenceItems[0]?.id ?? '';
};

const editPublication = (publication: FormatPublication) => {
    editingPublication.value = publication;
    publicationForm.clearErrors();
    publicationForm.title = publication.title;
    publicationForm.body = publication.body ?? '';
    publicationForm.evidence_item_id = publication.evidence_item.id;
    publicationForm.file = null;
};

const onPublicationFileChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    publicationForm.file = input.files?.[0] ?? null;
};

const onReplacementFileChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    replaceForm.file = input.files?.[0] ?? null;
};

const submitPublication = () => {
    if (editingPublication.value) {
        publicationForm.patch(`/formatos/${editingPublication.value.id}`, {
            preserveScroll: true,
            onSuccess: resetPublicationForm,
        });
        return;
    }

    publicationForm.post('/formatos', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: resetPublicationForm,
    });
};

const startReplacement = (publication: FormatPublication) => {
    replacingPublicationId.value = publication.id;
    replaceForm.reset();
    replaceForm.clearErrors();
};

const submitReplacement = (publication: FormatPublication) => {
    replaceForm.post(`/formatos/${publication.id}/replace-file`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            replacingPublicationId.value = null;
            replaceForm.reset();
        },
    });
};

const archivePublication = (publication: FormatPublication) => {
    useForm({}).patch(`/formatos/${publication.id}/archive`, {
        preserveScroll: true,
    });
};

const restorePublication = (publication: FormatPublication) => {
    useForm({}).patch(`/formatos/${publication.id}/restore`, {
        preserveScroll: true,
    });
};

const formatBytes = (bytes?: number) => {
    if (!bytes) return '0 KB';
    if (bytes < 1024 * 1024) return `${Math.ceil(bytes / 1024)} KB`;

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
};

const isRecentlyUpdated = (publication: FormatPublication) => {
    if (!publication.updated_at || !publication.published_at) return false;

    return publication.updated_at !== publication.published_at;
};
</script>

<template>
    <Head title="Formatos" />

    <AppLayout>
        <div class="min-h-screen bg-slate-50 px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto flex max-w-7xl flex-col gap-5">
                <section
                    class="flex flex-col gap-3 border-b border-slate-200 pb-5 lg:flex-row lg:items-end lg:justify-between"
                >
                    <div>
                        <p
                            class="text-xs font-semibold tracking-wide text-slate-500 uppercase"
                        >
                            Biblioteca institucional
                        </p>
                        <h1 class="text-2xl font-semibold text-slate-950">
                            Formatos publicados
                        </h1>
                        <p class="mt-1 max-w-2xl text-sm text-slate-600">
                            Formatos oficiales vinculados a rubros de evidencia.
                        </p>
                    </div>

                    <div
                        class="flex items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600"
                    >
                        <FileText class="h-4 w-4 text-slate-500" />
                        <span>{{ publications.length }} publicaciones</span>
                    </div>
                </section>

                <section
                    v-if="canManageFormats"
                    class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-slate-950">
                                {{
                                    editingPublication
                                        ? 'Editar formato'
                                        : 'Publicar formato'
                                }}
                            </h2>
                            <p class="text-sm text-slate-500">
                                El archivo vigente queda disponible para todos
                                los docentes.
                            </p>
                        </div>

                        <button
                            v-if="editingPublication"
                            type="button"
                            class="rounded-md border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            @click="resetPublicationForm"
                        >
                            Cancelar
                        </button>
                    </div>

                    <form
                        class="grid gap-3 lg:grid-cols-[1fr_18rem] lg:items-start"
                        @submit.prevent="submitPublication"
                    >
                        <div class="grid gap-3">
                            <div>
                                <label
                                    class="text-sm font-medium text-slate-700"
                                >
                                    Titulo
                                </label>
                                <input
                                    v-model="publicationForm.title"
                                    type="text"
                                    class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
                                    maxlength="160"
                                />
                                <p
                                    v-if="publicationForm.errors.title"
                                    class="mt-1 text-sm text-red-600"
                                >
                                    {{ publicationForm.errors.title }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="text-sm font-medium text-slate-700"
                                >
                                    Descripcion
                                </label>
                                <textarea
                                    v-model="publicationForm.body"
                                    rows="3"
                                    class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
                                />
                                <p
                                    v-if="publicationForm.errors.body"
                                    class="mt-1 text-sm text-red-600"
                                >
                                    {{ publicationForm.errors.body }}
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-3">
                            <div>
                                <label
                                    class="text-sm font-medium text-slate-700"
                                >
                                    Rubro
                                </label>
                                <select
                                    v-model="publicationForm.evidence_item_id"
                                    class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
                                >
                                    <option
                                        v-for="item in evidenceItems"
                                        :key="item.id"
                                        :value="item.id"
                                    >
                                        {{ item.name }}
                                    </option>
                                </select>
                                <p
                                    v-if="
                                        publicationForm.errors.evidence_item_id
                                    "
                                    class="mt-1 text-sm text-red-600"
                                >
                                    {{
                                        publicationForm.errors.evidence_item_id
                                    }}
                                </p>
                            </div>

                            <div v-if="!editingPublication">
                                <label
                                    class="text-sm font-medium text-slate-700"
                                >
                                    Archivo
                                </label>
                                <input
                                    type="file"
                                    :accept="acceptedFileTypes"
                                    class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                    @change="onPublicationFileChange"
                                />
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ allowedExtensions.join(', ') }} hasta
                                    {{ maxUploadKb }} KB.
                                </p>
                                <p
                                    v-if="publicationForm.errors.file"
                                    class="mt-1 text-sm text-red-600"
                                >
                                    {{ publicationForm.errors.file }}
                                </p>
                            </div>

                            <button
                                type="submit"
                                class="inline-flex items-center justify-center gap-2 rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60"
                                :disabled="publicationForm.processing"
                            >
                                <Upload class="h-4 w-4" />
                                {{
                                    editingPublication ? 'Guardar' : 'Publicar'
                                }}
                            </button>
                        </div>
                    </form>
                </section>

                <section
                    class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-[1fr_18rem]"
                >
                    <label class="relative block">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
                        />
                        <input
                            v-model="search"
                            type="search"
                            class="w-full rounded-md border border-slate-300 py-2 pr-3 pl-9 text-sm focus:border-slate-500 focus:outline-none"
                            placeholder="Buscar formato"
                        />
                    </label>

                    <label class="relative block">
                        <Filter
                            class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
                        />
                        <select
                            v-model="selectedEvidenceItemId"
                            class="w-full rounded-md border border-slate-300 py-2 pr-3 pl-9 text-sm focus:border-slate-500 focus:outline-none"
                        >
                            <option value="">Todos los rubros</option>
                            <option
                                v-for="item in evidenceItems"
                                :key="item.id"
                                :value="item.id"
                            >
                                {{ item.name }}
                            </option>
                        </select>
                    </label>
                </section>

                <section class="grid gap-3">
                    <article
                        v-for="publication in publications"
                        :key="publication.id"
                        class="rounded-lg border bg-white p-4 shadow-sm"
                        :class="[
                            publication.id === focusedPublicationId
                                ? 'border-blue-400 ring-2 ring-blue-100'
                                : 'border-slate-200',
                        ]"
                    >
                        <div
                            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2
                                        class="text-lg font-semibold text-slate-950"
                                    >
                                        {{ publication.title }}
                                    </h2>
                                    <span
                                        class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700"
                                    >
                                        {{ publication.evidence_item.name }}
                                    </span>
                                    <span
                                        v-if="publication.status === 'ARCHIVED'"
                                        class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800"
                                    >
                                        Archivado
                                    </span>
                                    <span
                                        v-else-if="
                                            isRecentlyUpdated(publication)
                                        "
                                        class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800"
                                    >
                                        Actualizado
                                    </span>
                                </div>

                                <p
                                    v-if="publication.body"
                                    class="mt-2 text-sm leading-6 whitespace-pre-line text-slate-600"
                                >
                                    {{ publication.body }}
                                </p>

                                <div
                                    v-if="publication.file"
                                    class="mt-3 flex flex-wrap items-center gap-3 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600"
                                >
                                    <FileText class="h-4 w-4 text-slate-500" />
                                    <span class="font-medium text-slate-800">
                                        {{ publication.file.file_name }}
                                    </span>
                                    <span>{{
                                        formatBytes(publication.file.size_bytes)
                                    }}</span>
                                    <span v-if="publication.updated_by_name">
                                        Actualizado por
                                        {{ publication.updated_by_name }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2 lg:justify-end">
                                <a
                                    v-if="publication.file"
                                    :href="publication.file.download_url"
                                    class="inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                >
                                    <Download class="h-4 w-4" />
                                    Descargar
                                </a>

                                <template v-if="canManageFormats">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                        @click="editPublication(publication)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                        Editar
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                        @click="startReplacement(publication)"
                                    >
                                        <RefreshCw class="h-4 w-4" />
                                        Reemplazar
                                    </button>
                                    <button
                                        v-if="publication.status === 'ACTIVE'"
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-md border border-amber-200 px-3 py-2 text-sm font-medium text-amber-800 hover:bg-amber-50"
                                        @click="archivePublication(publication)"
                                    >
                                        <Archive class="h-4 w-4" />
                                        Archivar
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-md border border-emerald-200 px-3 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-50"
                                        @click="restorePublication(publication)"
                                    >
                                        <RotateCcw class="h-4 w-4" />
                                        Restaurar
                                    </button>
                                </template>
                            </div>
                        </div>

                        <form
                            v-if="replacingPublicationId === publication.id"
                            class="mt-4 grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3 md:grid-cols-[1fr_auto]"
                            @submit.prevent="submitReplacement(publication)"
                        >
                            <div>
                                <input
                                    type="file"
                                    :accept="acceptedFileTypes"
                                    class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
                                    @change="onReplacementFileChange"
                                />
                                <p
                                    v-if="replaceForm.errors.file"
                                    class="mt-1 text-sm text-red-600"
                                >
                                    {{ replaceForm.errors.file }}
                                </p>
                            </div>
                            <button
                                type="submit"
                                class="inline-flex items-center justify-center gap-2 rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60"
                                :disabled="replaceForm.processing"
                            >
                                <RefreshCw class="h-4 w-4" />
                                Guardar archivo
                            </button>
                        </form>
                    </article>

                    <div
                        v-if="publications.length === 0"
                        class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center"
                    >
                        <FileText class="mx-auto h-8 w-8 text-slate-400" />
                        <h2 class="mt-3 text-base font-semibold text-slate-900">
                            No hay formatos publicados
                        </h2>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
