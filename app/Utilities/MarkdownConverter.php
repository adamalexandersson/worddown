<?php

namespace Worddown\Utilities;

use League\HTMLToMarkdown\HtmlConverter;
use League\HTMLToMarkdown\Converter\TableConverter;

/**
 * Handles conversion of HTML to Markdown and cleaning of Markdown content.
 */
class MarkdownConverter
{
    /** @var HtmlConverter */
    private HtmlConverter $converter;

    public function __construct()
    {
        $this->converter = new HtmlConverter([
            'strip_tags' => true,
            'remove_nodes' => 'script style',
            'hard_break' => true,
            'preserve_comments' => false,
            'suppress_errors' => true,
            'header_style' => 'atx',
        ]);
        
        $this->converter->getEnvironment()->addConverter(new TableConverter());
    }

    /**
     * Converts HTML content to Markdown and cleans the result.
     *
     * @param string $html The HTML content to convert
     * @return string The cleaned markdown content
     */
    public function htmlToMarkdown(string $html): string
    {
        try {
            $markdown = $this->converter->convert($html);
            return $this->cleanMarkdownContent($markdown);
        } catch (\Exception $e) {
            // Fallback: return cleaned HTML if conversion fails
            return $html;
        }
    }

    /**
     * Cleans and formats markdown content for better readability.
     *
     * Removes excessive spacing, cleans up empty lines, and improves formatting.
     *
     * @param string $markdown The markdown content to clean
     * @return string The cleaned markdown content
     */
    public function cleanMarkdownContent(string $markdown): string
    {
        // Remove excessive whitespace at the beginning and end
        $markdown = trim($markdown);
        
        // Replace multiple consecutive newlines with maximum 2 newlines
        $markdown = preg_replace('/\n{3,}/', "\n\n", $markdown);
        
        // Remove empty lines between headings and content
        $markdown = preg_replace('/\n{2,}(#{1,6}\s+[^\n]+)\n{2,}/', "\n\n$1\n\n", $markdown);
        
        // Remove excessive spacing around links
        $markdown = preg_replace('/\n{2,}\[([^\]]+)\]\(([^)]+)\)\n{2,}/', "\n\n[$1]($2)\n\n", $markdown);
        
        // Clean up spacing around horizontal rules
        $markdown = preg_replace('/\n{2,}(-{3,})\n{2,}/', "\n\n$1\n\n", $markdown);
        
        // Remove empty lines before and after lists
        $markdown = preg_replace('/\n{2,}([\*\-+]\s+[^\n]+)/', "\n\n$1", $markdown);
        $markdown = preg_replace('/([\*\-+]\s+[^\n]+)\n{2,}/', "$1\n\n", $markdown);
        
        // Clean up spacing around blockquotes
        $markdown = preg_replace('/\n{2,}(>\s+[^\n]+)/', "\n\n$1", $markdown);
        $markdown = preg_replace('/(>\s+[^\n]+)\n{2,}/', "$1\n\n", $markdown);
        
        // Remove excessive spacing around code blocks
        $markdown = preg_replace('/\n{2,}(```[^\n]*\n)/', "\n\n$1", $markdown);
        $markdown = preg_replace('/(```\n)\n{2,}/', "$1\n\n", $markdown);
        
        // Clean up spacing around inline code
        $markdown = preg_replace('/\n{2,}(`[^`]+`)\n{2,}/', "\n\n$1\n\n", $markdown);
        
        // Remove excessive spacing around bold and italic text
        $markdown = preg_replace('/\n{2,}(\*\*[^*]+\*\*)\n{2,}/', "\n\n$1\n\n", $markdown);
        $markdown = preg_replace('/\n{2,}(\*[^*]+\*)\n{2,}/', "\n\n$1\n\n", $markdown);
        
        // Clean up spacing around images
        $markdown = preg_replace('/\n{2,}(!\[[^\]]*\]\([^)]+\))\n{2,}/', "\n\n$1\n\n", $markdown);
        
        // Remove excessive spacing around tables
        $markdown = preg_replace('/\n{2,}(\|[^|]+\|[^|]+\|)\n{2,}/', "\n\n$1\n\n", $markdown);
        
        // Clean up any remaining excessive newlines at the end
        $markdown = preg_replace('/\n{3,}$/', "\n\n", $markdown);
        
        // Ensure consistent line endings
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        
        // Final trim to remove any leading/trailing whitespace
        $markdown = trim($markdown);
        
        return $markdown;
    }
} 