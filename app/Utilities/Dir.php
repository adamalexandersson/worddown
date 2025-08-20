<?php

namespace Worddown\Utilities;

use Exception;

class Dir
{
    /**
     * List directory
     *
     * @param string   $dirpath
     * @param string   $get
     * @param string[] $exclude
     *
     * @return array
     */
    public static function list($dirpath, $get = 'all', $exclude = ['.DS_Store'])
    {
        if (!is_dir($dirpath) || !is_readable($dirpath)) {
            return [];
        }

        $result = [];
        $dir = realpath($dirpath);
        $dh = opendir($dir);
        $ignore = ['.', '..'];

        while (($f = readdir($dh)) !== false) {
            if (!in_array($f, $ignore)) {
                $file = $dir . DIRECTORY_SEPARATOR . $f;

                switch ($get) {
                    case 'directories':
                        if (is_dir($file) && !in_array($f, $exclude)) {
                            $result[] = $file;
                        }
                        break;

                    case 'files':
                        if (!is_dir($file) && !in_array($f, $exclude)) {
                            $result[] = $file;
                        }
                        break;

                    case 'all':
                    default:
                        if (!in_array($f, $exclude)) {
                            $result[] = $file;
                        }
                }
            }
        }

        closedir($dh);

        return $result;
    }

    /**
     * Clear directory
     *
     * @param string $dir
     * @param string $return
     *
     * @return bool|void
     */
    public static function clear($dir, $return = 'bool')
    {
        if (!is_dir($dir)) {
            return false;
        }

        /**
         * Get list of files in directory.
         */
        $files = Dir::list($dir, 'all', []);
        $total = count($files);
        $total_removed = 0;

        foreach ($files as $file) { // iterate files
            if (is_dir($file)) {
                self::clear($file);
            } else {
                if (is_readable($file)) {
                    if (wp_delete_file($file)) {
                        $total_removed++;
                    }
                }
            }
        }

        switch ($return) {
            case 'bool':
                return ($total_removed > 0 && $total > 0);

            case 'count':
            default:
                return $total_removed;
        }
    }

    /**
     * Remove directory
     *
     * @param string $dir
     * @param bool   $clear_if_not_empty
     *
     * @throws \Exception
     * @return bool
     */
    public static function remove($dir, $clear_if_not_empty = true) {
        if (!is_dir($dir) || !is_readable($dir)) {
            return false;
        }
    
        $files = Dir::list($dir, 'all', []);
        $result = false;
    
        if ($files && $clear_if_not_empty) {
            self::clear($dir);
        }
    
        // Init WP_Filesystem
        global $wp_filesystem;
        if ( ! $wp_filesystem ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
    
        // Försök ta bort katalogen
        $result = $wp_filesystem->rmdir($dir, true);
    
        if (!$result) {
            throw new Exception('Directory could not be removed.');
        }
    
        return (bool) $result;
    }

    /**
     * Check if dir is empty
     *
     * @param string $dir_path
     *
     * @return bool
     */
    public static function isEmpty($dir_path)
    {
        $dir = Dir::list($dir_path, 'all', []);
        return empty($dir);
    }
}
