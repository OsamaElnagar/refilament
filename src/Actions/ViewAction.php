<?php

declare(strict_types=1);

namespace Refilament\Refilament\Actions;

/**
 * Built-in row view action (record navigation slice — the Filament-mirroring
 * counterpart of DeleteAction). A consumer writes `ViewAction::make()` and
 * gets the standard behavior: an eye icon, primary color and a per-record
 * link to the resource's view page — resolved through the table's URL
 * resolver at row serialization, so it "just works" in any resource table
 * with a view page, and the per-record `view` policy gates both rendering
 * and the resolved URL (a record the current user may not view gets no URL,
 * so the button never renders for it).
 *
 * The defaults live in setUp(), which runs after the global configureUsing()
 * pipeline and before the consumer's own fluent calls — so `->label()`, a
 * custom `->url()` etc. all win over them, exactly like Filament.
 */
class ViewAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'view';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('View');
        $this->icon('eye');
        $this->color('primary');
        $this->authorize('view');
        // Navigate to the resource's view page for the record — the table's
        // URL resolver supplies the URL (view page exists + canView), and
        // the row serializer drops the action when it resolves none.
        $this->urlPage('view');
    }
}
