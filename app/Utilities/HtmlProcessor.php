<?php

namespace Worddown\Utilities;

class HtmlProcessor
{
    /**
     * Classes to remove from the HTML completely.
     * @var array
     */
    private array $disallowedClasses = ['u-preloader'];

    /**
     * Cleans HTML content for markdown conversion.
     * 
     * Removes unwanted tags and attributes, keeping only content-relevant elements.
     * 
     * @param string $content The HTML content to clean
     * @return string The cleaned HTML content
     */
    public function cleanHtmlForMarkdown(string $content): string
    {   
        // Remove style tags and their content completely
        $content = preg_replace('/<style[^>]*>.*?<\/style>/s', '', $content);
        
        // Remove HTML-encoded style tags and their content
        $content = preg_replace('/&lt;style[^&]*&gt;.*?&lt;\/style&gt;/s', '', $content);
        
        // Remove script tags and their content completely
        $content = preg_replace('/<script[^>]*>.*?<\/script>/s', '', $content);

        // Replace <span> tags with <div> tags
        $content = preg_replace(['/<span(\s|>)/i', '/<\/span>/i'], ['<div$1', '</div>'], $content);

        // Remove all HTML comments
        $content = preg_replace('/<!--.*?-->/s', '', $content);

        // Remove all disallowed class elements
        $content = $this->removeElementByClass($content);

        // Unwrap headings (h1-h6) from surrounding <div> and <a> tags (repeat to handle nesting)
        for ($i = 0; $i < 3; $i++) {
            $content = preg_replace('/<(div|a)[^>]*>\s*(<h[1-6][^>]*>.*?<\/h[1-6]>)/is', '$2', $content);
        }
        
        // Clean and format the HTML
        $content = $this->trimAndFormatHtml($content);

        // Preprocess HTML for Markdown conversion (move heading links out of <a> wrappers)
        $content = $this->preprocessHtmlForMarkdown($content);

        return $content;
    }

    /**
     * Cleans and formats HTML for better readability.
     * 
     * Removes empty elements, cleans whitespace, and adds proper formatting.
     * Trims whitespace inside <div> and <p> tags.
     * 
     * @param string $html The HTML to clean and format
     * @return string The cleaned and formatted HTML
     */
    private function trimAndFormatHtml(string $html): string
    {
        // Remove empty divs and spans
        $html = preg_replace('/<div[^>]*>\s*<\/div>/', '', $html);
        $html = preg_replace('/<span[^>]*>\s*<\/span>/', '', $html);
        
        // Remove divs that only contain empty divs
        $html = preg_replace('/<div[^>]*>\s*(<div[^>]*>\s*<\/div>\s*)*<\/div>/', '', $html);
        
        // Clean up excessive whitespace
        $html = preg_replace('/\s+/', ' ', $html);
        
        // Remove whitespace between tags
        $html = preg_replace('/>\s+</', '><', $html);
        
        // Add proper spacing around block elements
        $block_elements = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'ul', 'ol', 'blockquote'];
        foreach ($block_elements as $element) {
            $html = preg_replace('/<\/' . $element . '>/', '</' . $element . ">\n", $html);
            $html = preg_replace('/<' . $element . '[^>]*>/', "\n<" . $element . '>', $html);
        }

        // Trim whitespace inside all heading tags (h1-h6)
        $html = preg_replace_callback('/<h([1-6])([^>]*)>(.*?)<\/h\1>/is', function($matches) {
            $trimmed = trim($matches[3]);
            return '<h' . $matches[1] . $matches[2] . '>' . $trimmed . '</h' . $matches[1] . '>';
        }, $html);
        
        // Clean up multiple newlines
        $html = preg_replace('/\n\s*\n/', "\n", $html);

        $html = $this->trimTagContent($html, ['div', 'p']);
        
        // Trim whitespace
        $html = trim($html);
        
