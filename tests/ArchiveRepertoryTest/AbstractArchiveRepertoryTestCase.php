<?php declare(strict_types=1);

namespace ArchiveRepertoryTest;

use CommonTest\AbstractHttpControllerTestCase;
use Laminas\Stdlib\Parameters;
use Omeka\Api\Representation\ItemRepresentation;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Base test case for Archive Repertory.
 *
 * The module moves files on the real file system, so every test runs against a
 * throwaway workspace used as the Omeka local store. Because Laminas rebuilds
 * the application (and therefore its service manager and request) on each
 * reset(), the store/ingester overrides are re-applied in getApplication() so
 * they survive a dispatch, and files are injected after the rebuild in
 * postDispatch().
 */
abstract class AbstractArchiveRepertoryTestCase extends AbstractHttpControllerTestCase
{
    /**
     * @var int
     */
    protected $umask;

    /**
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $filesystem;

    /**
     * @var string
     */
    protected $workspace;

    /**
     * Full path of the fixture image.
     *
     * @var string
     */
    protected $source;

    /**
     * Metadata of the fixture image (extension, media_type, size, content).
     *
     * @var array
     */
    protected $file = [];

    /**
     * @var array
     */
    protected $createdItemIds = [];

    /**
     * @var array
     */
    protected $createdItemSetIds = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->umask = umask(0);
        $this->filesystem = new Filesystem();
        $this->workspace = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR . 'omk_ar_' . bin2hex(random_bytes(8));
        mkdir($this->workspace . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'original', 0777, true);
        mkdir($this->workspace . DIRECTORY_SEPARATOR . 'tmp', 0777, true);
        $this->workspace = realpath($this->workspace);

        $this->source = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'fixtures'
            . DIRECTORY_SEPARATOR . 'image_test.png';
        $this->file = [
            'extension' => pathinfo($this->source, PATHINFO_EXTENSION),
            'media_type' => 'image/png',
            'size' => filesize($this->source),
            'content' => file_get_contents($this->source),
        ];

