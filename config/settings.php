<?php

if (!defined('ABSPATH')) {
    exit;
}

// Worddown settings schema config
return [
    'tabs' => [
        [
            'key' => 'general',
            'label' => __('General', 'worddown'),
            'icon' => 'settings',
            'sections' => [
                [
                    'title' => __('Content to Export', 'worddown'),
                    'fields' => [
                        [
                            'key' => 'export_post_types',
                            'type' => 'post_types',
                            'label' => __('Post Types', 'worddown'),
                            'description' => __('Select post types to export', 'worddown'),
                            'default' => ['post', 'page'],
                        ],
                        [
                            'key' => 'include_drafts',
                            'type' => 'boolean',
                            'label' => __('Drafts', 'worddown'),
                            'switch_label' => __('Include draft posts in export', 'worddown'),
                            'description' => __('This will include draft posts in the export.', 'worddown'),
                            'default' => false,
                        ],
                        [
                            'key' => 'include_private',
                            'type' => 'boolean',
                            'label' => __('Private', 'worddown'),
                            'switch_label' => __('Include private posts in export', 'worddown'),
                            'description' => __('This will include private posts in the export.', 'worddown'),
                            'default' => false,
                        ],
                        [
                            'key' => 'chunk_size',
                            'type' => 'number',
                            'label' => __('Chunk Size', 'worddown'),
                            'description' => __('Number of posts to process in each batch during background exports. Lower values use less memory but take longer.', 'worddown'),
                            'default' => 50,
                            'min' => 10,
                            'max' => 200,
                            'step' => 10,
                        ],
                    ],
                ],
                [
                    'title' => __('Adapters (Page Builders, etc.)', 'worddown'),
                    'fields' => [
                        [
                            'key' => 'adapters',
                            'type' => 'adapters',
                            'label' => __('Adapters', 'worddown'),
                            'description' => __('Enable or disable adapters for export.', 'worddown'),
                        ],
                    ],
                ],
            ],
        ],
        [
            'key' => 'schedule',
            'label' => __('Schedule', 'worddown'),
            'icon' => 'calendar-sync',
            'sections' => [
                [
                    'title' => __('WP Cron', 'worddown'),
                    'fields' => [
                        [
                            'key' => 'auto_export',
                            'type' => 'boolean',
                            'label' => __('Automatic Export', 'worddown'),
                            'switch_label' => __('Run export automatically', 'worddown'),
                            'description' => __('This will run the export automatically.', 'worddown'),
                            'default' => false,
                        ],
                        [
                            'key' => 'export_frequency',
                            'type' => 'select',
                            'label' => __('Export Frequency', 'worddown'),
                            'description' => __('How often to run the export', 'worddown'),
                            'options' => [
                                ['value' => 'hourly', 'label' => __('Hourly', 'worddown')],
                                ['value' => 'daily', 'label' => __('Daily', 'worddown')],
                                ['value' => 'weekly', 'label' => __('Weekly', 'worddown')],
                            ],
                            'default' => 'daily',
                        ],
                        [
                            'key' => 'export_time',
                            'type' => 'time',
                            'label' => __('Export Time', 'worddown'),
                            'description' => __('Time of day to run the export (server time)', 'worddown'),
                            'default' => '03:00',
                        ],
                    ],
                ],
                [
                    'title' => __('WP-CLI & Server Cron', 'worddown'),
                    'description' => __('You can automate exports using WP-CLI and your server\'s crontab for better reliability and performance', 'worddown'),
                    'content' => [
                        [
                            'type' => 'code',
                            'language' => 'bash',
                            'code' => "# Run export immediately (default)\nwp worddown export\n\n# Run export in background mode (recommended for large sites)\nwp worddown export --background\n\n# Example: Run for a specific subsite in multisite\nwp --url=subsite.example.com worddown export --background\nwp --url=example.com/subsite worddown export --background\n\n# Add to server crontab for scheduled export (every day at 3:00)\n0 3 * * * cd /path/to/wordpress && wp worddown export --background\n\n# This will run the export using the server's cron, which is more reliable than WordPress cron for high-traffic or performance-critical sites."
                        ],
                        [
                            'type' => 'text',
                            'content' => __('You can automate exports using WP-CLI and your server\'s crontab for better reliability and performance. Use the --background flag for large exports to prevent timeouts.', 'worddown')
                        ]
                    ]
                ],
            ],
        ],
        [
            'key' => 'api',
            'label' => __('API', 'worddown'),
            'icon' => 'key',
            'sections' => [
                [
                    'title' => __('API Authentication', 'worddown'),
                    'fields' => [
                        [
                            'key' => 'api_key',
                            'type' => 'text',
                            'label' => __('API Key', 'worddown'),
                            'description' => __('API key for accessing the Worddown API. Leave empty to only allow local access.', 'worddown'),
                            'default' => '',
                        ],
                    ],
                ],
                [
                    'title' => __('Available Endpoints', 'worddown'),
                    'content' => [
                        [
                            'type' => 'endpoint',
                            'method' => 'GET',
                            'url' => '/wp-json/worddown/v1/files',
                            'desc' => __('List all exported markdown files with metadata', 'worddown'),
                        ],
                        [
                            'type' => 'endpoint',
                            'method' => 'GET',
                            'url' => '/wp-json/worddown/v1/files/{post_id}',
                            'desc' => __('Get markdown content of a specific file by post ID', 'worddown'),
                        ],
                        [
                            'type' => 'endpoint',
                            'method' => 'POST',
                            'url' => '/wp-json/worddown/v1/export',
                            'desc' => __('Trigger a new export operation. Use background=true for large exports', 'worddown'),
                        ],
                    ],
                ],
                [
                    'title' => __('Example Usage (cURL)', 'worddown'),
                    'content' => [
                        [
                            'type' => 'code',
                            'language' => 'bash',
                            'code' => "# List all files\ncurl -H \"X-API-Key: {api_key}\" {site_url}/wp-json/worddown/v1/files\n\n# Get a specific file (replace 123 with actual post ID)\ncurl -H \"X-API-Key: {api_key}\" {site_url}/wp-json/worddown/v1/files/123\n\n# Trigger immediate export\ncurl -X POST -H \"X-API-Key: {api_key}\" {site_url}/wp-json/worddown/v1/export\n\n# Trigger background export (recommended for large sites)\ncurl -X POST -H \"X-API-Key: {api_key}\" -H \"Content-Type: application/json\" -d '{\"background\": true}' {site_url}/wp-json/worddown/v1/export"
                        ],
                    ],
                ],
            ],
        ],
    ],
]; 