import FieldsetLayout from '@/schemas/layouts/fieldset-layout';
import GridLayout from '@/schemas/layouts/grid-layout';
import SectionLayout from '@/schemas/layouts/section-layout';
import TabsLayout from '@/schemas/layouts/tabs-layout';
import { registerLayout } from '@/schemas/registry';

/**
 * Register the default layout types into the renderer registry. Called once
 * from the app entry point, alongside `registerDefaultFields()`.
 */
export function registerDefaultLayouts(): void {
    registerLayout('grid', GridLayout);
    registerLayout('section', SectionLayout);
    registerLayout('fieldset', FieldsetLayout);
    registerLayout('tabs', TabsLayout);
}
