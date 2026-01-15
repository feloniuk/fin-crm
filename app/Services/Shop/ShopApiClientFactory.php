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
            ShopType::Horoshop => new HoroshopApiClient($credentials),
            ShopType::PromUa => new PromUaApiClient($credentials),
        };
    }

    public static function makeFromType(ShopType $type, array $credentials): ShopApiClientInterface
    {
        $credentialsDTO = ApiCredentialsDTO::fromArray($credentials);

        return match ($type) {
            ShopType::Horoshop => new HoroshopApiClient($credentialsDTO),
            ShopType::PromUa => new PromUaApiClient($credentialsDTO),
        };
    }
}
