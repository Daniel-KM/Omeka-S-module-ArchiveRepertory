<?php
return [
    'form_elements' => [
        'factories' => [
            'ArchiveRepertory\Form\ConfigForm' => 'ArchiveRepertory\Service\Form\ConfigFormFactory',
            'ArchiveRepertory\Form\Element\PropertySelect' => 'ArchiveRepertory\Service\Form\Element\PropertySelectFactory',
        ],
    ],
    'local_dir' => OMEKA_PATH . '/files',
    'media_ingesters' => [
        'factories' => [
            'upload'  => 'ArchiveRepertory\Service\MediaIngester\UploadFactory',
        ]
    ],
    'service_manager' => [
        'factories' => [
            'Omeka\File\LocalStore' => 'ArchiveRepertory\Service\LocalStoreFactory',
            'Omeka\File\Manager' => 'ArchiveRepertory\Service\FileManagerFactory',
            'ArchiveRepertory\FileWriter' => 'ArchiveRepertory\Service\FileWriterFactory',
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'checkUnicodeInstallation' => 'ArchiveRepertory\View\Helper\CheckUnicodeInstallation',
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
