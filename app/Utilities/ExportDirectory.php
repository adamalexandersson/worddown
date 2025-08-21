<?php

namespace Worddown\Utilities;

use Worddown\Admin\Settings;
use Worddown\Utilities\Dir;

/**
 * Handles export directory operations and atomic directory swapping.
 * 
 * This class manages the export directory structure, including:
 * - Creating and managing pending export directories
 * - Swapping directories atomically
 * - Cleaning up temporary directories
 * 
 * @package Worddown\Utilities
 * @since 1.0.0
 */
class ExportDirectory
{
    /** @var string Base directory for all exports */
    private string $base_dir;

    /** @var string Current live export directory */
    private string $live_dir;

    /** @var string Pending export directory */
    private string $pending_dir;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $upload_dir = wp_upload_dir();
        $this->base_dir = trailingslashit($upload_dir['basedir']);
        $this->live_dir = $this->base_dir . 'worddown-export/';
        $this->pending_dir = $this->base_dir . 'worddown-export-pending/';

        // Create export directory on activation
        $this->setupLiveDirectory();
    }

    /**
     * Sets up the export directory with security files.
     * 
     * Creates the export directory if it doesn't exist and adds
     * .htaccess and index.php files for security.
     * 
     * @return void
     */
    public function setupLiveDirectory(): void
    {
        if (!file_exists($this->live_dir)) {
            wp_mkdir_p($this->live_dir);
            
            $this->addSecurityFiles($this->live_dir);
        }
        
        $this->addPostTypeDirectories($this->live_dir);
    }

    /**
     * Gets the live export directory path.
     * 
     * @return string
     */
    public function getLiveDirectory(): string
    {
        return $this->live_dir;
    }

    /**
     * Sets up a new pending export directory.
     * 
     * @return void
     */
    public function setupPendingDirectory(): void
    {
        if (!file_exists($this->pending_dir)) {
            wp_mkdir_p($this->pending_dir);
            
            $this->addSecurityFiles($this->pending_dir);
        }

        $this->addPostTypeDirectories($this->pending_dir);
    }

    /**
     * Gets the pending export directory path.
     * 
     * @return string
     */
    public function getPendingDirectory(): string
    {
        return $this->pending_dir;
    }

    /**
     * Adds security files (.htaccess and index.php) to a directory.
     * 
     * @param string $dir Directory path
     * @return void
     */
    private function addSecurityFiles(string $dir): void
    {
        // Add .htaccess to protect directory
        $htaccess_file = $dir . '.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "Deny from all\n";
            file_put_contents($htaccess_file, $htaccess_content);
        }
        
        // Add index.php for extra protection
        $index_file = $dir . 'index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, '<?php // Silence is golden');
        }
    }

    /**
     * Adds post type directories to a directory.
     * 
     * @param string $dir Directory path
     */

     public function addPostTypeDirectories($dir): void
     {
        $settings = di(Settings::class);
        $post_types = $settings::get('export_post_types', ['post', 'page']);
 
        foreach ($post_types as $post_type) {
            $post_type_dir = $dir . $post_type . '/';
            if (!file_exists($post_type_dir)) {
                wp_mkdir_p($post_type_dir);

                $this->addSecurityFiles($post_type_dir);
            }
        }
     }

    /**
     * Swaps the pending directory with the live directory.
     * 
     * @return bool True if swap was successful, false otherwise
     */
    public function swapDirectories(): bool
    {
        // Initialize WordPress Filesystem
        global $wp_filesystem;

        if (!file_exists($this->pending_dir)) {
            return false;
        }

        // Remove existing live directory if it exists
        if (file_exists($this->live_dir)) {
            Dir::remove($this->live_dir);
        }
        
        // Move pending to live
        if (!$wp_filesystem->move($this->pending_dir, $this->live_dir)) {
            return false;
        }
        
        return true;
    }

    /**
     * Cleans up the pending directory.
     * 
     * @return void
     */
    public function cleanupPendingDirectory(): void
    {
        if (file_exists($this->pending_dir)) {
            Dir::remove($this->pending_dir);
        }
    }
}
