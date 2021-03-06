<?php

namespace OhMyBrew\BasicShopifyResource\Models;

use OhMyBrew\BasicShopifyResource\Relationships\HasOne;
use OhMyBrew\BasicShopifyResource\Resource;
use OhMyBrew\BasicShopifyResource\Models\Theme;

/**
 * Asset API.
 */
class Asset extends Resource
{
    /**
     * The resource path part.
     *
     * @var string
     */
    protected $resourcePath = 'assets';

    /**
     * The resource name.
     *
     * @var string
     */
    protected $resourceName = 'asset';

    /**
     * The resource name (plural).
     *
     * @var string
     */
    protected $resourceNamePlural = 'assets';

    /**
     * The constructor.
     *
     * @return $this
     */
    public function __construct()
    {
        $this->relationships = [
            'theme' => new HasOne(Theme::class),
        ];
    }
}
