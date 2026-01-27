<?php

declare(strict_types=1);

namespace Trail\Services;

use DOMDocument;
use DOMElement;

class RssGenerator
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function generate(array $entries, ?int $userId = null): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Create RSS element
        $rss = $dom->createElement('rss');
        $rss->setAttribute('version', '2.0');
        $dom->appendChild($rss);

        // Create channel
        $channel = $dom->createElement('channel');
        $rss->appendChild($channel);

        // Channel info
        $title = $userId 
            ? $this->config['app']['rss_title'] . ' - User ' . $userId
            : $this->config['app']['rss_title'];
        
        $this->addElement($dom, $channel, 'title', $title);
        $this->addElement($dom, $channel, 'link', $this->config['app']['base_url']);
        $this->addElement($dom, $channel, 'description', $this->config['app']['rss_description']);
        $this->addElement($dom, $channel, 'language', 'en');
        $this->addElement($dom, $channel, 'lastBuildDate', date(DATE_RSS));

        // Add items
        foreach ($entries as $entry) {
            $item = $dom->createElement('item');
            $channel->appendChild($item);

            // Get text field (with fallback for old data during tests)
            $text = $entry['text'] ?? $entry['message'] ?? '';
            
            // Get link (with fallback for old url field during tests)
            if (!empty($entry['url'])) {
                // Old format: use url field directly
                $link = $entry['url'];
            } else {
                // New format: extract first URL from text if present
                $urls = !empty($text) ? TextSanitizer::extractUrls($text) : [];
                $link = !empty($urls) ? $urls[0] : $this->config['app']['base_url'] . '/entries/' . $entry['id'];
            }

            $this->addElement($dom, $item, 'title', $text);
            $this->addElement($dom, $item, 'link', $link);
            $this->addElement($dom, $item, 'description', $text);
            $this->addElement($dom, $item, 'author', $entry['user_name']);
            $this->addElement($dom, $item, 'pubDate', date(DATE_RSS, strtotime($entry['created_at'])));
            $this->addElement($dom, $item, 'guid', $this->config['app']['base_url'] . '/entries/' . $entry['id']);
        }

        return $dom->saveXML();
    }

    private function addElement(DOMDocument $dom, DOMElement $parent, string $name, string $value): void
    {
        $element = $dom->createElement($name);
        $element->appendChild($dom->createTextNode($value));
        $parent->appendChild($element);
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1, 'UTF-8');
    }
}
