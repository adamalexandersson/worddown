<?php

namespace Worddown\Application;

use Worddown\Application\Container;
use Worddown\Assets\ViteManifest;
use Worddown\Admin\Header;
use Worddown\Admin\Dashboard;
use Worddown\Admin\Settings;
use Worddown\Assets\Assets;
use Worddown\Export\Export;
use Worddown\Export\Api;
use Worddown\Export\Cli;

use WP_CLI;

class App
{
    /**
     * Boot the plugin
     *
     * @return void
     */
    public static function boot()
    {
        $app = new self();

        Container::register(ViteManifest::class, new ViteManifest());

        $app->registerHooks();
        $app->registerServices();
    }

    /**
     * Register the services
     *
     * @return void
     */
    protected function registerServices()
    {
        Container::register(Header::class, new Header());
        Container::register(Dashboard::class, new Dashboard());
        Container::register(Settings::class, new Settings());
        Container::register(Assets::class, new Assets());
        Container::register(Export::class, new Export());
        Container::register(Api::class, new Api());
        Container::register(Cli::class, new Cli());
    }

    /**
     * Register the hooks
     *
     * @return void
     */
    protected function registerHooks()
    {
        add_action('plugins_loaded', [$this, 'registerCli']);
        add_action('admin_menu', [$this, 'registerMenuPage']);
        add_action('admin_body_class', [$this, 'adminBodyClass']);
    }

    /**
     * Register the menu page
     *
     * @return void
     */
    public function registerMenuPage()
    {
        add_menu_page(
            __('Worddown', 'worddown'),
            __('Worddown', 'worddown'),
            'manage_options',
            'worddown',
            false,
            'data:image/svg+xml;base64,' . base64_encode(icon('worddown-alt')),
            30
        );
    }

    /**
     * Add a class to the admin body
     *
     * @param string $classes
     * @return string
     */
    public function adminBodyClass($classes)
    {
        $classes .= ' worddown-admin-page';

        return $classes;
    }

    /**
     * Register the CLI command
     *
     * @return void
     */
    public function registerCli()
    {
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('worddown', di(Cli::class));
        }
    }
} 