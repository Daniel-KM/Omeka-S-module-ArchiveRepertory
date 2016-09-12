<?php
return [
    'form_elements' => [
        'factories' => [
            'ArchiveRepertory\Form\ConfigForm' => 'ArchiveRepertory\Service\Form\ConfigFormFactory',
            'ArchiveRepertory\Form\Element\PropertySelect' => 'ArchiveRepertory\Service\Form\Element\PropertySelectFactory',
        ],
    ],
    'local_dir'=> OMEKA_PATH.'/files',
    'media_ingesters' => [
        'factories' => [
            'upload'  => 'ArchiveRepertory\Service\MediaIngester\UploadFactory',
        ]
    ],
    'file_manager' => [
        'store' => 'Omeka\File\ExternalStore',
    ],
    'service_manager' => [
        'factories' => [
            'Omeka\File\ExternalStore' => 'ArchiveRepertory\Service\ExternalStoreFactory',
            'Omeka\File\Manager' => 'ArchiveRepertory\Service\FileArchiveManagerFactory',
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            __DIR__ . '/../view/admin/',

        ],
    ],
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
