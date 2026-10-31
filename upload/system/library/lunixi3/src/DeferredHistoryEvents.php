<?php

declare (strict_types=1);
namespace Lunixi\OpenCart3;

/** A filtered native dispatcher: transactional business hooks run; audited mail hooks go to the outbox. */
final class DeferredHistoryEvents extends \Event
{
    private object $delegate;
    public function __construct(\Registry $registry, object $delegate)
    {
        parent::__construct($registry);
        $this->delegate = $delegate;
        $property = new \ReflectionProperty($delegate, 'data');
        foreach ($property->getValue($delegate) as $registration) {
            if (!self::deferred($registration['trigger'], $registration['action']->getId())) {
                $this->data[] = $registration;
            }
        }
    }
    public static function deferred(string $trigger, string $route): bool
    {
        return in_array($trigger, ['model/checkout/order/addOrderHistory/before', 'model/checkout/order/addOrderHistory/after'], true) && in_array($route, ['mail/order', 'mail/order/alert', 'extension/total/voucher/send'], true);
    }
    public function register($trigger, \Action $action, $priority = 0)
    {
        $this->delegate->register($trigger, $action, $priority);
        if (!self::deferred($trigger, $action->getId())) {
            parent::register($trigger, $action, $priority);
        }
    }
    public function unregister($trigger, $route)
    {
        $this->delegate->unregister($trigger, $route);
        parent::unregister($trigger, $route);
    }
    public function clear($trigger)
    {
        $this->delegate->clear($trigger);
        parent::clear($trigger);
    }
}
