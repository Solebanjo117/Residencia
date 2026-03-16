import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { RoleName } from '@/types/enums';
import type { User } from '@/types/models';

export function useAuth() {
    const page = usePage();
    const user = computed(() => page.props.auth.user as unknown as User);

    const isDocente = computed(
        () => user.value?.role?.name === RoleName.DOCENTE,
    );
    const isJefeOficina = computed(
        () => user.value?.role?.name === RoleName.JEFE_OFICINA,
    );
    const isJefeDepto = computed(
        () => user.value?.role?.name === RoleName.JEFE_DEPTO,
    );

    const hasRole = (roleName: RoleName) => user.value?.role?.name === roleName;

    return {
        user,
        isDocente,
        isJefeOficina,
        isJefeDepto,
        hasRole,
    };
}
