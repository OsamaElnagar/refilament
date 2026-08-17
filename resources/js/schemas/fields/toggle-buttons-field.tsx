import { cn } from '@/lib/utils';
import { Icon } from '@/components/icon';
import FieldHeader from '@/schemas/field-header';
import type { FieldProps } from '@/schemas/registry';

// Tailwind v4 scans for literal class names, so the per-color selected styles
// live in a lookup map (the PHP builder emits the same color names as
// Filament). Unknown colors fall back to primary.
const SELECTED_STYLES: Record<string, string> = {
    primary: 'bg-primary text-primary-foreground',
    success: 'bg-emerald-600 text-white',
    danger: 'bg-destructive text-white',
    warning: 'bg-amber-500 text-white',
    info: 'bg-sky-600 text-white',
    gray: 'bg-secondary text-secondary-foreground',
};

/**
 * Toggle buttons field — a segmented button group select. Each option renders
 * as a button (radio behaviour by default, checkbox-style when `multiple`).
 * `inline` lays them in a single row, `grouped` joins them into one control,
 * `hiddenButtonLabels` shows icons only. Per-option icons/colors/tooltips come
 * from value-keyed maps.
 */
export default function ToggleButtonsField({ node, value, error, onChange, formValues }: FieldProps) {
    const options = node.options ?? [];
    const multiple = node.multiple ?? false;
    const hiddenButtonLabels = node.hiddenButtonLabels ?? false;
    const disabled = node.disabled ?? node.readOnly ?? false;
    const icons = node.icons ?? {};
    const colors = node.colors ?? {};
    const tooltips = node.tooltips ?? {};

    const selectedRaw = value ?? node.default;
    const selectedValues = multiple
        ? new Set((Array.isArray(selectedRaw) ? selectedRaw : []).map(String))
        : new Set(selectedRaw == null ? [] : [String(selectedRaw)]);

    const toggleOption = (optionValue: string) => {
        if (multiple) {
            const next = new Set(selectedValues);

            if (next.has(optionValue)) {
                next.delete(optionValue);
            } else {
                next.add(optionValue);
            }

            onChange?.([...next]);
        } else {
            onChange?.(optionValue);
        }
    };

    const baseButton =
        'inline-flex items-center justify-center gap-1.5 text-sm font-medium transition-colors disabled:pointer-events-none disabled:opacity-50';

    const rowClass = node.grouped
        ? 'inline-flex overflow-hidden rounded-md border border-input shadow-xs'
        : 'flex flex-wrap gap-1.5';

    const groupBtnClass = (isSelected: boolean, color: string) =>
        cn(
            node.grouped
                ? 'border-r border-input last:border-r-0 px-3 py-2'
                : 'rounded-md border border-input px-3 py-2 shadow-xs',
            isSelected ? (SELECTED_STYLES[color] ?? SELECTED_STYLES.primary) : 'bg-background hover:bg-accent',
        );

    return (
        <div>
            <FieldHeader node={node} formValues={formValues} labelId={node.name} />

            <div
                role={multiple ? 'group' : 'radiogroup'}
                aria-invalid={error ? true : undefined}
                className={cn('w-fit', !node.inline && 'w-full')}
            >
                <div className={rowClass}>
                    {options.map((option) => {
                        const isSelected = selectedValues.has(option.value);
                        const color = colors[option.value] ?? 'primary';
                        const icon = icons[option.value];
                        const tooltip = tooltips[option.value];

                        return (
                            <button
                                key={option.value}
                                type="button"
                                role={multiple ? 'checkbox' : 'radio'}
                                aria-checked={isSelected}
                                title={tooltip}
                                disabled={disabled}
                                onClick={() => toggleOption(option.value)}
                                className={cn(baseButton, groupBtnClass(isSelected, color))}
                            >
                                {icon ? <Icon name={icon} size="sm" /> : null}
                                {!hiddenButtonLabels ? option.label : null}
                            </button>
                        );
                    })}
                </div>
            </div>

            {error ? <p className="mt-0.5 text-xs text-destructive">{error}</p> : null}
        </div>
    );
}