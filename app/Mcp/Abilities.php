<?php

namespace Worddown\Mcp;

use Worddown\Admin\Settings;
use Worddown\Export\FileRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers WordPress abilities for MCP integration.
 *
 * @package Worddown
 * @since 1.2.0
 */
class Abilities
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('wp_abilities_api_categories_init', [$this, 'registerCategories']);
        add_action('wp_abilities_api_init', [$this, 'registerAbilities']);
    }

    /**
     * Registers the WordDown ability category.
     *
     * @return void
     */
    public function registerCategories(): void
    {
        if (!function_exists('wp_register_ability_category') || !$this->isMcpEnabled()) {
            return;
        }

        wp_register_ability_category('worddown', [
            'label' => __('WordDown', 'worddown'),
            'description' => __('Abilities for accessing WordDown exported markdown files.', 'worddown'),
        ]);
    }

    /**
     * Registers WordDown abilities when the Abilities API is available.
     *
     * @return void
     */
    public function registerAbilities(): void
    {
        if (!function_exists('wp_register_ability') || !$this->isMcpEnabled()) {
            return;
        }

        wp_register_ability('worddown/list-files', [
            'label' => __('List exported markdown files', 'worddown'),
            'description' => __('Returns metadata for every markdown file WordDown has exported.', 'worddown'),
            'category' => 'worddown',
            'input_schema' => [
                'type' => 'object',
                'properties' => [],
            ],
            'output_schema' => $this->getListFilesOutputSchema(),
            'execute_callback' => function () {
                return di(FileRepository::class)->listFiles();
            },
            'permission_callback' => function () {
                return true;
            },
            'meta' => [
                'annotations' => [
                    'readonly' => true,
                    'idempotent' => true,
                ],
                'mcp' => [
                    'public' => true,
                    'type' => 'tool',
                ],
            ],
        ]);

        wp_register_ability('worddown/get-file', [
            'label' => __('Get exported markdown file', 'worddown'),
            'description' => __('Returns metadata and markdown content for a specific post by ID.', 'worddown'),
            'category' => 'worddown',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'integer',
                        'description' => __('WordPress post ID', 'worddown'),
                    ],
                ],
                'required' => ['id'],
            ],
            'output_schema' => $this->getGetFileOutputSchema(),
            'execute_callback' => function ($input) {
                $post_id = isset($input['id']) ? (int) $input['id'] : 0;

                return di(FileRepository::class)->getFile($post_id);
            },
            'permission_callback' => function () {
                return true;
            },
            'meta' => [
                'annotations' => [
                    'readonly' => true,
                    'idempotent' => true,
                ],
                'mcp' => [
                    'public' => true,
                    'type' => 'tool',
                ],
            ],
        ]);
    }

    /**
     * Whether MCP abilities are enabled in plugin settings.
     *
     * @return bool
     */
    private function isMcpEnabled(): bool
    {
        return !empty(Settings::get('mcp_enabled', false));
    }

    /**
     * Output schema for list-files ability.
     *
     * @return array<string, mixed>
     */
    private function getListFilesOutputSchema(): array
    {
        return [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'title' => ['type' => 'string'],
                    'type' => ['type' => 'string'],
                    'permalink' => ['type' => 'string'],
                    'filename' => ['type' => 'string'],
                    'fileurl' => ['type' => 'string'],
                    'file_size' => ['type' => 'integer'],
                    'last_modified' => ['type' => 'integer'],
                ],
                'required' => ['id', 'title', 'type', 'filename', 'fileurl'],
            ],
        ];
    }

    /**
     * Output schema for get-file ability.
     *
     * @return array<string, mixed>
     */
    private function getGetFileOutputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
                'type' => ['type' => 'string'],
                'permalink' => ['type' => 'string'],
                'filename' => ['type' => 'string'],
                'fileurl' => ['type' => 'string'],
                'file_size' => ['type' => 'integer'],
                'last_modified' => ['type' => 'integer'],
                'markdown' => ['type' => 'string'],
            ],
            'required' => ['id', 'title', 'type', 'filename', 'fileurl', 'markdown'],
        ];
    }
}
