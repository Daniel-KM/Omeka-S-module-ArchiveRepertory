<?php
/*
 * Archive Repertory
 *
 * Keeps original names of files and put them in a hierarchical structure.
 *
 * Copyright BibLibre, 2016
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */
namespace ArchiveRepertory;
use Omeka\Module\AbstractModule;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;
use Zend\Mvc\Controller\AbstractController;
use ArchiveRepertory\Form\ConfigArchiveRepertoryForm;
use Zend\View\Model\ViewModel;
use ArchiveRepertory\Service\FileArchiveManagerFactory;
use Zend\EventManager\SharedEventManagerInterface;
use Omeka\Event\Event;
use Zend\Math\Rand;
use Zend\Mvc\MvcEvent;
use Omeka\Mvc\Application;

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'ArchiveRepertoryFunctions.php';

require __DIR__.'/vendor/autoload.php';

class Module extends AbstractModule
{
    use \OmekaTestHelper\File\StaticFileWriterTrait;
    /**
     * @var array This plugin's options.
     */
    protected $_options = [
                           // Items options.
                           'archive_repertory_item_folder' => 'id',
                           'archive_repertory_item_prefix' => '',
                           'archive_repertory_item_convert' => 'Full',
                           // Files options.
                           'archive_repertory_file_keep_original_name' => '1',
                           'archive_repertory_file_convert' => 'Full',
                           'archive_repertory_file_base_original_name' => false,
                           // Other derivative folders.
                           'archive_repertory_derivative_folders' => '',
                           'archive_repertory_move_process' => 'internal',
                           // Max download without captcha (default to 30 MB).
//                           'archive_repertory_download_max_free_download' => 30000000,
                           'archive_repertory_legal_text' => 'I agree with terms of use.'
    ];
    static public  $config;
    /**
     * Default folder paths for each default type of files/derivatives.
     *
     * @see application/models/File::_pathsByType()
     * @var array
     */
    static private $_pathsByType = [
                                    'original' => 'original',
                                    'fullsize' => 'large',
                                    'thumbnail' => 'medium',
                                    'square_thumbnails' => 'square'];


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
    private $_derivativeExtensionsByType = [];

    public function onBootstrap(MvcEvent $event)
    {

        parent::onBootstrap($event);
    }


    public function install(ServiceLocatorInterface $serviceLocator) {
        $this->_installOptions($serviceLocator);
        $config = $serviceLocator->get('Config');
    }


    protected function _installOptions($serviceLocator) {
        foreach ($this->_options as $key => $value) {
            $serviceLocator->get('Omeka\Settings')->set($key, $value);
        }
    }



