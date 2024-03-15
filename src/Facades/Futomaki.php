<?php

namespace Inmanturbo\Futomaki\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Inmanturbo\Futomaki\Futomaki
 */
class Futomaki extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Inmanturbo\Futomaki\Futomaki::class;
    }
}
