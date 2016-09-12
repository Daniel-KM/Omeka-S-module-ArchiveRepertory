<?php
/**
 * Helpers for ArchiveRepertory -
 * These functions are needed if the server is not fully compliant with Unicode.
 *
 * @package ArchiveRepertory
 */

/**
 * An ugly, non-ASCII-character safe replacement of escapeshellarg().
 *
 * @see http://www.php.net/manual/function.escapeshellarg.php#111919
 */
function escapeshellarg_special($string) {
    return "'" . str_replace("'", "'\\''", $string) . "'";
}

/**
 * Get the base of a filename when it starts with an Unicode character.
 *
 * @param string $path
 *
 * @return string
 */
function basename_special($path) {
    return preg_replace( '/^.+[\\\\\\/]/', '', $path);
}

function checkUnicodeInstallation()
{
    $result = [];

    // First character check.
    $filename = 'éfilé.jpg';
    if (basename($filename) != $filename) {
        $result['ascii'] = sprintf('An error occurs when testing function "basename(\'%s\')".', $filename);
    }

    // Command line via web check (comparaison with a trivial function).
    $filename = "File~1 -À-é-ï-ô-ů-ȳ-Ø-ß-ñ-Ч-Ł-'.Test.png";

    if (escapeshellarg($filename) != escapeshellarg_special($filename)) {
        $result['cli'] = sprintf('An error occurs when testing function "escapeshellarg(\'%s\')".', $filename);
    }

    // File system check.
    $filepath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
    $filepath = preg_replace('|' . DIRECTORY_SEPARATOR . '+|', DIRECTORY_SEPARATOR, $filepath);
    if (!(touch($filepath) && file_exists($filepath))) {
        $result['fs'] = sprintf('A file system error occurs when testing function "touch \'%s\'".', $filepath);
    }

    return $result;
}
