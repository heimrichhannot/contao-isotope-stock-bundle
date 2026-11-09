<?php

namespace HeimrichHannot\IsotopeStockBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Input;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Keeps the back end product form from overwriting stock changes made while the form was open (orders, other editors).
 *
 * The stock value shown in the form is remembered on load; on save only the difference the editor typed is applied
 * to the current database value (tl_iso_product.stock, int unsigned NULL; NULL = unmanaged) instead of writing the
 * (possibly stale) absolute value back. A change that would make the stock negative is rejected.
 */
readonly class ProductStockFieldListener
{
    public const SESSION_PREFIX = 'huh_iso_stock_loaded.';

    /**
     * Back end actions that render the stock field before saving it; overrideAll and others save without a render.
     */
    public const FORM_ACTIONS = ['edit', 'editAll'];

    public function __construct(
        private Connection $connection,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * Contao also runs load callbacks in the request that submits the form; the value is only remembered when the
     * form is displayed, otherwise it would already contain the concurrent change.
     */
    #[AsCallback(table: 'tl_iso_product', target: 'fields.stock.load')]
    public function onLoad(mixed $value, DataContainer $dc): mixed
    {
        if (Input::post('FORM_SUBMIT') !== $dc->table && $this->isFormAction() && null !== ($session = $this->getSession())) {
            $session->set(self::SESSION_PREFIX . $dc->id, (string) $value);
        }

        return $value;
    }

    /**
     * @throws \RuntimeException if the change would make the stock negative (shown at the field, nothing is written)
     */
    #[AsCallback(table: 'tl_iso_product', target: 'fields.stock.save')]
    public function onSave(mixed $value, DataContainer $dc): mixed
    {
        $session = $this->getSession();
        $key = self::SESSION_PREFIX . $dc->id;

        if (null === $session || !$session->has($key)) {
            return $value;
        }

        $loaded = (string) $session->get($key);
        $session->remove($key);

        // e.g. overrideAll sets an absolute value; an entry left over from an abandoned edit form must not turn it into a delta
        if (!$this->isFormAction()) {
            return $value;
        }

        $value = (string) $value;

        if ('' === $value || '' === $loaded) {
            return $value;
        }

        $id = (int) $dc->id;
        $delta = (int) $value - (int) $loaded;

        if ($delta > 0) {
            $affected = $this->connection->executeStatement(
                'UPDATE tl_iso_product SET stock = stock + :delta WHERE id = :id AND stock IS NOT NULL',
                [
                    'delta' => $delta,
                    'id' => $id,
                ],
                [
                    'delta' => ParameterType::INTEGER,
                    'id' => ParameterType::INTEGER,
                ],
            );
        } elseif ($delta < 0) {
            // the column is unsigned: subtract the absolute amount and only if the stock suffices
            $affected = $this->connection->executeStatement(
                'UPDATE tl_iso_product SET stock = stock - :amount WHERE id = :id AND stock IS NOT NULL AND stock >= :amount',
                [
                    'amount' => -$delta,
                    'id' => $id,
                ],
                [
                    'amount' => ParameterType::INTEGER,
                    'id' => ParameterType::INTEGER,
                ],
            );
        } else {
            $affected = 1;
        }

        $current = $this->fetchStock($id);

        if (0 === $affected) {
            // the product is no longer stock managed (or gone): there is no concurrent stock to preserve, take the typed value
            if (null === $current) {
                return $value;
            }

            // keep the displayed value, so a corrected input is still applied as delta
            $session->set($key, $loaded);

            throw new \RuntimeException(\sprintf('Der Bestand wurde zwischenzeitlich (z. B. durch Bestellungen) auf %d geändert. Ihre Eingabe wird als Änderung gegenüber dem beim Öffnen angezeigten Bestand %d verrechnet und würde den Bestand negativ machen. Bitte geben Sie mindestens %d ein.', $current, (int) $loaded, (int) $loaded - $current));
        }

        return null === $current ? $value : (string) $current;
    }

    /**
     * @return int|null the current stock, null if it is unmanaged or the product doesn't exist
     */
    private function fetchStock(int $id): ?int
    {
        $stock = $this->connection->fetchOne('SELECT stock FROM tl_iso_product WHERE id = :id', [
            'id' => $id,
        ], [
            'id' => ParameterType::INTEGER,
        ]);

        return false === $stock || null === $stock ? null : (int) $stock;
    }

    private function isFormAction(): bool
    {
        return \in_array(Input::get('act'), self::FORM_ACTIONS, true);
    }

    private function getSession(): ?SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request || !$request->hasSession()) {
            return null;
        }

        return $request->getSession();
    }
}
