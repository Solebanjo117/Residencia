<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, watch } from 'vue';
import { Settings, Save, Filter } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';

const props = defineProps<{
    semesters: any[];
    departments: any[];
    categories: any[];
    requirements: any[];
    selectedSemester: string | null;
}>();

const filterSemester = ref(
    props.selectedSemester ||
        (props.semesters.length > 0 ? props.semesters[0].id : ''),
);

// Form state for bulk save
const form = useForm({
    semester_id: filterSemester.value,
    requirements: [] as any[],
});

// Watch and load requirements when semester changes
watch(filterSemester, (newValue) => {
    if (newValue && newValue !== props.selectedSemester) {
        router.get(
            '/admin/requirements',
            { semester_id: newValue },
            { preserveState: true, replace: true },
        );
    }
});

// Initialize form requirements from props
const initializeRequirements = () => {
    form.semester_id = filterSemester.value;
    form.requirements = [];

    // Helper to find if a requirement exists
    const getExisting = (itemId: number, deptId: number | null) => {
        return props.requirements.find(
            (r) =>
                r.evidence_item_id === itemId &&
                (deptId === null
                    ? r.department_id === null
                    : r.department_id === deptId),
        );
    };

    // We build a matrix entry for GLOBAL (null department) and EACH DEPARTMENT
    props.categories.forEach((category) => {
        category.items.forEach((item: any) => {
            // Global check
            const globalReq = getExisting(item.id, null);
            if (globalReq) {
                form.requirements.push({
                    department_id: null,
                    evidence_item_id: item.id,
                    is_mandatory: globalReq.is_mandatory,
                });
            }

            // Department checks
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
};

// Call initialization
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
    return req ? req.is_mandatory : true; // Default to true if checked next
};

const toggleRequirement = (itemId: number, deptId: number | null) => {
    const index = form.requirements.findIndex(
        (r) => r.evidence_item_id === itemId && r.department_id === deptId,
    );

    if (index >= 0) {
        form.requirements.splice(index, 1); // remove
    } else {
        form.requirements.push({
            department_id: deptId,
            evidence_item_id: itemId,
            is_mandatory: true, // Default to required
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
    form.post('/admin/requirements', {
        preserveScroll: true,
        onSuccess: () => {
            // Success handled by flash message via layout
        },
    });
};
</script>

<template>
    <Head title="Evidence Requirements" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Admin', href: '#' },
            { title: 'Evidence Matrix', href: '/admin/requirements' },
        ]"
    >
        <div class="mx-auto max-w-full px-6 py-8">
            <div
                class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">
                        Evidence Matrix
                    </h1>
                    <p class="mt-1 text-sm text-gray-500">
                        Configure which documents are required for each
                        department in a semester.
                    </p>
                </div>
                <div class="flex items-center gap-4">
                    <div class="relative">
                        <select
                            v-model="filterSemester"
                            class="appearance-none rounded-lg border border-gray-300 bg-white py-2 pr-10 pl-4 text-sm leading-tight text-gray-700 shadow-sm focus:border-blue-500 focus:bg-white focus:outline-none"
                        >
                            <option value="" disabled>
                                Select Semester to Configure...
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
                            class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-500"
                        >
                            <Filter class="h-4 w-4" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State if no semester selected -->
            <div
                v-if="!filterSemester"
                class="rounded-xl border border-gray-200 bg-white p-12 text-center text-gray-500 shadow-sm"
            >
                Please select a semester from the dropdown to configure evidence
                requirements.
            </div>

            <!-- Matrix -->
            <div
                v-else
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"
            >
                <form @submit.prevent="submitForm">
                    <div class="overflow-x-auto">
                        <table
                            class="min-w-full divide-y divide-gray-200 border-b"
                        >
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        scope="col"
                                        class="sticky left-0 z-10 w-1/3 bg-gray-50 px-6 py-4 text-left text-xs font-bold tracking-wider text-gray-900 uppercase shadow-[inset_-1px_0_0_rgba(229,231,235,1)]"
                                    >
                                        Evidence Item
                                    </th>
                                    <th
                                        scope="col"
                                        class="bg-blue-50 px-4 py-4 text-center text-xs font-bold tracking-wider text-blue-700 uppercase"
                                    >
                                        GLOBAL<br /><span
                                            class="text-[10px] font-normal text-blue-500"
                                            >(All Departments)</span
                                        >
                                    </th>
                                    <th
                                        v-for="dept in departments"
                                        :key="dept.id"
                                        scope="col"
                                        class="min-w-[120px] px-4 py-4 text-center text-xs font-medium tracking-wider text-gray-500 uppercase"
                                    >
                                        {{ dept.name }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <template
                                    v-for="category in categories"
                                    :key="'cat-' + category.id"
                                >
                                    <tr class="bg-gray-100">
                                        <td
                                            :colspan="2 + departments.length"
                                            class="sticky left-0 px-6 py-2 text-sm font-semibold text-gray-800 shadow-[inset_-1px_0_0_rgba(229,231,235,1)]"
                                        >
                                            {{ category.name }}
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="item in category.items"
                                        :key="'item-' + item.id"
                                        class="hover:bg-gray-50"
                                    >
                                        <td
                                            class="sticky left-0 bg-white px-6 py-3 text-sm text-gray-900 shadow-[inset_-1px_0_0_rgba(229,231,235,1)]"
                                        >
                                            <div class="font-medium">
                                                {{ item.name }}
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                {{ item.description }}
                                            </div>
                                        </td>

                                        <!-- Global Checkbox -->
                                        <td
                                            class="bg-blue-50/30 px-4 py-3 text-center"
                                        >
                                            <div
                                                class="flex flex-col items-center gap-1"
                                            >
                                                <input
                                                    type="checkbox"
                                                    class="focus:ring-opacity-50 h-5 w-5 cursor-pointer rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200"
                                                    :checked="
                                                        isChecked(item.id, null)
                                                    "
                                                    @change="
                                                        toggleRequirement(
                                                            item.id,
                                                            null,
                                                        )
                                                    "
                                                />
                                                <button type="button" v-if="
                                                        isChecked(item.id, null)
                                                    "
                                                    @click="
                                                        toggleMandatory(
                                                            item.id,
                                                            null,
                                                        )
                                                    "
                                                    class="mt-1 w-full rounded px-1 text-[10px] font-semibold"
                                                    :class="
                                                        isMandatory(
                                                            item.id,
                                                            null,
                                                        )
                                                            ? 'bg-red-100 text-red-700'
                                                            : 'bg-gray-200 text-gray-600'
                                                    "
                                                >
                                                    {{
                                                        isMandatory(
                                                            item.id,
                                                            null,
                                                        )
                                                            ? 'OBLIGATORIO'
                                                            : 'OPCIONAL'
                                                    }}
                                                </button>
                                            </div>
                                        </td>

                                        <!-- Department Checkboxes -->
                                        <td
                                            v-for="dept in departments"
                                            :key="'dept-' + dept.id"
                                            class="px-4 py-3 text-center"
                                        >
                                            <div
                                                class="flex flex-col items-center gap-1"
                                            >
                                                <input
                                                    type="checkbox"
                                                    class="focus:ring-opacity-50 h-4 w-4 cursor-pointer rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200"
                                                    :checked="
                                                        isChecked(
                                                            item.id,
                                                            dept.id,
                                                        )
                                                    "
                                                    @change="
                                                        toggleRequirement(
                                                            item.id,
                                                            dept.id,
                                                        )
                                                    "
                                                    :disabled="
                                                        isChecked(item.id, null)
                                                    "
                                                />
                                                <button type="button" v-if="
                                                        isChecked(
                                                            item.id,
                                                            dept.id,
                                                        ) &&
                                                        !isChecked(
                                                            item.id,
                                                            null,
                                                        )
                                                    "
                                                    @click="
                                                        toggleMandatory(
                                                            item.id,
                                                            dept.id,
                                                        )
                                                    "
                                                    class="mt-1 w-full rounded px-1 text-[10px] font-semibold"
                                                    :class="
                                                        isMandatory(
                                                            item.id,
                                                            dept.id,
                                                        )
                                                            ? 'bg-red-100 text-red-700'
                                                            : 'bg-gray-200 text-gray-600'
                                                    "
                                                >
                                                    {{
                                                        isMandatory(
                                                            item.id,
                                                            dept.id,
                                                        )
                                                            ? 'OBLIGATORIO'
                                                            : 'OPCIONAL'
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
                        class="flex items-center justify-between border-t border-gray-200 bg-gray-50 px-6 py-4"
                    >
                        <div class="text-xs text-gray-500">
                            * Note: Global settings override specific department
                            configurations.
                        </div>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center justify-center rounded-lg border border-transparent bg-green-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 focus:outline-none disabled:opacity-50"
                        >
                            <Save class="mr-2 h-5 w-5" />
                            Save Matrix
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
