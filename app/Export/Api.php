<?php

namespace Worddown\Export;

use Worddown\Admin\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles REST API endpoints for the Worddown plugin.
 * 
 * This class provides REST API endpoints for listing exported files,
 * retrieving individual file content, and triggering exports.
 * All endpoints require API key authentication.
 * 
 * @package Worddown
 * @since 1.0.0
 */
class Api
{
    /**
     * Constructor.
     * 
     * Initializes the API class and registers REST routes.
     * 
     * @param Settings $settings The settings instance
     * @param Export $export The export instance
     */
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    /**
     * Registers all REST API routes for the plugin.
     * 
     * Registers three main endpoints:
     * - GET /worddown/v1/files - List all exported files
     * - GET /worddown/v1/files/{id} - Get specific file content
     * - POST /worddown/v1/export - Trigger a new export
     * 
     * @return void
     */
    public function registerRestRoutes(): void
    {
        // List all exported files
        register_rest_route('worddown/v1', '/files', [
            'methods' => 'GET',
            'callback' => [$this, 'getFiles'],
            'permission_callback' => [$this, 'verifyApiKey'],
        ]);

        // Get a specific file by post ID
        register_rest_route('worddown/v1', '/files/(?P<id>\\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getFile'],
            'args' => [
                'id' => [
                    'validate_callback' => function($param) { 
                        return is_numeric($param); 
                    },
                    'required' => true,
                ],
            ],
            'permission_callback' => [$this, 'verifyApiKey'],
        ]);

        // Trigger a new export
        register_rest_route('worddown/v1', '/export', [
            'methods' => 'POST',
            'callback' => [$this, 'triggerExport'],
            'permission_callback' => [$this, 'verifyApiKey'],
        ]);

        // Trigger a new local export
        register_rest_route('worddown/v1', '/local-export', [
            'methods' => 'POST',
            'callback' => [$this, 'triggerLocalExport'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        // Get export status
        register_rest_route('worddown/v1', '/export-status', [
            'methods' => 'GET',
            'callback' => [$this, 'getExportStatus'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        // Cancel current export
        register_rest_route('worddown/v1', '/cancel-export', [
            'methods' => 'POST',
            'callback' => [$this, 'cancelExport'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
    }

    /**
     * Verifies the API key from the request headers.
     * 
     * Checks for the API key in the X-API-Key header and compares it
     * with the stored key from plugin settings.
     * 
     * @return bool True if API key is valid, false otherwise
     */
    public function verifyApiKey(): bool
    {
        $settings = di(Settings::class);
        $api_key = '';
        if (isset($_SERVER['HTTP_X_API_KEY'])) {
            $api_key = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_API_KEY']));
        } elseif (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (isset($headers['X-API-Key'])) {
                $api_key = sanitize_text_field(wp_unslash($headers['X-API-Key']));
            }
        }
        
        $stored_key = $settings::get('api_key', '');
        
        return !empty($stored_key) && $api_key === $stored_key;
    }

    /**
     * Lists all exported markdown files.
     * 
     * Scans the export directory for .md files and returns metadata
     * for each file including post information and file details.
     * 
     * @return \WP_REST_Response Response containing array of file metadata
     * 
     * @example
     * GET /wp-json/worddown/v1/files
     * 
     * Response:
     * [
     *   {
     *     "id": 43,
     *     "title": "Startsida",
     *     "type": "page",
     *     "permalink": "https://example.com/startsida/",
     *     "filename": "page-startsida-43.md",
     *     "fileurl": "https://example.com/wp-content/uploads/worddown-export/page/page-startsida-43.md",
     *     "file_size": 2048,
     *     "last_modified": 1703123456
     *   }
     * ]
     */
    public function getFiles(): \WP_REST_Response
    {
        $export = di(Export::class);
        $settings = di(Settings::class);
        $export_dir = $export->getExportDirectory();
        $files = [];
        
        // Get all markdown files in the export directory and subdirectories
        if (is_dir($export_dir)) {
            // Get settings to know which post types to scan
            $post_types = $settings::get('export_post_types', ['post', 'page']);
            
            foreach ($post_types as $post_type) {
                $post_type_dir = $export_dir . $post_type . '/';
                
                if (is_dir($post_type_dir)) {
                    $markdown_files = glob($post_type_dir . '*.md');
                    
                    foreach ($markdown_files as $file_path) {
                        $filename = basename($file_path);
                        
                        // Parse filename to extract post ID: post-slug-123.md
                        if (preg_match('/-(\d+)\.md$/', $filename, $matches)) {
                            $post_id = (int) $matches[1];
                            $post = get_post($post_id);
                            
                            if ($post) {
                                $upload_dir = wp_upload_dir();
                                $file_url = trailingslashit($upload_dir['baseurl']) . 'worddown-export/' . $post_type . '/' . $filename;
                                
                                $files[] = [
                                    'id' => $post_id,
                                    'title' => get_the_title($post_id),
                                    'type' => get_post_type($post_id),
                                    'permalink' => get_permalink($post_id),
                                    'filename' => $filename,
                                    'fileurl' => $file_url,
                                    'file_size' => filesize($file_path),
                                    'last_modified' => filemtime($file_path)
                                ];
                            }
                        }
                    }
                }
            }
        }
        
        return rest_ensure_response($files);
    }

    /**
     * Retrieves a specific markdown file by post ID.
     * 
     * Gets the markdown content for a specific post. If the file doesn't exist,
     * it will be generated automatically. Returns the actual markdown content.
     * 
     * @param \WP_REST_Request $request The REST request object containing the post ID
     * @return \WP_REST_Response|\WP_Error Response with markdown content, or error
     * 
     * @example
     * GET /wp-json/worddown/v1/files/43
     */
    public function getFile(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $export = di(Export::class);
        $post_id = $request['id'];
        $post = get_post($post_id);
        
        if (!$post) {
            return new \WP_Error('not_found', 'Post not found', ['status' => 404]);
        }
        
        $export_dir = $export->getExportDirectory();
        $post_type = get_post_type($post_id);
        $slug = $post->post_name ?: sanitize_title(get_the_title($post_id));
        $filename = sprintf('%s-%s-%d.md', $post_type, $slug, $post_id);
        $file_path = $export_dir . $post_type . '/' . $filename;
        
        // Check if file exists, if not generate it
        if (!file_exists($file_path)) {
            $export->exportPost($post_id);
        }
        
        // Check again after potential generation
        if (!file_exists($file_path)) {
            return new \WP_Error('export_failed', 'Failed to generate markdown file', ['status' => 500]);
        }
        
        // Read the file content
        $markdown_content = file_get_contents($file_path);
        
        if ($markdown_content === false) {
            return new \WP_Error('read_failed', 'Failed to read markdown file', ['status' => 500]);
        }
        
        // Output the markdown content directly as plain text
        status_header(200);
        header('Content-Type: text/plain; charset=utf-8');
        echo esc_html($markdown_content);
        exit;
    }

    /**
     * Triggers a new export of all configured post types.
     * 
     * @param \WP_REST_Request $request The REST request object
     * @return \WP_REST_Response Response with export results
     * 
     * @example
     * POST /wp-json/worddown/v1/export
     * 
     * Request body:
     * {
     *   "background": true
     * }
     * 
     * Response (background mode):
     * {
     *   "status": "started",
     *   "total_posts": 150,
     *   "message": "Background export started for 150 posts"
     * }
     * 
     * Response (immediate mode):
     * {
     *   "success": true,
     *   "message": "Successfully exported 15 posts/pages",
     *   "total_available": 20,
     *   "exported_count": 15,
     *   "timestamp": 1703123456
     * }
     */
    public function triggerExport(\WP_REST_Request $request): \WP_REST_Response
    {
        $export = di(Export::class);
        
        // Check if background mode is requested
        $background = $request->get_param('background') === 'true' || $request->get_param('background') === true;
        
        if ($background) {
            // Run in background mode
            $result = $export->runExport(null, null, true);
            return rest_ensure_response($result);
        } else {
            // Run in immediate mode (for backward compatibility)
            $exported_count = $export->runExport();
            
            return rest_ensure_response([
                'success' => true,
                'message' => sprintf('Successfully exported %d posts/pages', $exported_count),
                'total_available' => $exported_count,
                'exported_count' => $exported_count,
                'timestamp' => time(),
            ]);
        }
    }

    /**
     * Triggers a new local export (background mode).
     * 
     * @return \WP_REST_Response Response with export results
     */
    public function triggerLocalExport(): \WP_REST_Response
    {
        $export = di(Export::class);
        $result = $export->runExport(null, null, true); // Enable background mode
        
        return rest_ensure_response($result);
    }

    /**
     * Gets the current export status.
     * 
     * @return \WP_REST_Response Response with export status
     */
    public function getExportStatus(): \WP_REST_Response
    {
        $export = di(Export::class);
        $status = $export->getExportStatus();
        
        if (!$status) {
            // Check if we have recent export data to indicate completion
            $last_export = get_option('worddown_last_export');
            $current_export_id = get_option('worddown_current_export_id');
            $export_completed_flag = get_option('worddown_export_completed_flag', false);
            
            if ($last_export && !$current_export_id && $export_completed_flag) {
                // Export just completed, clear the flag and return appropriate status
                $completion_status = $export_completed_flag === 'cancelled' ? 'cancelled' : 'completed';
                $message = $export_completed_flag === 'cancelled' ? __('Export cancelled', 'worddown') : __('Export completed', 'worddown');
                
                delete_option('worddown_export_completed_flag');
                
                return rest_ensure_response([
                    'status' => $completion_status,
                    'message' => $message,
                    'last_export' => $last_export
                ]);
            }
            
            return rest_ensure_response([
                'status' => 'idle',
                'message' => __('No export currently running', 'worddown')
            ]);
        }
        
        return rest_ensure_response($status);
    }

    /**
     * Cancels the current export.
     * 
     * @return \WP_REST_Response Response with cancellation result
     */
    public function cancelExport(): \WP_REST_Response
    {
        $export = di(Export::class);
        $result = $export->cancelExport();
        
        return rest_ensure_response([
            'success' => $result,
            'message' => $result ? __('Export cancelled successfully', 'worddown') : __('No export to cancel', 'worddown')
        ]);
    }
} 