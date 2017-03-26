<?php
namespace ArchiveRepertory\File;

use Omeka\File\File;

class Manager extends \Omeka\File\Manager
{
    /**
     * Derivative extension for each type of files/derivatives, used when a
     * plugin doesn't use the Omeka standard ones. These lasts are used by
     * default. The dot before the extension should be specified if needed.
     *
     * This setting is used only when files are moved or deleted without use of
     * the original plugin, because the original plugin knows to create and
     * to delete them at the right place, of course.
     *
     * @var array
     */
    protected $_derivativeExtensionsByType = [];

    public function getBasename($name)
    {
        return substr($name, 0, strrpos($name, '.')) ? substr($name, 0, strrpos($name, '.')) : $name;
    }

    public function getStorageId(File $file, $media)
    {
        $folderName = $this->getItemFolderName($media->getItem());

        if ($this->getSetting('archive_repertory_file_keep_original_name') === '1') {
            $storageName = pathinfo($file->getSourceName(), PATHINFO_BASENAME);
            if ($folderName) {
                $storageName = "$folderName/$storageName";
            }
            $storageName = $this->checkExistingFile($storageName);
            $storageId = pathinfo($storageName, PATHINFO_FILENAME);
            if ($folderName) {
                $storageId = "$folderName/$storageId";
            }
        } else {
            $storageId = $file->getStorageId();
            if ($folderName) {
                $storageId = "$folderName/$storageId";
            }
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
     * @return string
     * The unique filename, that can be the same as input name.
     */
    public function checkExistingFile($filename)
    {
        // Get the partial path.
        $dirname = pathinfo($filename, PATHINFO_DIRNAME);

        // Get the real archive path.
        $filepath = $this->concatWithSeparator($this->getFullArchivePath('original'), $filename);
        $folder = pathinfo($filepath, PATHINFO_DIRNAME);
        $name = pathinfo($filepath, PATHINFO_FILENAME);
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);

        // Check folder for file with any extension or without any extension.
        $checkName = $name;
        $i = 1;
        $fileWriter = $this->getFileWriter();
        while ($fileWriter->glob($folder . DIRECTORY_SEPARATOR . $checkName . '{.*,.,\,,}', GLOB_BRACE)) {
            $checkName = $name . '.' . $i++;
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
     * @param object $item
     * @return string Unique sanitized name of the item.
     */
    public function getItemFolderName($item)
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
                $name = $this->_getRecordFolderNameFromMetadata($item);
        }

        $item_convert = $settings->get('archive_repertory_item_convert');
        return $this->_convertFilenameTo($name, $item_convert) ;
    }

    /**
     * Get the archive folder from a name path
     *
     * Example: 'original' can return '/var/www/omeka/files/original'.
     *
     * @param string $namePath the name of the path.
     * @return string
     *   Full archive path, or empty if none.
     */
    public function getFullArchivePath($namePath)
    {
        $archivePaths = $this->_getFullArchivePaths();
        return isset($archivePaths[$namePath])
            ? $archivePaths[$namePath]
            : '';
    }

    /**
     * Get the derivative filename from a filename and an extension.
     *
     * @param object $file
     * @return string
     *   Extension used for derivative files (usually "jpg" for images).
     */
    public function getDerivativeExtension($file)
    {
        return 'jpg';
    }

    /**
     * Moves/renames a file and its derivatives inside archive/files subfolders.
     *
     * New folders are created if needed. Old folders are removed if empty.
     * No update of the database is done.
     *
     * @param string $currentArchiveFilename
     *   Name of the current archive file to move.
     * @param string $newArchiveFilename
     *   Name of the new archive file, with archive folder if any (usually
     *   "collection/dc:identifier/").
     * @param optional string $derivativeExtension
     *   Extension of the derivative files to move, because it can be different
     *   from the new archive filename and it can't be determined here.
     * @return bool
     *   true if files are moved, else throw Omeka_Storage_Exception.
     */
    public function moveFilesInArchiveSubfolders($currentArchiveFilename, $newArchiveFilename, $derivativeExtension = '')
    {
        // A quick check to avoid some errors.
        if (trim($currentArchiveFilename) == '' || trim($newArchiveFilename) == '') {
            $msg = $this->translate('Cannot move file inside archive directory: no filename.');
            $this->_addError($msg);
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

        // Move the original file.
        $path = $this->getFullArchivePath('original');
        $result = $this->_createArchiveFolders($newArchiveFolder, $path);
        $this->_moveFile($currentArchiveFilename, $newArchiveFilename, $path);

        // If any, move derivative files using Omeka API.
        if ($derivativeExtension != '') {
            foreach ($this->_getFullArchivePaths() as $derivativeType => $path) {
                // Original is managed above.
                if ($derivativeType == 'original') {
                    continue;
                }

                // We create a folder in any case, even if there isn't any file
                // inside, in order to be fully compatible with any plugin that
                // manages base filename only.
                $result = $this->_createArchiveFolders($newArchiveFolder, $path);

                // Determine the current and new derivative filename, standard
                // or not.
                $currentDerivativeFilename = $this->_getDerivativeFilename($currentArchiveFilename, $derivativeExtension, $derivativeType);
                $newDerivativeFilename = $this->_getDerivativeFilename($newArchiveFilename, $derivativeExtension, $derivativeType);

                // Check if the derivative file exists or not to avoid some
                // errors when moving.

                if ($this->getFileWriter()->fileExists($this->concatWithSeparator($path, $currentDerivativeFilename))) {
                    $this->_moveFile($currentDerivativeFilename, $newDerivativeFilename, $path);
                }
            }
        }

        // Remove all old empty folders.
        if ($currentArchiveFolder != $newArchiveFolder) {
            $this->_removeArchiveFolders($currentArchiveFolder);
        }

        return true;
    }

    /**
     * Creates a unique name for a record folder from first metadata.
     *
     * If there isn't any identifier with the prefix, the record id will be used.
     * The name is sanitized and the possible prefix is removed.
     *
     * @param object $record
     * @return string Unique sanitized name of the record.
     */
    protected function _getRecordFolderNameFromMetadata($record)
    {
        $identifier = $this->_getRecordIdentifiers($record, null, true);

        return empty($identifier)
            ? (string) $record->getId()
            : $this->_sanitizeName($identifier);
    }

    /**
     * Gets identifiers of a record (with prefix if any, and only them).
     *
     * @param Record $record A collection or an item.
     * @param string $folder Optional. Allow to select a specific folder instead
     * of the default one.
     * @param bool $first Optional. Allow to return only the first value.
     * @return string|array.
     */
    protected function _getRecordIdentifiers($record, $folder = null, $first = false)
    {
        $api = $this->serviceLocator->get('Omeka\ApiManager');
        $settings = $this->serviceLocator->get('Omeka\Settings');

        $recordType = get_class($record);
        switch ($recordType) {
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
                return [(string) $record->getId()];
            default:
                foreach ($record->getValues() as $value) {
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
     * Get all archive folders with full paths, eventually with other derivative
     * folders. This function updates the derivative extensions too.
     *
     * @return array of folders.
     */
    protected function _getFullArchivePaths()
    {
        static $archivePaths = [];

        if (empty($archivePaths)) {
            $pathByTypes = $this->getPathsByType();
            $settings = $this->serviceLocator->get('Omeka\Settings');

            $storagePath = $this->_getLocalStoragePath();
            foreach ($pathByTypes as $name => $path) {
                $archivePaths[$name] = $this->concatWithSeparator($storagePath, $path);
            }

            $derivatives = explode(',', $this->getSetting('archive_repertory_derivative_folders'));
            foreach ($derivatives as $key => $value) {
                if (strpos($value, '|') === false) {
                    $name = trim($value);
                } else {
                    list($name, $extension) = explode('|', $value);
                    $name = trim($name);
                    $extension = trim($extension);
                    if ($extension != '') {
                        $this->_derivativeExtensionsByType[$name] = $extension;
                    }
                }
                $path = realpath($this->concatWithSeparator($storagePath, $name));
                if (!empty($name) && !empty($path) && $path != '/') {
                    $archivePaths[$name] = $path;
                } else {
                    unset($derivatives[$key]);
                    $settings->set('archive_repertory_derivative_folders', implode(', ', $derivatives));
                }
            }
        }

        return $archivePaths;
    }

    /**
     * Get the list of paths by type (original and standard thumbnail types).
     *
     * @internal In Omeka S, the name and the path are the same.
     *
     * @return array
     */
    protected function getPathsByType()
    {
        $pathsByType = array_keys($this->config['thumbnail_types']);
        array_unshift($pathsByType, 'original');
        return array_combine($pathsByType, $pathsByType);
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
            throw new  \Omeka\File\Exception\RuntimeException('[ArchiveRepertory] ' . 'local_dir is not configured properly in module.config.php, check if the repertory exists'.$config['local_dir']);
        }

        return $config['local_dir'];
    }

    /**
     * Get the derivative filename from a filename and an extension. A check can
     * be done on the derivative type to allow use of a non standard extension,
     * for example with a plugin that doesn't follow standard naming.
     *
     * @param string $filename
     * @param string $defaultExtension
     * @param string $derivativeType
     *   The derivative type allows to use a non standard extension.
     * @return string
     *   Filename with the new extension.
     */
    protected function _getDerivativeFilename($filename, $defaultExtension, $derivativeType = null)
    {
        $base = pathinfo($filename, PATHINFO_EXTENSION) ? substr($filename, 0, strrpos($filename, '.')) : $filename;
        $fullExtension = !is_null($derivativeType) && isset($this->_derivativeExtensionsByType[$derivativeType])
            ? $this->_derivativeExtensionsByType[$derivativeType]
            : '.' . $defaultExtension;
        return $base . $fullExtension;
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
                ? $this->_getFullArchivePaths()
                : [$pathFolder];
            foreach ($folders as $path) {
                $fullpath = $this->concatWithSeparator($path, $archiveFolder);
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
     * @return bool True if the path is created, Exception if an error occurs.
     */
    protected function _createFolder($path)
    {
        if ($path != '') {
            $fileWriter = $this->getFileWriter();
            if ($fileWriter->fileExists($path)) {
                if ($fileWriter->is_dir($path)) {
                    @chmod($path, 0755);
                    if ($fileWriter->is_writable($path)) {
                        return true;
                    }
                    $msg = $this->translate('Error directory non writable: "%s".', $path);
                    throw new \Omeka\File\Exception\RuntimeException('[ArchiveRepertory] ' . $msg);
                }
                $msg = $this->translate('Failed to create folder "%s": a file with the same name exists...', $path);
                throw new \Omeka\File\Exception\RuntimeException('[ArchiveRepertory] ' . $msg);
            }

            if (!$this->getFileWriter()->mkdir($path, 0755, true)) {
                $msg = sprintf($this->translate('Error making directory: "%s".'), $path);
                throw new \Omeka\File\Exception\RuntimeException('[ArchiveRepertory] ' . $msg);
            }
            @chmod($path, 0755);
        }
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
            foreach ($this->_getFullArchivePaths() as $path) {
                $folderPath = $this->concatWithSeparator($path, $archiveFolder);
                if (realpath($path) != realpath($folderPath)) {
                    $this->_removeFolder($folderPath);
                }
            }
        }
        return true;
    }

    /**
     * Checks and removes an empty folder.
     *
     * @note Currently, Omeka API doesn't provide a function to remove a folder.
     *
     * @param string $path Full path of the folder to remove.
     * @param bool $evenNonEmpty Remove non empty folder
     *   This parameter can be used with non standard folders.
     */
    protected function _removeFolder($path, $evenNonEmpty = false)
    {
        $path = realpath($path);
        if (strlen($path)
            && $path != '/'
            && file_exists($path)
            && is_dir($path)
            && is_readable($path)
            && ((count(@scandir($path)) == 2) // Only '.' and '..'.
                || $evenNonEmpty)
            && is_writable($path)
        ) {
            $this->_rrmdir($path);
        }
    }

    /**
     * Removes directories recursively.
     *
     * @param string $dirPath Directory name.
     * @return bool
     */
    protected function _rrmdir($dirPath)
    {
        $files = array_diff(scandir($dirPath), array('.', '..'));
        foreach ($files as $file) {
            $path = $dirPath . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->_rrmDir($path);
            } else {
                unlink($path);
            }
        }
        return rmdir($dirPath);
    }

    /**
     * Process the move operation according to admin choice.
     *
     * @param string $source
     * @param string $destination
     * @param string $path
     * @return bool True if success, else throw Omeka_Storage_Exception.
     */
    protected function _moveFile($source, $destination, $path = '')
    {
        $realSource = $this->concatWithSeparator($path, $source);
        $realDestination = $this->concatWithSeparator($path, $destination);
        if ($this->getFileWriter()->fileExists($realDestination)) {
            return true;
        }
        if (!$this->getFileWriter()->fileExists($realSource)) {
            $msg = sprintf(
                $this->translate('Error during move of a file from "%s" to "%s" (local dir: "%s"): source does not exist.'),
                $source,
                $destination,
                $path
            );
            $this->_addError($msg);
        }

        $result = null;
        try {
            $result = $this->getFileWriter()->rename($realSource, $realDestination);
        } catch (Omeka_Storage_Exception $e) {
            $msg = sprintf(
                $this->translate('Error during move of a file from "%s" to "%s" (local dir: "%s").'),
                $source,
                $destination,
                $path
            );
            $this->_addError($msg);
        }

        return $result;
    }

    protected function getFileWriter()
    {
        return $this->serviceLocator->get('ArchiveRepertory\FileWriter');
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
        $messenger = new Messenger;
        $messenger->addError($msg);
    }
}
