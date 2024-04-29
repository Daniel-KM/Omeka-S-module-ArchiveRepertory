<?php declare(strict_types=1);

namespace ArchiveRepertory\File;

use Common\Stdlib\PsrMessage;
use Laminas\Mvc\I18n\Translator;
use Omeka\Entity\Media;
use Omeka\Entity\Resource;
use Omeka\File\Exception\RuntimeException;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Settings\Settings;

class FileManager
{
    /**
     * @var \ArchiveRepertory\File\FileWriter
     */
    protected $fileWriter;

    /**
     *
     * @var \Omeka\Mvc\Controller\Plugin\Messenger
     */
    protected $messenger;

    /**
     * @var Omeka\Settings\Settings
     */
    protected $settings;

    /**
     * @var \Laminas\Mvc\I18n\Translator
     */
    protected $translator;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var array
     */
    protected $ingesters;

    /**
     * @var array
     */
    protected $thumbnailTypes;

    /**
     * List of types (original, derivative and others), paths and extensions.
     *
     * @var array
     */
    protected $derivatives;

    public function __construct(
        FileWriter $fileWriter,
        Messenger $messenger,
        Settings $settings,
        Translator $translator,
        string $basePath,
        array $ingesters,
        array $thumbnailTypes
    ) {
        $this->fileWriter = $fileWriter;
        $this->messenger = $messenger;
        $this->settings = $settings;
        $this->translator = $translator;
        $this->basePath = $basePath;
        $this->ingesters = $ingesters;
        $this->thumbnailTypes = $thumbnailTypes;
    }

    /**
     * Get the base filename from a filename path (remove the extension only).
     *
     * This method is used with standard hash name ("afbecd1234567890afbecd.jpg")
     * and Archive Repertory relative name ("1706/alpha.jpg"). In fact, this is
     * the central method of the module.
     *
     * @param string $name
     * @return string
     */
    public function getBaseName($name): string
    {
        $positionExtension = strrpos($name, '.');
        return $positionExtension ? substr($name, 0, $positionExtension) : $name;
    }

    /**
     * Get the full storage id of a media according to current settings.
     *
     * Note: The directory separator is always "/" to simplify management of
     * files and checks.
     * Note: Unlike Omeka Classic, the storage id doesn’t include the extension.
     *
     * @param Media $media
     * @return string
     */
    public function getStorageId(Media $media): string
    {
        $storageId = $media->getStorageId();
        $currentFilename = $media->getFilename();

        $item = $media->getItem();
        $itemFolderName = $this->getResourceFolderName($item);
        $itemSet = $item->getItemSets()->first();
        $itemSetFolderName = $itemSet ? $this->getResourceFolderName($itemSet) : '';
        $folderName = ($itemSetFolderName ? $itemSetFolderName . '/' : '')
            . ($itemFolderName ? $itemFolderName . '/' : '');

        $mediaConvert = $this->settings->get('archiverepertory_media_convert');
        if ($mediaConvert == 'hash') {
            $storageName = $this->hashStorageName($media);
            $storageId = $storageName;
        } else {
            $extension = $media->getExtension();
            $storageName = \ArchiveRepertory\Helpers::pathinfo($media->getSource(), PATHINFO_BASENAME);
            $storageName = $this->sanitizeName($storageName);
            $storageName = \ArchiveRepertory\Helpers::pathinfo($storageName, PATHINFO_FILENAME);
            $storageName = $this->convertFilenameTo($storageName, $mediaConvert);
            if ($extension) {
                $storageName = $storageName . '.' . $extension;
            }
        }

        // Process the check of the storage name to get the storage id.
        if ($folderName) {
            $storageName = $folderName . $storageName;
        }
        $storageName = $this->getSingleFilename($storageName, $currentFilename);
        $newStorageId = pathinfo($storageName, PATHINFO_FILENAME);
        if ($folderName) {
            $newStorageId = $folderName . $newStorageId;
        }
        if (mb_strlen($newStorageId) > 190) {
            if ($folderName) {
                $this->messenger->addError(new PsrMessage(
                    'Cannot move file "{file}" inside archive directory ("{dir}"): filepath longer than 190 characters.', // @translate
                    ['file' => pathinfo($media->getSource(), PATHINFO_BASENAME), 'dir' => $folderName]
                    ));
            } else {
                $this->messenger->addError(new PsrMessage(
                    'Cannot move file "{file}" inside archive directory: filepath longer than 190 characters.', // @translate
                    ['file' => pathinfo($media->getSource(), PATHINFO_BASENAME)]
                ));
            }
            return $storageId;
        }

        return $newStorageId;
    }

