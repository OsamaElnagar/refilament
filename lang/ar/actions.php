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
            'label' => 'إضافة :label',
            'modal' => [
                'heading' => 'إضافة :label',
                'actions' => [
                    'create' => [
                        'label' => 'إضافة',
                    ],
                    'create_another' => [
                        'label' => 'إضافة وبدء إضافة المزيد',
                    ],
                ],
            ],
            'notifications' => [
                'created' => [
                    'title' => 'تمت الإضافة',
                ],
            ],
        ],
    ],
    'edit' => [
        'single' => [
            'label' => 'تعديل',
            'modal' => [
                'heading' => 'تعديل :label',
                'actions' => [
                    'save' => [
                        'label' => 'حفظ التغييرات',
                    ],
                ],
            ],
            'notifications' => [
                'saved' => [
                    'title' => 'تم الحفظ',
                ],
            ],
        ],
    ],
    'view' => [
        'single' => [
            'label' => 'عرض',
            'modal' => [
                'heading' => 'عرض :label',
                'actions' => [
                    'close' => [
                        'label' => 'إغلاق',
                    ],
                ],
            ],
        ],
    ],
    'delete' => [
        'single' => [
            'label' => 'حذف',
            'modal' => [
                'heading' => 'حذف :label',
                'actions' => [
                    'delete' => [
                        'label' => 'حذف',
                    ],
                ],
            ],
            'notifications' => [
                'deleted' => [
                    'title' => 'تم الحذف',
                ],
            ],
        ],
        'multiple' => [
            'label' => 'حذف المحدد',
            'modal' => [
                'heading' => 'حذف المحدد :label',
                'actions' => [
                    'delete' => [
                        'label' => 'حذف',
                    ],
                ],
            ],
            'notifications' => [
                'deleted' => [
                    'title' => 'تم الحذف',
                ],
                'deleted_partial' => [
                    'title' => '{1} تم حذف سجل واحد من :total|{2} تم حذف سجلين من :total|[3,10] تم حذف :count سجلات من :total|[11,*] تم حذف :count سجل من :total',
                    'missing_authorization_failure_message' => '{1} ليس لديك إذن لحذف سجل واحد.|{2} ليس لديك إذن لحذف سجلين.|[3,10] ليس لديك إذن لحذف :count سجلات.|[11,*] ليس لديك إذن لحذف :count سجل.',
                    'missing_processing_failure_message' => '{1} تعذر حذف سجل واحد.|{2} تعذر حذف سجلين.|[3,10] تعذر حذف :count سجلات.|[11,*] تعذر حذف :count سجل.',
                ],
                'deleted_none' => [
                    'title' => 'لم يتم حذف أي شيء',
                    'missing_authorization_failure_message' => '{1} ليس لديك إذن لحذف سجل واحد.|{2} ليس لديك إذن لحذف سجلين.|[3,10] ليس لديك إذن لحذف :count سجلات.|[11,*] ليس لديك إذن لحذف :count سجل.',
                    'missing_processing_failure_message' => '{1} لم يتم حذف سجل واحد.|{2} لم يتم حذف سجلين.|[3,10] لم يتم حذف :count سجلات.|[11,*] لم يتم حذف :count سجل.',
                ],
            ],
        ],
    ],
    'restore' => [
        'single' => [
            'label' => 'استعادة',
            'modal' => [
                'heading' => 'استعادة :label',
                'actions' => [
                    'restore' => [
                        'label' => 'استعادة',
                    ],
                ],
            ],
            'notifications' => [
                'restored' => [
                    'title' => 'تمت الاستعادة',
                ],
            ],
        ],
        'multiple' => [
            'label' => 'استعادة المحدد',
            'modal' => [
                'heading' => 'استعادة :label',
                'actions' => [
                    'restore' => [
                        'label' => 'استعادة',
                    ],
                ],
            ],
            'notifications' => [
                'restored' => [
                    'title' => 'تمت الاستعادة',
                ],
                'restored_partial' => [
                    'title' => '{1} تمت استعادة سجل واحد من أصل :total|{2} تمت استعادة سجلين من أصل :total|[3,10] تمت استعادة :count سجلات من أصل :total|[11,*] تمت استعادة :count سجل من أصل :total',
                    'missing_authorization_failure_message' => '{1} ليس لديك صلاحية لاستعادة سجل واحد.|{2} ليس لديك صلاحية لاستعادة سجلين.|[3,10] ليس لديك صلاحية لاستعادة :count سجلات.|[11,*] ليس لديك صلاحية لاستعادة :count سجل.',
                    'missing_processing_failure_message' => '{1} تعذر استعادة سجل واحد.|{2} تعذر استعادة سجلين.|[3,10] تعذر استعادة :count سجلات.|[11,*] تعذر استعادة :count سجل.',
                ],
                'restored_none' => [
                    'title' => 'فشل في الاستعادة',
                    'missing_authorization_failure_message' => '{1} ليس لديك صلاحية لاستعادة سجل واحد.|{2} ليس لديك صلاحية لاستعادة سجلين.|[3,10] ليس لديك صلاحية لاستعادة :count سجلات.|[11,*] ليس لديك صلاحية لاستعادة :count سجل.',
                    'missing_processing_failure_message' => '{1} تعذر استعادة سجل واحد.|{2} تعذر استعادة سجلين.|[3,10] تعذر استعادة :count سجلات.|[11,*] تعذر استعادة :count سجل.',
                ],
            ],
        ],
    ],
    'force-delete' => [
        'single' => [
            'label' => 'حذف نهائي',
            'modal' => [
                'heading' => 'حذف نهائي لـ :label',
                'actions' => [
                    'delete' => [
                        'label' => 'حذف',
                    ],
                ],
            ],
            'notifications' => [
                'deleted' => [
                    'title' => 'تم الحذف',
                ],
            ],
        ],
        'multiple' => [
            'label' => 'حذف المحدد نهائياً',
            'modal' => [
                'heading' => 'حذف نهائي لـ :label',
                'actions' => [
                    'delete' => [
                        'label' => 'حذف',
                    ],
                ],
            ],
            'notifications' => [
                'deleted' => [
                    'title' => 'تم الحذف',
                ],
                'deleted_partial' => [
                    'title' => '{1} تم حذف سجل واحد نهائياً من أصل :total|{2} تم حذف سجلين نهائياً من أصل :total|[3,10] تم حذف :count سجلات نهائياً من أصل :total|[11,*] تم حذف :count سجل نهائياً من أصل :total',
                    'missing_authorization_failure_message' => '{1} ليس لديك صلاحية لحذف سجل واحد نهائياً.|{2} ليس لديك صلاحية لحذف سجلين نهائياً.|[3,10] ليس لديك صلاحية لحذف :count سجلات نهائياً.|[11,*] ليس لديك صلاحية لحذف :count سجل نهائياً.',
                    'missing_processing_failure_message' => '{1} تعذر حذف سجل واحد نهائياً.|{2} تعذر حذف سجلين نهائياً.|[3,10] تعذر حذف :count سجلات نهائياً.|[11,*] تعذر حذف :count سجل نهائياً.',
                ],
                'deleted_none' => [
                    'title' => 'فشل في الحذف نهائياً',
                    'missing_authorization_failure_message' => '{1} ليس لديك صلاحية لحذف سجل واحد نهائياً.|{2} ليس لديك صلاحية لحذف سجلين نهائياً.|[3,10] ليس لديك صلاحية لحذف :count سجلات نهائياً.|[11,*] ليس لديك صلاحية لحذف :count سجل نهائياً.',
                    'missing_processing_failure_message' => '{1} تعذر حذف سجل واحد نهائياً.|{2} تعذر حذف سجلين نهائياً.|[3,10] تعذر حذف :count سجلات نهائياً.|[11,*] تعذر حذف :count سجل نهائياً.',
                ],
            ],
        ],
    ],
];
