import { Info } from 'lucide-react';

/**
 * Demo embedded-view component (the embedded-React slice) — registered under
 * 'playground-callout' and rendered by the playground's `View::make(...)`
 * node. Receives the server-computed `viewData` as props: a consumer's
 * component can be any React; the server just names the key and supplies
 * the data.
 */
export default function PlaygroundCallout(props: Record<string, unknown>) {
    const title = typeof props.title === 'string' ? props.title : 'Embedded view';
    const body = typeof props.body === 'string' ? props.body : '';

    return (
        <div className="flex gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
            <Info className="mt-0.5 size-4 shrink-0" />
            <div>
                <p className="font-semibold">{title}</p>
                {body ? <p className="mt-1 leading-relaxed">{body}</p> : null}
            </div>
        </div>
    );
}
