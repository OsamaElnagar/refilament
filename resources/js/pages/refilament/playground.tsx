import AppShell from '@/components/shell/AppShell';
import { Card } from '@/components/ui/card';
import SchemaRenderer from '@/schemas/SchemaRenderer';
import { CONTRACT_VERSION } from '@/schemas/types';
import type { SchemaDocument } from '@/schemas/types';

export default function Playground(props: SchemaDocument) {
    if (props.contract !== CONTRACT_VERSION) {
        return (
            <AppShell>
                <main className="mx-auto w-full max-w-2xl px-6 py-12">
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
            <main className="mx-auto w-full max-w-2xl py-6">
                <header className="mb-8">
                    <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                        Schema Playground
                    </h1>
                    <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                        A serialized schema document (contract v{props.contract}) rendered by the React
                        runtime — PHP schema builder → JSON → <code>SchemaRenderer</code>.
                    </p>
                </header>

                <Card className="p-6">
                    <SchemaRenderer
                        schema={props.schema}
                        data={props.data}
                        errors={props.errors}
                        schemaId={props.id}
                    />
                </Card>

                <footer className="mt-6 text-center text-xs text-muted-foreground">
                    {props.schema.length} node{props.schema.length === 1 ? '' : 's'} · workbench
                    playground · slice 5
                </footer>
            </main>
        </AppShell>
    );
}
