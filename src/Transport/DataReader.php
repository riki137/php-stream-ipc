<?php

declare(strict_types=1);

namespace PhpStreamIpc\Transport;

interface DataReader
{
    public function read(): string;
}
