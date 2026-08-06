<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

class RichTextSanitizer
{
    private HTMLPurifier $purifier;

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
    }

    public function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        return $this->purifier->purify($html);
    }
}
