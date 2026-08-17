import { router } from '@inertiajs/react';
import { Loader2, SearchIcon } from 'lucide-react';
import { Fragment, useEffect, useRef, useState } from 'react';

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { readCsrfToken } from '@/lib/csrf';
import { panelUrl } from '@/lib/panel';
import { SHELL_SLOTS, ShellSlot } from '@/components/shell/ShellSlots';
import { Icon } from '@/components/icon';
import { renderNotification } from '@/notifications/renderNotification';
import type { NotificationPayload } from '@/notifications/renderNotification';

/**
 * A per-record action on a global search result (slice 3.5). Serialized pure
 * data — never a closure. With a `url` it is plain navigation; without one it
 * runs server-side through the typed action endpoint.
 */
interface SearchAction {
    name: string;
    label: string;
    color?: string;
    requiresConfirmation?: boolean;
    url?: string;
    /** Named icon key (the shared lucide registry), rendered beside the label. */
    icon?: string;
    /** A short hint shown on hover, mirroring Action::tooltip(). */
    tooltip?: string;
}

interface SearchHit {
    title: string;
    url: string;
    details: Record<string, string>;
    /** The resource table id — the {resource} segment of the action endpoint URL. */
    resource?: string;
    /** The record's primary key — sent with the action request. */
    record?: string | number;
    /** Per-record actions, omitted when the resource declares none. */
    actions?: SearchAction[];
}

type Categories = Record<string, SearchHit[]>;

interface SearchResponse {
    query: string;
    categories: Categories;
}

const DEBOUNCE_MS = 300;

/** Render a named action icon through the shared registry (components/icon).
 * Unknown keys render nothing, exactly like cells, badges and notifications. */
function ActionIcon({ name }: { name: string }) {
    return <Icon name={name} className="size-3 shrink-0" />;
}

/**
 * The panel global search command (slice 3.5), mounted in the AppShell header.
 * Typing debounces 300ms then fetches the typed search endpoint; results are
 * grouped by resource category and navigate to each record's page on select.
 * The term never crosses state between requests beyond the debounced query —
 * no persistent server component, just request/response (docs/ARCHITECTURE.md).
 *
 * Cmd/Ctrl+K opens the dialog; Esc closes it.
 */
