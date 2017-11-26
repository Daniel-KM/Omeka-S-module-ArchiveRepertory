<?php
namespace ArchiveRepertoryTest;

class MockFileWriter
{
    protected $files = [];

    public function moveUploadedFile($source, $destination)
    {
        $this->files = array_diff($this->files, [$source]);
        $this->files[] = $destination;
        return true;
    }

    public function is_dir($path)
    {
        return true;
    }

    public function fileExists($path)
    {
        return in_array($path, $this->files);
    }

    public function is_writable($path)
    {
        return true;
    }

    public function chmod($path, $permission)
    {
        return true;
    }

    public function putContents($path, $contents)
    {
        return true;
    }

    public function rename($path, $destination)
    {
        $this->files = array_diff($this->files, [$path]);
        $this->files[] = $destination;
        return true;
    }

    public function mkdir($directory_name, $permissions = 0777)
    {
        return true;
    }

    public function glob($pattern, $flag = 0)
    {
        $file = str_replace('{.*,.,\,,}', '.png', $pattern);
        return $this->fileExists($file) ? [$file] : [];
    }

    public function addFile($path)
    {
        $this->files[] = $path;
    }
}
