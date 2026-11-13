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

### Publishing to WordPress.org

A release is a git tag with the same name as `Stable tag` in `readme.txt`, without a `v` prefix (`1.2.5`, not `v1.2.5`). Pushing that tag runs `.github/workflows/release.yml`, which builds `dist/`, installs production Composer dependencies, and deploys to WordPress.org. Pushing `master` alone does not publish.

Before the first tag, add the GitHub Actions secrets `SVN_USERNAME` and `SVN_PASSWORD` on `adamalexandersson/worddown`. Directory images live in `.wordpress-org/` and are copied to the SVN `assets/` directory. `.distignore` controls what enters the plugin zip.

To build a local zip without publishing:

```bash
npm run build
wp dist-archive . ./releases/worddown.zip --create-target-dir --format=zip
```

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

## Adapters (page builders)

When a supported builder is detected, enable it under **Settings → Adapters**:

- **Divi** — Classic Builder shortcode layouts (`[et_pb_*]`, Divi 4 / unmigrated pages). The adapter boots Divi’s modules during export so shortcodes render to HTML before Markdown conversion, and cleans Divi shortcode noise from auto-generated excerpts. Native Divi 5 block layouts (`<!-- wp:divi/... -->`) continue through the standard `the_content` / `do_blocks` pipeline and are not handled by this adapter.
- **Modularity** — Injects Modularity modules around main content when available.

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
- Runs after page-builder adapters (Modularity, Divi, etc.), so you can still append or override HTML on adapter-handled posts.
- The filter receives the current HTML content, the post ID, and the post type.
- You can return your own HTML (to override) or append/prepend to the existing content.
- The returned HTML will be cleaned and converted to Markdown as usual, and will appear in the exported file.

### When to Use
- If your site uses a custom page builder and does not store main content in the standard WordPress content field.
- If you want to add extra HTML to the Markdown export for certain posts or post types.

## Custom Metadata Filter for Markdown Export

Use the `worddown_custom_metadata` filter to add or override YAML front matter keys after Worddown assembles core metadata (respecting your **Metadata to include** settings):

```php
add_filter('worddown_custom_metadata', function($meta, $post_id, $context) {
    $meta['reading_time'] = get_post_meta($post_id, 'reading_time', true);
    return $meta;
}, 10, 3);
```

### Parameters
- `$meta` — Front matter array after admin toggles (add keys or overwrite existing ones)
- `$post_id` — Post ID being exported
- `$context` — `Worddown\Export\PostContext` with preloaded post data (read-only)

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
- PHP 8.1 or higher
- Node.js 16.0 or higher (for development)
- npm 8.0 or higher (for development)

## Support

For support questions, feature requests, or bug reports, please create an issue in the plugin's repository.

## License

This plugin is licensed under the GPL v2 or later.

## Changelog

### 1.2.4
- Declaring support for upcoming Wordpress 7.1

### 1.2.3
- Add Divi adapter for classic Builder shortcode layouts (`[et_pb_*]`); skips Divi 5 block content
- Sanitize Divi shortcode artifacts from auto-generated YAML excerpts via the adapter

### 1.2.2
- Extract export metadata into MetaBuilder with PostContext and added worddown_custom_metadata filter for extending YAML front matter

### 1.2.1
- Fix export crash when HTML preprocessing fails on malformed or invalid content

### 1.2.0
- MCP support via Wordpress Abilities API
- Fix admin menu SVG icon for WordPress 7

### 1.1.4
- Added configurable meta data and optional author metadata: Allow per-field toggles for standard YAML keys and an optional nested author block (username, display name, email, roles). Add field_group support in settings UI; merge defaults for REST; keep schema in sync with setConfig.

### 1.1.3
- Add before/after export hooks to adapter. The Modularity adapter now uses these hooks for pre/post processing, reducing coupling and keeping the core exporter generic.

### 1.1.2
- Fixes and improvements
- Added testet up to Wordpress 6.9

### 1.1.1
- Fixes and improvements

### 1.1.0
- Implement atomic export using pending directory
- General improvements

### 1.0.0
- Initial release