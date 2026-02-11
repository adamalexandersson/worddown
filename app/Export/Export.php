<?php

namespace Worddown\Export;

use Worddown\Admin\Settings;
use Worddown\Export\Adapters;
use Worddown\Utilities\ExportDirectory;
use Worddown\Utilities\HtmlProcessor;
use Worddown\Utilities\MarkdownConverter;
use Symfony\Component\Yaml\Yaml;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the export of WordPress content to markdown files.
 * 
 * This class manages the export process, including file creation,
 * HTML cleaning, and markdown content generation.
 * 
 * Lifecycle hooks (for adapters/plugins to run code around export):
 * - worddown_before_export  Fired once before processing posts (each chunk in background)
 * - worddown_after_export   Fired once after processing posts (each chunk in background)
 * 
 * @package Worddown
 * @since 1.0.0
 */
class Export
{
    /** @var string The export directory path */
    private string $export_dir;
    
    /** @var Settings The settings instance */
    private Settings $settings;
    
    /** @var MarkdownConverter The HTML to Markdown converter */
    private MarkdownConverter $markdownConverter;

    /** @var Adapters */
    private Adapters $adaptersManager;

    /** @var HtmlProcessor */
    private HtmlProcessor $htmlProcessor;

    /** @var ExportDirectory */
    private ExportDirectory $exportDirectory;

    /**
     * Constructor.
     * 
     * @param Settings $settings The settings instance
     */
    public function __construct()
    {
        $this->settings = di(Settings::class);

        // Initialize the adapters manager
        $this->adaptersManager = new Adapters();
        foreach (Adapters::getAdapterClasses() as $adapterClass) {
            if (class_exists($adapterClass)) {
                $this->adaptersManager->registerAdapter(new $adapterClass());
            }
        }
        
        // Initialize the export directory
        $this->exportDirectory = new ExportDirectory();
        $this->export_dir = $this->exportDirectory->getLiveDirectory();

        // Initialize the HTML processor
        $this->htmlProcessor = new HtmlProcessor();
        
        // Initialize MarkdownConverter
        $this->markdownConverter = new MarkdownConverter();
        // Setup cron hook
        add_action('worddown_export_cron', [$this, 'handleExportCron']);
        add_action('worddown_process_export_chunk', [$this, 'processExportChunk'], 10, 2);
    }


    /**
     * Gets the export directory path.
     * 
     * @return string The export directory path
     */
    public function getExportDirectory(): string
    {
        return $this->export_dir;
    }

    /**
     * Handles the cron job export.
     * 
     * Called by WordPress cron to perform automatic exports.
     * 
     * @return void
     */
    public function handleExportCron(): void
    {        
        $this->runExport(null, null, true);
    }

    /**
     * Runs the export process for specified post types.
     * 
     * @param array|null $post_types Array of post types to export, or null to use settings
     * @param int|null $limit Maximum number of posts to export, or null for all
     * @param bool $background Whether to run in background mode
     * @return int|array Number of successfully exported posts, or export status if background
     */
    public function runExport(?array $post_types = null, ?int $limit = null, bool $background = false): int|array
    {
        if ($post_types === null) {
            $post_types = $this->settings::get('export_post_types', ['post', 'page']);
        }

        $this->exportDirectory->setupPendingDirectory();

        $include_drafts = !empty($this->settings::get('include_drafts', false));
        $include_private = !empty($this->settings::get('include_private', false));
        
        $args = [
            'post_type' => $post_types,
            'post_status' => array_merge(
                ['publish'],
                $include_drafts ? ['draft', 'pending'] : [],
                $include_private ? ['private'] : []
            ),
            'posts_per_page' => $limit ?? -1,
            'fields' => 'ids',
        ];
        
        $query = new \WP_Query($args);
        $total_posts = count($query->posts);
        
        if ($background) {
            // Start background export
            $this->startBackgroundExport($query->posts, $total_posts, $post_types);

            /* translators: %d is the total number of posts */
            $message = sprintf(__('Background export started for %d posts', 'worddown'), $total_posts);

            return [
                'status' => 'started',
                'total_posts' => $total_posts,
                'message' => $message,
            ];
        }
        
        // Run immediate export (for small batches)
        $exported_count = 0;
        do_action('worddown_before_export');
        try {
            foreach ($query->posts as $post_id) {
                if ($this->exportPost($post_id)) {
                    $exported_count++;
                }
            }
        } finally {
            do_action('worddown_after_export');
        }
        
        if ($this->exportDirectory->swapDirectories()) {
            update_option('worddown_last_export', [
                'timestamp' => time(),
                'count' => $exported_count,
                'post_types' => $post_types
            ]);

            $this->exportDirectory->cleanupPendingDirectory();

            return $exported_count;
        }

        $this->exportDirectory->cleanupPendingDirectory();
        return 0;
    }

