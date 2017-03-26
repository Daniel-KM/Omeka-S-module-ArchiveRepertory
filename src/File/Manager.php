<?php
namespace ArchiveRepertory\File;

use Omeka\Entity\Item;
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
        return substr($name, 0, strrpos($name, '.')) ? substr($name, 0, strrpos($name, '.')) : $name;
    }

    public function getStorageId(Media $media)
    {
        $folderName = $this->getItemFolderName($media->getItem());
        $storageId = $media->getStorageId();

        if ($this->getSetting('archive_repertory_file_keep_original_name') === '1') {
            $storageName = pathinfo($media->getSource(), PATHINFO_BASENAME);
            if ($folderName) {
                $storageName = "$folderName/$storageName";
            }
            $storageName = $this->checkExistingFile($storageName);
            $storageId = pathinfo($storageName, PATHINFO_FILENAME);
            if ($folderName) {
                $storageId = "$folderName/$storageId";
            }
        } elseif ($folderName) {
            $storageId = "$folderName/$storageId";
        }

        return $storageId;
    }

    /**
     * Checks if the file is a duplicate one. In that case, a suffix is added.
     *
     * Check is done on the basename, without extension, to avoid issues with
     * derivatives.
     *
     * @internal No check via database, because the file can be unsaved yet.
     *
     * @param string $filename
     * @return string The unique filename, that can be the same as input name.
     */
    public function checkExistingFile($filename)
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

        return ($dirname ? $dirname . DIRECTORY_SEPARATOR : '')
            . $checkName
            . ($extension ? '.' . $extension : '');
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
     * Gets item folder name from an item and create folder if needed.
     *
     * @param Item $item
     * @return string Unique sanitized name of the item.
     */
    public function getItemFolderName(Item $item)
    {
        $settings = $this->serviceLocator->get('Omeka\Settings');

        $folder = $settings->get('archive_repertory_item_folder');
        if (!$folder) {
            return '';
        }

        switch ($folder) {
            case 'id':
                return (string) $item->getId();
            case 'none':
            case '':
                return '';
            default:
                $name = $this->_getResourceFolderNameFromMetadata($item);
        }

        $item_convert = $settings->get('archive_repertory_item_convert');
        return $this->_convertFilenameTo($name, $item_convert) ;
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
    public function moveFilesInArchiveSubfolders($currentArchiveFilename, $newArchiveFilename)
    {
        // A quick check to avoid some errors.
        if (trim($currentArchiveFilename) == '' || trim($newArchiveFilename) == '') {
            $msg = $this->translate('Cannot move file inside archive directory: no filename.');
            $this->_addError($msg);
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
                $this->_moveFile($currentDerivativeFilename, $newDerivativeFilename, $derivative['path']);
            }
        }

        // Remove all old empty folders.
        if ($currentArchiveFolder != $newArchiveFolder) {
            $this->_removeArchiveFolders($currentArchiveFolder);
        }

        return true;
    }

    /**
     * Creates a unique name for a resource folder from first metadata.
     *
     * If there isn't any identifier with the prefix, the resource id will be used.
     * The name is sanitized and the possible prefix is removed.
     *
     * @param Resource $resource
     * @return string Unique sanitized name of the resource.
     */
    protected function _getResourceFolderNameFromMetadata(Resource $resource)
    {
        $identifier = $this->_getResourceIdentifiers($resource, null, true);

        return empty($identifier)
            ? (string) $resource->getId()
            : $this->_sanitizeName($identifier);
    }

    /**
     * Gets identifiers of a resource (with prefix if any, and only them).
     *
     * @param Resource $resource An item set or an item.
     * @param string $folder Optional. Allow to select a specific folder instead
     * of the default one.
     * @param bool $first Optional. Allow to return only the first value.
     * @return string|array.
     */
    protected function _getResourceIdentifiers(Resource $resource, $folder = null, $first = false)
    {
        $api = $this->serviceLocator->get('Omeka\ApiManager');
        $settings = $this->serviceLocator->get('Omeka\Settings');

        $resourceType = get_class($resource);
        switch ($resourceType) {
            case 'Omeka\Entity\Item':
                $folder = is_null($folder) ? $settings->get('archive_repertory_item_folder') : $folder;
                $prefix = $settings->get('archive_repertory_item_prefix');
                break;
            default:
                return [];
        }

        switch ($folder) {
            case '':
            case 'None':
                return [];
            case 'id':
                return [(string) $resource->getId()];
            default:
                foreach ($resource->getValues() as $value) {
                    if ($value->getProperty()->getId() != $folder) {
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
    protected function _sanitizeName($string)
    {
        $string = strip_tags($string);
        // The first character is a space and the last one is a no-break space.
        $string = trim($string, ' /\\?<>:*%|"\'`&; ');
        $string = preg_replace('/[\(\{]/', '[', $string);
        $string = preg_replace('/[\)\}]/', ']', $string);
        $string = preg_replace('/[[:cntrl:]\/\\\?<>:\*\%\|\"\'`\&\;#+\^\$\s]/', ' ', $string);
        return substr(preg_replace('/\s+/', ' ', $string), -250);
    }

    /**
     * Returns a formatted string for folder or file name.
     *
     * @internal The string should be already sanitized.
     * The string should be a simple name, not a full path or url, because "/",
     * "\" and ":" are removed (so a path should be sanitized by part).
     *
     * @see ArchiveRepertoryPlugin::_sanitizeName()
     *
     * @param string $string The string to sanitize.
     * @param string $format The format to convert to.
     * @return string The sanitized string.
     */
    protected function _convertFilenameTo($string, $format)
    {
        switch ($format) {
            case 'Keep name':
                return $string;
            case 'First letter':
                return $this->_convertFirstLetterToAscii($string);
            case 'Spaces':
                return $this->_convertSpacesToUnderscore($string);
            case 'First and spaces':
                $string = $this->_convertFilenameTo($string, 'First letter');
                return $this->_convertSpacesToUnderscore($string);
            case 'Full':
            default:
                return $this->_convertNameToAscii($string);
        }
    }

    /**
     * Returns an unaccentued string for folder or file name.
     *
     * @internal The string should be already sanitized.
     *
     * @see ArchiveRepertoryPlugin::_convertFilenameTo()
     *
     * @param string $string The string to convert to ascii.
     * @return string The converted string to use as a folder or a file name.
     */
    protected function _convertNameToAscii($string)
    {
        $string = htmlentities($string, ENT_NOQUOTES, 'utf-8');
        $string = preg_replace('#\&([A-Za-z])(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml)\;#', '\1', $string);
        $string = preg_replace('#\&([A-Za-z]{2})(?:lig)\;#', '\1', $string);
        $string = preg_replace('#\&[^;]+\;#', '_', $string);
        $string = preg_replace('/[^[:alnum:]\[\]_\-\.#~@+:]/', '_', $string);
        return substr(preg_replace('/_+/', '_', $string), -250);
    }

    /**
     * Returns a formatted string for folder or file path (first letter only).
     *
     * @internal The string should be already sanitized.
     *
     * @see ArchiveRepertoryPlugin::_convertFilenameTo()
     *
     * @param string $string The string to sanitize.
     * @return string The sanitized string.
     */
    protected function _convertFirstLetterToAscii($string)
    {
        $first = $this->_convertNameToAscii($string);
        if (empty($first)) {
            return '';
        }
        return $first[0] . $this->_substr_unicode($string, 1);
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
    protected function _substr_unicode($string, $start, $length = null)
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
     * @see ArchiveRepertoryPlugin::_convertFilenameTo()
     *
     * @param string $string The string to sanitize.
     * @return string The sanitized string.
     */
    protected function _convertSpacesToUnderscore($string)
    {
        return preg_replace('/\s+/', '_', $string);
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
            $settings = $this->serviceLocator->get('Omeka\Settings');
            $storagePath = $this->_getLocalStoragePath();

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
        $storagePath = $this->_getLocalStoragePath();
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
     * Get the local storage path (by default FILES_DIR).
     *
     * @return array
     */
    protected function _getLocalStoragePath()
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
    protected function _createArchiveFolders($archiveFolder, $pathFolder = '')
    {
        if ($archiveFolder != '') {
            $folders = empty($pathFolder)
                ? $this->getDerivatives()
                : [['path' => $pathFolder]];
            foreach ($folders as $derivative) {
                $fullpath = $this->concatWithSeparator($derivative['path'], $archiveFolder);
                $result = $this->_createFolder($fullpath);
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
    protected function _createFolder($path)
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
     * Removes empty folders in the archive repertory.
     *
     * @param string $archiveFolder Name of folder to delete, without files dir.
     * @return bool True if the path is created, Exception if an error occurs.
     */
    protected function _removeArchiveFolders($archiveFolder)
    {
        if (($archiveFolder != '.')
            && ($archiveFolder != '..')
            && ($archiveFolder != DIRECTORY_SEPARATOR)
            && ($archiveFolder != '')
        ) {
            $fileWriter = $this->getFileWriter();
            foreach ($this->getDerivatives() as $derivative) {
                $folderPath = $this->concatWithSeparator($derivative['path'], $archiveFolder);
                if (realpath($derivative['path']) != realpath($folderPath)) {
                    $fileWriter->removeDir($folderPath, true);
                }
            }
        }
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
    protected function _moveFile($source, $destination, $path = '')
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
            $this->_addError($msg);
            return false;
        }

        try {
            $result = $this->_createFolder(dirname($realDestination));
            $result = $fileWriter->rename($realSource, $realDestination);
        } catch (Exception $e) {
            $msg = sprintf(
                $this->translate('Error during move of a file from "%s" to "%s" (local dir: "%s").'),
                $source,
                $destination,
                $path
            );
            $this->_addError($msg);
            return false;
        }

        return $result;
    }

    protected function getFileWriter()
    {
        static $fileWriter;
        if (is_null($fileWriter)) {
            $fileWriter = $this->serviceLocator->get('ArchiveRepertory\FileWriter');
        }
        return $fileWriter;
    }

    protected function translate($string)
    {
        return $this->serviceLocator->get('MvcTranslator')->translate($string);
    }

    protected function getSetting($name)
    {
        return $this->serviceLocator->get('Omeka\Settings')->get($name);
    }

    protected function _addError($msg)
    {
        $messenger = new Messenger();
        $messenger->addError($msg);
    }
}
