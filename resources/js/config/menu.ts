import {
    LayoutGrid,
    UploadCloud,
    FileCheck,
    CalendarClock,
    Users,
    FileText,
    MessageCircle,
    FolderOpen, // Import FolderOpen icon
    BookOpen,
    Briefcase,
    Settings,
    Building2,
} from 'lucide-vue-next';
import { RoleName } from '@/types/enums';
import type { NavItem } from '@/types';

export const getNavItemsByRole = (roleName?: string): NavItem[] => {
    const common: NavItem[] = [
        {
            title: 'Dashboard',
            href: '/dashboard',
            icon: LayoutGrid,
        },
        {
            title: 'File Manager',
            href: '/files/manager',
            icon: FolderOpen,
        },
        {
            title: 'Asesorías (General)',
            href: '/asesorias',
            icon: MessageCircle,
        },
        {
            title: 'Asesorías 2',
            href: '/asesorias2',
            icon: MessageCircle,
        },
    ];

    if (!roleName) return common;

    switch (roleName) {
        case RoleName.DOCENTE:
            return [
                ...common,
                {
                    title: 'Mi Panel de Control',
                    href: '/docente/dashboard',
                    icon: BookOpen,
                },
                {
                    title: 'Mis Asesorías',
                    href: '/docente/asesorias',
                    icon: Users,
                },
                {
                    title: 'Mis Evidencias',
                    href: '/docente/evidencias',
                    icon: UploadCloud,
                },
            ];

        case RoleName.JEFE_OFICINA:
            return [
                ...common,
                {
                    title: 'Pendientes Revisión',
                    href: '/oficina/revisiones',
                    icon: FileCheck,
                },
                {
                    title: 'Reportes Docentes',
                    href: '/oficina/reportes',
                    icon: FileText,
                },
                {
                    title: 'Directorio Docentes',
                    href: '/admin/teachers',
                    icon: Users,
                },
                {
                    title: 'Departamentos',
                    href: '/admin/departments',
                    icon: Building2,
                },
                {
                    title: 'Cargas Académicas',
                    href: '/admin/teaching-loads',
                    icon: Briefcase,
                },
                {
                    title: 'Auditoría',
                    href: '/admin/audits',
                    icon: FileText,
                },
            ];

        case RoleName.JEFE_DEPTO:
            return [
                ...common,
                {
                    title: 'Ventanas de Entrega',
                    href: '/admin/windows',
                    icon: CalendarClock,
                },
                {
                    title: 'Configuración Semestre',
                    href: '/admin/semesters',
                    icon: BookOpen,
                },
                {
                    title: 'Matriz de Evidencias',
                    href: '/admin/requirements',
                    icon: Settings,
                },
                {
                    title: 'Directorio Docentes',
                    href: '/admin/teachers',
                    icon: Users,
                },
                {
                    title: 'Departamentos',
                    href: '/admin/departments',
                    icon: Building2,
                },
                {
                    title: 'Cargas Académicas',
                    href: '/admin/teaching-loads',
                    icon: Briefcase,
                },
            ];

        default:
            return common;
    }
};
