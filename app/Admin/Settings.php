<?php

namespace Worddown\Admin;

use Worddown\Export\Adapters;

/**
 * Admin Settings Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Settings
{
    private $config;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        add_action('init', [$this, 'setConfig']);
        add_action('admin_menu', [$this, 'addSettingsPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_filter('cron_schedules', [$this, 'addCronSchedules']);
        
        $this->maybeScheduleCron();
        
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    /**
     * Set the config
     *
     * @return void
     */
    public function setConfig()
    {
        $user_locale = get_user_locale();
        if ($user_locale) {
            switch_to_locale($user_locale);
        }

        $this->config = config('settings');
    }

    /**
     * Add cron schedules
     *
     * @param array $schedules The cron schedules
     * @return array The cron schedules
     */
    public function addCronSchedules($schedules) {
        $schedules['weekly'] = [
            'interval' => WEEK_IN_SECONDS,
            'display' => __('Weekly', 'worddown')
        ];

        return $schedules;
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
            __('Settings', 'worddown'),
            __('Settings', 'worddown'),
            'manage_options',
            'worddown-settings',
            [$this, 'renderSettingsPage']
        );
    }

    /**
     * Get all field definitions from the stored config
     *
     * @return array
     */
    private function getFieldDefinitions()
    {
        $fields = [];
        
        foreach ($this->config['tabs'] as $tab) {
            foreach ($tab['sections'] as $section) {
                if (isset($section['fields'])) {
                    foreach ($section['fields'] as $field) {
                        if (isset($field['key'])) {
                            $fields[$field['key']] = $field;
                        }
                    }
                }
            }
        }
        
        return $fields;
    }

    /**
     * Sanitize the settings dynamically based on stored config
     *
     * @param array $input The input settings
     * @return array The sanitized settings
     */
    public function sanitizeSettings($input)
    {
        if (!is_array($input)) {
            return $this->getDefaultSettings();
        }

        $sanitized = [];
        $fields = $this->getFieldDefinitions();
        
        foreach ($fields as $key => $field) {
            if (!isset($field['type'])) {
                continue;
            }
            
            $value = $input[$key] ?? null;
            
            switch ($field['type']) {
                case 'boolean':
                    $sanitized[$key] = !empty($value);
                    break;
                    
                case 'text':
                    $sanitized[$key] = sanitize_text_field($value ?? '');
                    break;
                    
                case 'number':
                    $sanitized[$key] = isset($value) && is_numeric($value) ? (int) $value : ($field['default'] ?? 0);
                    break;
                    
                case 'time':
                    $sanitized[$key] = sanitize_text_field($value ?? $field['default']);
                    break;
                    
                case 'select':
                    $allowed_values = [];
                    if (isset($field['options'])) {
                        foreach ($field['options'] as $option) {
                            if (isset($option['value'])) {
                                $allowed_values[] = $option['value'];
                            }
                        }
                    }
                    $sanitized[$key] = in_array($value, $allowed_values) ? $value : ($field['default'] ?? '');
                    break;
                    
                case 'post_types':
                    $sanitized[$key] = isset($value) && is_array($value)
                        ? array_map('sanitize_text_field', $value)
                        : ($field['default'] ?? ['post', 'page']);
                    break;
                    
                case 'array':
                    $sanitized[$key] = isset($value) && is_array($value) ? $value : ($field['default'] ?? []);
                    break;
            }
        }

        // Sanitize all adapters fields dynamically
        $files = Adapters::getAdapterFiles();
        
        foreach ($files as $file) {
            $slug = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', basename($file, '.php')));
            $key = 'include_' . $slug;
            $sanitized[$key] = !empty($input[$key]);
        }

        return $sanitized;
    }

    /**
     * Get the settings
     *
     * @param string|null $key The key to get
     * @param mixed|null $default The default value
     * @return mixed The settings
     */
    public static function get($key = null, $default = null)
    {
        $settings = get_option('worddown_settings', []);
        
        if ($key === null) {
            return $settings;
        }

        return $settings[$key] ?? $default;
    }

    /**
     * Get the default settings from stored config
     *
     * @return array The default settings
     */
    public function getDefaultSettings(): array
    {
        $defaults = [];
        
        foreach ($this->config['tabs'] as $tab) {
            foreach ($tab['sections'] as $section) {
                if (isset($section['fields'])) {
                    foreach ($section['fields'] as $field) {
                        if (isset($field['key']) && isset($field['default'])) {
                            $defaults[$field['key']] = $field['default'];
                        }
                    }
                }
            }
        }
        
        return $defaults;
    }

    /**
     * Register the settings
     *
     * @return void
     */
    public function registerSettings(): void
    {
        register_setting(
            'worddown',
            'worddown_settings',
            [
                'sanitize_callback' => [$this, 'sanitizeSettings'], 
                'default' => $this->getDefaultSettings()
            ]
        );
    }

    /**
     * Maybe schedule the cron
     *
     * @return void
     */
    public function maybeScheduleCron(): void
    {
        $settings = self::get();
        $auto_export = $settings['auto_export'] ?? false;
        
        // Clear existing schedule
        wp_clear_scheduled_hook('worddown_export_cron');
        
        // Schedule new cron if auto-export is enabled
        if ($auto_export) {
            $frequency = $settings['export_frequency'] ?? 'daily';
            $time_parts = explode(':', $settings['export_time'] ?? '03:00');
            $hour = (int) ($time_parts[0] ?? 3);
            $minute = (int) ($time_parts[1] ?? 0);
            
            // Calculate next run time based on settings
            $timestamp = strtotime('today ' . sprintf('%02d:%02d', $hour, $minute));
            if ($timestamp < time()) {
                $timestamp = strtotime('tomorrow ' . sprintf('%02d:%02d', $hour, $minute));
            }
            
            // Schedule the cron job
            wp_schedule_event($timestamp, $frequency, 'worddown_export_cron');
        }
    }

    /**
     * Render the settings page
     *
     * @return void
     */
    public function renderSettingsPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        include config('paths.plugin_path') . 'resources/views/settings-page.php';
    }

    /**
     * Register REST API routes for settings schema and values
     */
    public function registerRestRoutes()
    {
        register_rest_route('worddown/v1', '/settings-schema', [
            'methods' => 'GET',
            'callback' => [$this, 'getSettingsSchema'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('worddown/v1', '/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'getSettings'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
        
        register_rest_route('worddown/v1', '/settings', [
            'methods' => 'POST',
            'callback' => [$this, 'updateSettings'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('worddown/v1', '/post-types', [
            'methods' => 'GET',
            'callback' => function() {
                $post_types = get_post_types(['public' => true], 'objects');
                $options = [];
                foreach ($post_types as $type) {
                    $options[] = [
                        'value' => $type->name,
                        'label' => $type->label,
                    ];
                }
                return rest_ensure_response($options);
            },
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('worddown/v1', '/adapters', [
            'methods' => 'GET',
            'callback' => function() {
                $adapters = [];
                $files = Adapters::getAdapterFiles();

                foreach ($files as $file) {
                    $class = 'Worddown\\Adapters\\' . basename($file, '.php');
                    
                    // Skip if class doesn't exist or installed() returns false
                    if (!class_exists($class) || !method_exists($class, 'installed') || !$class::installed()) {
                        continue;
                    }

                    $slug = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', basename($file, '.php')));
                    $label = $class;

                    if (method_exists($class, 'label')) {
                        $label = $class::label();
                    } else {
                        $label = preg_replace('/([a-z])([A-Z])/', '$1 $2', basename($file, '.php'));
                    }

                    /* translators: %s is the adapter label */
                    $description = sprintf(__('Include %s content in export', 'worddown'), $label);
                    
                    $adapters[] = [
                        'slug' => $slug,
                        'label' => $label,
                        'description' => $description,
                    ];
                }

                return rest_ensure_response($adapters);
            },
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
    }

    /**
     * Get the settings schema from config
     */
    public function getSettingsSchema()
    {
        // Ensure we're using the correct locale for REST API calls
        if (defined('REST_REQUEST') && REST_REQUEST) {
            // Get the current user's locale
            $user_locale = get_user_locale();
            if ($user_locale) {
                switch_to_locale($user_locale);
            }
        }
        
        $schema = config('settings');
        
        //Restore locale if we switched it
        if (defined('REST_REQUEST') && REST_REQUEST) {
            restore_previous_locale();
        }
        
        // Optionally, you can filter/modify the schema here
        return rest_ensure_response($schema);
    }

    /**
     * Get the current settings
     */
    public function getSettings()
    {
        $settings = self::get();
        return rest_ensure_response($settings);
    }

    /**
     * Update the settings
     */
    public function updateSettings($request)
    {
        try {
            $params = $request->get_json_params();
            $sanitized = $this->sanitizeSettings($params);
            update_option('worddown_settings', $sanitized);

            $this->maybeScheduleCron();

            return rest_ensure_response([
                'success' => true,
                'settings' => self::get(),
            ]);
        } catch (\Throwable $e) {
            return rest_ensure_response([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
} 
