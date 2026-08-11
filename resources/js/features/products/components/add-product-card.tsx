import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';

export function AddProductCard({ onClick }: { onClick: () => void }) {
    return (
        <Button
            type="button"
            variant="ghost"
            onClick={onClick}
            className="group h-full min-h-80 w-full flex-col gap-4 rounded-xl border border-dashed border-primary/55 bg-transparent text-primary hover:border-primary hover:bg-primary/7 hover:text-primary"
        >
            <span className="flex size-12 items-center justify-center rounded-full border border-primary/30 bg-primary/10 transition-transform group-hover:scale-105">
                <Plus className="size-6" />
            </span>
            <span className="font-semibold">Add product</span>
            <span className="max-w-40 text-xs leading-relaxed font-normal text-wrap text-muted-foreground">
                Create a new catalog item in this category
            </span>
        </Button>
    );
}
