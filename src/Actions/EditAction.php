<?php

declare(strict_types=1);

namespace Refilament\Refilament\Actions;

/**
 * Built-in record edit action (record navigation slice — the Filament-mirroring
 * counterpart of ViewAction, in the same unified `Actions` namespace like every
 * other action). A consumer writes `EditAction::make()` and gets the standard
 * behavior: a pencil icon, primary color and a per-record link to the
 * resource's edit page — resolved through the table's URL resolver at row
 * serialization, so it "just works" in any resource table with an edit page,
 * and the per-record `update` policy gates both rendering and the resolved URL
 * (a record the current user may not edit gets no URL, so the button never
 * renders for it).
 *
 * The defaults live in setUp(), which runs after the global configureUsing()
 * pipeline and before the consumer's own fluent calls — so `->label()`, a
 * custom `->url()` etc. all win over them, exactly like Filament.
 */
class EditAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'edit';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Edit');
        $this->icon('pencil');
        $this->color('primary');
        $this->authorize('update');
        // Navigate to the resource's edit page for the record — the table's
        // URL resolver supplies the URL (edit page exists + canUpdate), and
        // the row serializer drops the action when it resolves none.
        $this->urlPage('edit');
    }
}
