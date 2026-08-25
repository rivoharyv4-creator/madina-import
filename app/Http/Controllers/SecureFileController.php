<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecureFileController extends Controller
{
    private const DIRECTORIES=[
        'product'=>'products',
        'payment'=>'payments',
        'expense'=>'expenses',
        'invoice'=>'invoices',
        'quote'=>'quotes',
        'logistics'=>'logistics',
    ];

    public function product(string $filename): StreamedResponse
    {
        return $this->show('product',$filename);
    }

    public function payment(string $filename): StreamedResponse
    {
        return $this->show('payment',$filename);
    }

    public function show(string $category, string $filename): StreamedResponse
    {
        abort_unless(isset(self::DIRECTORIES[$category]),404);
        abort_unless($filename===basename($filename),404);
        $path=self::DIRECTORIES[$category].'/'.$filename;
        $disk=Storage::disk('persistent');
        abort_unless($disk->exists($path),404);
        $mime=$disk->mimeType($path)?:'application/octet-stream';

        return $disk->response($path,$filename,[
            'Content-Type'=>$mime,
            'Cache-Control'=>'private, no-store, max-age=0',
            'X-Content-Type-Options'=>'nosniff',
            'Content-Disposition'=>'inline; filename="'.str_replace('"','',$filename).'"',
        ]);
    }
}
