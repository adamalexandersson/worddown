<?php

namespace Worddown\Interfaces;

interface AdapterInterface
{
    /**
     * Injects adapter content into the post content.
     *
     * @param string $content The existing post content
     * @param int $post_id The post ID
     * @return string The content with adapter content injected
     */
    public function inject(string $content, int $post_id): string;

    /**
     * Returns true if this injector supports the given post.
     *
     * @param int $post_id
     * @return bool
     */
    public function supports(int $post_id): bool;

    /**
     * Returns true if the adapter is installed and enabled.
     *
     * @return bool
     */
    public static function installed(): bool;
} 