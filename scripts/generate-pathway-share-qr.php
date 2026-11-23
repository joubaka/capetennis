<?php

// Generic public destination only: never encode a player, account or checkout.
require __DIR__.'/../vendor/autoload.php';

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

$destination = 'https://capetennis.co.za/ctr#pathway';
$renderer = new ImageRenderer(new RendererStyle(320, 4), new SvgImageBackEnd());
$writer = new Writer($renderer);
$target = __DIR__.'/../public/assets/img/pathway-share-qr.svg';
$writer->writeFile($destination, $target, 'UTF-8', ErrorCorrectionLevel::M());
$qr = preg_replace('/<\?xml[^>]+>\s*/', '', file_get_contents($target));
$qr = preg_replace('/<svg /', '<svg x="718" y="974" ', $qr, 1);
$poster = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="1080" height="1350" viewBox="0 0 1080 1350" role="img" aria-labelledby="title desc">
<title id="title">Your tennis. Your next step. Cape Tennis pathway.</title>
<desc id="desc">Explore Red and Orange ball development, then Green/Yellow tournament guidance for Wilson B, Wilson A and external TSA opportunities. Scan the generic QR or visit capetennis.co.za/ctr#pathway. Explore Player Plus for personal guidance where evidence and access are available.</desc>
<rect width="1080" height="1350" fill="#f3f6fb"/>
<path d="M0 0H1080V452H0Z" fill="#142e46"/>
<circle cx="1000" cy="50" r="280" fill="#214766"/>
<g fill="none" stroke="#39617f" stroke-width="2" opacity=".6"><path d="M774 0V452M930 0V452M670 100H1080M670 340H1080M670 220H1080"/></g>
<g font-family="Arial, Helvetica, sans-serif">
<text x="64" y="78" fill="#ffb45b" font-size="24" font-weight="700" letter-spacing="3">CAPE TENNIS</text>
<text x="64" y="185" fill="#fff" font-size="74" font-weight="700" letter-spacing="-3">Your tennis.</text>
<text x="64" y="270" fill="#ffb45b" font-size="74" font-weight="700" letter-spacing="-3">Your next step.</text>
<text x="68" y="330" fill="#dbe7f1" font-size="27">Find your place. Explore where you could play.</text>
<text x="68" y="376" fill="#dbe7f1" font-size="22">A pathway for players, parents and coaches.</text>
<text x="64" y="507" fill="#203d56" font-size="19" font-weight="700" letter-spacing="2">BALL-LEVEL DEVELOPMENT</text>
<rect x="64" y="532" width="464" height="132" rx="20" fill="#fff" stroke="#d7e1ec"/>
<circle cx="114" cy="582" r="17" fill="#da2934"/><text x="150" y="591" fill="#172e45" font-size="30" font-weight="700">Red ball</text>
<text x="92" y="634" fill="#4a6175" font-size="22">Build confidence through play.</text>
<rect x="550" y="532" width="466" height="132" rx="20" fill="#fff" stroke="#d7e1ec"/>
<circle cx="600" cy="582" r="17" fill="#ec790d"/><text x="636" y="591" fill="#172e45" font-size="30" font-weight="700">Orange ball</text>
<text x="578" y="634" fill="#4a6175" font-size="22">Explore the next challenge.</text>
<text x="64" y="724" fill="#203d56" font-size="19" font-weight="700" letter-spacing="2">GREEN/YELLOW TOURNAMENT GUIDANCE</text>
<rect x="64" y="750" width="952" height="166" rx="20" fill="#e5edf7"/>
<path d="M381 780V884M698 780V884" stroke="#bacbdc"/>
<text x="106" y="811" fill="#173952" font-size="29" font-weight="700">Wilson B</text>
<text x="423" y="811" fill="#173952" font-size="29" font-weight="700">Wilson A</text>
<text x="740" y="811" fill="#173952" font-size="29" font-weight="700">TSA</text>
<text x="106" y="857" fill="#4a6175" font-size="21">Event options</text>
<text x="423" y="857" fill="#4a6175" font-size="21">Event options</text>
<text x="740" y="857" fill="#4a6175" font-size="21">External opportunities</text>
<text x="64" y="1002" fill="#172e45" font-size="34" font-weight="700">What could you play next?</text>
<text x="64" y="1050" fill="#4a6175" font-size="23">Explore the free pathway guide.</text>
<text x="64" y="1086" fill="#4a6175" font-size="23">Discover Player Plus for your</text>
<text x="64" y="1122" fill="#4a6175" font-size="23">personal rating and pathway.</text>
<rect x="64" y="1160" width="616" height="64" rx="12" fill="#ffb45b"/>
<text x="86" y="1200" fill="#172e45" font-size="25" font-weight="700">capetennis.co.za/ctr#pathway</text>
<text x="64" y="1271" fill="#4a6175" font-size="18">Plan with your coach. Check each event's entry requirements.</text>
<text x="64" y="1300" fill="#4a6175" font-size="18">Personal ratings depend on eligible evidence and authorised access.</text>
</g>
__QR__
</svg>
SVG;
file_put_contents(__DIR__.'/../public/assets/img/pathway-coach-poster.svg', str_replace('__QR__', $qr, $poster));
echo "Generated generic pathway QR: {$destination}\n";
