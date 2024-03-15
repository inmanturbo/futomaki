<?php

namespace Inmanturbo\Futomaki;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Inmanturbo\Futomaki\Commands\FutomakiCommand;

class FutomakiServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('futomaki')
            ->hasCommand(FutomakiCommand::class);
    }
}
