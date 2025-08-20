<?php

use Worddown\Application\Container;
use Worddown\Assets\ViteManifest;

if (!function_exists('config')) {
    function config($key, $default = null) {
        static $configs = [];

        $parts = explode('.', $key, 2);
        $file = $parts[0];
        $item = $parts[1] ?? null;
        $configPath = __DIR__ . "/config/{$file}.php";

        if (!isset($configs[$file])) {
            if (file_exists($configPath)) {
                $configs[$file] = require $configPath;
            } else {
                $configs[$file] = [];
            }
        }

        if ($item === null) {
            return $configs[$file];
        }
        
        return $configs[$file][$item] ?? $default;
    }
}

if (!function_exists('di')) {
    function di($class) {
        return Container::get($class);
    }
} 

if (!function_exists('icon')) {
    function icon($name) {
        $viteManifest = di(ViteManifest::class);

        return esc_svg(file_get_contents(config('paths.plugin_path') . 'dist/' . $viteManifest->getAsset('resources/assets/icons/' . $name . '.svg')));
    }
}

if (!function_exists('esc_svg')) {
    function esc_svg($svg) {
        return wp_kses($svg, allowed_svg_tags());
    }
}

if (!function_exists('get_plugin_version')) {
    function get_plugin_version() {
        static $version = null;
        
        if ($version === null) {
            $plugin_data = get_plugin_data(config('paths.plugin_path') . 'worddown.php');
            $version = $plugin_data['Version'];
        }
        
        return $version;
    }
}

if (!function_exists('allowed_svg_tags')) {
    function allowed_svg_tags() {
        $kses_defaults = wp_kses_allowed_html('post');

        $svg_args = [
            'svg'   => [
                'fill'            => true,
                'class'           => true,
                'aria-hidden'     => true,
                'aria-labelledby' => true,
                'role'            => true,
                'xmlns'           => true,
                'width'           => true,
                'height'          => true,
                'viewbox'         => true,
                'stroke'          => true,
                'stroke-width'    => true,
                'stroke-linecap'  => true,
                'stroke-linejoin' => true,
                'stroke-dasharray' => true,
                'stroke-dashoffset' => true,
                'stroke-miterlimit' => true,
            ],
            'g'     => ['fill' => true],
            'title' => ['title' => true],
            'rect'  => [
                'fill' => true,
                'width' => true,
                'height' => true,
                'x' => true,
                'y' => true,
                'rx' => true,
                'ry' => true
            ],
            'circle' => [
                'fill' => true,
                'cx' => true,
                'cy' => true,
                'r' => true
            ],
            'path'  => [
                'd'               => true, 
                'fill'            => true  
            ]
        ];

        $allowed_tags = array_merge($kses_defaults, $svg_args);

        return $allowed_tags;
    }
}