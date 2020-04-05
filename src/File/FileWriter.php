<?php
namespace ArchiveRepertory\File;

class FileWriter
{
    public function putContents($path, $contents)
    {
        return file_put_contents($path, $contents);
    }

    public function fileExists($path)
    {
        return file_exists($path);
    }

    public function is_dir($path)
    {
        return is_dir($path);
    }

    public function is_writable($path)
    {
        return is_writable($path);
    }

    public function mkdir($directory_name, $permissions = 0777)
    {
        return mkdir($directory_name, $permissions, true);
    }

    public function getContents($path)
    {
        return file_get_contents($path);
    }

    public function moveUploadedFile($source, $destination)
    {
        return move_uploaded_file($source, $destination);
    }

    public function rename($oldname, $newname)
    {
        return rename($oldname, $newname);
    }

    public function chmod($path, $permission)
    {
        return chmod($path, $permission);
    }

    public function glob($pattern, $flags = 0)
    {
        return glob($pattern, $flags);
    }

    /**
     * Checks and removes a folder recursively.
     *
     * @param string $path Full path of the folder to remove.
     * @param bool $evenNonEmpty Remove non empty folder. This parameter can be
     * used with non standard folders.
     * @return bool
     */
    public function removeDir($path, $evenNonEmpty = false)
    {
        $path = realpath($path);
        if (mb_strlen($path)
            && $path != DIRECTORY_SEPARATOR
            && file_exists($path)
            && is_dir($path)
            && is_readable($path)
            && is_writable($path)
            && ($evenNonEmpty || count(array_diff(@scandir($path), ['.', '..'])) == 0)
        ) {
            return $this->recursiveRemoveDir($path);
        }
    }

    /**
     * Removes directories recursively.
     *
     * @param string $dirPath Directory name.
     * @return bool
     */
    protected function recursiveRemoveDir($dirPath)
    {
        $files = array_diff(scandir($dirPath), ['.', '..']);
        foreach ($files as $file) {
            $path = $dirPath . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->recursiveRemoveDir($path);
            } else {
                unlink($path);
            }
        }
        return rmdir($dirPath);
    }
}
