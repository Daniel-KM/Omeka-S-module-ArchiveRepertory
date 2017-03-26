<?php

namespace ArchiveRepertory;

class Helpers
{
    /**
     * An ugly, non-ASCII-character safe replacement of escapeshellarg().
     *
     * @see http://www.php.net/manual/function.escapeshellarg.php#111919
     *
     * @param string $string
     * @return string
     */
    public static function escapeshellarg($string)
    {
        return "'" . str_replace("'", "'\\''", $string) . "'";
    }

    /**
     * Get the base of a filename when it starts with an Unicode character.
     *
     * @param string $path
     * @return string
     */
    public static function basename($path)
    {
        return preg_replace('/^.+[\\\\\\/]/', '', $path);
    }

    /**
     * Helper to manage unicode paths.
     *
     * @todo Manage all pathinfo dirname in all file systems.
     *
     * @param string $path
     * @param integer $mode Pathinfo constants.
     * @return string
     */
    public static function pathinfo($path, $mode)
    {
        switch ($mode) {
            case PATHINFO_BASENAME:
                $result = preg_replace('/^.+[\\\\\\/]/', '', $path);
                break;
            case PATHINFO_FILENAME:
                $result = preg_replace('/^.+[\\\\\\/]/', '', $path);
                $positionExtension = strrpos($result, '.');
                if ($positionExtension) {
                    $result = substr($result, 0, $positionExtension);
                }
                break;
            case PATHINFO_EXTENSION:
                $positionExtension = strrpos($path, '.');
                $result = $positionExtension
                    ? substr($path, $positionExtension + 1)
                    : '';
                break;
            case PATHINFO_DIRNAME:
                $positionDir = strrpos($path, '/');
                $result = $positionDir
                    ? substr($path, 0, $positionDir - 1)
                    : $path;
                break;
        }
        return $result;
    }

    public static function checkUnicodeInstallation()
    {
        $result = [];

        // First character check.
        $filename = 'éfilé.jpg';
        if (basename($filename) != $filename) {
            $result['ascii'] = sprintf('An error occurs when testing function "basename(\'%s\') : %s".', $filename, basename($filename));
        }

        // Command line via web check (comparaison with a trivial function).
        $filename = "File~1 -À-é-ï-ô-ů-ȳ-Ø-ß-ñ-Ч-Ł-'.Test.png";

        if (escapeshellarg($filename) != self::escapeshellarg($filename)) {
            $result['cli'] = sprintf('An error occurs when testing function "escapeshellarg(\'%s\')".', $filename);
        }

        // File system check.
        $filepath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
        $filepath = preg_replace('|' . DIRECTORY_SEPARATOR . '+|', DIRECTORY_SEPARATOR, $filepath);
        if (!(touch($filepath) && file_exists($filepath))) {
            $result['fs'] = sprintf('A file system error occurs when testing function "touch \'%s\'".', $filepath);
        } else {
            unlink($filepath);
        }

        return $result;
    }
}
