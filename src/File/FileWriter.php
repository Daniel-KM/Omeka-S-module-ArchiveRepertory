<?php declare(strict_types=1);

namespace ArchiveRepertory\File;

class FileWriter
{
    public function putContents($path, $contents)
    {
        return file_put_contents((string) $path, $contents);
    }

    public function fileExists($path): bool
    {
        return file_exists((string) $path);
    }

    public function is_dir($path): bool
    {
        return is_dir((string) $path);
    }

    public function is_writeable($path): bool
    {
        return is_writeable((string) $path);
    }

    public function mkdir($directory_name, $permissions = 0777): bool
    {
        return mkdir((string) $directory_name, $permissions, true);
    }

    public function getContents($path)
    {
        return file_get_contents((string) $path);
    }

    public function moveUploadedFile($source, $destination): bool
    {
        return move_uploaded_file((string) $source, (string) $destination);
    }

    public function rename($oldname, $newname): bool
    {
        return rename((string) $oldname, (string) $newname);
    }

    public function chmod($path, $permission): bool
    {
        return chmod((string) $path, $permission);
    }

    public function glob($pattern, $flags = 0)
    {
        return glob((string) $pattern, $flags);
    }

    /**
     * Checks and removes a folder recursively.
     *
     * @todo Use Omeka dependencies.
     *
     * @param string $path Full path of the folder to remove.
     * @param bool $evenNonEmpty Remove non empty folder. This parameter can be
     * used with non standard folders.
     * @return bool
     */
    public function removeDir($path, $evenNonEmpty = false): bool
    {
        $path = realpath((string) $path);
        if ($path
            && mb_strlen($path)
            && $path != DIRECTORY_SEPARATOR
            && file_exists($path)
            && is_dir($path)
            && is_readable($path)
            && is_writeable($path)
            && ($evenNonEmpty || count(array_diff(@scandir($path), ['.', '..'])) == 0)
        ) {
            return $this->recursiveRemoveDir($path);
        }
        return false;
    }

    /**
     * Removes directories recursively.
     *
     * @param string $dirPath Directory name.
     * @return bool
     */
    protected function recursiveRemoveDir($dirPath): bool
    {
        $files = array_diff(scandir((string) $dirPath), ['.', '..']);
        foreach ($files as $file) {
            $path = $dirPath . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->recursiveRemoveDir($path);
            } else {
                unlink($path);
            }
        }
        return rmdir((string) $dirPath);
    }
}
