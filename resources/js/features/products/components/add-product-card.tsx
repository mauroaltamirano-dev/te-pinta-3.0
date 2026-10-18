import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';

export function AddProductCard({ onClick }: { onClick: () => void }) {
    return (
        <Button
            type="button"
            variant="ghost"
            onClick={onClick}
            className="h-full min-h-80 w-full flex-col gap-4 rounded-[0.5rem] border border-dashed border-admin-accent bg-transparent text-admin-accent-text hover:border-admin-accent hover:bg-transparent hover:text-admin-accent-text"
        >
            <span className="flex size-12 items-center justify-center rounded-full border border-admin-accent/30 bg-admin-selection">
                <Plus className="size-6" />
            </span>
            <span className="font-semibold">Agregar producto</span>
            <span className="max-w-40 text-xs leading-relaxed font-normal text-wrap text-admin-text-secondary">
                Creá un producto nuevo en esta categoría
            </span>
        </Button>
    );
}
