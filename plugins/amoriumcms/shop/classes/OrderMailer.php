<?php namespace AmoriumCms\Shop\Classes;

use App;
use Log;
use Mail;
use Exception;
use AmoriumCms\Shop\Models\Order;

/**
 * OrderMailer sends the order e-mails in the language the order was placed in.
 * A failing mail is logged and never stops the order.
 */
class OrderMailer
{
    /**
     * placed
     */
    public static function placed(Order $order)
    {
        static::send($order, 'amoriumcms.shop::mail.order_placed');
    }

    /**
     * statusChanged
     */
    public static function statusChanged(Order $order, ?string $note = null)
    {
        static::send($order, 'amoriumcms.shop::mail.order_status', ['note' => $note]);
    }

    /**
     * send
     */
    protected static function send(Order $order, string $template, array $extra = [])
    {
        if (!$order->customer_email) {
            return;
        }

        $locale = App::getLocale();

        try {
            if ($order->locale) {
                App::setLocale($order->locale);
            }

            $data = OrderService::present($order);
            $data['status_label'] = __(ucfirst(str_replace('_', ' ', $order->status)));
            $data += $extra;

            Mail::send($template, ['order' => $data], function ($message) use ($order) {
                $message->to($order->customer_email);
            });
        }
        catch (Exception $ex) {
            Log::error('Shop: order e-mail could not be sent', ['order' => $order->number, 'error' => $ex->getMessage()]);
        }
        finally {
            App::setLocale($locale);
        }
    }
}