    /**
     * Get the archive folder from a type.
     *
     * @example "original" returns "/var/www/omeka/files/original".
     *
     * @param string $type
     * @return string Full archive path, or empty if none.
     */
    public function getFullArchivePath($type): string
    {
        $deriv = $this->getDerivatives();
        return isset($deriv[$type]) ? $deriv[$type]['path'] : '';
    }

    /**
     * Moves/renames a file and its derivatives inside archive/files subfolders.
     *
     * New folders are created if needed. Old folders are removed if empty.
     * No update of the database is done.
     *
     * @param string $currentArchiveFilename Name of the current archive file to
     * move.
     * @param string $newArchiveFilename Name of the new archive file, with
     * archive folder if any (usually "collection/dc:identifier/").
     * @return bool True if files are moved, else set a message error.
     */
    public function moveFilesInArchiveFolders($currentArchiveFilename, $newArchiveFilename): bool
    {
        // A quick check to avoid some errors.
        $currentArchiveFilename = (string) $currentArchiveFilename;
        $newArchiveFilename = (string) $newArchiveFilename;
        if (trim($currentArchiveFilename) === '' || trim($newArchiveFilename) === '') {
            $this->messenger->addError(new PsrMessage(
                'Cannot move file inside archive directory: no filename.' // @translate
            ));
            return false;
        }

        // Move file only if it is not in the right place.
        // If the main file is at the right place, this is always the case for
        // the derivatives.
        $newArchiveFilename = str_replace('//', '/', $newArchiveFilename);
        if ($currentArchiveFilename == $newArchiveFilename) {
            return true;
        }

        $currentArchiveFolder = dirname($currentArchiveFilename);
        $newArchiveFolder = dirname($newArchiveFilename);

        // Determine the current and new derivative filename, standard
        // or not.
        $currentBase = $this->getBaseName($currentArchiveFilename);
        $newBase = $this->getBaseName($newArchiveFilename);

        // If any, move derivative files using Omeka API.
        $derivatives = $this->getDerivatives();
        foreach ($derivatives as $derivative) {
            foreach ($derivative['extension'] as $extension) {
                // Manage the original.
                if (is_null($extension)) {
                    $currentDerivativeFilename = $currentArchiveFilename;
                    $newDerivativeFilename = $newArchiveFilename;
                } elseif ($extension === '') {
                    $extension = pathinfo($currentArchiveFilename, PATHINFO_EXTENSION);
                    $extension = strlen($extension) ? '.' . $extension : '';
                    $currentDerivativeFilename = $currentBase . $extension;
                    $newDerivativeFilename = $newBase . $extension;
                } else {
                    $currentDerivativeFilename = $currentBase . $extension;
                    $newDerivativeFilename = $newBase . $extension;
                }

                // Check if the derivative file exists or not to avoid some
                // errors when moving something without derivatives: here, we
                // don't know anything of the media.
                $checkpath = $this->concatWithSeparator($derivative['path'], $currentDerivativeFilename);
                if (!$this->fileWriter->fileExists($checkpath)) {
                    continue;
                }
                $this->moveFile($currentDerivativeFilename, $newDerivativeFilename, $derivative['path']);
            }
        }

        // Remove all old empty folders.
        if ($currentArchiveFolder != $newArchiveFolder) {
            $this->removeArchiveFolders($currentArchiveFolder);
        }

        return true;
    }

