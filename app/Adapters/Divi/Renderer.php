<?php

namespace Worddown\Adapters\Divi;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders classic Divi Builder shortcodes using Elegant Themes APIs.
 *
 * Boots builder modules when needed, then prefers et_builder_render_layout,
 * falling back to do_shortcode and the_content.
 *
 * @package Worddown
 * @since 1.2.3
 */
class Renderer
{
    private static bool $booted = false;

    /**
     * Ensure Divi Builder modules / shortcodes are registered (idempotent).
     *
     * @since 1.2.3
     * @return void
     */
    public function boot(): void
    {
        if (self::$booted) {
            return;
        }

        try {
            if (\function_exists('et_builder_init_global_settings')) {
                \et_builder_init_global_settings();
            }

            if (\function_exists('et_builder_add_main_elements')) {
                \et_builder_add_main_elements();
            }
        } catch (\Throwable $e) {
            // Avoid retrying a broken boot path on every post.
        }

        self::$booted = true;
    }

    /**
     * Render raw Divi shortcode content to HTML.
     *
     * @since 1.2.3
     * @param string $content Raw post content containing [et_pb_*] shortcodes.
     * @param int    $post_id Post ID being exported (context for Divi modules).
     * @return string Rendered HTML, or the original content if rendering fails.
     */
    public function render(string $content, int $post_id): string
    {
        $this->boot();

        try {
            if (\function_exists('et_builder_render_layout')) {
                $rendered = \et_builder_render_layout($content);
                if (\is_string($rendered) && $rendered !== '') {
                    return $rendered;
                }
            }

            if (\function_exists('do_shortcode')) {
                $rendered = \do_shortcode($content);
                if (\is_string($rendered) && $rendered !== '' && $rendered !== $content) {
                    return $rendered;
                }
            }

            $rendered = \apply_filters('the_content', $content);

            return \is_string($rendered) ? $rendered : $content;
        } catch (\Throwable $e) {
            return $content;
        }
    }
}
