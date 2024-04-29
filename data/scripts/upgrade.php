<?php declare(strict_types=1);

namespace ArchiveRepertory;

use Common\Stdlib\PsrMessage;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $oldVersion
 * @var string $newVersion
 */

/**
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Omeka\Mvc\Controller\Plugin\Api $api
 * @var \Omeka\Settings\Settings $settings
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$connection = $services->get('Omeka\Connection');
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$settings = $services->get('Omeka\Settings');
$translate = $plugins->get('translate');
$messenger = $plugins->get('messenger');

$localConfig = include dirname(__DIR__, 2) . '/config/module.config.php';

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.57')) {
    $message = new \Omeka\Stdlib\Message(
        $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Common', '3.4.57'
    );
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
}

if (version_compare($oldVersion, '3.14.0', '<')) {
    $defaultSettings = $localConfig['archiverepertory']['config'];

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
    foreach ($localConfig['archiverepertory']['config'] as $name => $value) {
        $oldName = str_replace('archiverepertory_', 'archive_repertory_', $name);
        $settings->set($name, $settings->get($oldName, $value));
        $settings->delete($oldName);
    }
    $settings->delete('archive_repertory_ingesters');
}

if (version_compare($oldVersion, '3.15.14.3', '<')) {
    $message = new PsrMessage(
        'The process is now working with background processes.' // @translate
    );
    $messenger->addSuccess($message);
}
