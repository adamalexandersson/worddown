<?php

namespace Worddown\Adapters\Divi;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Detects whether Divi Theme / Divi Builder is present.
 *
 * Prefers Elegant Themes' recommended {@see ET_CORE_VERSION}, then builder
 * version constants, known builder functions, and Divi/Extra themes.
 *
 * @package Worddown
 * @since 1.2.3
 */
final class Detection
{
    /** @var list<string> */
    private const VERSION_CONSTANTS = [
        'ET_CORE_VERSION',
        'ET_BUILDER_VERSION',
        'ET_BUILDER_PLUGIN_VERSION',
    ];

    /** @var list<string> */
    private const PRESENCE_FUNCTIONS = [
        'et_pb_is_pagebuilder_used',
        'et_builder_add_main_elements',
        'et_builder_render_layout',
        'et_builder_init_global_settings',
        'et_core_is_builder_used_on_current_request',
    ];

    /** @var list<string> */
    private const BUILDER_THEMES = [
        'divi',
        'extra',
    ];

    /**
     * Whether Divi Theme or Divi Builder appears to be available on this site.
     *
     * @since 1.2.3
     * @return bool True when Divi identifiers are detected.
     */
    public static function isInstalled(): bool
    {
        foreach (self::VERSION_CONSTANTS as $constant) {
            if (\defined($constant)) {
                return true;
            }
        }

        foreach (self::PRESENCE_FUNCTIONS as $function) {
            if (\function_exists($function)) {
                return true;
            }
        }

        $themeTemplate = self::themeTemplate();

        return $themeTemplate !== '' && \in_array($themeTemplate, self::BUILDER_THEMES, true);
    }

    /**
     * Active (parent) theme template slug.
     *
     * @since 1.2.3
     * @return string Theme template directory name, or empty string.
     */
    private static function themeTemplate(): string
    {
        if (!\function_exists('wp_get_theme')) {
            return '';
        }

        $theme = \wp_get_theme();
        if (!$theme || !\is_object($theme) || !\method_exists($theme, 'get_template')) {
            return '';
        }

        return \strtolower((string) $theme->get_template());
    }
}
