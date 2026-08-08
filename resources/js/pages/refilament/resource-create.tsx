import { router } from '@inertiajs/react';

import { Card } from '@/components/ui/card';
import AppShell from '@/components/shell/AppShell';
import SchemaRenderer from '@/schemas/SchemaRenderer';
import { CONTRACT_VERSION } from '@/schemas/types';
import type { SchemaDocument } from '@/schemas/types';

interface ResourceCreateProps extends SchemaDocument {
    /** The resource's table id — the list route to return to on success. */
    resource: string;
    /** Display title derived from the resource's model (e.g. "User"). */
    resourceTitle: string;
}

/**
 * The generic page behind every auto-registered create route — the package
 * serves GET /refilament/{resource}/create for each discovered resource, so
 * no per-resource create page component is needed.
 */
export default function ResourceCreate(props: ResourceCreateProps) {
    if (props.contract !== CONTRACT_VERSION) {
        return (
            <AppShell>
                <main className="mx-auto w-full max-w-2xl">
                    <div className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        Unsupported contract version <code>{props.contract}</code> — expected{' '}
                        <code>{CONTRACT_VERSION}</code>.
                    </div>
                </main>
            </AppShell>
        );
    }

    return (
        <AppShell>
            <main className="mx-auto w-full max-w-2xl">
                <header className="mb-8">
                    <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                        Create {props.resourceTitle}
                    </h1>
                    <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                        An auto-registered create page — the package serves{' '}
                        <code>/refilament/{props.resource}/create</code> for every discovered
                        resource, no app-side route or page component needed.
                    </p>
                </header>

                <Card className="p-6">
                    <SchemaRenderer
                        schema={props.schema}
                        data={props.data}
                        errors={props.errors}
                        schemaId={props.id}
                        submitLabel={`Create ${props.resourceTitle}`}
                        operation="create"
                        onSuccess={() => router.visit(`/refilament/${props.resource}`)}
                    />
                </Card>

                <footer className="mt-6 text-center text-xs text-muted-foreground">
                    contract v{props.contract} · auto-registered create page
                </footer>
            </main>
        </AppShell>
    );
}