        // Trigger a first application build (with overrides) and authenticate.
        $this->loginAsAdmin();
        $this->resetArchiveSettings();
    }

    public function tearDown(): void
    {
        foreach ($this->createdItemIds as $id) {
            try {
                $this->api()->delete('items', $id);
            } catch (\Exception $e) {
                // Ignore cleanup errors.
            }
        }
        foreach ($this->createdItemSetIds as $id) {
            try {
                $this->api()->delete('item_sets', $id);
            } catch (\Exception $e) {
                // Ignore cleanup errors.
            }
        }

        if ($this->workspace) {
            $this->filesystem->remove($this->workspace);
        }
        umask($this->umask);
    }

    /**
     * Rebuild the application, re-applying the file store and ingester mocks so
     * they survive the reset() done before each dispatch.
     */
    public function getApplication()
    {
        $isNew = $this->application === null;
        $application = parent::getApplication();
        if ($isNew && !empty($this->workspace)) {
            $this->applyServiceOverrides($application->getServiceManager());
        }
        return $application;
    }

    /**
     * Point the local store, temp dir and file manager to the test workspace.
     */
    protected function applyServiceOverrides($services): void
    {
        $services->setAllowOverride(true);

        $config = $services->get('Config');
        $config['temp_dir'] = $this->workspace;
        $config['file_store']['local']['base_path'] = $this->workspace . DIRECTORY_SEPARATOR . 'files';
        $services->setService('Config', $config);

        $services->setFactory('Omeka\File\Store\Local', MockLocalStoreFactory::class);
        $services->setFactory('Omeka\File\TempFileFactory', MockTempFileFactoryFactory::class);
        $services->setFactory('ArchiveRepertory\FileManager', MockFileManagerFactory::class);
        $services->setAllowOverride(false);

        $uploader = $services->get('Omeka\File\Uploader');
        $tempFileFactory = $services->get('Omeka\File\TempFileFactory');

        $ingesterManager = $services->get('Omeka\Media\Ingester\Manager');
        $ingesterManager->setAllowOverride(true);
        $mockUpload = new MockUpload($uploader);
        $mockUpload->setTempFileFactory($tempFileFactory);
        $ingesterManager->setService('upload', $mockUpload);
        $ingesterManager->setAllowOverride(false);
    }

    /**
     * Authenticate as admin on the current application.
     *
     * Uses the authentication adapter (not the cached identity) so the admin
     * user is managed by the current entity manager. Otherwise, resources
     * created through the API would reference a detached owner and Doctrine
     * would treat it as a new, non-cascaded entity.
     */
    protected function loginAsAdmin(): void
    {
        $services = $this->getServiceLocator();
        $auth = $services->get('Omeka\AuthenticationService');
        if ($auth->hasIdentity()) {
            return;
        }
        $adapter = $auth->getAdapter();
        $adapter->setIdentity($this->adminEmail);
        $adapter->setCredential('root');
        $auth->authenticate();
        $user = $auth->getIdentity();
        if ($user) {
            $services->get('Omeka\Settings\User')->setTargetId($user->getId());
        }
    }

    /**
     * Get the service locator of the current application.
     */
    protected function getServiceLocator()
    {
        return $this->getApplication()->getServiceManager();
    }

    /**
     * Get the settings service.
     */
    protected function settings(): \Omeka\Settings\Settings
    {
        return $this->getServiceLocator()->get('Omeka\Settings');
    }

    /**
     * Get the (mocked) file manager of the current application.
     */
    protected function fileManager(): MockFileManager
    {
        return $this->getServiceLocator()->get('ArchiveRepertory\FileManager');
    }

    /**
     * Reset every module setting to a neutral, deterministic default.
     */
    protected function resetArchiveSettings(): void
    {
        $settings = $this->settings();
        $settings->set('archiverepertory_item_set_folder', '');
        $settings->set('archiverepertory_item_set_prefix', '');
        $settings->set('archiverepertory_item_set_convert', 'keep');
        $settings->set('archiverepertory_item_folder', '');
        $settings->set('archiverepertory_item_prefix', '');
        $settings->set('archiverepertory_item_convert', 'keep');
        $settings->set('archiverepertory_media_convert', 'keep');
        $settings->set('archiverepertory_keep_parenthesis', false);
    }

    /**
     * Dispatch a POST request, injecting uploaded files after the reset done by
     * dispatch(), else the rebuilt request would drop them.
     *
     * @param string $url
     * @param array $data POST parameters.
     * @param array $files Files parameter, as expected by setFiles().
     * @param string $csrfName Name of the CSRF field to fill with a valid token
     * generated after the reset, so its session hash matches the dispatched
     * request (the item form uses "csrf", the module config form uses
     * "module_{id}_configure_csrf").
     */
    protected function postDispatch(string $url, array $data = [], array $files = [], string $csrfName = 'csrf'): void
    {
        $this->reset();
        $this->getApplication();
        if ($this->requiresLogin) {
            $this->loginAsAdmin();
        }
        if ($files) {
            $this->getRequest()->setFiles(new Parameters($files));
        }
        $data[$csrfName] = (new \Laminas\Form\Element\Csrf($csrfName))->getValue();
        $this->url($url, 'POST', $data);
        $this->getApplication()->run();
    }

    /**
     * Create an item through the API and register it for cleanup.
     */
    protected function createItem(array $data = []): ItemRepresentation
    {
        $item = $this->api()->create('items', $data)->getContent();
        $this->createdItemIds[] = $item->id();
        return $item;
    }

    /**
     * Copy the fixture to the temp dir and return a "file" upload parameter.
     *
     * @param int $index File index in the request.
     * @param string $name Uploaded file name (its source).
     */
    protected function fileParam(int $index, string $name): array
    {
        $tempname = $this->workspace . DIRECTORY_SEPARATOR . 'tmp'
            . DIRECTORY_SEPARATOR . 'uploaded_' . bin2hex(random_bytes(8));
        copy($this->source, $tempname);
        return [
            $index => [
                'name' => $name,
                'type' => $this->file['media_type'],
                'tmp_name' => $tempname,
                'size' => $this->file['size'],
                'error' => 0,
                'content' => $this->file['content'],
            ],
        ];
    }

    /**
     * Full path of a file inside a derivative folder of the workspace store.
     *
     * @param string $type "original", "large", "medium", "square"…
     * @param string $relative Relative filename (may include subfolders).
     */
    protected function storePath(string $type, string $relative = ''): string
    {
        $path = $this->workspace . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . $type;
        return $relative === '' ? $path : $path . DIRECTORY_SEPARATOR . $relative;
    }

    /**
     * Assert the core invariant: the name stored in database points to an
     * existing original file whose content was preserved.
     */
    protected function assertMediaFileConsistency($media): void
    {
        $filename = is_string($media)
            ? $media
            : (method_exists($media, 'getFilename') ? $media->getFilename() : $media->filename());
        $this->assertNotEmpty($filename, 'Media has no filename.');
        $path = $this->storePath('original', $filename);
        $this->assertFileExists($path, sprintf('Original file missing for "%s".', $filename));
        $this->assertSame(
            $this->file['size'],
            filesize($path),
            sprintf('Content of "%s" was altered.', $filename)
        );
    }

    /**
     * Assert every thumbnail derivative of a file is present next to it.
     */
    protected function assertDerivativesExist(string $originalFilename): void
    {
        foreach ($this->derivativePaths($originalFilename) as $type => $path) {
            $this->assertFileExists($path, sprintf('Missing "%s" derivative for "%s".', $type, $originalFilename));
        }
    }

    /**
     * Assert no derivative remains at the given (old) location.
     */
    protected function assertDerivativesAbsent(string $originalFilename): void
    {
        foreach ($this->derivativePaths($originalFilename) as $type => $path) {
            $this->assertFileDoesNotExist($path, sprintf('Stale "%s" derivative left for "%s".', $type, $originalFilename));
        }
    }

    /**
     * Map thumbnail type => full derivative path for an original filename.
     */
    protected function derivativePaths(string $originalFilename): array
    {
        $dir = pathinfo($originalFilename, PATHINFO_DIRNAME);
        $base = pathinfo($originalFilename, PATHINFO_FILENAME);
        $relative = ($dir && $dir !== '.' ? $dir . '/' : '') . $base . '.jpg';
        $paths = [];
        foreach (['large', 'medium', 'square'] as $type) {
            $paths[$type] = $this->storePath($type, $relative);
        }
        return $paths;
    }

    // Helpers to build item edit requests.

    protected function literal(int $propertyId, string $value): array
    {
        return [
            'type' => 'literal',
            'property_id' => $propertyId,
            '@value' => $value,
        ];
    }

    /**
     * "o:media" entry uploading the file at the given request index.
     */
    protected function uploadMedia(int $index): array
    {
        return [
            'o:ingester' => 'upload',
            'file_index' => $index,
            'o:is_public' => 1,
        ];
    }

    /**
     * "o:media" entry referencing an already stored media (no new upload).
     */
    protected function existingMedia(int $mediaId): array
    {
        return [
            'o:id' => $mediaId,
            'o:is_public' => 1,
        ];
    }

    /**
     * Build the "file" request parameter for a map of index => uploaded name.
     */
    protected function buildFiles(array $map): array
    {
        $file = [];
        foreach ($map as $index => $name) {
            $file[$index] = $this->fileParam((int) $index, $name)[$index];
        }
        return ['file' => $file];
    }

    /**
     * Dispatch an item edit through the admin controller.
     */
    protected function editItem(int $itemId, array $data, array $files = []): void
    {
        $data['values_json'] = '{}';
        $this->postDispatch('/admin/item/' . $itemId . '/edit', $data, $files);
    }

    protected function mediaFilenames($item): array
    {
        $result = [];
        foreach ($item->media() as $media) {
            $result[] = $media->filename();
        }
        return $result;
    }

    protected function firstSuccessMessage(): string
    {
        $messenger = $this->getServiceLocator()->get('ControllerPluginManager')->get('messenger');
        $messages = $messenger->get();
        return isset($messages[\Omeka\Mvc\Controller\Plugin\Messenger::SUCCESS][0])
            ? (string) $messages[\Omeka\Mvc\Controller\Plugin\Messenger::SUCCESS][0]
            : '';
    }

    protected function errorMessages(): array
    {
        $messenger = $this->getServiceLocator()->get('ControllerPluginManager')->get('messenger');
        $messages = $messenger->get();
        return array_map('strval', $messages[\Omeka\Mvc\Controller\Plugin\Messenger::ERROR] ?? []);
    }

    protected function warningMessages(): array
    {
        $messenger = $this->getServiceLocator()->get('ControllerPluginManager')->get('messenger');
        $messages = $messenger->get();
        return array_map('strval', $messages[\Omeka\Mvc\Controller\Plugin\Messenger::WARNING] ?? []);
    }
}