    /**
     * Removes empty folders in the archive repertory.
     *
     * @param string $archiveFolder Name of folder to delete, without files dir.
     */
    public function removeArchiveFolders($archiveFolder): void
    {
        if (in_array($archiveFolder, ['.', '..', '/', '\\', ''])) {
            return;
        }

        foreach ($this->getDerivatives() as $derivative) {
            $folderPath = $this->concatWithSeparator($derivative['path'], $archiveFolder);
            // Of course, the main storage dir is not removed (in the case there
            // is no item folder).
            if (realpath($derivative['path']) != realpath($folderPath)) {
                // Check if there is an empty directory and remove it only in
                // that case. The directory may be not empty in multiple cases,
                // for example when the config changes or when there is a
                // duplicate name.
                $this->fileWriter->removeDir($folderPath, false);
            }
        }
    }

    public function concatWithSeparator($firstDir, $secondDir): string
    {
        if (empty($firstDir)) {
            return $secondDir;
        }
        if (empty($secondDir)) {
            return $firstDir;
        }
        $firstDir = rtrim($firstDir, DIRECTORY_SEPARATOR);
        $secondDir = ltrim($secondDir, DIRECTORY_SEPARATOR);
        return $firstDir . DIRECTORY_SEPARATOR . $secondDir;
    }

    /**
     * Get all archive folders with full paths and extensions.
     *
     * @return array
     */
    protected function getDerivatives(): array
    {
        if (is_null($this->derivatives)) {
            $this->derivatives = [];

            // Prepare standard paths and extensions.
            $derivatives = $this->getConfiguredTypes();
            $storagePath = $this->getLocalStoragePath();

            // Add specific paths and extensions
            foreach ($this->ingesters as $name => $params) {
                // Bypass internal ingesters.
                if ($params) {
                    $params['path'] = $this->concatWithSeparator($storagePath, $params['path']);
                    $derivatives[$name] = $params;
                }
            }
            $this->derivatives = $derivatives;
        }

        return $this->derivatives;
    }

    /**
     * Get the list of original and standard derivatives by type.
     *
     * Note: In Omeka S, the name and the path are the same.
     *
     * @return array
     */
    protected function getConfiguredTypes(): array
    {
        // No need to be static, it is called only from getDerivatives().
        $storagePath = $this->getLocalStoragePath();
        $types = [];
        $types['original'] = [
            'path' => $this->concatWithSeparator($storagePath, 'original'),
            'extension' => [null],
        ];
        foreach ($this->thumbnailTypes as $path => $value) {
            $value = [];
            $value['path'] = $this->concatWithSeparator($storagePath, $path);
            $value['extension'] = ['.jpg'];
            $types[$path] = $value;
        }
        return $types;
    }

    /**
     * Check if a file is a duplicate and return it with a suffix if needed.
     *
     * Note: The check is done on the basename, without extension, to avoid
     * issues with derivatives and because the table uses the basename too.
     * No check via database, because the file can be unsaved yet.
     *
     * @param string $filename
     * @param string $currentFilename It avoids to change when it is single.
     * @return string The unique filename, that can be the same as input name.
     */
    protected function getSingleFilename($filename, $currentFilename): string
    {
        // Get the partial path.
        $dirname = pathinfo($filename, PATHINFO_DIRNAME);

        // Get the real archive path.
        $fullOriginalPath = $this->getFullArchivePath('original');
        $filepath = $this->concatWithSeparator($fullOriginalPath, $filename);
        $folder = pathinfo($filepath, PATHINFO_DIRNAME);
        $name = pathinfo($filepath, PATHINFO_FILENAME);
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);
        $currentFilepath = $this->concatWithSeparator($fullOriginalPath, $currentFilename);

