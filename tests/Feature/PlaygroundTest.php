<?php

declare(strict_types=1);

it('serves the schema playground as an Inertia page', function () {
    $response = $this->get('/refilament/playground', ['X-Inertia' => 'true']);

    $response->assertOk();
    $response->assertJsonPath('component', 'refilament/playground');
    $response->assertJsonPath('props.id', 'playground');
    $response->assertJsonPath('props.contract', 1);
    $response->assertJsonCount(7, 'props.schema');

    // Section 1 — post details, containing a 2-column grid.
    $response->assertJsonPath('props.schema.0.type', 'section');
    $response->assertJsonPath('props.schema.0.heading', 'Post details');
    $response->assertJsonPath('props.schema.0.schema.0.type', 'grid');
    $response->assertJsonPath('props.schema.0.schema.0.columns', 2);

    $response->assertJsonPath('props.schema.0.schema.0.schema.0.type', 'text_input');
    $response->assertJsonPath('props.schema.0.schema.0.schema.0.name', 'title');
    $response->assertJsonPath('props.schema.0.schema.0.schema.0.validation', ['required']);
    $response->assertJsonPath('props.schema.0.schema.0.schema.0.maxLength', 255);
    $response->assertJsonPath('props.schema.0.schema.0.schema.0.columnSpan', 2);

    $response->assertJsonPath('props.schema.0.schema.0.schema.2.type', 'select');
    $response->assertJsonPath('props.schema.0.schema.0.schema.2.name', 'status');
    $response->assertJsonPath('props.schema.0.schema.0.schema.2.searchable', true);
    $response->assertJsonPath('props.schema.0.schema.0.schema.2.options', [
        ['value' => 'draft', 'label' => 'Draft'],
        ['value' => 'published', 'label' => 'Published'],
        ['value' => 'archived', 'label' => 'Archived'],
    ]);

    $response->assertJsonPath('props.schema.0.schema.1.type', 'text_input');
    $response->assertJsonPath('props.schema.0.schema.1.inputType', 'password');
    $response->assertJsonPath('props.schema.0.schema.1.revealable', true);

    // Section 1 grid — the slug field carries a unique rule, so the client
    // live-validates it (slice 2.5).
    $response->assertJsonPath('props.schema.0.schema.0.schema.1.name', 'slug');
    $response->assertJsonPath('props.schema.0.schema.0.schema.1.validation', ['unique:posts,slug']);

    // Section 2 — location, containing the dependent country/state selects.
    $response->assertJsonPath('props.schema.1.type', 'section');
    $response->assertJsonPath('props.schema.1.heading', 'Location');
    $response->assertJsonPath('props.schema.1.schema.0.schema.0.name', 'country');
    $response->assertJsonPath('props.schema.1.schema.0.schema.1.type', 'select');
    $response->assertJsonPath('props.schema.1.schema.0.schema.1.name', 'state');
    $response->assertJsonPath('props.schema.1.schema.0.schema.1.dependsOn', ['country']);
    $response->assertJsonPath('props.schema.1.schema.0.schema.1.placeholder', 'Pick a state');
    $response->assertJsonMissingPath('props.schema.1.schema.0.schema.1.options');

    // Section 3 — publishing, exercising the slice 1.4 field types.
    $response->assertJsonPath('props.schema.2.type', 'section');
    $response->assertJsonPath('props.schema.2.heading', 'Publishing');
    $response->assertJsonPath('props.schema.2.schema.0.type', 'grid');

    $response->assertJsonPath('props.schema.2.schema.0.schema.0.type', 'textarea');
    $response->assertJsonPath('props.schema.2.schema.0.schema.0.name', 'excerpt');
    $response->assertJsonPath('props.schema.2.schema.0.schema.0.rows', 4);
    $response->assertJsonPath('props.schema.2.schema.0.schema.0.columnSpan', 2);

    $response->assertJsonPath('props.schema.2.schema.0.schema.1.type', 'checkbox');
    $response->assertJsonPath('props.schema.2.schema.0.schema.1.name', 'featured');
    $response->assertJsonPath('props.schema.2.schema.0.schema.1.inline', true);
    $response->assertJsonPath('props.schema.2.schema.0.schema.1.default', false);
    $response->assertJsonPath('props.schema.2.schema.0.schema.1.validation', ['boolean']);

    $response->assertJsonPath('props.schema.2.schema.0.schema.2.type', 'toggle');
    $response->assertJsonPath('props.schema.2.schema.0.schema.2.name', 'allow_comments');
    $response->assertJsonPath('props.schema.2.schema.0.schema.2.inline', true);
    $response->assertJsonPath('props.schema.2.schema.0.schema.2.default', true);

    // Section 4 — publication, exercising the slice 1.5 field types.
    $response->assertJsonPath('props.schema.3.type', 'section');
    $response->assertJsonPath('props.schema.3.heading', 'Publication');

    $response->assertJsonPath('props.schema.3.schema.0.type', 'radio');
    $response->assertJsonPath('props.schema.3.schema.0.name', 'visibility');
    $response->assertJsonPath('props.schema.3.schema.0.inline', true);
    $response->assertJsonPath('props.schema.3.schema.0.default', 'public');
    $response->assertJsonPath('props.schema.3.schema.0.validation', ['required', 'in:public,members,private']);
    $response->assertJsonPath('props.schema.3.schema.0.options', [
        ['value' => 'public', 'label' => 'Public'],
        ['value' => 'members', 'label' => 'Members only'],
        ['value' => 'private', 'label' => 'Private'],
    ]);

    $response->assertJsonPath('props.schema.3.schema.1.type', 'grid');
    $response->assertJsonPath('props.schema.3.schema.1.columns', 3);

    $response->assertJsonPath('props.schema.3.schema.1.schema.0.type', 'text_input');
    $response->assertJsonPath('props.schema.3.schema.1.schema.0.name', 'publish_date');
    $response->assertJsonPath('props.schema.3.schema.1.schema.0.inputType', 'date');
    $response->assertJsonPath('props.schema.3.schema.1.schema.0.validation', ['date', 'nullable']);

    $response->assertJsonPath('props.schema.3.schema.1.schema.1.type', 'text_input');
    $response->assertJsonPath('props.schema.3.schema.1.schema.1.name', 'publish_time');
    $response->assertJsonPath('props.schema.3.schema.1.schema.1.inputType', 'time');
    $response->assertJsonPath('props.schema.3.schema.1.schema.1.validation', ['date_format:H:i', 'nullable']);

    $response->assertJsonPath('props.schema.3.schema.1.schema.2.type', 'text_input');
    $response->assertJsonPath('props.schema.3.schema.1.schema.2.name', 'reading_time');
    $response->assertJsonPath('props.schema.3.schema.1.schema.2.inputType', 'number');
    $response->assertJsonPath('props.schema.3.schema.1.schema.2.step', 5);
    $response->assertJsonPath('props.schema.3.schema.1.schema.2.validation', ['numeric', 'min:0', 'max:120']);

    // Section 5 — visibility rules (slice 2.4): sibling-driven show/hide.
    $response->assertJsonPath('props.schema.4.type', 'section');
    $response->assertJsonPath('props.schema.4.heading', 'Visibility rules');
    $response->assertJsonPath('props.schema.4.schema.0.type', 'grid');

    $response->assertJsonPath('props.schema.4.schema.0.schema.0.name', 'show_author');
    $response->assertJsonPath('props.schema.4.schema.0.schema.0.type', 'toggle');
    $response->assertJsonPath('props.schema.4.schema.0.schema.1.name', 'author_bio');
    $response->assertJsonPath('props.schema.4.schema.0.schema.1.whenTruthy', ['show_author']);

    $response->assertJsonPath('props.schema.4.schema.0.schema.2.name', 'comment_review_note');
    $response->assertJsonPath('props.schema.4.schema.0.schema.2.whenFalsy', ['allow_comments']);

    // A node with no visibility rule never carries the keys (omit-when-unset).
    $response->assertJsonMissingPath('props.schema.4.schema.0.schema.0.whenTruthy');
    $response->assertJsonMissingPath('props.schema.4.schema.0.schema.0.whenFalsy');

    // Section 6 — fieldset + tabs layouts (slice 2.6).
    $response->assertJsonPath('props.schema.5.type', 'section');
    $response->assertJsonPath('props.schema.5.heading', 'Fieldset + Tabs layouts');

    $response->assertJsonPath('props.schema.5.schema.0.type', 'fieldset');
    $response->assertJsonPath('props.schema.5.schema.0.label', 'Billing address');
    $response->assertJsonPath('props.schema.5.schema.0.columns', 2);
    $response->assertJsonPath('props.schema.5.schema.0.schema.0.name', 'company');
    $response->assertJsonPath('props.schema.5.schema.0.schema.1.name', 'website');

    $response->assertJsonPath('props.schema.5.schema.1.type', 'tabs');
    $response->assertJsonMissingPath('props.schema.5.schema.1.activeTab');
    $response->assertJsonPath('props.schema.5.schema.1.schema.0.type', 'tab');
    $response->assertJsonPath('props.schema.5.schema.1.schema.0.label', 'Billing');
    $response->assertJsonPath('props.schema.5.schema.1.schema.0.schema.0.name', 'plan_frequency');
    $response->assertJsonPath('props.schema.5.schema.1.schema.1.type', 'tab');
    $response->assertJsonPath('props.schema.5.schema.1.schema.1.label', 'Plan');
    $response->assertJsonPath('props.schema.5.schema.1.schema.1.schema.0.name', 'plan_tier');

    // Section 7 — invoice arithmetic (slice C3): computed fields carry their
    // client-side expression as data, rendered read-only.
    $response->assertJsonPath('props.schema.6.type', 'section');
    $response->assertJsonPath('props.schema.6.heading', 'Invoice arithmetic');
    $response->assertJsonPath('props.schema.6.schema.0.type', 'grid');
    $response->assertJsonPath('props.schema.6.schema.0.columns', 2);

    $response->assertJsonPath('props.schema.6.schema.0.schema.0.name', 'quantity');
    $response->assertJsonPath('props.schema.6.schema.0.schema.0.default', 2);
    $response->assertJsonPath('props.schema.6.schema.0.schema.1.name', 'unit_price');
    $response->assertJsonPath('props.schema.6.schema.0.schema.1.default', 49.99);

    $response->assertJsonPath('props.schema.6.schema.0.schema.2.name', 'subtotal');
    $response->assertJsonPath('props.schema.6.schema.0.schema.2.computed', 'quantity * unit_price');
    $response->assertJsonPath('props.schema.6.schema.0.schema.2.readOnly', true);
    $response->assertJsonPath('props.schema.6.schema.0.schema.2.validation', ['numeric', 'nullable']);

    $response->assertJsonPath('props.schema.6.schema.0.schema.3.name', 'vat_amount');
    $response->assertJsonPath('props.schema.6.schema.0.schema.3.computed', 'subtotal * 0.14');

    $response->assertJsonPath('props.schema.6.schema.0.schema.4.name', 'total_amount');
    $response->assertJsonPath('props.schema.6.schema.0.schema.4.computed', 'subtotal + vat_amount');
    $response->assertJsonPath('props.schema.6.schema.0.schema.4.columnSpan', 2);

    $response->assertJsonPath('props.data', [
        'title' => '',
        'slug' => '',
        'status' => 'draft',
        'password' => '',
        'country' => '',
        'state' => '',
        'excerpt' => '',
        'featured' => false,
        'allow_comments' => true,
        'visibility' => 'public',
        'publish_date' => '',
        'publish_time' => '',
        'reading_time' => '',
        'show_author' => false,
        'author_bio' => '',
        'comment_review_note' => '',
        'company' => '',
        'website' => '',
        'plan_frequency' => 'monthly',
        'plan_tier' => 'pro',
        'quantity' => 2,
        'unit_price' => 49.99,
        'subtotal' => null,
        'vat_amount' => null,
        'total_amount' => null,
    ]);
    $response->assertJsonPath('props.errors', []);
});
