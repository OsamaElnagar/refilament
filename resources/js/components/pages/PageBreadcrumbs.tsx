import { router } from '@inertiajs/react';
import type { MouseEvent } from 'react';

import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';

/**
 * One serialized breadcrumb crumb (slice 1.11 — docs/CONTRACT.md, "Pages").
 * `label` is always present; `url` is omitted for the current page (the last
 * crumb is never a link, mirroring Filament, where the final entry gets
 * aria-current="page").
 */
export interface PageBreadcrumb {
    label: string;
    url?: string;
}

/**
 * Renders a page's serialized breadcrumbs (slice 1.11) above the page title —
 * the React analogue of Filament's `<x-filament::breadcrumbs>` header slot.
 * Crumb links navigate through Inertia (router.visit, same as PageActions);
 * the final crumb renders as the current page (aria-current, not a link).
 * Renders nothing when the array is empty (the server omits the key).
 */
export default function PageBreadcrumbs({ breadcrumbs }: { breadcrumbs: PageBreadcrumb[] }) {
    if (breadcrumbs.length === 0) {
        return null;
    }

    const onCrumbClick = (url: string) => (event: MouseEvent<HTMLAnchorElement>) => {
        event.preventDefault();

        router.visit(url);
    };

    return (
        <Breadcrumb className="mb-2">
            <BreadcrumbList>
                {breadcrumbs.map((crumb, index) => {
                    const isLast = index === breadcrumbs.length - 1;

                    return (
                        <BreadcrumbItem key={`${crumb.label}-${index}`}>
                            {index > 0 ? <BreadcrumbSeparator /> : null}
                            {isLast || crumb.url === undefined ? (
                                <BreadcrumbPage>{crumb.label}</BreadcrumbPage>
                            ) : (
                                <BreadcrumbLink href={crumb.url} onClick={onCrumbClick(crumb.url)}>
                                    {crumb.label}
                                </BreadcrumbLink>
                            )}
                        </BreadcrumbItem>
                    );
                })}
            </BreadcrumbList>
        </Breadcrumb>
    );
}
