export type FolderTreeNode = {
    id: number | string;
    name: string;
    children?: FolderTreeNode[];
    is_virtual?: boolean;
    parent_id?: number | null;
    readable_url?: string | null;
};

export const INDIVIDUAL_PROJECT_FOLDER_PATHS: Record<string, string[]> = {
    CAPACITACION: ['4.PROYECTOS INDIVIDUALES', '4.1-CAPACITACION'],
    ASESORIAS_DOCENTES: ['4.PROYECTOS INDIVIDUALES', '4.3-PROYECTOS DOCENTES'],
    MATERIAL_DIDACTICO: ['4.PROYECTOS INDIVIDUALES', '4.4-MATERIAL DIDACTICO'],
};

export function findFolderPathById(
    nodes: FolderTreeNode[],
    folderId: number | string | null,
): FolderTreeNode[] | null {
    if (folderId == null) {
        return null;
    }

    for (const node of nodes) {
        if (!node.is_virtual && String(node.id) === String(folderId)) {
            return [node];
        }

        const children = node.children ?? [];
        const childPath = findFolderPathById(children, folderId);
        if (childPath) {
            return [node, ...childPath];
        }
    }

    return null;
}

export function findFolderByPathSegments(
    nodes: FolderTreeNode[],
    segments: string[],
): FolderTreeNode | null {
    if (segments.length === 0) {
        return null;
    }

    for (const node of nodes) {
        const children = node.children ?? [];

        if (node.name === segments[0]) {
            if (segments.length === 1 && !node.is_virtual) {
                return node;
            }

            const nestedMatch = findFolderByPathSegments(
                children,
                segments.slice(1),
            );

            if (nestedMatch) {
                return nestedMatch;
            }
        }

        const descendantMatch = findFolderByPathSegments(children, segments);
        if (descendantMatch) {
            return descendantMatch;
        }
    }

    return null;
}

export function folderPathLabel(path: FolderTreeNode[] | null): string {
    if (!path || path.length === 0) {
        return '';
    }

    return path
        .filter((node) => !node.is_virtual)
        .map((node) => node.name)
        .join(' / ');
}

export function findFirstFolderId(
    nodes: FolderTreeNode[],
): number | string | null {
    for (const node of nodes) {
        if (!node.is_virtual) {
            return node.id;
        }

        const childId = findFirstFolderId(node.children ?? []);
        if (childId != null) {
            return childId;
        }
    }

    return null;
}
