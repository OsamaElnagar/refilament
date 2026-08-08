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
      'label' => 'إضافة :label',
      'modal' => 
      array (
        'heading' => 'إضافة :label',
        'actions' => 
        array (
          'create' => 
          array (
            'label' => 'إضافة',
          ),
          'create_another' => 
          array (
            'label' => 'إضافة وبدء إضافة المزيد',
          ),
        ),
      ),
      'notifications' => 
      array (
        'created' => 
        array (
          'title' => 'تمت الإضافة',
        ),
      ),
    ),
  ),
  'edit' => 
  array (
    'single' => 
    array (
      'label' => 'تعديل',
      'modal' => 
      array (
        'heading' => 'تعديل :label',
        'actions' => 
        array (
          'save' => 
          array (
            'label' => 'حفظ التغييرات',
          ),
        ),
      ),
      'notifications' => 
      array (
        'saved' => 
        array (
          'title' => 'تم الحفظ',
        ),
      ),
    ),
  ),
  'view' => 
  array (
    'single' => 
    array (
      'label' => 'عرض',
      'modal' => 
      array (
        'heading' => 'عرض :label',
        'actions' => 
        array (
          'close' => 
          array (
            'label' => 'إغلاق',
          ),
        ),
      ),
    ),
  ),
  'delete' => 
  array (
    'single' => 
    array (
      'label' => 'حذف',
      'modal' => 
      array (
        'heading' => 'حذف :label',
        'actions' => 
        array (
          'delete' => 
          array (
            'label' => 'حذف',
          ),
        ),
      ),
      'notifications' => 
      array (
        'deleted' => 
        array (
          'title' => 'تم الحذف',
        ),
      ),
    ),
    'multiple' => 
    array (
      'label' => 'حذف المحدد',
      'modal' => 
      array (
        'heading' => 'حذف المحدد :label',
        'actions' => 
        array (
          'delete' => 
          array (
            'label' => 'حذف',
          ),
        ),
      ),
      'notifications' => 
      array (
        'deleted' => 
        array (
          'title' => 'تم الحذف',
        ),
        'deleted_partial' => 
        array (
          'title' => '{1} تم حذف سجل واحد من :total|{2} تم حذف سجلين من :total|[3,10] تم حذف :count سجلات من :total|[11,*] تم حذف :count سجل من :total',
          'missing_authorization_failure_message' => '{1} ليس لديك إذن لحذف سجل واحد.|{2} ليس لديك إذن لحذف سجلين.|[3,10] ليس لديك إذن لحذف :count سجلات.|[11,*] ليس لديك إذن لحذف :count سجل.',
          'missing_processing_failure_message' => '{1} تعذر حذف سجل واحد.|{2} تعذر حذف سجلين.|[3,10] تعذر حذف :count سجلات.|[11,*] تعذر حذف :count سجل.',
        ),
        'deleted_none' => 
        array (
          'title' => 'لم يتم حذف أي شيء',
          'missing_authorization_failure_message' => '{1} ليس لديك إذن لحذف سجل واحد.|{2} ليس لديك إذن لحذف سجلين.|[3,10] ليس لديك إذن لحذف :count سجلات.|[11,*] ليس لديك إذن لحذف :count سجل.',
          'missing_processing_failure_message' => '{1} لم يتم حذف سجل واحد.|{2} لم يتم حذف سجلين.|[3,10] لم يتم حذف :count سجلات.|[11,*] لم يتم حذف :count سجل.',
        ),
      ),
    ),
  ),
  'restore' => 
  array (
    'single' => 
    array (
      'label' => 'استعادة',
      'modal' => 
      array (
        'heading' => 'استعادة :label',
        'actions' => 
        array (
          'restore' => 
          array (
            'label' => 'استعادة',
          ),
        ),
      ),
      'notifications' => 
      array (
        'restored' => 
        array (
          'title' => 'تمت الاستعادة',
        ),
      ),
    ),
    'multiple' => 
    array (
      'label' => 'استعادة المحدد',
      'modal' => 
      array (
        'heading' => 'استعادة :label',
        'actions' => 
        array (
          'restore' => 
          array (
            'label' => 'استعادة',
          ),
        ),
      ),
      'notifications' => 
      array (
        'restored' => 
        array (
          'title' => 'تمت الاستعادة',
        ),
        'restored_partial' => 
        array (
          'title' => '{1} تمت استعادة سجل واحد من أصل :total|{2} تمت استعادة سجلين من أصل :total|[3,10] تمت استعادة :count سجلات من أصل :total|[11,*] تمت استعادة :count سجل من أصل :total',
          'missing_authorization_failure_message' => '{1} ليس لديك صلاحية لاستعادة سجل واحد.|{2} ليس لديك صلاحية لاستعادة سجلين.|[3,10] ليس لديك صلاحية لاستعادة :count سجلات.|[11,*] ليس لديك صلاحية لاستعادة :count سجل.',
          'missing_processing_failure_message' => '{1} تعذر استعادة سجل واحد.|{2} تعذر استعادة سجلين.|[3,10] تعذر استعادة :count سجلات.|[11,*] تعذر استعادة :count سجل.',
        ),
        'restored_none' => 
        array (
          'title' => 'فشل في الاستعادة',
          'missing_authorization_failure_message' => '{1} ليس لديك صلاحية لاستعادة سجل واحد.|{2} ليس لديك صلاحية لاستعادة سجلين.|[3,10] ليس لديك صلاحية لاستعادة :count سجلات.|[11,*] ليس لديك صلاحية لاستعادة :count سجل.',
          'missing_processing_failure_message' => '{1} تعذر استعادة سجل واحد.|{2} تعذر استعادة سجلين.|[3,10] تعذر استعادة :count سجلات.|[11,*] تعذر استعادة :count سجل.',
        ),
      ),
    ),
  ),
  'force-delete' => 
  array (
    'single' => 
    array (
      'label' => 'حذف نهائي',
      'modal' => 
      array (
        'heading' => 'حذف نهائي لـ :label',
        'actions' => 
        array (
          'delete' => 
          array (
            'label' => 'حذف',
          ),
        ),
      ),
      'notifications' => 
      array (
        'deleted' => 
        array (
          'title' => 'تم الحذف',
        ),
      ),
    ),
    'multiple' => 
    array (
      'label' => 'حذف المحدد نهائياً',
      'modal' => 
      array (
        'heading' => 'حذف نهائي لـ :label',
        'actions' => 
        array (
          'delete' => 
          array (
            'label' => 'حذف',
          ),
        ),
      ),
      'notifications' => 
      array (
        'deleted' => 
        array (
          'title' => 'تم الحذف',
        ),
        'deleted_partial' => 
        array (
          'title' => '{1} تم حذف سجل واحد نهائياً من أصل :total|{2} تم حذف سجلين نهائياً من أصل :total|[3,10] تم حذف :count سجلات نهائياً من أصل :total|[11,*] تم حذف :count سجل نهائياً من أصل :total',
          'missing_authorization_failure_message' => '{1} ليس لديك صلاحية لحذف سجل واحد نهائياً.|{2} ليس لديك صلاحية لحذف سجلين نهائياً.|[3,10] ليس لديك صلاحية لحذف :count سجلات نهائياً.|[11,*] ليس لديك صلاحية لحذف :count سجل نهائياً.',
          'missing_processing_failure_message' => '{1} تعذر حذف سجل واحد نهائياً.|{2} تعذر حذف سجلين نهائياً.|[3,10] تعذر حذف :count سجلات نهائياً.|[11,*] تعذر حذف :count سجل نهائياً.',
        ),
        'deleted_none' => 
        array (
          'title' => 'فشل في الحذف نهائياً',
          'missing_authorization_failure_message' => '{1} ليس لديك صلاحية لحذف سجل واحد نهائياً.|{2} ليس لديك صلاحية لحذف سجلين نهائياً.|[3,10] ليس لديك صلاحية لحذف :count سجلات نهائياً.|[11,*] ليس لديك صلاحية لحذف :count سجل نهائياً.',
          'missing_processing_failure_message' => '{1} تعذر حذف سجل واحد نهائياً.|{2} تعذر حذف سجلين نهائياً.|[3,10] تعذر حذف :count سجلات نهائياً.|[11,*] تعذر حذف :count سجل نهائياً.',
        ),
      ),
    ),
  ),
);
