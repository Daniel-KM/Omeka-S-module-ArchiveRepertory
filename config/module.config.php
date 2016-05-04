<?php
return [
        'forms' => [
        'invokables' => [
                         'ArchiveRepertory\Form\ConfigArchiveRepertoryForm' => 'ArchiveRepertory\Form\ConfigArchiveRepertoryForm',
        ],

        ],
    'controllers' => [
        'invokables' => [
                         'ArchiveRepertory\Controller\DownloadController' => 'ArchiveRepertory\Controller\DownloadController',
        ],
    ],

    'media_ingesters' => [
     'invokables' => [
               'upload'  => 'ArchiveRepertory\Media\Ingester\UploadAnywhere',
        ]],
    'file_manager' => [
        'store' => 'Omeka\File\ExternalStore',

    ],
        'service_manager' => [

                              'factories' => [ 'Omeka\File\ExternalStore'       => 'ArchiveRepertory\Service\ExternalStoreFactory',
                                              'Omeka\File\Manager'          => 'ArchiveRepertory\Service\FileArchiveManagerFactory']
        ],
    'router' => [
        'routes' => [
            'my_route' => [
                           'type' => 'segment',
                'options' => [
                              'route' => 'archive-repertory/download/files/$id',
                    'constraints' => [
                                      'id' => '([^/]+)/(.*)',
                    ],
                    'defaults' => [
                                   '__NAMESPACE__' => 'ArchiveRepertory\Controller',
                                   'controller' => 'Download',
                                   'action' => 'files',
                    ],
                ],
            ],
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
                                __DIR__ . '/../view/admin/',

        ],
    ],
//
        //  'view_helpers' => [
//        'invokables' => [
        //                       'myViewHelper' => 'ArchiveRepertory\View\Helper\MyViewHelper',
//        ],
//    ],
    'translator' => [
        'translation_file_patterns' => [
            [
             'type' => 'gettext',
             'base_dir' => __DIR__ . '/../language',
             'pattern' => '%s.mo',
             'text_domain' => null,
            ],
        ],
    ],
];
