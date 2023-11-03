<?php

namespace HeimrichHannot\IsotopeStockBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class HeimrichHannotIsotopeStockBundle extends Bundle
{
    public function getPath()
    {
        return \dirname(__DIR__);
    }

}