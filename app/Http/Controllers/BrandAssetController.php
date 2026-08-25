<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BrandAssetController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        return response()->file(public_path('brand/madina-import-logo-transparent.png'),[
            'Content-Type'=>'image/png',
            'Cache-Control'=>'public, max-age=3600',
            'X-Content-Type-Options'=>'nosniff',
        ]);
    }
}
