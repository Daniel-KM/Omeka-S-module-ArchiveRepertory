<?php
namespace ArchiveRepertory;

return [
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'checkUnicodeInstallation' => View\Helper\CheckUnicodeInstallation::class,
        ],
    ],
    'form_elements' => [
        'factories' => [
            Form\ConfigForm::class => Service\Form\ConfigFormFactory::class,
        ],
    ],
    'service_manager' => [
        'factories' => [
            'ArchiveRepertory\FileManager' => Service\FileManagerFactory::class,
            'ArchiveRepertory\FileWriter' => Service\FileWriterFactory::class,
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'archiverepertory' => [
        // Ingesters that modify the storage id and location of files.
        // Other modules can add their own ingesters.
        // Note: the config is merged in the alphabetic order of modules.
        'ingesters' => [
            // An empty array means that the thumbnail types / paths in config
            // and the default extension ("jpg") will be used.
            // See the module Image Server for a full example.
            'upload' => [],
            'url' => [],
            'sideload' => [],
        ],
        'config' => [
            // Item sets options.
            'archiverepertory_item_set_folder' => '',
            'archiverepertory_item_set_prefix' => '',
            'archiverepertory_item_set_convert' => 'full',

            // Items options.
            'archiverepertory_item_folder' => 'id',
            'archiverepertory_item_prefix' => '',
            'archiverepertory_item_convert' => 'full',

            // Media options.
            'archiverepertory_media_convert' => 'full',
        ],
    ],
];