        return $html;
    }

    /**
     * Preprocess HTML to normalize heading links for Markdown conversion.
     * Moves <a><h1-6>...</h1-6></a> to <h1-6><a>...</a></h1-6> and
     * <a>...<h1-6>...</h1-6>...</a> to ...<h1-6><a>...</a></h1-6>...
     *
     * @param string $content
     * @return string
     */
    private function preprocessHtmlForMarkdown(string $content): string
    {
        // Remove all <a> tags with href starting with '#' (hash links), but keep their content
        $content = preg_replace(
            '/<a\b[^>]*href\s*=\s*["\']#.*?["\'][^>]*>(.*?)<\/a>/is',
            '$1',
            $content
        );

        // Ensure only the first <img> is kept in each <figure>
        $content = $this->keepOnlyFirstImgInFigure($content);

        // Move <a href="..."><h2>Text</h2></a> to <h2><a href="...">Text</a></h2>
        $content = preg_replace_callback(
            '/<a\s+([^>]+)>\s*<(h[1-6])([^>]*)>(.*?)<\/\2>\s*<\/a>/is',
            function($matches) {
                // $matches[2] = h2, $matches[3] = heading attributes, $matches[1] = a attributes, $matches[4] = text
                return '<' . $matches[2] . $matches[3] . '><a ' . $matches[1] . '>' . $matches[4] . '</a></' . $matches[2] . '>';
            },
            $content
        );
        
        // Move <a ...><div>...</div><h2>Title</h2></a> to <div>...</div><h2><a ...>Title</a></h2>
        // But only if the heading doesn't already contain a link
        $content = preg_replace_callback(
            '/<a\s+([^>]+)>(.*?)<(h[1-6])([^>]*)>(.*?)<\/\3>(.*?)<\/a>/is',
            function($matches) {
                // Check if the heading content already contains a link
                if (preg_match('/<a[^>]*>/', $matches[5])) {
                    // Heading already has a link, don't modify it
                    return $matches[0]; // Return the original unchanged
                }
                
                // Check if this is a simple paragraph link (no heading involved)
                $beforeHeading = trim($matches[2]);
                $afterHeading = trim($matches[6]); // Placeholder to keep if needed in the future
                
                // If there's no heading in the content, don't modify it
                if (empty($matches[5])) {
                    return $matches[0];
                }
                
                // If the content before the heading contains significant text (not just divs/spans), don't modify
                if (!empty($beforeHeading) && preg_match('/[a-zA-Z0-9]/', wp_strip_all_tags($beforeHeading))) {
                    return $matches[0];
                }
                
                // $matches[1] = a attributes
                // $matches[2] = content before heading (e.g. <div>...</div>)
                // $matches[3] = heading tag (h2, h3, etc)
                // $matches[4] = heading attributes
                // $matches[5] = heading text
                // $matches[6] = content after heading (if any)
                return $matches[2] . '<' . $matches[3] . $matches[4] . '><a ' . $matches[1] . '>' . $matches[5] . '</a></' . $matches[3] . '>' . $matches[6];
            },
            $content
        );
        
        return $content;
    }

    /**
     * Keep only the first <img> tag in each <figure> element.
     * 
     * @param string $content
     * @return string
     */
    private function keepOnlyFirstImgInFigure(string $content): string
    {
        return preg_replace_callback(
            '/<figure\b[^>]*>(.*?)<\/figure>/is',
            function ($matches) {
                $figureContent = $matches[1];
                // Find all <img ...> tags
                if (preg_match_all('/<img\b[^>]*>/is', $figureContent, $imgMatches)) {
                    if (count($imgMatches[0]) > 0) {
                        // Keep only the first <img>
                        $firstImg = $imgMatches[0][0];
                        // Remove all <img ...> tags
                        $figureContent = preg_replace('/<img\b[^>]*>/is', '', $figureContent);
                        // Prepend the first <img> at the start
                        $figureContent = $firstImg . $figureContent;
                    }
                }
                return '<figure>' . $figureContent . '</figure>';
            },
            $content
        );
    }

    /**
     * Trims the content of specified tags.
     * 
     * @param string $html
     * @param array $tags
     * @return string
     */
    public function trimTagContent($html, $tags = ['div', 'p']) {
        libxml_use_internal_errors(true); // Suppress DOM warnings for bad HTML
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        foreach ($tags as $tag) {
            $elements = $dom->getElementsByTagName($tag);
            foreach ($elements as $el) {
                // Only trim if the element has text content
                if ($el->hasChildNodes()) {
                    foreach ($el->childNodes as $child) {
                        if ($child->nodeType === XML_TEXT_NODE) {
                            $child->nodeValue = trim($child->nodeValue);
                        }
                    }
                }
            }
        }
        $result = $dom->saveHTML();

        return str_replace('<?xml encoding="utf-8" ?>', '', $result);
    }

    /**
     * Removes all elements with disallowed classes from the HTML.
     *
     * @param string $html The HTML content
     * @return string The HTML content without disallowed class elements
     */
    public function removeElementByClass(string $html): string
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $xpath = new \DOMXPath($dom);
        foreach ($this->disallowedClasses as $class) {
            foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]') as $node) {
                $node->parentNode->removeChild($node);
            }
        }

        $result = $dom->saveHTML();
        return str_replace('<?xml encoding="utf-8" ?>', '', $result);
    }
} 