        // Check the name.
        $checkName = $name;
        // The name should already be sanitized, but escape all glob patterns
        // anyway, starting with "\".
        $existingFilepaths = $this->fileWriter->glob(str_replace(['\\', '[', ']', '{', '}', '?', '*'], ['\\\\', '\[', '\]', '\{', '\}', '\?', '\*'], $folder . DIRECTORY_SEPARATOR . $checkName) . '{.*,.,\,,}', GLOB_BRACE);

        // Check if the filename exists.
        if (empty($existingFilepaths)) {
            // Nothing to do.
        }
        // There are filenames, so check if the current one is inside.
        elseif (in_array($currentFilepath, $existingFilepaths)) {
            // Keep the existing one if there are many filepaths, but use the
            // default one if it is unique.
            if (count($existingFilepaths) > 1) {
                $checkName = pathinfo($currentFilename, PATHINFO_FILENAME);
            }
        }
        // Check folder for file with any extension or without any extension.
        else {
            $i = 0;
            while ($this->fileWriter->glob(str_replace(['\\', '[', ']', '{', '}', '?', '*'], ['\\\\', '\[', '\]', '\{', '\}', '\?', '\*'], $folder . DIRECTORY_SEPARATOR . $checkName) . '{.*,.,\,,}', GLOB_BRACE)) {
                $checkName = $name . '.' . ++$i;
            }
        }