    /**
     * Starts a background export process.
     * 
     * @param array $post_ids Array of post IDs to export
     * @param int $total_posts Total number of posts
     * @param array $post_types Array of post types being exported
     * @return void
     */
    private function startBackgroundExport(array $post_ids, int $total_posts, array $post_types): void
    {
        $export_id = uniqid('export_', true);
        $settings = di(Settings::class);
        $chunk_size = $settings::get('chunk_size', 50); // Get chunk size from settings, default to 50
        
        $export_status = [
            'id' => $export_id,
            'status' => 'running',
            'total_posts' => $total_posts,
            'processed' => 0,
            'exported' => 0,
            'failed' => 0,
            'post_types' => $post_types,
            'started_at' => time(),
            'estimated_completion' => null,
            'current_operation' => __('Starting export...', 'worddown'),
            'chunks' => array_chunk($post_ids, $chunk_size)
        ];
        
        update_option('worddown_export_status_' . $export_id, $export_status);
        update_option('worddown_current_export_id', $export_id);
        
        // Schedule the first chunk to run immediately
        wp_schedule_single_event(time(), 'worddown_process_export_chunk', [$export_id, 0]);
    }

    /**
     * Processes a single chunk of posts for background export.
     * 
     * @param string $export_id The export ID
     * @param int $chunk_index The current chunk index
     * @return void
     */
    public function processExportChunk(string $export_id, int $chunk_index): void
    {
        do_action('worddown_before_export');

        $export_status = get_option('worddown_export_status_' . $export_id);
        if (!$export_status || $export_status['status'] !== 'running') {
            return;
        }
        
        $chunks = $export_status['chunks'];
        if ($chunk_index >= count($chunks)) {
            // All chunks processed, mark as complete
            $this->completeBackgroundExport($export_id, $export_status);
            return;
        }
        
        $current_chunk = $chunks[$chunk_index];

        /* translators: %1$d is the current chunk number, %2$d is the total number of chunks, %3$d is the number of posts in the current chunk */
        $export_label = __('Processing chunk %1$d of %2$d (%3$d posts)', 'worddown');
        
        $export_status['current_operation'] = sprintf(
            $export_label,
            $chunk_index + 1,
            count($chunks),
            count($current_chunk)
        );
        
        // Process current chunk
        foreach ($current_chunk as $post_id) {
            $export_status['processed']++;
            
            try {
                if ($this->exportPost($post_id)) {
                    $export_status['exported']++;
                } else {
                    $export_status['failed']++;
                }
            } catch (\Exception $e) {
                $export_status['failed']++;
            }
        }
        
        // Update progress
        $export_status['progress_percentage'] = round(($export_status['processed'] / $export_status['total_posts']) * 100, 2);
        
        // Estimate completion time
        if ($export_status['processed'] > 0) {
            $elapsed_time = time() - $export_status['started_at'];
            if ($elapsed_time > 0) {
                $posts_per_second = $export_status['processed'] / $elapsed_time;
                $remaining_posts = $export_status['total_posts'] - $export_status['processed'];
                $export_status['estimated_completion'] = time() + ($remaining_posts / $posts_per_second);
            }
        }
        
        update_option('worddown_export_status_' . $export_id, $export_status);

        do_action('worddown_after_export');
        
        // Schedule next chunk (with small delay to prevent overwhelming the server)
        wp_schedule_single_event(time() + 2, 'worddown_process_export_chunk', [$export_id, $chunk_index + 1]);
    }

    /**
     * Completes a background export and updates final status.
     * 
     * @param string $export_id The export ID
     * @param array $export_status The final export status
     * @return void
     */
    private function completeBackgroundExport(string $export_id, array $export_status): void
    {
        if ($this->exportDirectory->swapDirectories()) {
            $export_status['status'] = 'completed';
            $export_status['completed_at'] = time();
            $export_status['current_operation'] = __('Export completed successfully', 'worddown');
            $export_status['progress_percentage'] = 100;
            
            update_option('worddown_export_status_' . $export_id, $export_status);
            
            // Update last export info
            update_option('worddown_last_export', [
                'timestamp' => time(),
                'count' => $export_status['exported'],
                'post_types' => $export_status['post_types']
            ]);
            
            // Set completion flag for API to detect
            update_option('worddown_export_completed_flag', true);
        } else {
            $this->exportDirectory->cleanupPendingDirectory();
        }
        
        // Clear current export ID
        delete_option('worddown_current_export_id');
        
        // Clean up old export status data (keep only last 5 exports)
        $this->cleanupOldExportStatus();
    }

    /**
     * Cleans up old export status data to prevent database bloat.
     * 
     * @return void
     */
    private function cleanupOldExportStatus(): void
    {
        global $wpdb;
        
        // Try to get cached export status options
        $cache_key = 'worddown_export_status_options';
        $export_status_options = wp_cache_get($cache_key);

        if (false === $export_status_options) {
            $export_status_options = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id DESC", 'worddown_export_status_%'));

            if (!empty($export_status_options)) {
                wp_cache_set($cache_key, $export_status_options, '', HOUR_IN_SECONDS);
            }
        }

        // Keep only the last 5 exports
        if (is_array($export_status_options) && count($export_status_options) > 5) {
            $options_to_delete = array_slice($export_status_options, 5);
            
            if (!empty($options_to_delete)) {
                foreach ($options_to_delete as $option_name) {
                    delete_option($option_name);
                }
                
                // Update the cached list
                $export_status_options = array_slice($export_status_options, 0, 5);
                wp_cache_set($cache_key, $export_status_options, '', HOUR_IN_SECONDS);
            }
        }
    }

