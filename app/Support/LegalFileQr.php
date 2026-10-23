<?php

namespace App\Support;

use App\Models\LegalFile;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

final class LegalFileQr
{
    public static function dataUri(LegalFile $file): string
    {
        return (new SvgWriter)->write(new QrCode(
            data: $file->qr_identifier,
            size: 240,
            margin: 12,
        ))->getDataUri();
    }
}
