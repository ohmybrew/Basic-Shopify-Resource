<?php

namespace OhMyBrew\BasicShopifyResource;

use Exception;
use OhMyBrew\BasicShopifyAPI;

/**
 * Handles the static API connection.
 */
class Connection
{
    /**
     * The properties of the resource, such as ID, title, etc.
     *
     * @var null|BasicShopifyAPI
     */
    protected static $connection = null;

    /**
     * Creates the connection.
     *
     * @param bool        $private Public or private API calls.
     * @param string      $shop    The shop to target.
     * @param array       $apiData API details required.
     * @param object|null $client  The Guzzle client to use.
     *
     * @return BasicShopifyAPI
     */
    public static function set(bool $private, string $shop, array $apiData, $client = null)
    {
        $api = new BasicShopifyAPI($private);
        $api->setShop($shop);

        if ($client) {
            $api->setClient($client);
        }

        if ($private) {
            if (!isset($apiData['key']) || !isset($apiData['password'])) {
                throw new Exception('API key and password required for private API calls');
            }

            $api->setApiKey($apiData['key']);
            $api->setApiPassword($apiData['password']);
        } else {
            if (!isset($apiData['key']) || !isset($apiData['secret']) || !isset($apiData['token'])) {
                throw new Exception('API key, secret, and token required for public API calls');
            }

            $api->setApiKey($apiData['key']);
            $api->setApiSecret($apiData['secret']);
            $api->setAccessToken($apiData['token']);
        }

        self::$connection = $api;
    }

    /**
     * Gets the connection.
     *
     * @return BasicShopifyAPI
     */
    public static function get()
    {
        $connection = self::$connection;
        if (null === $connection) {
            throw new Exception('No connection was setup');
        }

        return $connection;
    }

    /**
     * Clears the connection saved.
     *
     * @return void
     */
    public static function clear()
    {
        self::$connection = null;
    }
}
