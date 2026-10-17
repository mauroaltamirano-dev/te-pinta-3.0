import { router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Member, RoleOption } from '../types';

export function MemberList({
    members,
    roles,
    currentUserId,
}: {
    members: Member[];
    roles: RoleOption[];
    currentUserId: number;
}) {
    const options = { preserveScroll: true };
    const changeRole = (member: Member, role: string) =>
        router.patch(`/admin/users/${member.id}/role`, { role }, options);
    const toggleActive = (member: Member) =>
        router.post(
            `/admin/users/${member.id}/${member.is_active ? 'deactivate' : 'activate'}`,
            {},
            options,
        );

    return (
        <ul className="divide-y divide-admin-border">
            {members.map((member) => {
                const isSelf = member.id === currentUserId;

                return (
                    <li
                        key={member.id}
                        className="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5"
                    >
                        <div className="min-w-0">
                            <p className="flex items-center gap-2 font-medium text-white">
                                <span className="truncate">{member.name}</span>
                                {isSelf && <Badge variant="outline">Vos</Badge>}
                                {!member.is_active && (
                                    <Badge variant="secondary">Inactivo</Badge>
                                )}
                            </p>
                            <p className="truncate text-sm text-admin-text-tertiary">
                                {member.email}
                            </p>
                        </div>
                        <div className="grid grid-cols-2 gap-2 sm:flex sm:items-center">
                            <Select
                                value={member.role}
                                disabled={isSelf}
                                onValueChange={(role) =>
                                    changeRole(member, role)
                                }
                            >
                                <SelectTrigger
                                    aria-label={`Rol de ${member.name}`}
                                    className="h-10 border-admin-border bg-admin-canvas sm:w-40"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {roles.map((role) => (
                                        <SelectItem
                                            key={role.value}
                                            value={role.value}
                                        >
                                            {role.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={isSelf}
                                onClick={() => toggleActive(member)}
                                className="h-10"
                            >
                                {member.is_active ? 'Desactivar' : 'Reactivar'}
                            </Button>
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}
