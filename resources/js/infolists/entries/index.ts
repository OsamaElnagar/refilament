import { IconEntry } from '@/infolists/entries/icon-entry';
import { TextEntry } from '@/infolists/entries/text-entry';
import { registerEntry } from '@/infolists/registry';

/**
 * Register the built-in infolist entry types (slice 3.3). Consumers and
 * plugins can add their own read-only entry types via registerEntry().
 */
export function registerDefaultEntries(): void {
    registerEntry('text_entry', TextEntry);
    registerEntry('icon_entry', IconEntry);
}

export { registerDefaultEntries as default };