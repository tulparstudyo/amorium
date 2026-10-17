<?php namespace AmoriumCms\Shop\Classes;

use AmoriumCms\Shop\Models\Address;

/**
 * AddressPresenter
 */
class AddressPresenter
{
    public static function present(Address $address): array
    {
        return [
            'id' => $address->id,
            'type' => $address->type,
            'title' => $address->title,
            'is_default' => $address->is_default,
        ] + array_only($address->attributesToArray(), Address::SNAPSHOT_FIELDS);
    }
}
