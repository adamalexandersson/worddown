<?php

namespace Worddown\Export;

use WP_CLI;

if (!defined('ABSPATH')) {
    exit;
}

class Cli
{
    public function __construct() {}

    /**
     * Handles the export command for WP CLI.
     *
     * ## OPTIONS
     *
     * [--background]
     * : Run export in background mode (recommended for large exports)
     *
     * ## EXAMPLES
     *
     *     # Run export immediately (default)
     *     wp worddown export
     *
     *     # Run export in background mode
     *     wp worddown export --background
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function export($args, $assoc_args)
    {
        $export = di(Export::class);
        
        // Check if background mode is requested
        $background = isset($assoc_args['background']);
        
        if ($background) {
            WP_CLI::log('Starting Worddown export in background mode...');
            $result = $export->runExport(null, null, true);
            
            if (isset($result['status']) && $result['status'] === 'started') {
                WP_CLI::success("Background export started for {$result['total_posts']} posts.");
                WP_CLI::log('Export is running in the background. Use the dashboard to monitor progress.');
            } else {
                WP_CLI::error('Failed to start background export.');
            }
        } else {
            WP_CLI::log('Starting Worddown export...');
            $count = $export->runExport();
            WP_CLI::success("Exported {$count} posts to markdown files.");
        }
    }
} 