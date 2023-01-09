<?php declare(strict_types=1);

namespace ArchiveRepertory;

use Omeka\Stdlib\Message;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $oldVersion
 * @var string $newVersion
 */

/**
 * @var \Doctrine\DBAL\Connection $connection
 * @var array $config
 * @var \Omeka\Mvc\Controller\Plugin\Api $api
 * @var \Omeka\Settings\Settings $settings
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$connection = $services->get('Omeka\Connection');
$config = require dirname(__DIR__, 2) . '/config/module.config.php';
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$settings = $services->get('Omeka\Settings');
$messenger = $plugins->get('messenger');

if (version_compare($oldVersion, '3.14.0', '<')) {
    $defaultSettings = $config[strtolower(__NAMESPACE__)]['config'];

    $settings->set('archiverepertory_item_set_folder',
        $defaultSettings['archiverepertory_item_set_folder']);
    $settings->set('archiverepertory_item_set_prefix',
        $defaultSettings['archiverepertory_item_set_prefix']);
    $settings->set('archiverepertory_item_set_convert',
        $defaultSettings['archiverepertory_item_set_convert']);

    $itemConvert = strtolower($settings->get['archive_repertory_item_convert']);
    if ($itemConvert == 'keep name') {
        $itemConvert = 'keep';
    }
    $settings->set('archiverepertory_item_convert', $itemConvert);

    $mediaConvert = $settings->get('archive_repertory_file_keep_original_name')
        ? $defaultSettings['archiverepertory_media_convert']
        : 'hash';
    $settings->set('archiverepertory_media_convert', $mediaConvert);
    $settings->delete('archive_repertory_file_keep_original_name');

    $settings->delete('archive_repertory_derivative_folders');
}

if (version_compare($oldVersion, '3.15.3', '<')) {
    foreach ($config[strtolower(__NAMESPACE__)]['config'] as $name => $value) {
        $oldName = str_replace('archiverepertory_', 'archive_repertory_', $name);
        $settings->set($name, $settings->get($oldName, $value));
        $settings->delete($oldName);
    }
    $settings->delete('archive_repertory_ingesters');
}

if (version_compare($oldVersion, '3.15.14.3', '<')) {
    $message = new Message(
        'The process is now working with background processes.' // @translate
    );
    $messenger->addSuccess($message);
}
