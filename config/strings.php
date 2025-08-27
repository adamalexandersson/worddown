<?php

if (!defined('ABSPATH')) {
    exit;
}

// Temporary solution: These strings needs to be registered here so they gets added to the POT file. Later we should use the i18n API.

return [
    'strings' => [
        __('Exported Files', 'worddown'),
        __('Editing:', 'worddown'),
        __('Failed to load dashboard data.', 'worddown'),
        __('Export completed successfully!', 'worddown'),
        __('Export failed.', 'worddown'),
        __('Export failed to start.', 'worddown'),
        __('Never', 'worddown'),
        __('Last Export', 'worddown'),
        __('Enabled', 'worddown'),
        __('Disabled', 'worddown'),
        __('Auto Export', 'worddown'),
        __('Exported files by Post Type', 'worddown'),
        __('Exported files', 'worddown'),
        __('Export Now', 'worddown'),
        __('files exported', 'worddown'),
        __('Next Scheduled Export:', 'worddown'),
        __('Exporting...', 'worddown'),
        __('Start a manual export of all content based on your current settings.', 'worddown'),
        __('Export Information', 'worddown'),
        __('Export Directory:', 'worddown'),
        __('Directory exists and is writable', 'worddown'),
        __('Directory does not exist or is not writable', 'worddown'),
        __('File Format:', 'worddown'),
        __('Saving...', 'worddown'),
        __('Save Settings', 'worddown'),
        __('Settings saved!', 'worddown'),
        __('Failed to save settings.', 'worddown'),
        __('Export started successfully!', 'worddown'),
        __('Export was cancelled.', 'worddown'),
        __('Export cancelled successfully.', 'worddown'),
        __('Failed to cancel export.', 'worddown'),
        __('Cancel Export', 'worddown'),
        __('Export in progress...', 'worddown'),
        __('Total Posts', 'worddown'),
        __('Exported', 'worddown'),
        __('Failed', 'worddown'),
        __('Estimated completion:', 'worddown'),
        __('Export is running in the background. You can safely close this page.', 'worddown'),
        __('Start a background export of all content based on your current settings.', 'worddown'),
        __('Supported adapters will be shown here automatically when they are available.', 'worddown')
    ],
];