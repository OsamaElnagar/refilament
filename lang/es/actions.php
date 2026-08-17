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
            'label' => 'Crear :label',
            'modal' => [
                'heading' => 'Crear :label',
                'actions' => [
                    'create' => [
                        'label' => 'Crear',
                    ],
                    'create_another' => [
                        'label' => 'Crear y crear otro',
                    ],
                ],
            ],
            'notifications' => [
                'created' => [
                    'title' => 'Creado',
                ],
            ],
        ],
    ],
    'edit' => [
        'single' => [
            'label' => 'Editar',
            'modal' => [
                'heading' => 'Editar :label',
                'actions' => [
                    'save' => [
                        'label' => 'Guardar cambios',
                    ],
                ],
            ],
            'notifications' => [
                'saved' => [
                    'title' => 'Guardado',
                ],
            ],
        ],
    ],
    'view' => [
        'single' => [
            'label' => 'Ver',
            'modal' => [
                'heading' => 'Vista de :label',
                'actions' => [
                    'close' => [
                        'label' => 'Cerrar',
                    ],
                ],
            ],
        ],
    ],
    'delete' => [
        'single' => [
            'label' => 'Borrar',
            'modal' => [
                'heading' => 'Borrar :label',
                'actions' => [
                    'delete' => [
                        'label' => 'Borrar',
                    ],
                ],
            ],
            'notifications' => [
                'deleted' => [
                    'title' => 'Borrado',
                ],
            ],
        ],
        'multiple' => [
            'label' => 'Borrar seleccionados',
            'modal' => [
                'heading' => 'Borrar :label seleccionados',
                'actions' => [
                    'delete' => [
                        'label' => 'Borrar',
                    ],
                ],
            ],
            'notifications' => [
                'deleted' => [
                    'title' => 'Borrados',
                ],
                'deleted_partial' => [
                    'title' => 'Borrados :count de :total',
                    'missing_authorization_failure_message' => 'Usted no tiene permiso para eliminar :count.',
                    'missing_processing_failure_message' => ':count no se pudieron eliminar.',
                ],
                'deleted_none' => [
                    'title' => 'No se pudo eliminar',
                    'missing_authorization_failure_message' => 'Usted no tiene permiso para eliminar :count.',
                    'missing_processing_failure_message' => ':count no se pudieron eliminar.',
                ],
            ],
        ],
    ],
    'restore' => [
        'single' => [
            'label' => 'Restaurar',
            'modal' => [
                'heading' => 'Restaurar :label',
                'actions' => [
                    'restore' => [
                        'label' => 'Restaurar',
                    ],
                ],
            ],
            'notifications' => [
                'restored' => [
                    'title' => 'Registro restaurado',
                ],
            ],
        ],
        'multiple' => [
            'label' => 'Restaurar seleccionados',
            'modal' => [
                'heading' => 'Restaurar los :label seleccionados',
                'actions' => [
                    'restore' => [
                        'label' => 'Restaurar',
                    ],
                ],
            ],
            'notifications' => [
                'restored' => [
                    'title' => 'Registros restaurados',
                ],
                'restored_partial' => [
                    'title' => 'Restaurados :count de :total',
                    'missing_authorization_failure_message' => 'Usted no tiene permiso para restaurar :count.',
                    'missing_processing_failure_message' => ':count no se pudieron restaurar.',
                ],
                'restored_none' => [
                    'title' => 'Ningún registro restaurado',
                    'missing_authorization_failure_message' => 'Usted no tiene permiso para restaurar :count.',
                    'missing_processing_failure_message' => ':count no se pudieron restaurar.',
                ],
            ],
        ],
    ],
    'force-delete' => [
        'single' => [
            'label' => 'Forzar borrado',
            'modal' => [
                'heading' => 'Forzar el borrado de :label',
                'actions' => [
                    'delete' => [
                        'label' => 'Eliminar',
                    ],
                ],
            ],
            'notifications' => [
                'deleted' => [
                    'title' => 'Registro eliminado',
                ],
            ],
        ],
        'multiple' => [
            'label' => 'Forzar la eliminación de los elementos seleccionados',
            'modal' => [
                'heading' => 'Forzar la eliminación de los :label seleccionados',
                'actions' => [
                    'delete' => [
                        'label' => 'Eliminar',
                    ],
                ],
            ],
            'notifications' => [
                'deleted' => [
                    'title' => 'Registros eliminados',
                ],
                'deleted_partial' => [
                    'title' => 'Borrados :count de :total',
                    'missing_authorization_failure_message' => 'Usted no tiene permiso para eliminar :count.',
                    'missing_processing_failure_message' => ':count no se pudieron eliminar.',
                ],
                'deleted_none' => [
                    'title' => 'No se pudo eliminar',
                    'missing_authorization_failure_message' => 'Usted no tiene permiso para eliminar :count.',
                    'missing_processing_failure_message' => ':count no se pudieron eliminar.',
                ],
            ],
        ],
    ],
];
