import { useState } from 'react';

import type { LayoutProps } from '@/schemas/registry';

/**
 * Tabs layout (slice 2.6). Renders a horizontal tab bar; the active tab's
 * schema is the only panel shown. Active tab is pure client state.
 */
export default function TabsLayout({ node, renderChildren }: LayoutProps) {
    const [active, setActive] = useState<number>(node.activeTab ?? 1);
    const tabs = node.schema ?? [];

    return (
        <div>
            {tabs.length > 1 ? (
                <div
                    role="tablist"
                    className="mb-4 flex gap-1 border-b border-border"
                >
                    {tabs.map((tab, index) => {
                        const isActive = index + 1 === active;

                        return (
                            <button
                                key={tab.label ?? index}
                                type="button"
                                role="tab"
                                aria-selected={isActive}
                                onClick={() => setActive(index + 1)}
                                className={`-mb-px rounded-t-md border-b-2 px-4 py-2 text-sm font-medium transition-colors ${
                                    isActive
                                        ? 'border-foreground text-foreground'
                                        : 'border-transparent text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                {tab.label}
                            </button>
                        );
                    })}
                </div>
            ) : null}

            {/* Render only the active tab's children. */}
            {tabs[active - 1] ? renderChildren(tabs[active - 1].schema ?? []) : null}
        </div>
    );
}