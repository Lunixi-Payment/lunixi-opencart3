<?php

declare (strict_types=1);
/**
 * Native services exposed by the platform registry.
 * @property \Loader $load
 * @property \Config $config
 * @property \Url $url
 * @property \Cart\User $user
 * @property \Request $request
 * @property \Response $response
 * @property \Session $session
 * @property \Language $language
 * @property \Document $document
 * @property \Cart\Cart $cart
 */
class ModelExtensionPaymentLunixi extends Model
{
    public function getMethod($address, $total)
    {
        $this->load->language('extension/payment/lunixi');
        if (!$this->config->get('payment_lunixi_status') || !(int) $this->config->get('payment_lunixi_profile_id') || $this->cart->hasRecurringProducts()) {
            return [];
        }
        try {
            require_once DIR_SYSTEM . 'library/lunixi3/autoload.php';
            $kernel = new \Lunixi\OpenCart3\Kernel($this->registry);
            $kernel->compatibility()->assertReady();
            $kernel->assertConfigured();
            \Lunixi\OpenCart3\Kernel::secretBox();
            return ['code' => 'lunixi', 'title' => $this->language->get('text_title'), 'terms' => '', 'sort_order' => (int) $this->config->get('payment_lunixi_sort_order')];
        } catch (\Throwable $error) {
            return [];
        }
    }
}
