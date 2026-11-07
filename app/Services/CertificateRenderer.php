<?php

namespace App\Services;

use App\Models\Certificate;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Dompdf\Dompdf;
use Dompdf\Options;
use GdImage;

class CertificateRenderer
{
    public function __construct(private CertificateService $certificates) {}

    public function png(Certificate $certificate, string $format = 'post'): string
    {
        [$width, $height] = match ($format) {
            'landscape' => [1600, 1132], 'story' => [1080, 1920], default => [1080, 1350],
        };
        $image = imagecreatetruecolor($width, $height);
        $green = imagecolorallocate($image, 11, 61, 46);
        $ivory = imagecolorallocate($image, 250, 247, 238);
        $gold = imagecolorallocate($image, 193, 153, 73);
        $muted = imagecolorallocate($image, 104, 112, 103);
        imagefill($image, 0, 0, $green);
        for ($x = 0; $x <= $width; $x += 60) {
            for ($y = 0; $y <= $height; $y += 60) {
                imageline($image, $x, $y - 20, $x + 20, $y, $gold);
                imageline($image, $x + 20, $y, $x, $y + 20, $gold);
            }
        }
        imagefilledrectangle($image, 36, 36, $width - 36, $height - 36, $ivory);
        imagesetthickness($image, 2);
        imagerectangle($image, 54, 54, $width - 54, $height - 54, $gold);
        imagerectangle($image, 66, 66, $width - 66, $height - 66, $gold);
        $landscape = $format === 'landscape';
        $top = $format === 'story' ? 260 : ($landscape ? 120 : 110);
        $this->text($image, 'QUR’ON AI USTOZ', $top, 28, $gold, $width - 180, true);
        $this->text($image, 'ILM · AMAL · TARAQQIYOT', $top + 48, 12, $muted, $width - 180);
        $this->text($image, 'SERTIFIKAT', $top + 155, $landscape ? 72 : 58, $green, $width - 180, true);
        $this->text($image, $certificate->type === 'tajwid' ? 'TAJVID NAZARIY BILIMLARI' : 'KURSNI YAKUNLASH', $top + 205, 17, $gold, $width - 180, true);
        imageline($image, (int) ($width * .3), $top + 242, (int) ($width * .7), $top + 242, $gold);
        $y = $this->text($image, $certificate->recipient_name, $top + 320, $landscape ? 44 : 38, $green, $width - 210, true);
        $y = $this->text($image, $certificate->title, $y + ($landscape ? 35 : 50), 24, $green, $width - 230);
        $description = $certificate->type === 'tajwid'
            ? 'Tajvid nazariy imtihonidan muvaffaqiyatli o‘tdi.'
            : 'Kurs darslari va baholash talablarini muvaffaqiyatli yakunladi.';
        $y = $this->text($image, $description, $y + ($landscape ? 15 : 42), 17, $muted, $width - 240);
        if ($certificate->score !== null) {
            $y = $this->text($image, 'NATIJA: '.$certificate->score.'%', $y + ($landscape ? 20 : 44), 27, $gold, $width - 200, true);
        }
        $bottom = $height - ($landscape ? 390 : 430);
        $qr = $this->qr($this->certificates->verificationUrl($certificate), $landscape ? 5 : 4);
        $qrSize = imagesx($qr);
        imagecopy($image, $qr, (int) (($width - $qrSize) / 2), $bottom, 0, 0, $qrSize, $qrSize);
        $this->text($image, 'QR orqali haqiqiyligini tekshiring', $bottom + $qrSize + 28, 12, $muted, $width - 160);
        $this->text($image, $certificate->issued_at->timezone(config('app.display_timezone', 'Asia/Tashkent'))->format('d.m.Y').'  ·  '.$certificate->code, $height - 95, 11, $muted, $width - 150);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    public function pdf(Certificate $certificate): string
    {
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $pdf = new Dompdf($options);
        $png = base64_encode($this->png($certificate, 'landscape'));
        $pdf->loadHtml('<html><head><style>@page{margin:0}body{margin:0;line-height:0}img{display:block;width:100%;height:100%}</style></head><body><img src="data:image/png;base64,'.$png.'"></body></html>');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return $pdf->output();
    }

    public function qr(string $url, int $scale = 5): GdImage
    {
        $matrix = Encoder::encode($url, ErrorCorrectionLevel::M())->getMatrix();
        $scale = min($scale, max(1, intdiv(240, $matrix->getWidth() + 8)));
        $size = ($matrix->getWidth() + 8) * $scale;
        $image = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $white);
        for ($y = 0; $y < $matrix->getWidth(); $y++) {
            for ($x = 0; $x < $matrix->getWidth(); $x++) {
                if ($matrix->get($x, $y) === 1) {
                    imagefilledrectangle($image, ($x + 4) * $scale, ($y + 4) * $scale, ($x + 5) * $scale - 1, ($y + 5) * $scale - 1, $black);
                }
            }
        }

        return $image;
    }

    private function text(GdImage $image, string $text, int $baseline, int $size, int $color, int $maxWidth, bool $bold = false): int
    {
        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans'.($bold ? '-Bold' : '').'.ttf');
        $text = preg_replace('/\s+/u', ' ', trim($text));
        while ($size > 12 && $this->width($text, $size, $font) > $maxWidth) {
            $size--;
        }
        $lines = [''];
        foreach (explode(' ', $text) as $word) {
            $index = count($lines) - 1;
            $candidate = trim($lines[$index].' '.$word);
            if ($lines[$index] !== '' && $this->width($candidate, $size, $font) > $maxWidth) {
                $lines[] = '';
                $index++;
            }
            if ($this->width($word, $size, $font) <= $maxWidth) {
                $lines[$index] = trim($lines[$index].' '.$word);
            } else {
                foreach (mb_str_split($word) as $character) {
                    if ($this->width($lines[$index].$character, $size, $font) > $maxWidth) {
                        $lines[] = '';
                        $index++;
                    }
                    $lines[$index] .= $character;
                }
            }
        }
        foreach ($lines as $line) {
            $x = (int) ((imagesx($image) - $this->width($line, $size, $font)) / 2);
            imagettftext($image, $size, 0, $x, $baseline, $color, $font, trim($line));
            $baseline += (int) ceil($size * 1.5);
        }

        return $baseline;
    }

    private function width(string $text, int $size, string $font): int
    {
        $box = imagettfbbox($size, 0, $font, $text);

        return $box[2] - $box[0];
    }
}
