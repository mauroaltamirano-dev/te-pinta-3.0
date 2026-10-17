import type { Role } from '@/types/auth';

export interface Member {
    id: number;
    name: string;
    email: string;
    role: Role;
    role_label: string;
    is_active: boolean;
}

export interface RoleOption {
    value: Role;
    label: string;
}
