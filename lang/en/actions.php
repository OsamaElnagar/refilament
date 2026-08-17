<?php

/*
 * Translation data derived from the FilamentPHP packages (https://filamentphp.com),
 * Copyright (c) Filament, licensed under the MIT License:
 * https://github.com/filamentphp/filament/blob/3.x/LICENSE.md
 *
 * Localized strings were adapted verbatim from Filament language files and are
 * redistributed under the same MIT terms. The keys below are namespaced as
 * "refilament::" in this package.
 */

return [
    'create' => [
        'single' => [
            'label' => 'New :label',
            'modal' => [
                'heading' => 'Create :label',
                'actions' => [
                    'create' => [
                        'label' => 'Create',
                    ],
                    'create_another' => [
                        'label' => 'Create & create another',
                    ],
                ],
            ],
            'notifications' => [
                'created' => [
                    'title' => 'Created',
                ],
            ],
        ],
    ],
    'edit' => [
        'single' => [
            'label' => 'Edit',
            'modal' => [
                'heading' => 'Edit :label',
                'actions' => [
                    'save' => [
                        'label' => 'Save changes',
                    ],
                ],
            ],
            'notifications' => [
                'saved' => [
                    'title' => 'Saved',
                ],
            ],
        ],
    ],
    'view' => [
        'single' => [
            'label' => 'View',
            'modal' => [
                'heading' => 'View :label',
                'actions' => [
                    'close' => [
                        'label' => 'Close',
                    ],
                ],
            ],
        ],
    ],
    'delete' => [
        'single' => [
            'label' => 'Delete',
            'modal' => [
                'heading' => 'Delete :label',
                'actions' => [
                    'delete' => [
                        'label' => 'Delete',
                    ],
                ],
            ],
            'notifications' => [
                'deleted' => [
                    'title' => 'Deleted',
                ],
            ],
        ],
        'multiple' => [
            'label' => 'Delete selected',
            'modal' => [
                'heading' => 'Delete selected :label',
                'actions' => [
                    'delete' => [
                        'label' => 'Delete',
                    ],
                ],
            ],
            'notifications' => [
                'deleted' => [
                    'title' => 'Deleted',
                ],
                'deleted_partial' => [
                    'title' => 'Deleted :count of :total',
                    'missing_authorization_failure_message' => 'You don\'t have permission to delete :count.',
                    'missing_processing_failure_message' => ':count could not be deleted.',
                ],
                'deleted_none' => [
                    'title' => 'Failed to delete',
                    'missing_authorization_failure_message' => 'You don\'t have permission to delete :count.',
                    'missing_processing_failure_message' => ':count could not be deleted.',
                ],
            ],
        ],
    ],
    'restore' => [
        'single' => [
            'label' => 'Restore',
            'modal' => [
                'heading' => 'Restore :label',
                'actions' => [
                    'restore' => [
                        'label' => 'Restore',
                    ],
                ],
            ],
            'notifications' => [
                'restored' => [
                    'title' => 'Restored',
                ],
            ],
        ],
        'multiple' => [
            'label' => 'Restore selected',
            'modal' => [
                'heading' => 'Restore selected :label',
                'actions' => [
                    'restore' => [
                        'label' => 'Restore',
                    ],
                ],
            ],
            'notifications' => [
                'restored' => [
                    'title' => 'Restored',
                ],
                'restored_partial' => [
                    'title' => 'Restored :count of :total',
                    'missing_authorization_failure_message' => 'You don\'t have permission to restore :count.',
                    'missing_processing_failure_message' => ':count could not be restored.',
                ],
                'restored_none' => [
                    'title' => 'Failed to restore',
                    'missing_authorization_failure_message' => 'You don\'t have permission to restore :count.',
                    'missing_processing_failure_message' => ':count could not be restored.',
                ],
            ],
        ],
    ],
    'force-delete' => [
        'single' => [
            'label' => 'Force delete',
            'modal' => [
                'heading' => 'Force delete :label',
                'actions' => [
                    'delete' => [
                        'label' => 'Delete',
                    ],
                ],
            ],
            'notifications' => [
                'deleted' => [
                    'title' => 'Deleted',
                ],
            ],
        ],
        'multiple' => [
            'label' => 'Force delete selected',
            'modal' => [
                'heading' => 'Force delete selected :label',
                'actions' => [
                    'delete' => [
                        'label' => 'Delete',
                    ],
                ],
            ],
            'notifications' => [
                'deleted' => [
                    'title' => 'Deleted',
                ],
                'deleted_partial' => [
                    'title' => 'Deleted :count of :total',
                    'missing_authorization_failure_message' => 'You don\'t have permission to delete :count.',
                    'missing_processing_failure_message' => ':count could not be deleted.',
                ],
                'deleted_none' => [
                    'title' => 'Failed to delete',
                    'missing_authorization_failure_message' => 'You don\'t have permission to delete :count.',
                    'missing_processing_failure_message' => ':count could not be deleted.',
                ],
            ],
        ],
    ],
];
