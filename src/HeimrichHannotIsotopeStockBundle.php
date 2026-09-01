<?php

namespace HeimrichHannot\IsotopeStockBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class HeimrichHannotIsotopeStockBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
