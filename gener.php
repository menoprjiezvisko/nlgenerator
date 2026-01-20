<?php
/**
 * HNonline – Dynamický odpočítavací GIF (animovaný) pre newsletter
 * Závislosť: GifCreator.php (Sybio/GifCreator) v tom istom priečinku.
 *
 * Test:
 * http://localhost/gener.php?end=2026-01-25T22:59:00Z&w=500&h=150&text=FFFFFF&bg=1A1A1A
 */

declare(strict_types=1);

// Väčšina “Z” timestampov je UTC; nastavíme defaultne UTC.
date_default_timezone_set('UTC');

require_once __DIR__ . '/GifCreator.php';

use GifCreator\GifCreator;

header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$endStr = (string)($_GET['end'] ?? '2026-01-25T23:59:59Z');
// odstráň milisekundy typu 2026-01-25T22:59:00.000Z -> 2026-01-25T22:59:00Z
$endStr = preg_replace('/\.(\d+)Z$/', 'Z', $endStr);

$w = max(200, min(800, (int)($_GET['w'] ?? 500)));
$h = max(60,  min(400, (int)($_GET['h'] ?? 150)));

$textHex = strtoupper(substr(preg_replace('/[^A-F0-9]/', '', (string)($_GET['text'] ?? 'FFFFFF')), 0, 6));
$bgHex   = strtoupper(substr(preg_replace('/[^A-F0-9]/', '', (string)($_GET['bg']   ?? '1A1A1A')), 0, 6));

$framesCount = max(1, min(60, (int)($_GET['frames'] ?? 10))); // 1–60 framov
$stepSeconds = max(1, min(60, (int)($_GET['step'] ?? 1)));    // koľko sekúnd medzi framami

// Farby
$textColor = hexdec($textHex);
$bgColor = hexdec($bgHex);
$textR = ($textColor >> 16) & 0xFF; $textG = ($textColor >> 8) & 0xFF; $textB = $textColor & 0xFF;
$bgR   = ($bgColor   >> 16) & 0xFF; $bgG   = ($bgColor   >> 8) & 0xFF; $bgB   = $bgColor & 0xFF;

// Výpočet zostávajúceho času (timestamp je najstabilnejší)
$endTs = strtotime($endStr);
if ($endTs === false) {
    // fallback: ak je end zlý formát, ukáž error text
    $endTs = time();
}

$remainingBase = max(0, $endTs - time());

$pngFrames = [];
$durations = [];

// GIF delay jednotky sú v centisekundách (1/100s)
$delayCs = $stepSeconds * 100;

for ($i = 0; $i < $framesCount; $i++) {
    $remaining = max(0, $remainingBase - ($i * $stepSeconds));

    if ($remaining <= 0) {
        $label = 'ČAS UPLYNUL!';
    } else {
        $days = intdiv($remaining, 86400);
        $hours = intdiv($remaining % 86400, 3600);
        $minutes = intdiv($remaining % 3600, 60);
        $seconds = $remaining % 60;
        $label = sprintf(' %02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    $img = imagecreatetruecolor($w, $h);
    if ($img === false) {
        break;
    }

    $bgC = imagecolorallocate($img, $bgR, $bgG, $bgB);
    $textC = imagecolorallocate($img, $textR, $textG, $textB);
    $shadowC = imagecolorallocate($img, 20, 20, 20);
    imagefill($img, 0, 0, $bgC);

    // Text (built-in font). Pre väčší font odporúčam TTF + imagettftext.
    $font = 5;
    $tw = imagefontwidth($font) * strlen($label);
    $th = imagefontheight($font);
    $x = (int)(($w - $tw) / 2);
    $y = (int)(($h - $th) / 2);

    imagestring($img, $font, $x + 2, $y + 2, $label, $shadowC);
    imagestring($img, $font, $x, $y, $label, $textC);

    ob_start();
    imagepng($img);
    $pngFrames[] = ob_get_clean();
    imagedestroy($img);

    $durations[] = $delayCs;

    // ak už je 0, stačí 1 frame
    if ($remainingBase <= 0) {
        break;
    }
}

// Ak sa nepodarilo nič vyrobiť, sprav aspoň 1 frame “error”
if (count($pngFrames) === 0) {
    $img = imagecreatetruecolor($w, $h);
    $bgC = imagecolorallocate($img, 255, 0, 0);
    $textC = imagecolorallocate($img, 255, 255, 255);
    imagefill($img, 0, 0, $bgC);
    imagestring($img, 5, 10, 10, 'TIMER ERROR', $textC);
    ob_start();
    imagepng($img);
    $pngFrames[] = ob_get_clean();
    imagedestroy($img);
    $durations[] = 100;
}

$gc = new GifCreator();
$gc->create($pngFrames, $durations, 0);

echo $gc->getGif();