export default function GlobalSearch() {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [term, setTerm] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [categories, setCategories] = useState<Categories>({});
    const [error, setError] = useState<string | null>(null);
    /** Per-hit action key (`resource:record:action`) while its closure runs. */
    const [runningAction, setRunningAction] = useState<string | null>(null);
    /** An action awaiting confirmation (slice 3.5) before it is sent. */
    const [confirm, setConfirm] = useState<{ hit: SearchHit; action: SearchAction } | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent): void => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                setOpen(true);
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);

    useEffect(() => {
        if (!open) {
            return;
        }

        const timer = window.setTimeout(() => {
            setTerm(query);
        }, DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
    }, [open, query]);

    useEffect(() => {
        if (term.trim() === '') {
            setCategories({});
            setIsLoading(false);
            setError(null);

            return;
        }

        const controller = new AbortController();

        async function fetchResults(): Promise<void> {
            setIsLoading(true);
            setError(null);

            try {
                const params = new URLSearchParams({ q: term });
                const response = await fetch(`${panelUrl('/search')}?${params.toString()}`, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error(`Search failed (${response.status})`);
                }

                const payload = (await response.json()) as SearchResponse;
                setCategories(payload.categories);
            } catch (caught) {
                if (controller.signal.aborted) {
                    return;
                }

                setError('Search failed — please try again.');
            } finally {
                if (!controller.signal.aborted) {
                    setIsLoading(false);
                }
            }
        }

        void fetchResults();

        return () => controller.abort();
    }, [term]);

    const hasResults = Object.keys(categories).length > 0;
    const isEmpty = !isLoading && !hasResults && term.trim() !== '';

    /**
     * Run a per-hit result action (slice 3.5). An action carrying a `url` is
     * plain navigation — close the dialog and visit, the honest
     * request/response model. A closure action runs through the typed endpoint
     * (POST /refilament/search/{resource}/action/{action} with the record key):
     * the server rebuilds the action from the resource, re-checks visibility
     * and the per-record authorization gate, then calls the closure. The
     * response's notification/message surfaces as a sonner toast; failures
     * (422 domain errors, network errors) toast their message.
     */
    const runAction = async (hit: SearchHit, action: SearchAction): Promise<void> => {
        if (action.url) {
            setOpen(false);
            setQuery('');
            router.visit(action.url);

            return;
        }

        if (!hit.resource || hit.record === undefined) {
            return;
        }

        const actionKey = `${hit.resource}:${String(hit.record)}:${action.name}`;

        setRunningAction(actionKey);

        try {
            const headers: Record<string, string> = {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            };

            const csrfToken = readCsrfToken();

            if (csrfToken) {
                headers['X-CSRF-TOKEN'] = csrfToken;
            }

            const response = await fetch(panelUrl(`/search/${hit.resource}/action/${action.name}`), {
                method: 'POST',
                headers,
                body: JSON.stringify({ record: hit.record }),
            });

            const payload = (await response.json().catch(() => null)) as {
                message?: string;
                notification?: NotificationPayload;
                errors?: Record<string, string[]>;
            } | null;

            if (!response.ok) {
                const message = payload?.errors?.action?.[0] ?? payload?.message ?? 'Action failed.';

                renderNotification({ title: message, status: 'danger' });

                return;
            }

            if (payload?.notification) {
                renderNotification(payload.notification);
            } else if (payload?.message) {
                renderNotification({ title: payload.message });
            }
        } catch {
            renderNotification({ title: 'Network error — please try again.', status: 'danger' });
        } finally {
            setRunningAction(null);
        }
    };

    return (
        <div className="flex items-center">
            <ShellSlot name={SHELL_SLOTS.globalSearchStart} />
            <Button
                variant="outline"
                className="h-9 w-56 justify-start gap-2 text-muted-foreground"
                onClick={() => setOpen(true)}
            >
                <SearchIcon className="size-4" />
                Search…
                <kbd className="pointer-events-none ml-auto hidden select-none items-center gap-1 rounded border bg-muted px-1.5 font-mono text-[10px] font-medium sm:flex">
                    ⌘K
                </kbd>
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader className="sr-only">
                        <DialogTitle>Global search</DialogTitle>
                        <DialogDescription>Search across the panel's records.</DialogDescription>
                    </DialogHeader>

                    <div className="relative">
                        <SearchIcon className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            ref={inputRef}
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Type to search…"
                            className="h-11 pl-9"
                            autoFocus
                        />
                    </div>

                    <div className="max-h-72 overflow-y-auto">
                        {error ? (
                            <p className="rounded-lg border border-border bg-muted/40 p-4 text-sm text-muted-foreground">
                                {error}
                            </p>
                        ) : null}

                        {isLoading ? (
                            <div className="space-y-2" aria-label="Loading results">
                                <Skeleton className="h-4 w-24" />
                                <Skeleton className="h-9 w-full" />
                                <Skeleton className="h-9 w-full" />
                            </div>
                        ) : null}

                        {isEmpty ? (
                            <p className="rounded-lg border border-dashed border-border p-4 text-sm text-muted-foreground">
                                No results for “{term}”.
                            </p>
                        ) : null}

                        {!isLoading && !isEmpty ? (
                            Object.entries(categories).map(([category, hits]) => (
                                <section key={category} className="mb-3 last:mb-0">
                                    <h3 className="mb-1 px-1 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        {category}
                                    </h3>

                                    <ul className="space-y-1">
                                        {hits.map((hit, index) => (
                                            <li key={`${category}-${index}`}>
                                                <div className="group flex items-center gap-1 rounded-md px-2 py-1.5 transition-colors hover:bg-muted">
                                                    <button
                                                        type="button"
                                                        className="flex min-w-0 flex-1 items-center justify-between gap-3 py-0.5 text-left"
                                                        onClick={() => {
                                                            setOpen(false);
                                                            setQuery('');
                                                            router.visit(hit.url);
                                                        }}
                                                    >
                                                        <span className="truncate text-sm font-medium">{hit.title}</span>
                                                        {Object.keys(hit.details).length > 0 ? (
                                                            <span className="flex gap-2">
                                                                {Object.entries(hit.details).map(([label, value]) => (
                                                                    <span key={label} className="text-xs text-muted-foreground">
                                                                        {label}: {value}
                                                                    </span>
                                                                ))}
                                                            </span>
                                                        ) : null}
                                                    </button>

                                                    {(hit.actions ?? []).length > 0 ? (
                                                        <span className="flex shrink-0 items-center gap-1">
                                                            {hit.actions?.map((action) => {
                                                                const actionKey = `${hit.resource}:${String(hit.record)}:${action.name}`;
                                                                const isRunning = runningAction === actionKey;

                                                                const actionButton = (
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        className="h-7 gap-1 px-2 text-xs text-muted-foreground hover:text-foreground"
                                                                        disabled={isRunning}
                                                                        onClick={() => {
                                                                            // A `requiresConfirmation` action (slice 3.5) pauses at a
                                                                            // confirm dialog before the request is sent, mirroring
                                                                            // table actions.
                                                                            if (action.requiresConfirmation) {
                                                                                setConfirm({ hit, action });
                                                                            } else {
                                                                                void runAction(hit, action);
                                                                            }
                                                                        }}
                                                                    >
                                                                        {isRunning ? (
                                                                            <Loader2 className="size-3 animate-spin" aria-hidden="true" />
                                                                        ) : action.icon ? (
                                                                            <ActionIcon name={action.icon} />
                                                                        ) : null}
                                                                        {action.label}
                                                                    </Button>
                                                                );

                                                                // A `tooltip` (slice 3.5) wraps the button in the shared Tooltip
                                                                // primitive — hover shows the hint, mirroring table-side hints.
                                                                return action.tooltip ? (
                                                                    <Tooltip key={action.name}>
                                                                        <TooltipTrigger render={actionButton} />
                                                                        <TooltipContent>{action.tooltip}</TooltipContent>
                                                                    </Tooltip>
                                                                ) : (
                                                                    <Fragment key={action.name}>{actionButton}</Fragment>
                                                                );
                                                            })}
                                                        </span>
                                                    ) : null}
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            ))
                        ) : null}
                    </div>
                </DialogContent>
            </Dialog>

            <AlertDialog
                open={confirm !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setConfirm(null);
                    }
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Confirm {confirm?.action.label ?? 'action'}</AlertDialogTitle>
                        <AlertDialogDescription>
                            This runs the “{confirm?.action.label}” action on “{confirm?.hit.title}”. This action
                            cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            disabled={runningAction !== null}
                            onClick={() => {
                                if (confirm) {
                                    const { hit, action } = confirm;
                                    setConfirm(null);
                                    void runAction(hit, action);
                                }
                            }}
                            className={cn(
                                confirm?.action.color === 'danger' &&
                                    'bg-destructive text-destructive-foreground hover:bg-destructive/90',
                            )}
                        >
                            {confirm?.action.label ?? 'Confirm'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
            <ShellSlot name={SHELL_SLOTS.globalSearchEnd} />
        </div>
    );
}