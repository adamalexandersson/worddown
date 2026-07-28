<?php

namespace Worddown\Adapters\Divi;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helpers for classic Divi shortcode content and excerpt sanitization.
 *
 * @package Worddown
 * @since 1.2.3
 */
final class Content
{
    /**
     * Whether content contains classic Divi Builder shortcodes.
     *
     * @since 1.2.3
     * @param string $content Post content or excerpt.
     * @return bool True when [et_pb_ markers are present.
     */
    public static function hasShortcodeLayout(string $content): bool
    {
        return \str_contains($content, '[et_pb_');
    }

    /**
     * Build a plain-text excerpt from Divi shortcode content or a polluted excerpt.
     *
     * @since 1.2.3
     * @param string $content       Raw post_content (may contain [et_pb_*]).
     * @param string $excerpt       Current excerpt (may be auto-generated shortcode soup).
     * @param string $manualExcerpt Explicit post_excerpt when set by the author.
     * @param int    $wordCount     Max words for the generated excerpt.
     * @return string Plain-text excerpt without Divi shortcode markup.
     */
    public static function sanitizeExcerpt(
        string $content,
        string $excerpt,
        string $manualExcerpt = '',
        int $wordCount = 55
    ): string {
        $manualExcerpt = \trim($manualExcerpt);
        if ($manualExcerpt !== '' && !self::hasShortcodeLayout($manualExcerpt)) {
            return self::plainTrim($manualExcerpt, $wordCount);
        }

        if (!self::hasShortcodeLayout($excerpt) && \trim($excerpt) !== '') {
            return self::plainTrim($excerpt, $wordCount);
        }

        $source = self::hasShortcodeLayout($content) ? $content : $excerpt;

        return self::plainTrim(self::stripShortcodes($source), $wordCount);
    }

    /**
     * Remove shortcode tags, keeping inner text.
     *
     * @since 1.2.3
     * @param string $content Content that may contain shortcodes.
     * @return string Content with shortcode tags removed.
     */
    private static function stripShortcodes(string $content): string
    {
        $content = \preg_replace('/\[\/[^\]]+\]/', ' ', $content) ?? $content;
        $content = \preg_replace('/\[[^\]]+\]/', ' ', $content) ?? $content;

        return $content;
    }

    /**
     * Strip tags and trim to a word limit.
     *
     * @since 1.2.3
     * @param string $text      Source text.
     * @param int    $wordCount Maximum words.
     * @return string Plain trimmed text.
     */
    private static function plainTrim(string $text, int $wordCount): string
    {
        $text = \function_exists('wp_strip_all_tags')
            ? \wp_strip_all_tags($text)
            : \strip_tags($text);
        $text = \html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = \preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = \trim($text);

        if ($text === '') {
            return '';
        }

        if (\function_exists('wp_trim_words')) {
            return \wp_trim_words($text, $wordCount, '…');
        }

        $words = \preg_split('/\s+/u', $text, $wordCount + 1) ?: [];
        if (\count($words) > $wordCount) {
            $words = \array_slice($words, 0, $wordCount);

            return \implode(' ', $words) . '…';
        }

        return \implode(' ', $words);
    }
}
