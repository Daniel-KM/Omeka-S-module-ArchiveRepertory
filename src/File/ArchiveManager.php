<?php
namespace ArchiveRepertory\File;
use Omeka\File\Manager;
use Omeka\File\File;

use Omeka\File\Store\StoreInterface;
use Omeka\File\Thumbnailer\ThumbnailerInterface;
use Omeka\Entity\Media;
use Zend\ServiceManager\ServiceLocatorInterface;

class ArchiveManager extends Manager
{

    protected $media;
    protected $moduleObject = null;
    protected $storageName = [];
    public function __construct(array $config, $tempDir, ServiceLocatorInterface $serviceLocator)
    {
        parent::__construct($config,$tempDir,$serviceLocator);
        $this->moduleObject = $serviceLocator
            ->get('ModuleManager')->getModule('ArchiveRepertory');


    }

    public function setMedia($media) {
        $this->media=$media;
        return $this;
    }



    public function storeThumbnails(File $file) {
        $extension = $this->getExtension($file);
        $storageName = $this->getStorageName($file);
        $file->setStorageId(str_replace(".$extension", '', $storageName));

        return parent::storeThumbnails($file);
    }

    protected function _getItemFolderName($item)
    {
        if (!($folder = $this->moduleObject->getOption('archive_repertory_item_folder')))
            return '';

        switch ($folder) {
            case 'id':
                return (string) $item->getId();
            case 'none':
            case '':
                return '';
            default:
                $name = $this->_getRecordFolderNameFromMetadata(
                                                                $item,
                                                                $folder
                );

        }

        return $this->moduleObject->_convertFilenameTo($name, $this->moduleObject->getOption('archive_repertory_item_convert')) ;
    }

    protected function _getRecordFolderNameFromMetadata($record, $elementId)
    {

        $identifier = $this->moduleObject->_getRecordIdentifiers($record, null, true);

        return empty($identifier)
            ? (string) $record->getId()
            : $this->moduleObject->_sanitizeName($identifier);
    }


    public function getBasename($name)
    {
        return substr($name,0,strrpos($name, '.')) ? substr($name,0,strrpos($name, '.')): $name;
    }

    public function getStoragePath($prefix, $name, $extension = null)
    {
        if ($this->media) {
            $prefix=($prefix ? $prefix.'/' : ''). ($this->moduleObject->getItemFolderName($this->media->getItem()));
        }
        return sprintf('%s/%s%s', $prefix, $name, $extension ? ".$extension" : null);
    }


    public function getStorageName(File $file)
    {
        $idfile=spl_object_hash($file);
        if (isset($this->storageName[$idfile]))
            return $this->storageName[$idfile];

        $extension = $this->getExtension($file);

        if ($this->moduleObject->getOption('archive_repertory_file_keep_original_name') === '1')  {
                $path =$this->moduleObject->checkExistingFile($this->getStoragePath('',$file->getSourceName())) ;
                return $this->storageName[$idfile]=pathinfo($path, PATHINFO_FILENAME).($extension ? ".$extension": '');

        }

        $this->storageName[$idfile] = sprintf('%s%s', $file->getStorageId(),
            $extension ? ".$extension" : null);

        return $this->storageName[$idfile];
    }


    protected function _getDerivativeFilename($filename, $defaultExtension, $derivativeType = null)
    {
        $base = pathinfo($filename, PATHINFO_EXTENSION) ? substr($filename, 0, strrpos($filename, '.')) : $filename;
        $fullExtension = !is_null($derivativeType) && isset($this->_derivativeExtensionsByType[$derivativeType])
            ? $this->_derivativeExtensionsByType[$derivativeType]
            : '.' . $defaultExtension;
        return $base . $fullExtension;
    }

    public function _getDerivativeExtension($file)
    {
        return 'jpg';
    }


}
