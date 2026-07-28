<?php

namespace Worddown\Adapters;

use Worddown\Adapters\Divi\Content;
use Worddown\Adapters\Divi\Detection;
use Worddown\Adapters\Divi\Renderer;
use Worddown\Admin\Settings;
use Worddown\Export\PostContext;
use Worddown\Interfaces\AdapterInterface;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adapter for classic Divi Builder shortcode layouts ([et_pb_*]).
 *
 * Self-contained: boots Divi during export, re-renders shortcode content,
 * and sanitizes Divi-polluted YAML excerpts via worddown_custom_metadata.
 * Divi 5 block layouts are left to core the_content / do_blocks.
 *
 * @package Worddown
 * @since 1.2.3
 */
class Divi implements AdapterInterface
{
    private Renderer $renderer;

    /**
     * @since 1.2.3
     */
    public function __construct()
    {
        $this->renderer = new Renderer();

        \add_action('worddown_before_export', [$this, 'beforeExport']);
        \add_filter('worddown_custom_metadata', [$this, 'sanitizeExportMetadata'], 10, 3);
    }

    /**
     * Boot Divi modules once before export so shortcodes can expand.
     *
     * @since 1.2.3
     * @return void
     */
    public function beforeExport(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->renderer->boot();
    }

    /**
     * Sanitize Divi shortcode noise from exported YAML excerpt metadata.
     *
     * @since 1.2.3
     * @param array<string, mixed> $meta    Front matter assembled by MetaBuilder.
     * @param int                  $post_id Post ID being exported.
     * @param mixed                $context Preloaded post context.
     * @return array<string, mixed> Filtered metadata.
     */
    public function sanitizeExportMetadata(array $meta, int $post_id, $context): array
    {
        if (!$this->isEnabled() || !$context instanceof PostContext) {
            return $meta;
        }

        if (!isset($meta['excerpt']) || !\is_string($meta['excerpt'])) {
            return $meta;
        }

        $post = $context->post;
        if (!Content::hasShortcodeLayout($post->post_content)) {
            return $meta;
        }

        if (!Content::hasShortcodeLayout($meta['excerpt'])) {
            return $meta;
        }

        $meta['excerpt'] = Content::sanitizeExcerpt(
            $post->post_content,
            $meta['excerpt'],
            \is_string($post->post_excerpt) ? $post->post_excerpt : ''
        );

        return $meta;
    }

    /**
     * Replace unexpanded Divi shortcodes with rendered HTML.
     *
     * If Divi rendering fails or leaves shortcodes intact, falls back to a
     * plain-text HTML paragraph built from shortcode inner content.
     *
     * @since 1.2.3
     * @param string $content HTML from the_content / prior adapters (may still contain shortcodes).
     * @param int    $post_id Post ID being exported.
     * @return string Rendered HTML suitable for Markdown conversion.
     */
    public function inject(string $content, int $post_id): string
    {
        $post = \get_post($post_id);
        if (!$post) {
            return $content;
        }

        $raw = $post->post_content;
        if (!Content::hasShortcodeLayout($raw)) {
            return $content;
        }

        $originalPost = $GLOBALS['post'] ?? null;
        $wpQuery = $GLOBALS['wp_query'] ?? null;
        $wasSingular = null;

        if ($wpQuery instanceof \WP_Query) {
            $wasSingular = $wpQuery->is_singular;
            $wpQuery->is_singular = true;
        }

        $GLOBALS['post'] = $post;

        try {
            if (\function_exists('setup_postdata')) {
                \setup_postdata($post);
            }

            $rendered = $this->renderer->render($raw, $post_id);

            if (Content::hasShortcodeLayout($rendered)) {
                return $this->fallbackPlainHtml($raw);
            }

            return $rendered;
        } catch (\Throwable $e) {
            return $this->fallbackPlainHtml($raw);
        } finally {
            if ($originalPost !== null) {
                $GLOBALS['post'] = $originalPost;
                if (\function_exists('setup_postdata')) {
                    \setup_postdata($originalPost);
                }
            } else {
                unset($GLOBALS['post']);
                if (\function_exists('wp_reset_postdata')) {
                    \wp_reset_postdata();
                }
            }

            if ($wpQuery instanceof \WP_Query && $wasSingular !== null) {
                $wpQuery->is_singular = $wasSingular;
            }
        }
    }

    /**
     * Whether this adapter should process the given post.
     *
     * @since 1.2.3
     * @param int $post_id Post ID.
     * @return bool True when Divi is enabled and content has [et_pb_*].
     */
    public function supports(int $post_id): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $post = \get_post($post_id);
        if (!$post) {
            return false;
        }

        return Content::hasShortcodeLayout($post->post_content);
    }

    /**
     * Whether Divi Theme or Divi Builder is available.
     *
     * @since 1.2.3
     * @return bool True when Divi install identifiers are detected.
     */
    public static function installed(): bool
    {
        return Detection::isInstalled();
    }

    /**
     * Human-readable adapter label for the settings UI.
     *
     * @since 1.2.3
     * @return string
     */
    public static function label(): string
    {
        return 'Divi';
    }

    /**
     * Adapter description for the settings UI.
     *
     * @since 1.2.3
     * @return string Translated description.
     */
    public static function description(): string
    {
        return \__('Include classic Divi Builder shortcode content in export (Divi 4). Divi 5 block layouts use the standard content pipeline.', 'worddown');
    }

    /**
     * Divi is present and the admin adapter toggle is on.
     *
     * @since 1.2.3
     * @return bool
     */
    private function isEnabled(): bool
    {
        return static::installed() && (bool) Settings::get('include_divi', false);
    }

    /**
     * Last-resort HTML when Divi cannot expand shortcodes.
     *
     * @since 1.2.3
     * @param string $raw Raw shortcode content.
     * @return string Minimal HTML paragraph with plain inner text.
     */
    private function fallbackPlainHtml(string $raw): string
    {
        $plain = Content::sanitizeExcerpt($raw, $raw, '', 200);
        if ($plain === '') {
            return '';
        }

        $escaped = \function_exists('esc_html')
            ? \esc_html($plain)
            : \htmlspecialchars($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return '<p>' . $escaped . '</p>';
    }
}
