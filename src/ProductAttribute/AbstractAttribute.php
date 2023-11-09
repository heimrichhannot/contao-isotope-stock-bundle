<?php

namespace HeimrichHannot\IsotopeStockBundle\ProductAttribute;

use Contao\Message;
use HeimrichHannot\UtilsBundle\Util\Utils;
use Isotope\Interfaces\IsotopeProduct;
use Symfony\Contracts\Service\Attribute\SubscribedService;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

abstract class AbstractAttribute implements ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    abstract public static function getName(): string;

    public function isActive(IsotopeProduct $product): bool
    {
        return in_array(static::getName(), $product->getType()->getAttributes());
    }

    protected function addErrorMessage(string $message): void
    {
        if ($this->utils()->container()->isFrontend()) {
            $_SESSION['ISO_ERROR'][] = $message;
        } else {
            Message::addError($message);
        }
    }

    #[SubscribedService]
    private function utils(): Utils
    {
        return $this->container->get(__METHOD__);
    }


}