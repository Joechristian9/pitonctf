import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/** A native dropdown styled like the inputs: dependable inside cards and dialogs. options: [{ value, label }]. */
export default function FilterSelect({
    id,
    label,
    value,
    onChange,
    options,
    className,
}) {
    return (
        <div className={className}>
            <Label htmlFor={id} className="sr-only">
                {label}
            </Label>
            <select
                id={id}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className={cn(
                    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none',
                    'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                )}
            >
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </div>
    );
}
