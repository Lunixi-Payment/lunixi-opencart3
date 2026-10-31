<?php

declare (strict_types=1);
namespace Lunixi\OpenCart3;

/** Operator-visible preflight; never disables third-party business/fraud hooks silently. */
final class Compatibility
{
    private \Registry $registry;
    private NativeLedgerStore $store;
    public function __construct(\Registry $registry, NativeLedgerStore $store)
    {
        $this->registry = $registry;
        $this->store = $store;
    }
    public function report(): array
    {
        $issues = [];
        if (!defined('VERSION') || VERSION !== '3.0.5.1') {
            $issues[] = 'platform_version';
        }
        $catalog = defined('DIR_CATALOG') ? DIR_CATALOG : DIR_APPLICATION;
        if (function_exists('modification') && modification($catalog . 'model/checkout/order.php') !== $catalog . 'model/checkout/order.php') {
            $issues[] = 'modified_native_order';
        }
        if (!is_file($catalog . 'model/checkout/order.php') || hash_file('sha256', $catalog . 'model/checkout/order.php') !== '1b3f1e136cc7a74db21c18c81bf3d7995151e7d3eaa2b1d073b1f9b5ded03faf') {
            $issues[] = 'modified_native_order';
        }
        if (PHP_VERSION_ID < 80300 || PHP_INT_SIZE < 8) {
            $issues[] = 'php_runtime';
        }
        foreach (['bcmath', 'curl', 'sodium', 'json', 'mysqli'] as $extension) {
            if (!extension_loaded($extension)) {
                $issues[] = 'extension:' . $extension;
            }
        }
        foreach ($this->store->nonTransactionalTables() as $table) {
            $issues[] = 'nontransactional:' . $table;
        }
        $config = $this->registry->get('config');
        $totals = $this->store->query('SELECT code FROM `' . $this->store->prefix() . "extension` WHERE type='total'")->rows;
        $audited = ['sub_total', 'shipping', 'tax', 'total', 'coupon', 'reward', 'voucher', 'credit', 'handling', 'low_order_fee'];
        foreach ($totals as $row) {
            if ($config->get('total_' . $row['code'] . '_status') && !in_array($row['code'], $audited, true)) {
                $issues[] = 'total_hook:' . $row['code'];
            }
        }
        // Native fraud executes with override=false. Only inspected native implementations are accepted.
        $fraud = $this->store->query('SELECT code FROM `' . $this->store->prefix() . "extension` WHERE type='fraud'")->rows;
        foreach ($fraud as $row) {
            if ($config->get('fraud_' . $row['code'] . '_status') && !in_array($row['code'], ['ip', 'maxmind', 'fraudlabspro'], true)) {
                $issues[] = 'fraud_hook:' . $row['code'];
            }
        }
        $event = $this->registry->get('event');
        if ($event instanceof DeferredHistoryEvents) {
            $issues[] = 'nested_history_dispatch';
        } else {
            $property = new \ReflectionProperty($event, 'data');
            $registrations = $property->getValue($event);
            foreach ($this->store->query('SELECT `trigger`,`action` FROM `' . $this->store->prefix() . "event` WHERE status=1 AND LEFT(`trigger`,8)='catalog/'")->rows as $row) {
                $registrations[] = ['trigger' => substr($row['trigger'], 8), 'route_id' => $row['action']];
            }
            foreach ($registrations as $registration) {
                $pattern = '/^' . str_replace(['\*', '\?'], ['.*', '.'], preg_quote($registration['trigger'], '/')) . '/';
                // Conservative representative native order calls, including wildcard listeners.
                $targets = ['getOrder', 'getOrderProducts', 'getOrderOptions', 'getOrderTotals', 'addOrderHistory', 'createInvoiceNo'];
                if (is_file($catalog . 'model/checkout/order.php')) {
                    preg_match_all('/public\s+function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', file_get_contents($catalog . 'model/checkout/order.php'), $nativeMethods);
                    $targets = array_unique(array_merge($targets, $nativeMethods[1]));
                }
                $matches = false;
                foreach ($targets as $method) {
                    foreach (['before', 'after'] as $phase) {
                        if (preg_match($pattern, 'model/checkout/order/' . $method . '/' . $phase)) {
                            $matches = true;
                        }
                    }
                }
                if (!$matches) {
                    continue;
                }
                $action = $registration['route_id'] ?? (isset($registration['action']) && method_exists($registration['action'], 'getId') ? $registration['action']->getId() : '');
                $auditedMail = DeferredHistoryEvents::deferred($registration['trigger'], $action);
                $transactional = in_array($registration['trigger'], ['model/checkout/order/addOrderHistory/before', 'model/checkout/order/addOrderHistory/after'], true) && in_array($action, ['event/activity/addOrderHistory', 'event/statistics/addOrderHistory'], true);
                if (!$auditedMail && !$transactional) {
                    $issues[] = 'order_hook:' . $registration['trigger'] . ':' . $action;
                }
            }
        }
        return array_values(array_unique($issues));
    }
    public function assertReady(): void
    {
        if ($this->report() !== []) {
            throw new \RuntimeException('Lunixi compatibility preflight requires operator review.');
        }
    }
}