        $result = ($dirname && $dirname !== '.' ? $dirname . DIRECTORY_SEPARATOR : '')
            . $checkName
            . ($extension ? '.' . $extension : '');
        return $result;
    }

    /**
     * Gets resource folder name from a resource and create folder if needed.
     *
     * @param Resource $resource
     * @return string Unique sanitized name of the resource.
     */
    protected function getResourceFolderName(?Resource $resource = null): string
    {
        // This check may allow to make Archive Repertory more compatible.
        if (is_null($resource)) {
            return '';
        }

        $resourceName = $resource->getResourceName();
        switch ($resourceName) {
            case 'item_sets':
                $folder = $this->settings->get('archiverepertory_item_set_folder');
                $prefix = $this->settings->get('archiverepertory_item_set_prefix');
                $convert = $this->settings->get('archiverepertory_item_set_convert');
                break;
            case 'items':
                $folder = $this->settings->get('archiverepertory_item_folder');
                $prefix = $this->settings->get('archiverepertory_item_prefix');
                $convert = $this->settings->get('archiverepertory_item_convert');
                break;
            default:
                throw new RuntimeException('[ArchiveRepertory] ' . (new PsrMessage(
                    'Unallowed resource type "{resource_name}".', // @translate
                    ['resource_name' => $resourceName]
                ))->setTranslator($this->translator));
        }

        if (empty($folder)) {
            return '';
        }

        switch ($folder) {
            case '':
                return '';
            case 'id':
                return (string) $resource->getId();
            default:
                $identifier = $this->getResourceIdentifier($resource, $folder, $prefix);
                $name = $this->sanitizeName($identifier);
                return empty($name)
                    ? (string) $resource->getId()
                    : $this->convertFilenameTo($name, $convert) ;
        }
    }

    /**
     * Gets first identifier of a resource.
     *
     * @param Resource $resource An item set or an item.
     * @param string $termId
     * @param string $prefix
     * @return string
     */
    protected function getResourceIdentifier(Resource $resource, $termId, $prefix): string
    {
        foreach ($resource->getValues() as $value) {
            if ($value->getProperty()->getId() != $termId) {
                continue;
            }
            if ($prefix) {
                $matches = [];
                preg_match('/^' . $prefix . '(.*)/', $value->getValue(), $matches);
                if (isset($matches[1])) {
                    return trim($matches[1]);
                }
                continue;
            }
            return $value->getValue();
        }
        return '';
    }

    /**
     * Hash a stable single storage name for a specific media.
     *
     * Note: A random name is not used to avoid possible issues when the option
     * changes.
     * @see \Omeka\File\TempFile::getStorageId()
     *
     * @param Media $media
     * @return string
     */
    protected function hashStorageName(Media $media): string
    {
        return substr(hash('sha256', $media->getId() . '/' . $media->getSource()), 0, 40);
    }

    /**
     * Returns a sanitized string for folder or file path.
     *
     * The string should be a simple name, not a full path or url, because "/",
     * "\" and ":" are removed (so a path should be sanitized by part).
     *
     * @param string $string The string to sanitize.
     * @return string The sanitized string.
     */
    protected function sanitizeName($string): string
    {
        $string = strip_tags((string) $string);
        // The first character is a space and the last one is a no-break space.
        $string = trim($string, ' /\\?<>:*%|"\'`&; ');
        $string = $this->settings->get('archiverepertory_keep_parenthesis')
            ? str_replace(['{', '}'], ['[', ']'], $string)
            : str_replace(['(', '{', '}', ')'], ['[', '[', ']', ']'], $string);
        $string = preg_replace('/[[:cntrl:]\/\\\?<>:\*\%\|\"\'`\&\;#+\^\$\s]/', ' ', $string);
        return substr(preg_replace('/\s+/', ' ', $string), -180);
    }

    /**
     * Returns a formatted string for folder or file name.
     *
     * Note: The string should be already sanitized.
     * The string should be a simple name, not a full path or url, because "/",
     * "\" and ":" are removed (so a path should be sanitized by part).
     *
     * See \ArchiveRepertoryPlugin::sanitizeName()
     *
     * @param string $string The string to sanitize.
     * @param string $format The format to convert to.
     * @return string The sanitized string.
     */
    protected function convertFilenameTo($string, $format): string
    {
        switch ($format) {
            case 'keep':
                return $string;
            case 'first letter':
                return $this->convertFirstLetterToAscii($string);
            case 'spaces':
                return $this->convertSpacesToUnderscore($string);
            case 'first and spaces':
                $string = $this->convertFilenameTo($string, 'first letter');
                return $this->convertSpacesToUnderscore($string);
            case 'full':
            default:
                return $this->convertNameToAscii($string);
        }
    }

    /**
     * Returns an unaccentued string for folder or file name.
     *
     * Note: The string should be already sanitized.
     *
     * See \ArchiveRepertoryPlugin::convertFilenameTo()
     *
     * @param string $string The string to convert to ascii.
     * @return string The converted string to use as a folder or a file name.
     */
    protected function convertNameToAscii($string): string
    {
        $string = htmlentities($string, ENT_NOQUOTES, 'utf-8');
        $string = preg_replace('#\&([A-Za-z])(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml|caron)\;#', '\1', $string);
        $string = preg_replace('#\&([A-Za-z]{2})(?:lig)\;#', '\1', $string);
        $string = preg_replace('#\&[^;]+\;#', '_', $string);
        $string = $this->settings->get('archiverepertory_keep_parenthesis')
            ? preg_replace('/[^[:alnum:]\[\]_\-\.\(\)#~@+:]/', '_', $string)
            : preg_replace('/[^[:alnum:]\[\]_\-\.#~@+:]/', '_', $string);
        return substr(preg_replace('/_+/', '_', $string), -180);
    }

    /**
     * Returns a formatted string for folder or file path (first letter only).
     *
     * Note: The string should be already sanitized.
     *
     * See \ArchiveRepertoryPlugin::convertFilenameTo()
     *
     * @param string $string The string to sanitize.
     * @return string The sanitized string.
     */
    protected function convertFirstLetterToAscii($string): string
    {
        $first = $this->convertNameToAscii($string);
        if (empty($first)) {
            return '';
        }
        return $first[0] . $this->substr_unicode($string, 1);
    }

    /**
     * Returns a formatted string for folder or file path (spaces only).
     *
     * Note: The string should be already sanitized.
     *
     * @param string $string The string to sanitize.
     * @return string The sanitized string.
     */
    protected function convertSpacesToUnderscore($string): string
    {
        return preg_replace('/\s+/', '_', $string);
    }

    /**
     * Get a sub string from a string when mb_substr is not available.
     *
     * @see http://www.php.net/manual/en/function.mb-substr.php#107698
     *
     * @param string $string
     * @param int $start
     * @param int $length (optional)
     * @return string
     */
    protected function substr_unicode($string, $start, $length = null): string
    {
        return join(
            '',
            array_slice(
                preg_split('//u', $string, -1, PREG_SPLIT_NO_EMPTY),
                $start,
                $length
            )
        );
    }

    /**
     * Get the local storage path (by default the Omeka path + "/files").
     *
     * @return string
     */
    protected function getLocalStoragePath(): string
    {
        return $this->basePath;
    }

    /**
     * Checks if the folders exist in the archive repertory, then creates them.
     *
     * @param string $archiveFolder
     *   Name of folder to create inside archive dir.
     * @param string $pathFolder
     *   (Optional) Name of folder where to create archive folder. If not set,
     *   the archive folder will be created in all derivative paths.
     * @return bool True if each path is created, Exception if an error occurs.
     */
    protected function createArchiveFolders($archiveFolder, $pathFolder = ''): bool
    {
        if ($archiveFolder != '') {
            $folders = empty($pathFolder)
                ? $this->getDerivatives()
                : [['path' => $pathFolder]];
            foreach ($folders as $derivative) {
                $fullpath = $this->concatWithSeparator($derivative['path'], $archiveFolder);
                $this->createFolder($fullpath);
            }
        }
        return true;
    }

    /**
     * Checks and creates a folder.
     *
     * @note Currently, Omeka API doesn't provide a function to create a folder.
     *
     * @param string $path Full path of the folder to create.
     * @return bool True if the path is created
     * @throws \Omeka\File\Exception\RuntimeException
     */
    protected function createFolder($path): bool
    {
        if ($path == '') {
            return true;
        }

        if ($this->fileWriter->fileExists($path)) {
            if ($this->fileWriter->is_dir($path)) {
                @chmod($path, 0775);
                if ($this->fileWriter->is_writeable($path)) {
                    return true;
                }
                throw new RuntimeException('[ArchiveRepertory] ' . (new PsrMessage(
                    'Error directory non writeable: "{dir}".', // @translate
                    ['dir' => $path]
                ))->setTranslator($this->translator));
            }
            throw new RuntimeException('[ArchiveRepertory] ' . (new PsrMessage(
                'Failed to create folder "dir": a file with the same name exists…', // @ŧranslate
                ['dir' => $path]
            ))->setTranslator($this->translator));
        }

        if (!$this->fileWriter->mkdir($path, 0775, true)) {
            throw new RuntimeException('[ArchiveRepertory] ' . (new PsrMessage(
                'Error making directory: "dir".', // @translate
                ['dir' => $path]
            ))->setTranslator($this->translator));
        }
        @chmod($path, 0775);

        return true;
    }

    /**
     * Process the move operation according to admin choice.
     *
     * @param string $source
     * @param string $destination
     * @param string $path
     * @return bool True if success, else set a message error.
     */
    protected function moveFile($source, $destination, $path = ''): bool
    {
        $realSource = $this->concatWithSeparator($path, $source);
        $realDestination = $this->concatWithSeparator($path, $destination);
        if ($this->fileWriter->fileExists($realDestination)) {
            return true;
        }

        if (!$this->fileWriter->fileExists($realSource)) {
            $this->messenger->addError(new PsrMessage(
                'Error during move of a file from "{source}" to "{destination}" (local dir: "{dir}"): source does not exist.', // @translate
                ['source' => $source, 'destination' => $destination, 'dir' => $path]
            ));
            return false;
        }

        try {
            $result = $this->createFolder(dirname($realDestination));
            $result = $this->fileWriter->rename($realSource, $realDestination);
        } catch (\Exception $e) {
            $this->messenger->addError(new PsrMessage(
                'Error during move of a file from "{source}" to "{destination}" (local dir: "{dir}").', // @translate
                ['source' => $source, 'destination' => $destination, 'dir' => $path]
            ));
            return false;
        }

        return $result;
    }
}
