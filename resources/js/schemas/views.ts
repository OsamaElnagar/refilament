import type { ComponentType } from 'react';

/**
 * Consumer-registered React components for embedded `View` schema nodes (the
 * embedded-React slice). A server `View::make('stats-card')` node renders
 * whatever component a consumer registered under 'stats-card' here; the key
 * is the same string the PHP builder's `view()` carries. Registered once at
 * the app entry alongside registerDefaultFields()/registerDefaultLayouts().
 */
const viewComponents = new Map<string, ComponentType<Record<string, unknown>>>();

export function registerViewComponent(
    key: string,
    component: ComponentType<Record<string, unknown>>,
): void {
    viewComponents.set(key, component);
}

export function getViewComponent(key: string): ComponentType<Record<string, unknown>> | undefined {
    return viewComponents.get(key);
}
