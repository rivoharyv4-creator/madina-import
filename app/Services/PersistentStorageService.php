<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PersistentStorageService
{
    public function ensureDirectories(): void
    {
        $disk=Storage::disk('persistent');
        foreach(config('madina.persistent_directories',[]) as $directory){
            if(!$disk->exists($directory)) $disk->makeDirectory($directory);
        }
    }

    public function storeProduct(?UploadedFile $file): ?string
    {
        $this->ensureDirectories();
        return $file?->store('products','persistent');
    }

    public function storePaymentProof(?UploadedFile $file): ?string
    {
        $this->ensureDirectories();
        return $file?->store('payments','persistent');
    }

    public function storeExpenseProof(?UploadedFile $file): ?string
    {
        return $this->storeFile($file,'expenses');
    }

    public function storeInvoice(?UploadedFile $file): ?string
    {
        return $this->storeFile($file,'invoices');
    }

    public function storeQuote(?UploadedFile $file): ?string
    {
        return $this->storeFile($file,'quotes');
    }

    public function storeLogisticsDocument(?UploadedFile $file): ?string
    {
        return $this->storeFile($file,'logistics');
    }

    public function putExport(string $filename, string $contents): string
    {
        $this->ensureDirectories();
        $path='exports/'.basename($filename);
        Storage::disk('persistent')->put($path,$contents);
        return $path;
    }

    public function putDocumentPdf(string $directory, string $filename, string $contents): string
    {
        abort_unless(in_array($directory,['quotes','invoices','receipts'],true),404);
        $this->ensureDirectories();
        $path=$directory.'/'.basename($filename);
        Storage::disk('persistent')->put($path,$contents);
        return $path;
    }

    public function download(string $path, string $downloadName): StreamedResponse
    {
        abort_unless(Storage::disk('persistent')->exists($path),404);
        return Storage::disk('persistent')->download($path,$downloadName);
    }

    public function dataUri(?string $path): ?string
    {
        if(!$path||!Storage::disk('persistent')->exists($path)) return null;
        $mime=Storage::disk('persistent')->mimeType($path)?:'image/jpeg';
        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('persistent')->get($path));
    }

    private function storeFile(?UploadedFile $file, string $directory): ?string
    {
        $this->ensureDirectories();
        return $file?->store($directory,'persistent');
    }
}