    protected function _uninstallOptions($serviceLocator) {
        foreach ($this->_options as $key => $value) {
            $serviceLocator->get('Omeka\Settings')->delete($key);
        }
    }


    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $this->_uninstallOptions($serviceLocator);
    }


    public function getConfigForm(PhpRenderer $renderer)
    {
        $form = new ConfigArchiveRepertoryForm($this->getServiceLocator(),
                                               $this->_getLocalStoragePath(),
                                               $this->_checkUnicodeInstallation());
        return $renderer->render( 'plugins/archive-repertory-config-form',
                                 [
                                  'form' => $form
                                 ]);
    }

    /**
     * Saves plugin configuration page and creates folders if needed.
     *
     * @param array Options set in the config form.
     */
    public function handleConfigForm(AbstractController $controller)
    {
        $post =$controller->getRequest()->getPost();
        foreach ($this->_options as $optionKey => $optionValue) {
            if (isset($post[$optionKey])) {
                $this->setOption($this->getServiceLocator(),$optionKey, $post[$optionKey]);
            }
        }

    }
    public function filenameMatchingSourceName($media) {
        return
            strstr($media->getSource(),'.',true) == basename_special(strstr($media->getFilename(),'.',true));
    }

    /**
     * Manages folders for attached files of items.
     */
    public function afterSaveItem(\Zend\EventManager\Event $event)
    {
        $serviceLocator = $this->getServiceLocator();
        $item = $this->entityApi()->find('Omeka\Entity\Item',$event->getParam('request')->getId());

        $archiveFolder = $this->getItemFolderName($item);

        // Check if files are already attached and if they are at the right place.
        $files = $item->getMedia();

        $filemanager = $serviceLocator->get('Omeka\File\Manager');
        foreach ($files as $file) {
            if ($file->getIngester() != 'upload')
                continue;
            $filemanager->setMedia($file);
            $newFilename = $this->concatWithSeparator($archiveFolder,basename_special($file->getFilename()));
            if (($this->getOption('archive_repertory_file_keep_original_name') === '1') &&
                !$this->filenameMatchingSourceName($file)) {
               $newFilename = $this->checkExistingFile($filemanager->getStoragePath('',$file->getSource())) ;
            }
            if (($this->getOption('archive_repertory_file_keep_original_name') !== '1') &&
                $this->filenameMatchingSourceName($file)) {
                $extension = pathinfo($file->getFilename(), PATHINFO_EXTENSION);
                $newFilename= $this->concatWithSeparator($archiveFolder,bin2hex(Rand::getBytes(20))).'.'.$extension;
            }
            if ($file->getFilename() != $newFilename) {
                // Check if the original file exists, else this is an undetected
                // error during the convert process.
                $path = $this->_getFullArchivePath('original');
                if (!self::getFileWriter()->fileExists($this->concatWithSeparator($path, $file->getFilename()))) {
                    $msg = $this->translate('This file is not present in the original directory :'.$path.'/'.$file->getFilename());
                    $msg .= ' ' .$this->translate('There was an undetected error before storage, probably during the convert process.');
                    throw new \Omeka\File\Exception\RuntimeException('[ArchiveRepertory] ' . $msg);
                }

                $result = $this->_moveFilesInArchiveSubfolders(
                                                               $file->getFilename(),
                                                               $newFilename,
                                                               $this->_getDerivativeExtension($file));
                if (!$result) {
                    $msg = $this->translate('Cannot move files inside archive directory.');
                    throw new  \Omeka\File\Exception\RuntimeException('[ArchiveRepertory] ' . $msg);
                }

                // Update file in Omeka database immediately for each file.
                $file->setFilename($newFilename);
                // As it's not a file hook, the file is not automatically saved.
                $em= $this->getServiceLocator()->get('Omeka\EntityManager');
                $em->persist($file);
                $em->flush();
            }
        }
    }


    /**
     * Gets identifiers of a record (with prefix if any, and only them).
     *
     * @param Record $record A collection or an item.
     * @param string $folder Optional. Allow to select a specific folder instead
     * of the default one.
     * @param boolean $first Optional. Allow to return only the first value.
     *
     * @return string|array.
     */
    public function _getRecordIdentifiers($record, $folder = null, $first = false)
    {
        $api = $this->serviceLocator->get('Omeka\ApiManager');
        $recordType = get_class($record);
        switch ($recordType) {

            case 'Omeka\Entity\Item':
                $folder = is_null($folder) ? $this->getOption('archive_repertory_item_folder') : $folder;
                $prefix = $this->getOption('archive_repertory_item_prefix');
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
                    if ($value->getProperty()->getId() != $folder)
                        continue;
                    if ($prefix) {
                        preg_match('/^'.$prefix.'(.*)/',$value->getValue(),$matches);
                        if (isset($matches[1])) return trim($matches[1]);
                        continue;
                    }
                    return $value->getValue();
                }
                return '';
        }
    }



    /**
     * Gets item folder name from an item and create folder if needed.
     *
     * @param object $item
     *
     * @return string Unique sanitized name of the item.
     */
    public function getItemFolderName($item)
    {
        $folder = $this->getOption('archive_repertory_item_folder');

        switch ($folder) {
            case 'id':
                return (string) $item->getId();
            case 'none':
            case '':
                return '';
            default:
                $name = $this->_getRecordFolderNameFromMetadata(
                                                                $item,
                                                                $folder,
                                                                $this->getOption('archive_repertory_item_prefix')
                );
        }

        return $this->_convertFilenameTo($name, $this->getOption('archive_repertory_item_convert'));
    }



    public function getOption($key,$serviceLocator=null) {
        if (!$serviceLocator)
            $serviceLocator = $this->getServiceLocator();
        return $serviceLocator->get('Omeka\Settings')->get($key);
    }




    /**
     * Creates a unique name for a record folder from first metadata.
     *
     * If there isn't any identifier with the prefix, the record id will be used.
     * The name is sanitized and the possible prefix is removed.
     *
     * @param object $record
     * @param integer $elementId
     * @param string $prefix
     *
     * @return string Unique sanitized name of the record.
     */
    protected function _getRecordFolderNameFromMetadata($record, $elementId, $prefix)
    {
        $identifier = $this->_getRecordIdentifiers($record, null, true);

        return empty($identifier)
            ? (string) $record->getId()
            : $this->_sanitizeName($identifier);
    }



    /**
     * Checks and creates a folder.
     *
     * @note Currently, Omeka API doesn't provide a function to create a folder.
     *
     * @param string $path Full path of the folder to create.
     *
     * @return boolean True if the path is created, Exception if an error occurs.
     */
    protected function _createFolder($path)
    {
        if ($path != '') {
            if (self::getFileWriter()->fileExists($path)) {
                if (self::getFileWriter()->is_dir($path)) {
                    @chmod($path, 0755);
                    if (self::getFileWriter()->is_writable($path)) {
                        return true;
                    }
                    $msg = $this->translate('Error directory non writable: "%s".', $path);
                    throw new \Omeka\File\Exception\RuntimeException('[ArchiveRepertory] ' . $msg);
                }
                $msg = $this->translate('Failed to create folder "%s": a file with the same name exists...', $path);
                throw new \Omeka\File\Exception\RuntimeException('[ArchiveRepertory] ' . $msg);
            }

            if (!self::getFileWriter()->mkdir($path, 0755, true)) {
                $msg = $this->translate('Error making directory: "%s".', $path);
                throw new \Omeka\File\Exception\RuntimeException('[ArchiveRepertory] ' . $msg);
            }
            @chmod($path, 0755);
        }
        return true;
    }

    /**
     * Checks and removes an empty folder.
     *
     * @note Currently, Omeka API doesn't provide a function to remove a folder.
     *
     * @param string $path Full path of the folder to remove.
     * @param boolean $evenNonEmpty Remove non empty folder
     *   This parameter can be used with non standard folders.
     *
     * @return void.
     */
    protected function _removeFolder($path, $evenNonEmpty = false)
    {
        $path = realpath($path);
        if (file_exists($path)
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
     *
     * @return boolean
     */
    protected function _rrmdir($dirPath)
    {
        $glob = glob($dirPath);
        foreach ($glob as $g) {
            if (!is_dir($g)) {
                unlink($g);
            }
            else {
                $this->_rrmdir("$g/*");
                rmdir($g);
            }
        }
        return true;
    }

    /**
     * Get the local storage path (by default FILES_DIR).
     */
    protected function _getLocalStoragePath()
    {
        $config = $this->getServiceLocator()->get('Config');
        if (!self::getFileWriter()->is_dir($config['local_dir']))
            throw new  \Omeka\File\Exception\RuntimeException('[ArchiveRepertory] ' . 'local_dir is not configured properly in module.config.php, check if the repertory exists'.$config['local_dir']);

        return $config['local_dir'];

    }

    /**
     * Get the archive folder from a name path
     *
     * Example: 'original' can return '/var/www/omeka/files/original'.
     *
     * @param string $namePath the name of the path.
     *
     * @return string
     *   Full archive path, or empty if none.
     */
    protected function _getFullArchivePath($namePath)
    {
        $archivePaths = $this->_getFullArchivePaths();
        return isset($archivePaths[$namePath])
            ? $archivePaths[$namePath]
            : '';
    }


    protected function concatWithSeparator($first_dir, $second_dir)
    {
        if (!$first_dir || $first_dir=='')
            return $second_dir;
        if (!$second_dir || $second_dir=='')
            return $first_dir;
        if (substr($first_dir,-1)==DIRECTORY_SEPARATOR)
            $first_dir = substr($first_dir,0,-1);

        if ($second_dir[0]==DIRECTORY_SEPARATOR)
            $second_dir = substr($second_dir,1);

        return $first_dir.DIRECTORY_SEPARATOR.$second_dir;

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
            $storagePath = $this->_getLocalStoragePath();
            foreach (self::$_pathsByType as $name => $path) {
                $archivePaths[$name] = $this->concatWithSeparator($storagePath, $path);
            }

            $derivatives = explode(',', $this->getOption('archive_repertory_derivative_folders'));
            foreach ($derivatives as $key => $value) {
                if (strpos($value, '|') === false) {
                    $name = trim($value);
                }
                else {
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
                }
                else {
                    unset($derivatives[$key]);
                    $this->setOption($this->getServiceLocator(),'archive_repertory_derivative_folders', implode(', ', $derivatives));
                }
            }
        }

        return $archivePaths;
    }

    /**
     * Checks if the folders exist in the archive repertory, then creates them.
     *
     * @param string $archiveFolder
     *   Name of folder to create inside archive dir.
     * @param string $pathFolder
     *   (Optional) Name of folder where to create archive folder. If not set,
     *   the archive folder will be created in all derivative paths.
     *
     * @return boolean
     *   True if each path is created, Exception if an error occurs.
     */
    protected function _createArchiveFolders($archiveFolder, $pathFolder = '')
    {
        if ($archiveFolder != '') {
            $folders = empty($pathFolder)
                ? $this->_getFullArchivePaths()
                : array($pathFolder);
            foreach ($folders as $path) {
                $fullpath = $this->concatWithSeparator($path, $archiveFolder);
                $result = $this->_createFolder($fullpath);
            }
        }
        return true;
    }

    /**
     * Removes empty folders in the archive repertory.
     *
     * @param string $archiveFolder Name of folder to delete, without files dir.
     *
     * @return boolean True if the path is created, Exception if an error occurs.
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
     * Get the derivative filename from a filename and an extension. A check can
     * be done on the derivative type to allow use of a non standard extension,
     * for example with a plugin that doesn't follow standard naming.
     *
     * @param string $filename
     * @param string $defaultExtension
     * @param string $derivativeType
     *   The derivative type allows to use a non standard extension.
     *
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
     * Get the derivative filename from a filename and an extension.
     *
     * @param object $file
     *
     * @return string
     *   Extension used for derivative files (usually "jpg" for images).
     */
    protected function _getDerivativeExtension($file)
    {
        $filemanager = (new FileArchiveManagerFactory())->createService($this->getServiceLocator());
        return $filemanager->_getDerivativeExtension($file);
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
     *
     * @return boolean
     *   true if files are moved, else throw Omeka_Storage_Exception.
     */
    protected function _moveFilesInArchiveSubfolders($currentArchiveFilename, $newArchiveFilename, $derivativeExtension = '')
    {
        // A quick check to avoid some errors.
        if (trim($currentArchiveFilename) == '' || trim($newArchiveFilename) == '') {
            $msg = $this->translate('Cannot move file inside archive directory: no filename.');
            throw new Omeka_Storage_Exception('[ArchiveRepertory] ' . $msg);
        }

        // Move file only if it is not in the right place.
        // If the main file is at the right place, this is always the case for
        // the derivatives.
        $newArchiveFilename = str_replace('//','/',$newArchiveFilename);
        if ($currentArchiveFilename == $newArchiveFilename) {
            return true;
        }

        $currentArchiveFolder = dirname($currentArchiveFilename);
        $newArchiveFolder = dirname($newArchiveFilename);

        // Move the original file.
        $path = $this->_getFullArchivePath('original');
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

                if (self::getFileWriter()->fileExists($this->concatWithSeparator($path, $currentDerivativeFilename))) {

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
     * Process the move operation according to admin choice.
     *
     * @return boolean True if success, else throw Omeka_Storage_Exception.
     */
    protected function _moveFile($source, $destination, $path)
    {

        $realSource = $this->concatWithSeparator($path, $source);
        $realDestination = $this->concatWithSeparator($path, $destination);
        if (!self::getFileWriter()->fileExists($realSource)) {
            $msg = $this->translate('Error during move of a file from "%s" to "%s" (local dir: "%s"): source does not exist.',
                                    $source, $destination, $path);
            throw new Omeka_Storage_Exception('[ArchiveRepertory] ' . $msg);
        }
        $serviceLocator = $this->getServiceLocator();
        $result = null;
        try {
            $result = self::getFileWriter()->rename($realSource, $realDestination);

        } catch (Omeka_Storage_Exception $e) {
            $msg = $serviceLocator->get('MvcTranslator')->translate('Error during move of a file from "%s" to "%s" (local dir: "%s").',
                                                                    $source, $destination, $path);
            throw new Omeka_Storage_Exception($e->getMessage() . "\n" . '[ArchiveRepertory] ' . $msg);
        }

        return $result;
    }

    /**
     * Returns a sanitized string for folder or file path.
     *
     * The string should be a simple name, not a full path or url, because "/",
     * "\" and ":" are removed (so a path should be sanitized by part).
     *
     * @param string $string The string to sanitize.
     *
     * @return string The sanitized string.
     */
    public function _sanitizeName($string)
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
     *      *
     * @see ArchiveRepertoryPlugin::_sanitizeName()
     *
     * @param string $string The string to sanitize.
     * @param string $format The format to convert to.
     *
     * @return string The sanitized string.
     */
    public function _convertFilenameTo($string, $format)
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
     *
     * @return string The converted string to use as a folder or a file name.
     */
    private function _convertNameToAscii($string)
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
     *
     * @return string The sanitized string.
     */
    private function _convertFirstLetterToAscii($string)
    {
        $first = $this->_convertNameToAscii($string);
        if (empty($first)) {
            return '';
        }
        return $first[0] . $this->_substr_unicode($string, 1);
    }

    /**
     * Returns a formatted string for folder or file path (spaces only).
     *
     * @internal The string should be already sanitized.
     *
     * @see ArchiveRepertoryPlugin::_convertFilenameTo()
     *
     * @param string $string The string to sanitize.
     *
     * @return string The sanitized string.
     */
    private function _convertSpacesToUnderscore($string)
    {
        return preg_replace('/\s+/', '_', $string);
    }

    /**
     * Get a sub string from a string when mb_substr is not available.
     *
     * @see http://www.php.net/manual/en/function.mb-substr.php#107698
     *
     * @param string $string
     * @param integer $start
     * @param integer $length (optional)
     *
     * @return string
     */
    protected function _substr_unicode($string, $start, $length = null) {
        return join('', array_slice(
                                    preg_split("//u", $string, -1, PREG_SPLIT_NO_EMPTY), $start, $length));
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
     *
     * @return string
     * The unique filename, that can be the same as input name.
     */
    public function checkExistingFile($filename)
    {

        // Get the partial path.
        $dirname = pathinfo($filename, PATHINFO_DIRNAME);

        // Get the real archive path.
        $filepath = $this->concatWithSeparator($this->_getFullArchivePath('original'), $filename);
        $folder = pathinfo($filepath, PATHINFO_DIRNAME);
        $name = pathinfo($filepath, PATHINFO_FILENAME);
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);

        // Check folder for file with any extension or without any extension.
        $checkName = $name;
        $i = 1;
        while (self::getFileWriter()->glob($folder . DIRECTORY_SEPARATOR . $checkName . '{.*,.,\,,}', GLOB_BRACE)) {
            $checkName = $name . '.' . $i++;
        }

        return ($dirname ? $dirname . DIRECTORY_SEPARATOR : '')
            . $checkName
            . ($extension ? '.' . $extension : '');
    }

    /**
     * Checks if all the system (server + php + web environment) allows to
     * manage Unicode filename securely.
     *
     * @internal This function simply checks the true result of functions
     * escapeshellarg() and touch with a non Ascii filename.
     *
     * @return array of issues.
     */
    protected function _checkUnicodeInstallation()
    {
        $result = [];

        // First character check.
        $filename = 'éfilé.jpg';
        if (basename($filename) != $filename) {
            $result['ascii'] = $this->translate('An error occurs when testing function "basename(\'%s\')".', $filename);
        }

        // Command line via web check (comparaison with a trivial function).
        $filename = "File~1 -À-é-ï-ô-ů-ȳ-Ø-ß-ñ-Ч-Ł-'.Test.png";

        if (escapeshellarg($filename) != escapeshellarg_special($filename)) {
            $result['cli'] = $this->translate('An error occurs when testing function "escapeshellarg(\'%s\')".', $filename);
        }

        // File system check.
        $filepath = $this->concatWithSeparator(sys_get_temp_dir(), $filename);
        if (!(touch($filepath) && self::getFileWriter()->fileExists($filepath))) {
            $result['fs'] = $this->translate('A file system error occurs when testing function "touch \'%s\'".', $filepath);
        }

        return $result;
    }

    public function setOption($serviceLocator,$name,$value) {
        return  $serviceLocator->get('Omeka\Settings')->set($name,$value);
    }

    public function getConfig() {
        if (self::$config)
            return self::$config;
        return include __DIR__ . '/config/module.config.php';
    }

    public static function setConfig($config) {
        self::$config=$config;
    }


    public function translate($string,$options='',$serviceLocator=null) {

        if (!$serviceLocator)
            $serviceLocator = $this->getServiceLocator();

        return $serviceLocator->get('MvcTranslator')->translate($string,$options);
    }


    public function hydrateMedia(Event $event) {
        $serviceLocator = $this->getServiceLocator();

        if ($file = $event->getParam('request')->getFileData() == [])
            return '';

    }


    public function attachListeners(SharedEventManagerInterface $sharedEventManager) {
       $sharedEventManager->attach('Omeka\Api\Adapter\MediaAdapter',
                                    'api.hydrate.pre', [ $this, 'hydrateMedia' ]);

        $sharedEventManager->attach('Omeka\Api\Adapter\ItemAdapter',
                                    'api.update.post', [ $this, 'afterSaveItem' ]);

    }


    protected function entityApi($serviceLocator=null) {
        if (!$serviceLocator)
            $serviceLocator=$this->getServiceLocator();
        return $serviceLocator->get('Omeka\EntityManager');
    }
}