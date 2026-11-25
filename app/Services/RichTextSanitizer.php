<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

class RichTextSanitizer
{
    private HTMLPurifier $purifier;

    private HTMLPurifier $eventPurifier;

    public const MAX_EVENT_BYTES = 4 * 1024 * 1024;

    public const MAX_IMAGE_BYTES = 1024 * 1024;

    public function __construct()
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set(
            'HTML.Allowed',
            'p[class],br,strong,b,em,i,u,s,ul,ol,li,blockquote,'
            . 'h1[class],h2[class],h3[class],h4[class],'
            . 'span[class],a[href|title|target]'
        );
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('URI.DisableExternalResources', true);
        $config->set('URI.DisableResources', true);
        $config->set('AutoFormat.RemoveEmpty', false);

        $this->purifier = new HTMLPurifier($config);

        $eventConfig = HTMLPurifier_Config::createDefault();
        $eventConfig->set('Attr.AllowedFrameTargets', ['_blank']);
        $eventConfig->set('AutoFormat.RemoveEmpty', false);
        $eventConfig->set('HTML.Allowed', 'p[class|style],div[style],br,strong,b,em,i,u,s,sub,sup,ul[style],ol[style|start],li[style],blockquote[style],'
            . 'h1[class|style],h2[class|style],h3[class|style],h4[class|style],h5[style],h6[style],span[class|style],a[href|title|target],'
            . 'table[style|width|border|cellpadding|cellspacing],caption[style],thead,tbody,tfoot,tr[style],th[style|colspan|rowspan|width|scope],td[style|colspan|rowspan|width],'
            . 'img[src|alt|title|width|height|style]');
        $eventConfig->set('CSS.AllowedProperties', ['color', 'background-color', 'font-family', 'font-size', 'font-weight', 'font-style', 'text-decoration', 'text-align', 'vertical-align', 'line-height', 'margin-left', 'margin-right', 'margin-top', 'margin-bottom', 'padding', 'padding-left', 'padding-right', 'padding-top', 'padding-bottom', 'border', 'border-width', 'border-style', 'border-color', 'border-collapse', 'width', 'height', 'list-style-type']);
        $eventConfig->set('URI.DisableResources', false);
        $eventConfig->set('URI.DisableExternalResources', false);
        $eventConfig->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'data' => true]);
        $this->eventPurifier = new HTMLPurifier($eventConfig);
    }

    public function validateEventContent(?string $html, string $field): void
    {
        if (strlen($html ?? '') > self::MAX_EVENT_BYTES) {
            throw \Illuminate\Validation\ValidationException::withMessages([$field => 'This content is too large. Keep the complete message below 4 MB.']);
        }
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">'.($html ?: '<p></p>'), LIBXML_NONET);
        foreach ($document->getElementsByTagName('img') as $image) {
            $source = trim($image->getAttribute('src'));
            if (str_starts_with(strtolower($source), 'data:')) {
                [$metadata, $payload] = array_pad(explode(',', $source, 2), 2, '');
                $decoded = str_contains(strtolower($metadata), ';base64')
                    ? base64_decode(rawurldecode($payload), true)
                    : rawurldecode($payload);
                if (strlen($decoded ?: '') > self::MAX_IMAGE_BYTES) {
                    throw \Illuminate\Validation\ValidationException::withMessages([$field => 'An embedded image is too large. Use images smaller than 1 MB.']);
                }
            }
        }
    }

    public function sanitizeEventContent(?string $html): ?string
    {
        return $html === null ? null : $this->eventPurifier->purify($html);
    }

    public function hasEventContent(?string $html): bool
    {
        $clean = $this->sanitizeEventContent($html) ?? '';
        return trim(html_entity_decode(strip_tags($clean), ENT_QUOTES | ENT_HTML5, 'UTF-8')) !== '' || str_contains($clean, '<img ');
    }

    public function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        return $this->purifier->purify($html);
    }
}
