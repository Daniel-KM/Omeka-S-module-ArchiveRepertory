<?php
namespace ArchiveRepertory\File;

use Omeka\Entity\Item;
use Omeka\Entity\ItemSet;
use Omeka\Entity\Media;
use Omeka\Entity\Resource;
use Omeka\File\File;
use Omeka\File\Exception\RuntimeException;
use Omeka\Mvc\Controller\Plugin\Messenger;

class Manager extends \Omeka\File\Manager
{
    /**
     * List of types (original, derivative and others), paths and extensions.
     *
     * @var array
     */
    protected $derivatives;

    public function getBasename($name)
    {
        $positionExtension = strrpos($name, '.');
        return $positionExtension ? substr($name, 0, $positionExtension) : $name;
    }

    /**
     * Get the full storage id of a media according to current settings.
     *
     * @internal The directory separator is always "/" to simplify management
     * of files and checks.
     *
     * @param Media $media
     * @return string
     */
    public function getStorageId(Media $media)
    {
        $storageId = $media->getStorageId();

        $item = $media->getItem();
        $itemFolderName = $this->getResourceFolderName($item);
        $itemSet = $item->getItemSets()->first();
        $itemSetFolderName = $itemSet ? $this->getResourceFolderName($itemSet) : '';
        $folderName = ($itemSetFolderName ? $itemSetFolderName . '/' : '')
            . ($itemFolderName ? $itemFolderName . '/' : '');

        $mediaConvert = $this->getSetting('archive_repertory_media_convert');
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
        $storageName = $this->getSingleFilename($storageName);
        $newStorageId = pathinfo($storageName, PATHINFO_FILENAME);
        if ($folderName) {
            $newStorageId= $folderName . $newStorageId;
        }

        if (strlen($newStorageId) > 190) {
            $msg = sprintf(
                $this->translate('Cannot move file "%s" inside archive directory: filename too long.'),
                pathinfo($media->getSource(), PATHINFO_BASENAME)
            );
            $this->addError($msg);
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
    public function getFullArchivePath($type)
    {
        $derivatives = $this->getDerivatives();
        return isset($derivatives[$type]) ? $derivatives[$type]['path'] : '';
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
    public function moveFilesInArchiveFolders($currentArchiveFilename, $newArchiveFilename)
    {
        // A quick check to avoid some errors.
        if (trim($currentArchiveFilename) == '' || trim($newArchiveFilename) == '') {
            $msg = $this->translate('Cannot move file inside archive directory: no filename.');
            $this->addError($msg);
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
        $currentBase = $this->getBasename($currentArchiveFilename);
        $newBase = $this->getBasename($newArchiveFilename);

        // If any, move derivative files using Omeka API.
        $fileWriter = $this->getFileWriter();
        $derivatives = $this->getDerivatives();
        foreach ($derivatives as $type => $derivative) {
            foreach ($derivative['extension'] as $extension) {
                // Manage the original.
                if (is_null($extension)) {
                    $currentDerivativeFilename = $currentArchiveFilename;
                    $newDerivativeFilename = $newArchiveFilename;
                } else {
                    $currentDerivativeFilename = $currentBase . $extension;
                    $newDerivativeFilename = $newBase . $extension;
                }

                // Check if the derivative file exists or not to avoid some
                // errors when moving something without derivatives: here, we
                // don't know anything of the media.
                $checkpath = $this->concatWithSeparator($derivative['path'], $currentDerivativeFilename);
                if (!$fileWriter->fileExists($checkpath)) {
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
    public function removeArchiveFolders($archiveFolder)
    {
        if (in_array($archiveFolder, ['.', '..', '/', '\\', ''])) {
            return;
        }

        $fileWriter = $this->getFileWriter();
        foreach ($this->getDerivatives() as $derivative) {
            $folderPath = $this->concatWithSeparator($derivative['path'], $archiveFolder);
            // Of course, the main storage dir is not removed (in the case there
            // is no item folder).
            if (realpath($derivative['path']) != realpath($folderPath)) {
                // Check if there is an empty directory and remove it only in
                // that case. The directory may be not empty in multiple case,
                // for example when the config change or when there is a
                // duplicate name.
                $fileWriter->removeDir($folderPath, false);
            }
        }
    }

    public function concatWithSeparator($firstDir, $secondDir)
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
    protected function getDerivatives()
    {
        if (is_null($this->derivatives)) {
            $this->derivatives = [];

            // Prepare standard paths and extensions.
            $derivatives = $this->getConfiguredTypes();
            $storagePath = $this->getLocalStoragePath();

            // Add specific paths and extensions
            $ingesters = $this->getSetting('archive_repertory_ingesters');
            foreach ($ingesters as $name => $params) {
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
     * @internal In Omeka S, the name and the path are the same.
     *
     * @return array
     */
    protected function getConfiguredTypes()
    {
        $types = $this->config['thumbnail_types'];
        $storagePath = $this->getLocalStoragePath();
        foreach ($types as $path => &$value) {
            $value = [];
            $value['path'] = $this->concatWithSeparator($storagePath, $path);
            $value['extension'] = ['.' . self::THUMBNAIL_EXTENSION];
        }
        $types = ['original' => [
            'path' => $this->concatWithSeparator($storagePath, self::ORIGINAL_PREFIX),
            'extension' => [null],
        ]] + $types;
        return $types;
    }

    /**
     * Check if a file is a duplicate and returns it with a suffix if needed.
     *
     * @internal The check is done on the basename, without extension, to avoid
     * issues with derivatives.
     * @internal No check via database, because the file can be unsaved yet.
     *
     * @param string $filename
     * @return string The unique filename, that can be the same as input name.
     */
    protected function getSingleFilename($filename)
    {
        // Get the partial path.
        $dirname = pathinfo($filename, PATHINFO_DIRNAME);

        // Get the real archive path.
        $filepath = $this->concatWithSeparator($this->getFullArchivePath(self::ORIGINAL_PREFIX), $filename);
        $folder = pathinfo($filepath, PATHINFO_DIRNAME);
        $name = pathinfo($filepath, PATHINFO_FILENAME);
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);

        // Check folder for file with any extension or without any extension.
        $checkName = $name;
        $i = 0;
        $fileWriter = $this->getFileWriter();
        while ($fileWriter->glob($folder . DIRECTORY_SEPARATOR . $checkName . '{.*,.,\,,}', GLOB_BRACE)) {
            $checkName = $name . '.' . ++$i;
        }

        $result = ($dirname ? $dirname . DIRECTORY_SEPARATOR : '')
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
    protected function getResourceFolderName(Resource $resource)
    {
        $resourceType = get_class($resource);
        switch ($resourceType) {
            case ItemSet::class:
                $folder = $this->getSetting('archive_repertory_item_set_folder');
                $prefix = $this->getSetting('archive_repertory_item_set_prefix');
                $convert = $this->getSetting('archive_repertory_item_set_convert');
                break;
            case Item::class:
                $folder = $this->getSetting('archive_repertory_item_folder');
                $prefix = $this->getSetting('archive_repertory_item_prefix');
                $convert = $this->getSetting('archive_repertory_item_convert');
                break;
            default:
                throw new RuntimeException('[ArchiveRepertory] ' . sprintf('Unallowed resource type "%s".', $resourceType));
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
     * Gets first identifier of a resource (with prefix if any).
     *
     * @param Resource $resource An item set or an item.
     * @param string $termId
     * @param string $prefix
     * @return string
     */
    protected function getResourceIdentifier(Resource $resource, $termId, $prefix)
    {
        foreach ($resource->getValues() as $value) {
            if ($value->getProperty()->getId() != $termId) {
                continue;
            }
            if ($prefix) {
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
     * @internal We cannot use a random name.
     * @see Omeka\File\File::getStorageId()
     *
     * @param Media $media
     * @return string
     */
    protected function hashStorageName(Media $media)
    {
        $storageName = substr(hash('sha256', $media->getId() . '/' . $media->getSource()), 0, 40);
        return $storageName;
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
    protected function sanitizeName($string)
    {
        $string = strip_tags($string);
        // The first character is a space and the last one is a no-break space.
        $string = trim($string, ' /\\?<>:*%|"\'`&; ');
        $string = preg_replace('/[\(\{]/', '[', $string);
        $string = preg_replace('/[\)\}]/', ']', $string);
        $string = preg_replace('/[[:cntrl:]\/\\\?<>:\*\%\|\"\'`\&\;#+\^\$\s]/', ' ', $string);
        return substr(preg_replace('/\s+/', ' ', $string), -180);
    }

    /**
     * Returns a formatted string for folder or file name.
     *
     * @internal The string should be already sanitized.
     * The string should be a simple name, not a full path or url, because "/",
     * "\" and ":" are removed (so a path should be sanitized by part).
     *
     * @see ArchiveRepertoryPlugin::sanitizeName)
     *
     * @param string $string The string to sanitize.
     * @param string $format The format to convert to.
     * @return string The sanitized string.
     */
    protected function convertFilenameTo($string, $format)
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
     * @internal The string should be already sanitized.
     *
     * @see ArchiveRepertoryPlugin::convertFilenameTo()
     *
     * @param string $string The string to convert to ascii.
     * @return string The converted string to use as a folder or a file name.
     */
    protected function convertNameToAscii($string)
    {
        $string = htmlentities($string, ENT_NOQUOTES, 'utf-8');
        $string = preg_replace('#\&([A-Za-z])(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml)\;#', '\1', $string);
        $string = preg_replace('#\&([A-Za-z]{2})(?:lig)\;#', '\1', $string);
        $string = preg_replace('#\&[^;]+\;#', '_', $string);
        $string = preg_replace('/[^[:alnum:]\[\]_\-\.#~@+:]/', '_', $string);
        return substr(preg_replace('/_+/', '_', $string), -180);
    }

    /**
     * Returns a formatted string for folder or file path (first letter only).
     *
     * @internal The string should be already sanitized.
     *
     * @see ArchiveRepertoryPlugin::convertFilenameTo()
     *
     * @param string $string The string to sanitize.
     * @return string The sanitized string.
     */
    protected function convertFirstLetterToAscii($string)
    {
        $first = $this->convertNameToAscii($string);
        if (empty($first)) {
            return '';
        }
        return $first[0] . $this->substr_unicode($string, 1);
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
    protected function substr_unicode($string, $start, $length = null)
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
     * Returns a formatted string for folder or file path (spaces only).
     *
     * @internal The string should be already sanitized.
     *
     * @param string $string The string to sanitize.
     * @return string The sanitized string.
     */
    protected function convertSpacesToUnderscore($string)
    {
        return preg_replace('/\s+/', '_', $string);
    }

    /**
     * Get the local storage path.
     *
     * @return array
     */
    protected function getLocalStoragePath()
    {
        $config = $this->serviceLocator->get('Config');
        if (!$this->getFileWriter()->is_dir($config['local_dir'])) {
            throw new RuntimeException('[ArchiveRepertory] ' . 'local_dir is not configured properly in module.config.php, check if the repertory exists' . $config['local_dir']);
        }

        return $config['local_dir'];
    }

    /**
     * Checks if the folders exist in the archive repertory, then creates them.
     *
     * @param string $archiveFolder
     *   Name of folder to create inside archive dir.
     * @param string $pathFolder
     *   (Optional) Name of folder where to create archive folder. If not set,
     *   the archive folder will be created in all derivative paths.
     * @return bool
     *   True if each path is created, Exception if an error occurs.
     */
    protected function createArchiveFolders($archiveFolder, $pathFolder = '')
    {
        if ($archiveFolder != '') {
            $folders = empty($pathFolder)
                ? $this->getDerivatives()
                : [['path' => $pathFolder]];
            foreach ($folders as $derivative) {
                $fullpath = $this->concatWithSeparator($derivative['path'], $archiveFolder);
                $result = $this->createFolder($fullpath);
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
     * @throws Omeka\File\Exception\RuntimeException
     */
    protected function createFolder($path)
    {
        if ($path == '') {
            return true;
        }

        $fileWriter = $this->getFileWriter();
        if ($fileWriter->fileExists($path)) {
            if ($fileWriter->is_dir($path)) {
                @chmod($path, 0755);
                if ($fileWriter->is_writable($path)) {
                    return true;
                }
                $msg = $this->translate('Error directory non writable: "%s".', $path);
                throw new RuntimeException('[ArchiveRepertory] ' . $msg);
            }
            $msg = $this->translate('Failed to create folder "%s": a file with the same name exists...', $path);
            throw new RuntimeException('[ArchiveRepertory] ' . $msg);
        }

        if (!$fileWriter->mkdir($path, 0755, true)) {
            $msg = sprintf($this->translate('Error making directory: "%s".'), $path);
            throw new RuntimeException('[ArchiveRepertory] ' . $msg);
        }
        @chmod($path, 0755);

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
    protected function moveFile($source, $destination, $path = '')
    {
        $fileWriter = $this->getFileWriter();
        $realSource = $this->concatWithSeparator($path, $source);
        $realDestination = $this->concatWithSeparator($path, $destination);
        if ($fileWriter->fileExists($realDestination)) {
            return true;
        }
        if (!$fileWriter->fileExists($realSource)) {
            $msg = sprintf(
                $this->translate('Error during move of a file from "%s" to "%s" (local dir: "%s"): source does not exist.'),
                $source,
                $destination,
                $path
            );
            $this->addError($msg);
            return false;
        }

        try {
            $result = $this->createFolder(dirname($realDestination));
            $result = $fileWriter->rename($realSource, $realDestination);
        } catch (Exception $e) {
            $msg = sprintf(
                $this->translate('Error during move of a file from "%s" to "%s" (local dir: "%s").'),
                $source,
                $destination,
                $path
            );
            $this->addError($msg);
            return false;
        }

        return $result;
    }

    protected function translate($string)
    {
        return $this->serviceLocator->get('MvcTranslator')->translate($string);
    }

    protected function getFileWriter()
    {
        static $fileWriter;
        if (is_null($fileWriter)) {
            $fileWriter = $this->serviceLocator->get('ArchiveRepertory\FileWriter');
        }
        return $fileWriter;
    }

    protected function getSetting($name)
    {
        // Tests doesn't pass with a static settings.
        return $this->serviceLocator->get('Omeka\Settings')->get($name);
    }

    protected function addError($msg)
    {
        $messenger = new Messenger();
        $messenger->addError($msg);
    }
}
