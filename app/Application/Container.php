<?php

namespace Worddown\Application;

class Container
{
    private static array $instances = [];

    /**
     * Register a service
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function register(string $key, $value): void
    {
        self::$instances[$key] = $value;
    }

    /**
     * Get a registered service
     *
     * @param string $key
     * @return mixed
     */
    public static function get(string $key)
    {
        if (!isset(self::$instances[$key])) {
            throw new \RuntimeException(esc_html("Service not registered: {$key}"));
        }
        
        return self::$instances[$key];
    }
} 