<?php

namespace Worddown\Admin;

use Worddown\Utilities\Dir;
use Worddown\Admin\Settings;
use Worddown\Export\Export;

/**
 * Dashboard Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dashboard
{
    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'addSettingsPage']);

        $this->registerRestRoutes();
    }

    /**
     * Add the settings page
     *
     * @return void
     */
    public function addSettingsPage(): void
    {
        add_submenu_page(
            'worddown',
            __('Dashboard', 'worddown'),
            __('Dashboard', 'worddown'),
            'manage_options',
            'worddown',
            [$this, 'renderDashboardPage']
        );
    }

    /**
     * Render the dashboard page
     *
     * @return void
     */
    public function renderDashboardPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        include config('paths.plugin_path') . 'resources/views/dashboard.php';
    }

    /**
     * Register REST API endpoint for dashboard data
     *
     * @return void
     */
    public function registerRestRoutes(): void
    {   
        add_action('rest_api_init', function () {
            register_rest_route('worddown/v1', '/dashboard', [
                'methods' => 'GET',
                'callback' => [$this, 'getDashboardData'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
            ]);
        });
    }

    /**
     * Get dashboard data for React dashboard
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getDashboardData($request): \WP_REST_Response
    {
        $settings = di(Settings::class);
        $settingsArr = $settings::get();
        $last_export = get_option('worddown_last_export', []);
        
        $export_dir = di(Export::class)->getExportDirectory();
        $exported_files_count = 0;

        if (is_dir($export_dir)) {
            $exported_files_count = count(glob($export_dir . '/**/*.md', GLOB_BRACE));
        }

        $counts = [];

        if (is_dir($export_dir)) {
            $dirs = Dir::list($export_dir, 'directories');
            
            foreach ($dirs as $dir) {
                $type = basename($dir);
                $counts[$type] = count(glob($dir . '/*.md'));
            }
        }

        $next_scheduled = wp_next_scheduled('worddown_export_cron');
        $auto_export = !empty($settingsArr['auto_export']);

        return rest_ensure_response([
            'last_export' => $last_export,
            'exported_files_count' => $exported_files_count,
            'export_dir' => $export_dir,
            'counts' => $counts,
            'next_scheduled' => $next_scheduled,
            'auto_export' => $auto_export,
        ]);
    }
} 