import { CodeEntry } from '@/infolists/entries/code-entry';
import { ColorEntry } from '@/infolists/entries/color-entry';
import { IconEntry } from '@/infolists/entries/icon-entry';
import { ImageEntry } from '@/infolists/entries/image-entry';
import { KeyValueEntry } from '@/infolists/entries/key-value-entry';
import { RepeatableEntry } from '@/infolists/entries/repeatable-entry';
import { TextEntry } from '@/infolists/entries/text-entry';
import { ViewEntry } from '@/infolists/entries/view-entry';
import { registerEntry } from '@/infolists/registry';

/**
 * Register the built-in infolist entry types (slice 3.3, extended 3.9).
 * Consumers and plugins can add their own read-only entry types via
 * registerEntry().
 */
export function registerDefaultEntries(): void {
    registerEntry('text_entry', TextEntry);
    registerEntry('icon_entry', IconEntry);
    registerEntry('color_entry', ColorEntry);
    registerEntry('image_entry', ImageEntry);
    registerEntry('key_value_entry', KeyValueEntry);
    registerEntry('repeatable_entry', RepeatableEntry);
    registerEntry('code_entry', CodeEntry);
    registerEntry('view_entry', ViewEntry);
}

export { registerDefaultEntries as default };