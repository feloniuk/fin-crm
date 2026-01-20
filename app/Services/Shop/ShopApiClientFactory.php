<?php

namespace App\Services\Shop;

use App\Contracts\ShopApiClientInterface;
use App\DTOs\ApiCredentialsDTO;
use App\Enums\ShopType;
use App\Models\Shop;

class ShopApiClientFactory
{
    public static function make(Shop $shop): ShopApiClientInterface
    {
        $credentials = ApiCredentialsDTO::fromArray($shop->api_credentials);

        return match ($shop->type) {
            ShopType::HOROSHOP => new HoroshopApiClient($credentials),
            ShopType::PROM_UA => new PromUaApiClient($credentials),
        };
    }

    public static function makeFromType(ShopType $type, array $credentials): ShopApiClientInterface
    {
        $credentialsDTO = ApiCredentialsDTO::fromArray($credentials);

        return match ($type) {
            ShopType::HOROSHOP => new HoroshopApiClient($credentialsDTO),
            ShopType::PROM_UA => new PromUaApiClient($credentialsDTO),
        };
    }
}
