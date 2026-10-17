import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Role } from '@/types/auth';
import type { RoleOption } from '../types';

interface MemberFormData {
    name: string;
    email: string;
    password: string;
    role: Role;
}

export function MemberFormDialog({
    open,
    onOpenChange,
    roles,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    roles: RoleOption[];
}) {
    const form = useForm<MemberFormData>({
        name: '',
        email: '',
        password: '',
        role: 'waiter',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/admin/users', {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="dark admin-shell sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Crear usuario</DialogTitle>
                    <DialogDescription>
                        Compartí la contraseña temporal con la persona por un
                        canal privado. El rol define qué puede ver y modificar.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <Field
                        id="member-name"
                        label="Nombre"
                        value={form.data.name}
                        error={form.errors.name}
                        autoFocus
                        onChange={(value) => form.setData('name', value)}
                    />
                    <Field
                        id="member-email"
                        label="Email"
                        type="email"
                        value={form.data.email}
                        error={form.errors.email}
                        onChange={(value) => form.setData('email', value)}
                    />
                    <Field
                        id="member-password"
                        label="Contraseña temporal"
                        type="password"
                        autoComplete="new-password"
                        value={form.data.password}
                        error={form.errors.password}
                        onChange={(value) => form.setData('password', value)}
                    />
                    <div className="grid gap-2">
                        <Label htmlFor="member-role">Rol</Label>
                        <Select
                            value={form.data.role}
                            onValueChange={(value) =>
                                form.setData('role', value as Role)
                            }
                        >
                            <SelectTrigger
                                id="member-role"
                                aria-invalid={Boolean(form.errors.role)}
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
                        <FieldError message={form.errors.role} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && (
                                <LoaderCircle className="animate-spin" />
                            )}
                            Crear usuario
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Field({
    id,
    label,
    value,
    error,
    onChange,
    ...props
}: {
    id: string;
    label: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
    type?: 'email' | 'password';
    autoComplete?: string;
    autoFocus?: boolean;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                value={value}
                required
                onChange={(event) => onChange(event.target.value)}
                aria-invalid={Boolean(error)}
                {...props}
            />
            <FieldError message={error} />
        </div>
    );
}

function FieldError({ message }: { message?: string }) {
    return message ? (
        <p role="alert" className="text-sm text-destructive">
            {message}
        </p>
    ) : null;
}