    /**
     * Gets the current export status.
     * 
     * @return array|null The current export status or null if no export running
     */
    public function getExportStatus(): ?array
    {
        $export_id = get_option('worddown_current_export_id');
        if (!$export_id) {
            return null;
        }
        
        return get_option('worddown_export_status_' . $export_id);
    }

    /**
     * Cancels the current background export.
     * 
     * @return bool True if export was cancelled, false otherwise
     */
    public function cancelExport(): bool
    {
        $export_id = get_option('worddown_current_export_id');
        if (!$export_id) {
            return false;
        }
        
        $export_status = get_option('worddown_export_status_' . $export_id);
        if ($export_status) {
            $export_status['status'] = 'cancelled';
            $export_status['cancelled_at'] = time();
            $export_status['current_operation'] = __('Export cancelled by user', 'worddown');
            update_option('worddown_export_status_' . $export_id, $export_status);
        }
        
        // Clear all scheduled cron jobs for this export using WordPress functions
        $this->clearScheduledExportJobs();

        // Cleanup pending directory
        $this->exportDirectory->cleanupPendingDirectory();
        
        // Set completion flag for API to detect cancelled export
        update_option('worddown_export_completed_flag', 'cancelled');
        
        delete_option('worddown_current_export_id');
        return true;
    }

    /**
     * Clears all scheduled cron jobs for a specific export using WordPress functions.
     * 
     * @param string $export_id The export ID to clear jobs for
     * @return void
     */
    private function clearScheduledExportJobs(): void
    {
        // This will remove all pending worddown_process_export_chunk jobs
        \wp_clear_scheduled_hook('worddown_process_export_chunk');
    }

    /**
     * Exports a single post to a markdown file.
     * 
     * @param int $post_id The post ID to export
     * @return bool True if export was successful, false otherwise
     */
    public function exportPost(int $post_id): bool
    {
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        $title = get_the_title($post_id);
        $post_type = get_post_type($post_id);
        $slug = $post->post_name ?: sanitize_title($title);
        $content = $this->generateMarkdownFile($post_id);
        
        // Create filename using slug
        $filename = sprintf(
            '%s-%s-%d.md',
            $post_type,
            $slug,
            $post_id
        );
        
        $filepath = $this->exportDirectory->getPendingDirectory() . $post_type . '/' . $filename;
        
        $result = file_put_contents($filepath, $content);
        
        return $result !== false;
    }

    /**
     * Generates markdown content for a post.
     * 
     * @param int $post_id The post ID
     * @return string The markdown content
     */
    public function generateMarkdownFile(int $post_id): string
    {
        $post = get_post($post_id);
        if (!$post) {
            return '';
        }

        $title = get_the_title($post_id);
        $content = apply_filters('the_content', $post->post_content);
        $permalink = get_permalink($post_id);
        $post_type = get_post_type($post_id);
        $date = get_the_date('Y-m-d H:i:s', $post_id);
        $modified = get_the_modified_date('Y-m-d H:i:s', $post_id);
        $slug = $post->post_name ?: sanitize_title($title);
        $excerpt = get_the_excerpt($post_id);
        
        // Filter for third party plugins to inject custom HTML content
        $content = apply_filters('worddown_custom_html_content', $content, $post_id, $post_type);
        
        // Get categories
        $categories = get_the_category($post_id);
        $category_names = [];
        foreach ($categories as $category) {
            $category_names[] = $category->name;
        }
        
        // Inject adapter content (Modularity, etc.)
        $content = $this->adaptersManager->injectAll($content, $post_id);
        
        // Clean and prepare HTML for markdown conversion
        $content = $this->htmlProcessor->cleanHtmlForMarkdown($content);
        
        // Convert HTML to Markdown
        $markdown_content = $this->markdownConverter->htmlToMarkdown($content);
        
        // Build meta data array
        $metaData = [
            'date' => $date,
            'modified' => $modified,
            'slug' => $slug,
            'id' => $post_id,
            'type' => $post_type,
            'excerpt' => $excerpt,
            'permalink' => $permalink,
        ];
        if (!empty($category_names)) {
            $metaData['category'] = $category_names;
        }
        
        return $this->formatMarkdownFile($title, $metaData, $markdown_content);
    }

    /**
     * Formats the output of the markdown file with meta data and content.
     *
     * @param string $title
     * @param array $metaData
     * @param string $markdown_content
     * @return string
     */
    private function formatMarkdownFile(string $title, array $metaData, string $markdown_content): string
    {
        $yaml = Yaml::dump($metaData, 2, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        $frontMatter = [
            '---',
            rtrim($yaml),
            '---',
            '',
        ];
        
        $parts = array_merge(
            $frontMatter,
            [
                '# ' . $title,
                '',
                $markdown_content,
                '',
            ]
        );

        return rtrim(implode("\n", $parts)) . "\n";
    }
} 