import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type { TableFilter } from '@/tables/types';

/**
 * The filter-form state accessors the table owns (server-side filtering). The
 * panel is presentational: it renders the registered filter controls and lets
 * the table's live column-filter state drive them.
 */
export interface FilterAccessors {
    /** The current value of a filter (single value or array for multiple). */
    filterValue: (filter: TableFilter) => string | string[];
    /** Replace a filter's value ('' clears it). */
    setFilterValue: (filter: TableFilter, value: string | string[]) => void;
    /** Live (undebounced) text-filter inputs, keyed by filter name. */
    textInputs: Record<string, string>;
    /** Update one text-filter's live input value. */
    setTextInput: (name: string, value: string) => void;
}

interface FilterPanelProps {
    filters: TableFilter[];
    accessors: FilterAccessors;
    /** The number of currently active filters — shows the panel's Reset action. */
    activeCount: number;
    /** Clear every filter and the search term. */
    onReset: () => void;
    /**
     * Responsive grid (above/below content layouts); otherwise a single column
     * (dropdown / modal / side-column layouts).
     */
    grid?: boolean;
    className?: string;
}

/** The single-select control's styling (shared by select and trashed filters). */
const selectClasses =
    'h-9 rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-xs outline-none transition focus-visible:ring-2 focus-visible:ring-ring/50';

/**
 * The table's filter form (slice: filters layout). Renders the registered
 * filters as labelled form fields — free-text, multi-select menu (multiple
 * select) and single-select — plus a Reset action that appears once any filter
 * is active. The host (TableRenderer) places it according to the table's
 * FiltersLayout: above/below the content in a responsive grid, or in a
 * dropdown / modal / side column in a single column.
 */
export function FilterPanel({ filters, accessors, activeCount, onReset, grid, className }: FilterPanelProps) {
    return (
        <div className={cn('flex flex-col gap-4', className)}>
            <div className="flex items-center justify-between gap-3">
                <span className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                    Filters
                </span>

                {activeCount > 0 ? (
                    <button
                        type="button"
                        onClick={onReset}
                        className="text-xs font-medium text-muted-foreground transition hover:text-destructive"
                    >
                        Reset
                    </button>
                ) : null}
            </div>

            <div className={cn(grid && 'grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4')}>
                {filters.map((filter) => (
                    <FilterControl key={filter.name} filter={filter} accessors={accessors} />
                ))}
            </div>
        </div>
    );
}

function FilterControl({ filter, accessors }: { filter: TableFilter; accessors: FilterAccessors }) {
    if (filter.type === 'text') {
        return (
            <div className="flex flex-col gap-1.5">
                <Label htmlFor={`table-filter-${filter.name}`} className="text-xs font-medium text-foreground">
                    {filter.label}
                </Label>
                <Input
                    id={`table-filter-${filter.name}`}
                    type="search"
                    value={accessors.textInputs[filter.name] ?? ''}
                    onChange={(event) => accessors.setTextInput(filter.name, event.target.value)}
                    placeholder={filter.placeholder ?? `Filter by ${filter.label.toLowerCase()}…`}
                    className="h-9"
                />
            </div>
        );
    }

    if (filter.type === 'select' && filter.multiple) {
        const optionLabels = new Map(filter.options.map((option) => [option.value, option.label]));
        const selected = accessors.filterValue(filter);
        const values = Array.isArray(selected) ? selected : selected ? [selected] : [];

        return (
            <div className="flex flex-col gap-1.5">
                <Label htmlFor={`table-filter-${filter.name}`} className="text-xs font-medium text-foreground">
                    {filter.label}
                </Label>
                <Select
                    multiple
                    modal={false}
                    value={values}
                    onValueChange={(next) => accessors.setFilterValue(filter, next)}
                >
                    <SelectTrigger id={`table-filter-${filter.name}`} className="w-full justify-between">
                        {values.length === 0 ? (
                            <span className="truncate text-muted-foreground">
                                {filter.placeholder ?? `Select ${filter.label.toLowerCase()}`}
                            </span>
                        ) : (
                            <span className="flex flex-wrap items-center gap-1">
                                {values.map((value) => (
                                    <span
                                        key={value}
                                        className="rounded-md bg-secondary px-1.5 py-0.5 text-xs font-medium text-secondary-foreground"
                                    >
                                        {optionLabels.get(value) ?? value}
                                    </span>
                                ))}
                            </span>
                        )}
                    </SelectTrigger>
                    <SelectContent alignItemWithTrigger={false}>
                        {filter.options.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-1.5">
            <Label htmlFor={`table-filter-${filter.name}`} className="text-xs font-medium text-foreground">
                {filter.label}
            </Label>
            <select
                id={`table-filter-${filter.name}`}
                value={accessors.filterValue(filter) as string}
                onChange={(event) => accessors.setFilterValue(filter, event.target.value)}
                className={selectClasses}
            >
                {filter.options.some((option) => option.value === '') ? null : (
                    <option value="">All</option>
                )}
                {filter.options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </div>
    );
}