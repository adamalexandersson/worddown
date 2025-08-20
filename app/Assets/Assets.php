<?php

namespace Worddown\Assets;

class Assets
{
    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminProfileColors']);
    }

    /**
     * Enqueue the admin assets
     *
     * @param string $hook The current admin page hook
     * @return void
     */
    public function enqueueAdminAssets($hook)
    {
        if ('worddown_page_worddown-settings' !== $hook && 'toplevel_page_worddown' !== $hook) {
            return;
        }
    
        $viteManifest = di(ViteManifest::class);
    
        $adminJs = $viteManifest->getAsset('resources/assets/js/admin.tsx');
        $adminCss = $viteManifest->getCss('resources/assets/js/admin.tsx');
    
        if ($adminJs) {
            wp_enqueue_script(
                'worddown',
                config('paths.plugin_url') . 'dist/' . $adminJs,
                ['jquery', 'wp-util', 'wp-i18n'],
                config('app.version'),
                [
                    'strategy'  => 'defer',
                    'in_footer' => true,
                ]
            );
            
            $translations = $this->getJsTranslations();

            wp_localize_script('worddown', 'worddown_variables', [
                'restNonce' => wp_create_nonce('wp_rest'),
                'restUrl' => esc_url_raw(rest_url()) . 'worddown/v1',
                'strings' => $translations,
            ]);
        }
    
        foreach ($adminCss as $index => $cssFile) {
            wp_enqueue_style(
                'worddown-' . $index,
                config('paths.plugin_url') . 'dist/' . $cssFile,
                [],
                config('app.version'),
            );
        }
    }

    /**
     * Temporary solution: Extracts translations from the plugin's MO file for use in JS localization.
     * This method loads the correct MO file for the current locale from resources/languages,
     * parses it, and returns an array of singular => translation pairs for use in wp_localize_script.
     *
     * @return array
     */
    private function getJsTranslations()
    {
        $locale = determine_locale();
        $domain = 'worddown';
        $plugin_lang_dir = trailingslashit(config('paths.plugin_path')) . 'resources/languages/';
        $mo_file = $plugin_lang_dir . "{$domain}-{$locale}.mo";
        $translations = [];

        if (file_exists($mo_file)) {
            $mo = new \MO();
            if ($mo->import_from_file($mo_file)) {
                foreach ($mo->entries as $entry) {
                    if (!empty($entry->translations[0])) {
                        $translations[$entry->singular] = $entry->translations[0];
                    }
                }
            }
        }

        return $translations;
    }

    /**
     * Enqueue the admin profile colors
     *
     * @return void
     */
    public function enqueueAdminProfileColors()
    {
        $admin_color = get_user_option('admin_color');
    
        global $_wp_admin_css_colors;
        if (!isset($_wp_admin_css_colors[$admin_color])) {
            return;
        }
    
        $scheme = $_wp_admin_css_colors[$admin_color];
        $colors = $scheme->colors;
        $color_count = count($colors);
        $primary_color = $color_count === 4 ? $colors[2] : $colors[1];
        $secondary_color = $color_count === 4 ? $colors[1] : $colors[2];
        $css_vars = array();
    
        array_push($css_vars, sprintf(
            '--wp-admin-color-primary: %s',
            esc_attr($primary_color)
        ));
    
        array_push($css_vars, sprintf(
            '--wp-admin-color-secondary: %s',
            esc_attr($secondary_color)
        ));
    
        array_push($css_vars, sprintf(
            '--wp-admin-color-primary-light: color-mix(in srgb, %s 10%%, transparent)',
            esc_attr($primary_color)
        ));

        array_push($css_vars, sprintf(
            '--wp-admin-color-primary-dark: color-mix(in srgb, %s 85%%, #000)',
            esc_attr($primary_color)
        ));
    
        array_push($css_vars, sprintf(
            '--wp-admin-color-primary-border: color-mix(in srgb, %s 20%%, transparent)',
            esc_attr($primary_color)
        ));
    
        array_push($css_vars, sprintf(
            '--wp-admin-color-secondary-light: color-mix(in srgb, %s 10%%, transparent)',
            esc_attr($secondary_color)
        ));

        array_push($css_vars, sprintf(
            '--wp-admin-color-secondary-dark: color-mix(in srgb, %s 85%%, #000)',
            esc_attr($secondary_color)
        ));
    
        array_push($css_vars, sprintf(
            '--wp-admin-color-secondary-border: color-mix(in srgb, %s 20%%, transparent)',
            esc_attr($secondary_color)
        ));
    
        foreach ($colors as $index => $color) {
            array_push($css_vars, sprintf(
                '--wp-admin-color-%d: %s',
                (int) $index,
                esc_attr($color)
            ));
        }
    
        $css_output = ':root {' . implode(';', array_map('esc_html', $css_vars)) . '}';
        wp_add_inline_style('wp-admin', $css_output);
    }
} 