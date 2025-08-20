<?php

namespace Worddown\Adapters;

use Worddown\Admin\Settings;
use Modularity\Editor;
use Modularity\Helper\Post as ModularityPost;

use Worddown\Interfaces\AdapterInterface;

class Modularity implements AdapterInterface
{
    /**
     * Injects Modularity modules into the post content.
     * 
     * @param string $content
     * @param int $post_id
     * @return string
     */
    public function inject(string $content, int $post_id): string
    {
        try {
            // Get modules for the post
            $modules = Editor::getPostModules($post_id);

            // Ensure 'main-content' is present in the modules array
            if (!array_key_exists('main-content', $modules)) {
                $modules['main-content'] = null;
            }

            // Get Modularity options
            $modularityOptions = get_option('modularity-options', []);
            $enabled_modules = $modularityOptions['enabled-modules'] ?? [];

            $original_post = $GLOBALS['post'] ?? null;
            $GLOBALS['post'] = get_post($post_id);

            $template_key = ModularityPost::getPostTemplate($post_id);

            if ($original_post !== null) {
                $GLOBALS['post'] = $original_post;
            } else {
                unset($GLOBALS['post']);
            }

            $enabled_areas = $modularityOptions['enabled-areas'][$template_key] ?? [];

            $modules_html = '';
            $original_content_inserted = false;
            
            $areaOrder = [
                'slider-area',
                'main-content',
                'top-sidebar',
                'above-columns-sidebar',
                'left-sidebar',
                'left-sidebar-bottom',
                'content-area-top',
                'content-area',
                'content-area-bottom',
                'right-sidebar',
            ];

            // Sort the modules array by keys according to $areaOrder
            uksort($modules, function($a, $b) use ($areaOrder) {
                $a_index = array_search($a, $areaOrder);
                $b_index = array_search($b, $areaOrder);
                if ($a_index === false) $a_index = 999;
                if ($b_index === false) $b_index = 999;
                return $a_index <=> $b_index;
            });

            // Process each module area in the sorted order
            foreach ($modules as $area_slug => $area) {
                if ($area_slug === 'main-content') {
                    $modules_html .= $content . "\n\n";
                    $original_content_inserted = true;
                    
                    continue;
                }

                if (!in_array($area_slug, $enabled_areas)) {
                    continue; // skip modules in disabled areas
                }
                
                if (!isset($area['modules']) || !is_array($area['modules'])) {
                    continue;
                }

                foreach ($area['modules'] as $module) {
                    // Skip hidden modules
                    if (isset($module->hidden) && $module->hidden === true) {
                        continue;
                    }

                    // Skip disabled modules
                    if (!in_array($module->post_type, $enabled_modules)) {
                        continue;
                    }

                    // Output module HTML
                    $module_html = \Modularity\App::$display->outputModule(
                        $module,
                        array('edit_module' => true),
                        array(),
                        false
                    );

                    if (!empty($module_html)) {
                        $modules_html .= $module_html . "\n\n";
                    }
                }
            }
            
            // If main-content was not added to the modules array, add it at the beginning
            if (!$original_content_inserted) {
                $content .= "\n\n" . $modules_html;
            } else {
                $content = $modules_html;
            }
        } catch (\Exception $e) {}

        return $content;
    }

    /**
     * Checks if the post has Modularity modules.
     * 
     * @param int $post_id
     * @return bool
     */
    public function supports(int $post_id): bool
    {
        if (!class_exists('\\Modularity\\Editor') || !class_exists('\\Modularity\\App')) {
            return false;
        }
        
        $include_modularity = Settings::get('include_modularity', false);
        if (!$include_modularity) {
            return false;
        }
        
        $modules = Editor::getPostModules($post_id);
        if (empty($modules)) {
            return false;
        }

        return true;
    }

    /**
     * Returns true if the Modularity plugin is installed and enabled.
     *
     * @return bool
     */
    public static function installed(): bool
    {
        return class_exists('\\Modularity\\Editor') && class_exists('\\Modularity\\App');
    }
} 