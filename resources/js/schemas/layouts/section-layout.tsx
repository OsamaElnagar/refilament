import { Card, CardContent, CardDescription, CardHeader } from '@/components/ui/card';
import type { LayoutProps } from '@/schemas/registry';

export default function SectionLayout({ node, renderChildren }: LayoutProps) {
    return (
        <Card>
            {node.heading || node.description ? (
                <CardHeader>
                    {/* Real heading semantics, not the primitive's div — the
                        CardTitle wrapper renders a div, which screen readers
                        (and role-based locators) would not see as a heading. */}
                    {node.heading ? (
                        <h3 className="text-base font-semibold leading-none tracking-tight">{node.heading}</h3>
                    ) : null}

                    {node.description ? <CardDescription className="mt-1">{node.description}</CardDescription> : null}
                </CardHeader>
            ) : null}

            <CardContent className="space-y-6">{renderChildren(node.schema ?? [])}</CardContent>
        </Card>
    );
}
