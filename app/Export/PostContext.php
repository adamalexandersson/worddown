<?php

namespace Worddown\Export;

use WP_Post;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Preloaded post data for export metadata assembly.
 *
 * @package Worddown
 * @since 1.2.2
 */
final class PostContext
{
    /**
     * @param list<string> $category_names
     */
    public function __construct(
        public readonly WP_Post $post,
        public readonly int $post_id,
        public readonly string $title,
        public readonly string $permalink,
        public readonly string $post_type,
        public readonly string $date,
        public readonly string $modified,
        public readonly string $slug,
        public readonly string $excerpt,
        public readonly array $category_names,
        public readonly int $author_id,
    ) {
    }

    public static function fromPost(int $post_id): ?self
    {
        $post = get_post($post_id);
        if (!$post) {
            return null;
        }

        $title = get_the_title($post_id);
        $permalink = get_permalink($post_id);
        $post_type = get_post_type($post_id);
        $date = get_the_date('Y-m-d H:i:s', $post_id);
        $modified = get_the_modified_date('Y-m-d H:i:s', $post_id);
        $slug = $post->post_name ?: sanitize_title($title);
        $excerpt = get_the_excerpt($post_id);

        $category_names = [];
        foreach (get_the_category($post_id) as $category) {
            $category_names[] = $category->name;
        }

        return new self(
            $post,
            $post_id,
            $title,
            is_string($permalink) ? $permalink : '',
            is_string($post_type) ? $post_type : '',
            is_string($date) ? $date : '',
            is_string($modified) ? $modified : '',
            $slug,
            is_string($excerpt) ? $excerpt : '',
            $category_names,
            (int) $post->post_author,
        );
    }
}
