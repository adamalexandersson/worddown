# Worddown
WordPress plugin that exports pages or posts to a markdown files for AI chatbots

## Installation

1. Download the plugin zip file
2. Go to WordPress admin panel > Plugins > Add New
3. Click on "Upload Plugin" and choose the downloaded zip file
4. Click "Install Now" and then "Activate"

## Development

### Build Process

The plugin uses Vite for asset compilation. Here's how to get started with development:

1. Install dependencies:
   ```bash
   npm install
   ```

2. Development mode with hot reload:
   ```bash
   npm run dev
   ```

3. Build for production:
   ```bash
   npm run build
   ```

The build process will:
- Compile and bundle JavaScript files
- Process SCSS files to CSS
- Generate a manifest file for asset versioning
- Output optimized files to the `dist` directory

### Build Output Structure

After building, the following files will be generated in the `dist` directory:
- `js/` - Contains compiled JavaScript files with hash-based versioning
- `css/` - Contains compiled CSS files with hash-based versioning
- `.vite/manifest.json` - Contains the mapping of source files to their hashed versions

## Translations

The plugin comes with support for multiple languages. Here's how to work with translations:

### Updating Translation Template

To update the POT (template) file when new strings are added to the plugin:

```bash
wp i18n make-pot . resources/languages/worddown.pot --include=config,app,resources
```

### Adding a New Translation

1. Copy the template file to create a new PO file for your language:
   ```bash
   cp resources/languages/worddown.pot resources/languages/worddown-{language_code}.po
   ```
   Replace {language_code} with your language code (e.g., sv_SE for Swedish)

2. Edit the PO file using a translation editor like Poedit
3. Save the file - this will automatically generate the required .mo file

### Available Translations

- Swedish (sv_SE)

## Custom HTML Content Filters for Markdown Export

If you use a custom page builder (like ACF Flexible Content, Elementor, etc.) or want to inject custom HTML for a post before it is converted to Markdown, you can use the following filters:

### Filter

Use the `worddown_custom_html_content` filter to inject or override the HTML content for any post before Markdown conversion:

```php
add_filter('worddown_custom_html_content', function($content, $post_id, $post_type) {
    // Example: Replace content for all posts
    if ($post_type === 'page') {
        $content .= '<div>My custom HTML for page ' . $post_id . '</div>';
    }
    return $content;
}, 10, 3);
```

```php
add_filter('worddown_custom_html_content', function($content, $post_id, $post_type) {
    // Example: Inject ACF Flexible Content HTML
    if (have_rows('flexible_content_field', $post_id)) {
        ob_start();
        while (have_rows('flexible_content_field', $post_id)) {
            the_row();
            if (get_row_layout() == 'text_block') {
               echo '<div class="text-block">' . get_sub_field('text') . '</div>';
            }
            // ... handle other layouts ...
        }
        $content .= ob_get_clean();
    }
    return $content;
}, 10, 3);
```

### How It Works
- The filter receives the current HTML content, the post ID, and the post type.
- You can return your own HTML (to override) or append/prepend to the existing content.
- The returned HTML will be cleaned and converted to Markdown as usual, and will appear in the exported file.

### When to Use
- If your site uses a custom page builder and does not store main content in the standard WordPress content field.
- If you want to add extra HTML to the Markdown export for certain posts or post types.

## WP-CLI Command: Export

You can export posts and pages to Markdown files directly from the command line using WP-CLI.

### Usage

```
# Run export immediately (default)
wp worddown export

# Run export in background mode (recommended for large sites)
wp worddown export --background
```

This will export all configured post types to Markdown files according to your plugin settings.

### Multisite Usage

To run the export for a specific subsite in a WordPress multisite installation, use the `--url` flag:

```
wp --url=subsite.example.com worddown export --background
```

or for subdirectory multisite:

```
wp --url=example.com/subsite worddown export --background
```

### Description

- The command uses your plugin's settings to determine which post types to export.
- The exported Markdown files are saved in the configured export directory.
- You can automate exports via cron or scripts using this command.
- Use the `--background` flag for large exports to prevent timeouts and memory issues.

## REST API

The plugin provides REST API endpoints for programmatic access to export functionality.

### Available Endpoints

- `GET /wp-json/worddown/v1/files` - List all exported markdown files
- `GET /wp-json/worddown/v1/files/{post_id}` - Get specific file content
- `POST /wp-json/worddown/v1/export` - Trigger export (supports background mode)

### Authentication

All API endpoints require an API key. Set your API key in the plugin settings and include it in the `X-API-Key` header.

### API Examples

```bash
# List all exported files
curl -H "X-API-Key: {api_key}" {site_url}/wp-json/worddown/v1/files

# Get a specific file (replace 123 with actual post ID)
curl -H "X-API-Key: {api_key}" {site_url}/wp-json/worddown/v1/files/123

# Trigger immediate export
curl -X POST -H "X-API-Key: {api_key}" {site_url}/wp-json/worddown/v1/export

# Trigger background export (recommended for large sites)
curl -X POST -H "X-API-Key: {api_key}" \
     -H "Content-Type: application/json" \
     -d '{"background": true}' \
     {site_url}/wp-json/worddown/v1/export
```

**Note**: Use background mode for large exports to prevent timeouts and memory issues.

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- Node.js 16.0 or higher (for development)
- npm 8.0 or higher (for development)

## Support

For support questions, feature requests, or bug reports, please create an issue in the plugin's repository.

## License

This plugin is licensed under the GPL v2 or later.

## Changelog

### 1.1.0
- Implement atomic export using pending directory
- General improvements

### 1.0.0
- Initial release