<?php

namespace Pdir\ContaoLlmsBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class PdirContaoLlmsBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
