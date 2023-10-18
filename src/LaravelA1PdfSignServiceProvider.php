<?php

namespace NatanParmigiano\LaravelA1PdfSign;

use Illuminate\Support\ServiceProvider;
use NatanParmigiano\LaravelA1PdfSign\Commands\{SignPdfCommand, ValidatePdfSignatureCommand};

class LaravelA1PdfSignServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SignPdfCommand::class,
                ValidatePdfSignatureCommand::class
            ]);
        }
    }
}
