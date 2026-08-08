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

return array (
  'create' => 
  array (
    'single' => 
    array (
      'label' => 'Crear :label',
      'modal' => 
      array (
        'heading' => 'Crear :label',
        'actions' => 
        array (
          'create' => 
          array (
            'label' => 'Crear',
          ),
          'create_another' => 
          array (
            'label' => 'Crear y crear otro',
          ),
        ),
      ),
      'notifications' => 
      array (
        'created' => 
        array (
          'title' => 'Creado',
        ),
      ),
    ),
  ),
  'edit' => 
  array (
    'single' => 
    array (
      'label' => 'Editar',
      'modal' => 
      array (
        'heading' => 'Editar :label',
        'actions' => 
        array (
          'save' => 
          array (
            'label' => 'Guardar cambios',
          ),
        ),
      ),
      'notifications' => 
      array (
        'saved' => 
        array (
          'title' => 'Guardado',
        ),
      ),
    ),
  ),
  'view' => 
  array (
    'single' => 
    array (
      'label' => 'Ver',
      'modal' => 
      array (
        'heading' => 'Vista de :label',
        'actions' => 
        array (
          'close' => 
          array (
            'label' => 'Cerrar',
          ),
        ),
      ),
    ),
  ),
  'delete' => 
  array (
    'single' => 
    array (
      'label' => 'Borrar',
      'modal' => 
      array (
        'heading' => 'Borrar :label',
        'actions' => 
        array (
          'delete' => 
          array (
            'label' => 'Borrar',
          ),
        ),
      ),
      'notifications' => 
      array (
        'deleted' => 
        array (
          'title' => 'Borrado',
        ),
      ),
    ),
    'multiple' => 
    array (
      'label' => 'Borrar seleccionados',
      'modal' => 
      array (
        'heading' => 'Borrar :label seleccionados',
        'actions' => 
        array (
          'delete' => 
          array (
            'label' => 'Borrar',
          ),
        ),
      ),
      'notifications' => 
      array (
        'deleted' => 
        array (
          'title' => 'Borrados',
        ),
        'deleted_partial' => 
        array (
          'title' => 'Borrados :count de :total',
          'missing_authorization_failure_message' => 'Usted no tiene permiso para eliminar :count.',
          'missing_processing_failure_message' => ':count no se pudieron eliminar.',
        ),
        'deleted_none' => 
        array (
          'title' => 'No se pudo eliminar',
          'missing_authorization_failure_message' => 'Usted no tiene permiso para eliminar :count.',
          'missing_processing_failure_message' => ':count no se pudieron eliminar.',
        ),
      ),
    ),
  ),
  'restore' => 
  array (
    'single' => 
    array (
      'label' => 'Restaurar',
      'modal' => 
      array (
        'heading' => 'Restaurar :label',
        'actions' => 
        array (
          'restore' => 
          array (
            'label' => 'Restaurar',
          ),
        ),
      ),
      'notifications' => 
      array (
        'restored' => 
        array (
          'title' => 'Registro restaurado',
        ),
      ),
    ),
    'multiple' => 
    array (
      'label' => 'Restaurar seleccionados',
      'modal' => 
      array (
        'heading' => 'Restaurar los :label seleccionados',
        'actions' => 
        array (
          'restore' => 
          array (
            'label' => 'Restaurar',
          ),
        ),
      ),
      'notifications' => 
      array (
        'restored' => 
        array (
          'title' => 'Registros restaurados',
        ),
        'restored_partial' => 
        array (
          'title' => 'Restaurados :count de :total',
          'missing_authorization_failure_message' => 'Usted no tiene permiso para restaurar :count.',
          'missing_processing_failure_message' => ':count no se pudieron restaurar.',
        ),
        'restored_none' => 
        array (
          'title' => 'Ningún registro restaurado',
          'missing_authorization_failure_message' => 'Usted no tiene permiso para restaurar :count.',
          'missing_processing_failure_message' => ':count no se pudieron restaurar.',
        ),
      ),
    ),
  ),
  'force-delete' => 
  array (
    'single' => 
    array (
      'label' => 'Forzar borrado',
      'modal' => 
      array (
        'heading' => 'Forzar el borrado de :label',
        'actions' => 
        array (
          'delete' => 
          array (
            'label' => 'Eliminar',
          ),
        ),
      ),
      'notifications' => 
      array (
        'deleted' => 
        array (
          'title' => 'Registro eliminado',
        ),
      ),
    ),
    'multiple' => 
    array (
      'label' => 'Forzar la eliminación de los elementos seleccionados',
      'modal' => 
      array (
        'heading' => 'Forzar la eliminación de los :label seleccionados',
        'actions' => 
        array (
          'delete' => 
          array (
            'label' => 'Eliminar',
          ),
        ),
      ),
      'notifications' => 
      array (
        'deleted' => 
        array (
          'title' => 'Registros eliminados',
        ),
        'deleted_partial' => 
        array (
          'title' => 'Borrados :count de :total',
          'missing_authorization_failure_message' => 'Usted no tiene permiso para eliminar :count.',
          'missing_processing_failure_message' => ':count no se pudieron eliminar.',
        ),
        'deleted_none' => 
        array (
          'title' => 'No se pudo eliminar',
          'missing_authorization_failure_message' => 'Usted no tiene permiso para eliminar :count.',
          'missing_processing_failure_message' => ':count no se pudieron eliminar.',
        ),
      ),
    ),
  ),
);
