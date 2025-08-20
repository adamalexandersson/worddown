<?php

namespace Worddown\Assets;

class ViteManifest
{
    private string $manifestPath;
    private ?array $manifest = null;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        $this->manifestPath = config('paths.plugin_path') . 'dist/.vite/manifest.json';
    }

    /**
     * Get the manifest
     *
     * @return array The manifest
     */
    public function getManifest(): array
    {
        if ($this->manifest === null) {
            if (!file_exists($this->manifestPath)) {
                return [];
            }

            $manifestContent = file_get_contents($this->manifestPath);
            $this->manifest = json_decode($manifestContent, true) ?? [];
        }

        return $this->manifest;
    }

    /**
     * Get the asset
     *
     * @param string $name The name of the asset
     * @return string|null The asset
     */
    public function getAsset(string $name): ?string
    {
        $manifest = $this->getManifest();

        return $manifest[$name]['file'] ?? null;
    }

    /**
     * Get the CSS
     *
     * @param string $name The name of the asset
     * @return array The CSS
     */
    public function getCss(string $name): array
    {
        $manifest = $this->getManifest();

        return $manifest[$name]['css'] ?? [];
    }

    /**
     * Get the imports
     *
     * @param string $name The name of the asset
     * @return array The imports
     */
    public function getImports(string $name): array
    {
        $manifest = $this->getManifest();
        
        return $manifest[$name]['imports'] ?? [];
    }
} 
