<?php

namespace Worddown\Export;

use Worddown\Admin\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared read-only access to exported markdown files.
 *
 * Used by the REST API and MCP abilities.
 *
 * @package Worddown
 * @since 1.2.0
 */
class FileRepository
{
    /**
     * Lists all exported markdown files.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listFiles(): array
    {
        $export = di(Export::class);
        $settings = di(Settings::class);
        $export_dir = $export->getExportDirectory();
        $files = [];

        if (!is_dir($export_dir)) {
            return $files;
        }

        $post_types = $settings::get('export_post_types', ['post', 'page']);

        foreach ($post_types as $post_type) {
            $post_type_dir = $export_dir . $post_type . '/';

            if (!is_dir($post_type_dir)) {
                continue;
            }

            $markdown_files = glob($post_type_dir . '*.md');

            if ($markdown_files === false) {
                continue;
            }

            foreach ($markdown_files as $file_path) {
                $filename = basename($file_path);

                if (!preg_match('/-(\d+)\.md$/', $filename, $matches)) {
                    continue;
                }

                $post_id = (int) $matches[1];
                $post = get_post($post_id);

                if (!$post) {
                    continue;
                }

                $upload_dir = wp_upload_dir();
                $file_url = trailingslashit($upload_dir['baseurl']) . 'worddown-export/' . $post_type . '/' . $filename;

                $files[] = [
                    'id' => $post_id,
                    'title' => get_the_title($post_id),
                    'type' => get_post_type($post_id),
                    'permalink' => get_permalink($post_id),
                    'filename' => $filename,
                    'fileurl' => $file_url,
                    'file_size' => filesize($file_path),
                    'last_modified' => filemtime($file_path),
                ];
            }
        }

        return $files;
    }

    /**
     * Retrieves metadata and markdown content for a post export.
     *
     * @param int $postId WordPress post ID.
     * @return array<string, mixed>|\WP_Error
     */
    public function getFile(int $postId): array|\WP_Error
    {
        $post = get_post($postId);

        if (!$post) {
            return new \WP_Error('not_found', 'Post not found', ['status' => 404]);
        }

        $export = di(Export::class);
        $export_dir = $export->getExportDirectory();
        $post_type = get_post_type($postId);
        $slug = $post->post_name ?: sanitize_title(get_the_title($postId));
        $filename = sprintf('%s-%s-%d.md', $post_type, $slug, $postId);
        $file_path = $export_dir . $post_type . '/' . $filename;

        if (!file_exists($file_path)) {
            $export->exportPost($postId);
        }

        if (!file_exists($file_path)) {
            return new \WP_Error('export_failed', 'Failed to generate markdown file', ['status' => 500]);
        }

        $markdown_content = file_get_contents($file_path);

        if ($markdown_content === false) {
            return new \WP_Error('read_failed', 'Failed to read markdown file', ['status' => 500]);
        }

        $upload_dir = wp_upload_dir();
        $file_url = trailingslashit($upload_dir['baseurl']) . 'worddown-export/' . $post_type . '/' . $filename;

        return [
            'id' => $postId,
            'title' => get_the_title($postId),
            'type' => $post_type,
            'permalink' => get_permalink($postId),
            'filename' => $filename,
            'fileurl' => $file_url,
            'file_size' => filesize($file_path),
            'last_modified' => filemtime($file_path),
            'markdown' => $markdown_content,
        ];
    }
}
