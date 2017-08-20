<?php
namespace ArchiveRepertory;

return [
    'form_elements' => [
        'factories' => [
            'ArchiveRepertory\Form\Config' => Service\Form\ConfigFactory::class,
            'ArchiveRepertory\Form\Element\PropertySelect' => Service\Form\Element\PropertySelectFactory::class,
        ],
    ],
    'local_dir' => OMEKA_PATH . DIRECTORY_SEPARATOR . 'files',
    'service_manager' => [
        'factories' => [
            'ArchiveRepertory\FileManager' => Service\FileManagerFactory::class,
            'ArchiveRepertory\FileWriter' => Service\FileWriterFactory::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'checkUnicodeInstallation' => View\Helper\CheckUnicodeInstallation::class,
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
