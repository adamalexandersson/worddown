<?php

namespace Worddown\Export;

use Worddown\Admin\Settings;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds YAML front matter metadata for exported markdown files.
 *
 * Filter: worddown_custom_metadata — see readme.txt Developer section.
 *
 * @package Worddown
 * @since 1.2.2
 */
class MetaBuilder
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * @return array<string, mixed>
     */
    public function build(PostContext $ctx): array
    {
        $meta = [];

        foreach ($this->getCoreFields() as $field) {
            if (empty($this->settings::get($field['setting_key'], $field['default']))) {
                continue;
            }

            $value = $field['resolve']($ctx);

            if (isset($field['shouldInclude']) && !$field['shouldInclude']($value)) {
                continue;
            }

            $meta[$field['yaml_key']] = $value;
        }

        $author = $this->buildAuthorMeta($ctx);
        if (!empty($author)) {
            $meta['author'] = $author;
        }

        /**
         * Filters export front matter metadata after core fields are assembled.
         *
         * @param array<string, mixed> $meta    Front matter array (after admin toggles).
         * @param int                    $post_id Post ID.
         * @param PostContext            $ctx     Preloaded post data; read-only for extensions.
         */
        $filtered = apply_filters('worddown_custom_metadata', $meta, $ctx->post_id, $ctx);

        return is_array($filtered) ? $filtered : $meta;
    }

    /**
     * @return list<array{yaml_key: string, setting_key: string, default: bool, resolve: callable(PostContext): mixed, shouldInclude?: callable(mixed): bool}>
     */
    private function getCoreFields(): array
    {
        return [
            [
                'yaml_key' => 'date',
                'setting_key' => 'export_meta_date',
                'default' => true,
                'resolve' => fn (PostContext $ctx) => $ctx->date,
            ],
            [
                'yaml_key' => 'modified',
                'setting_key' => 'export_meta_modified',
                'default' => true,
                'resolve' => fn (PostContext $ctx) => $ctx->modified,
            ],
            [
                'yaml_key' => 'slug',
                'setting_key' => 'export_meta_slug',
                'default' => true,
                'resolve' => fn (PostContext $ctx) => $ctx->slug,
            ],
            [
                'yaml_key' => 'id',
                'setting_key' => 'export_meta_id',
                'default' => true,
                'resolve' => fn (PostContext $ctx) => $ctx->post_id,
            ],
            [
                'yaml_key' => 'type',
                'setting_key' => 'export_meta_type',
                'default' => true,
                'resolve' => fn (PostContext $ctx) => $ctx->post_type,
            ],
            [
                'yaml_key' => 'excerpt',
                'setting_key' => 'export_meta_excerpt',
                'default' => true,
                'resolve' => fn (PostContext $ctx) => $ctx->excerpt,
            ],
            [
                'yaml_key' => 'permalink',
                'setting_key' => 'export_meta_permalink',
                'default' => true,
                'resolve' => fn (PostContext $ctx) => $ctx->permalink,
            ],
            [
                'yaml_key' => 'category',
                'setting_key' => 'export_meta_category',
                'default' => true,
                'resolve' => fn (PostContext $ctx) => $ctx->category_names,
                'shouldInclude' => fn ($value) => !empty($value),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAuthorMeta(PostContext $ctx): array
    {
        $user = get_userdata($ctx->author_id);
        if (!$user instanceof WP_User) {
            return [];
        }

        $author = [];

        foreach ($this->getAuthorFields() as $field) {
            if (empty($this->settings::get($field['setting_key'], $field['default']))) {
                continue;
            }

            $author[$field['yaml_key']] = $field['resolve']($user);
        }

        return $author;
    }

    /**
     * @return list<array{yaml_key: string, setting_key: string, default: bool, resolve: callable(WP_User): mixed}>
     */
    private function getAuthorFields(): array
    {
        return [
            [
                'yaml_key' => 'username',
                'setting_key' => 'export_meta_author_username',
                'default' => false,
                'resolve' => fn (WP_User $user) => $user->user_login,
            ],
            [
                'yaml_key' => 'name',
                'setting_key' => 'export_meta_author_name',
                'default' => false,
                'resolve' => fn (WP_User $user) => $user->display_name,
            ],
            [
                'yaml_key' => 'email',
                'setting_key' => 'export_meta_author_email',
                'default' => false,
                'resolve' => fn (WP_User $user) => $user->user_email,
            ],
            [
                'yaml_key' => 'roles',
                'setting_key' => 'export_meta_author_roles',
                'default' => false,
                'resolve' => fn (WP_User $user) => array_values($user->roles),
            ],
        ];
    }
}
