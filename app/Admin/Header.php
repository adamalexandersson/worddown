<?php

namespace Worddown\Admin;

class Header
{
    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        add_action('in_admin_header', [$this, 'render']);
    }

    /**
     * Render the dashboard page
     *
     * @return void
     */
    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || strpos($screen->base, 'worddown') === false) {
            return;
        }

        $current_page = str_replace(['worddown_page_', 'toplevel_page_'], '', $screen->base);

        $worddown_submenus = [
            [
                'url' => admin_url('admin.php?page=worddown'),
                'menu_slug' => 'worddown',
                'icon' => 'dashboard',
                'menu_title' => __('Dashboard', 'worddown'),
            ],
            [
                'url' => admin_url('admin.php?page=worddown-settings'),
                'menu_slug' => 'worddown-settings',
                'icon' => 'settings',
                'menu_title' => __('Settings', 'worddown'),
            ],
        ];
        
        include config('paths.plugin_path') . 'resources/views/header.php';
    }
} 