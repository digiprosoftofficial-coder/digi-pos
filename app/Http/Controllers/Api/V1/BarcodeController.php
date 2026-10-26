<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Picqer\Barcode\BarcodeGeneratorSVG;

class BarcodeController extends Controller
{
    use ApiResponses;

    protected array $types = [
        'code128' => BarcodeGeneratorSVG::TYPE_CODE_128,
        'code39' => BarcodeGeneratorSVG::TYPE_CODE_39,
        'ean13' => BarcodeGeneratorSVG::TYPE_EAN_13,
        'ean8' => BarcodeGeneratorSVG::TYPE_EAN_8,
        'upca' => BarcodeGeneratorSVG::TYPE_UPC_A,
    ];

    /**
     * Render a barcode as SVG for the given code.
     * Fetched by the SPA with its bearer token and injected inline.
     */
    public function svg(Request $request): Response
    {
        $code = trim((string) $request->query('code', ''));
        $typeKey = $request->query('type', 'code128');
        $widthFactor = max(1, min(4, (int) $request->query('w', 2)));
        $height = max(20, min(120, (int) $request->query('h', 50)));

        if ($code === '') {
            return response('Missing code', 422);
        }

        $type = $this->types[$typeKey] ?? BarcodeGeneratorSVG::TYPE_CODE_128;

        try {
            $generator = new BarcodeGeneratorSVG;
            $svg = $generator->getBarcode($code, $type, $widthFactor, $height);
        } catch (\Throwable $e) {
            // Fall back to CODE 128 which accepts any ASCII payload.
            $generator = new BarcodeGeneratorSVG;
            $svg = $generator->getBarcode($code, BarcodeGeneratorSVG::TYPE_CODE_128, $widthFactor, $height);
        }

        return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }
}
