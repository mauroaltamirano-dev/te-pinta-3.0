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
import { Textarea } from '@/components/ui/textarea';
import type { Customer } from '../types';

interface CustomerFormData {
    name: string;
    phone: string;
    address: string;
    notes: string;
}

export function CustomerFormDialog({
    open,
    onOpenChange,
    customer,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    customer?: Customer;
}) {
    const form = useForm<CustomerFormData>({
        name: customer?.name ?? '',
        phone: customer?.phone ?? '',
        address: customer?.address ?? '',
        notes: customer?.notes ?? '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (customer) {
            form.put(`/admin/customers/${customer.id}`, options);

            return;
        }

        form.post('/admin/customers', options);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="dark admin-shell sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {customer ? 'Edit customer' : 'Create customer'}
                    </DialogTitle>
                    <DialogDescription>
                        Only the name is required. Private notes never appear in
                        public checkout or WhatsApp messages.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <CustomerField
                        id="customer-name"
                        label="Name"
                        value={form.data.name}
                        error={form.errors.name}
                        required
                        autoFocus
                        onChange={(value) => form.setData('name', value)}
                    />
                    <CustomerField
                        id="customer-phone"
                        label="Phone"
                        value={form.data.phone}
                        error={form.errors.phone}
                        inputMode="tel"
                        onChange={(value) => form.setData('phone', value)}
                    />
                    <TextField
                        id="customer-address"
                        label="Address"
                        value={form.data.address}
                        error={form.errors.address}
                        rows={3}
                        onChange={(value) => form.setData('address', value)}
                    />
                    <TextField
                        id="customer-notes"
                        label="Private notes"
                        value={form.data.notes}
                        error={form.errors.notes}
                        rows={4}
                        help="Visible only to administrators."
                        onChange={(value) => form.setData('notes', value)}
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && (
                                <LoaderCircle className="animate-spin" />
                            )}
                            {customer ? 'Save changes' : 'Create customer'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function CustomerField({
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
    required?: boolean;
    autoFocus?: boolean;
    inputMode?: 'tel';
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                aria-invalid={Boolean(error)}
                aria-describedby={error ? `${id}-error` : undefined}
                {...props}
            />
            <FieldError id={`${id}-error`} message={error} />
        </div>
    );
}

function TextField({
    id,
    label,
    value,
    error,
    help,
    rows,
    onChange,
}: {
    id: string;
    label: string;
    value: string;
    error?: string;
    help?: string;
    rows: number;
    onChange: (value: string) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Textarea
                id={id}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                aria-invalid={Boolean(error)}
                aria-describedby={
                    error ? `${id}-error` : help ? `${id}-help` : undefined
                }
                rows={rows}
            />
            {help && (
                <p id={`${id}-help`} className="text-xs text-muted-foreground">
                    {help}
                </p>
            )}
            <FieldError id={`${id}-error`} message={error} />
        </div>
    );
}

function FieldError({ id, message }: { id: string; message?: string }) {
    return message ? (
        <p id={id} role="alert" className="text-sm text-destructive">
            {message}
        </p>
    ) : null;
}
