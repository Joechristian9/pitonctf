import { Search } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/** A search box with an icon. The label is for screen readers; the placeholder repeats it on screen. */
export default function SearchInput({
    id,
    label,
    placeholder = label,
    className,
    ...props
}) {
    return (
        <div className={cn('relative', className)}>
            <Label htmlFor={id} className="sr-only">
                {label}
            </Label>
            <Search
                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />
            <Input
                id={id}
                type="search"
                autoComplete="off"
                placeholder={placeholder}
                className="pl-9"
                {...props}
            />
        </div>
    );
}
