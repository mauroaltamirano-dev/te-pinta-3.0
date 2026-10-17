import { Head, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { MemberFormDialog } from '@/features/users/components/member-form-dialog';
import { MemberList } from '@/features/users/components/member-list';
import type { Member, RoleOption } from '@/features/users/types';

export default function Users({
    members,
    roles,
    currentUserId,
}: {
    members: Member[];
    roles: RoleOption[];
    currentUserId: number;
}) {
    const [formOpen, setFormOpen] = useState(false);
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const actionError = errors.role ?? errors.membership;

    return (
        <>
            <Head title="Usuarios" />
            <section className="grid gap-6 font-orders">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm font-medium text-admin-accent">
                            Equipo del restaurante
                        </p>
                        <h1 className="mt-1 text-3xl font-semibold tracking-tight text-white">
                            Usuarios
                        </h1>
                        <p className="mt-1 text-sm text-admin-text-tertiary">
                            Creá accesos para tu equipo y elegí qué puede ver o
                            modificar cada rol.
                        </p>
                    </div>
                    <Button
                        type="button"
                        onClick={() => setFormOpen(true)}
                        className="h-11 rounded-lg px-4"
                    >
                        <Plus /> Crear usuario
                    </Button>
                </header>

                {actionError && !formOpen && (
                    <Alert variant="destructive">
                        <AlertDescription>{actionError}</AlertDescription>
                    </Alert>
                )}

                <div className="overflow-hidden rounded-lg bg-admin-surface">
                    <p
                        className="border-b border-admin-border px-4 py-3 text-sm text-admin-text-tertiary sm:px-5"
                        role="status"
                    >
                        {members.length}{' '}
                        {members.length === 1 ? 'usuario' : 'usuarios'}
                    </p>
                    <MemberList
                        members={members}
                        roles={roles}
                        currentUserId={currentUserId}
                    />
                </div>
            </section>
            {formOpen && (
                <MemberFormDialog
                    open={formOpen}
                    onOpenChange={setFormOpen}
                    roles={roles}
                />
            )}
        </>
    );
}
