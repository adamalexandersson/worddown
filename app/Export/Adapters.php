<?php

namespace Worddown\Export;

use Worddown\Interfaces\AdapterInterface;

class Adapters
{
    /** @var AdapterInterface[] */
    private array $adapters = [];

    /**
     * Registers a new adapter.
     * 
     * @param AdapterInterface $adapter
     * @return void
     */
    public function registerAdapter(AdapterInterface $adapter): void
    {
        $this->adapters[] = $adapter;
    }

    /**
     * Injects all adapter content into the post content.
     * 
     * @param string $content
     * @param int $post_id
     * @return string
     */
    public function injectAll(string $content, int $post_id): string
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($post_id)) {
                $content = $adapter->inject($content, $post_id);
            }
        }

        return $content;
    }

    /**
     * Get all adapter file paths.
     *
     * @return array
     */
    public static function getAdapterFiles(): array
    {
        $adapter_dir = __DIR__ . '/../Adapters';
        
        return \Worddown\Utilities\Dir::list($adapter_dir, 'files');
    }

    /**
     * Get all adapter class names.
     *
     * @return array
     */
    public static function getAdapterClasses(): array
    {
        $files = self::getAdapterFiles();
        $namespace = 'Worddown\\Adapters\\';

        return array_map(function($file) use ($namespace) {
            return $namespace . basename($file, '.php');
        }, $files);
    }
} 