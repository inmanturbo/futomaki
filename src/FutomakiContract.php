<?php

namespace Inmanturbo\Futomaki;

interface FutomakiContract
{
    public static function getRemoteDatabaseName(): string;

    public static function getRemoteDriver(): string;
}
