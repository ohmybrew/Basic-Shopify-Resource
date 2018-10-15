<?php

namespace OhMyBrew\BasicShopifyResource;

use Tightenco\Collect\Support\Collection;

/**
 * Resource class which all models are based on.
 */
abstract class Resource
{
    const INCLUDES_ONE = 0;
    const INCLUDES_MANY = 1;
    const HAS_ONE = 3;
    const HAS_MANY = 4;

    /**
     * The properties of the resource, such as ID, title, etc.
     *
     * @var array
     */
    protected $properties = [];

    /**
     * The modified properties of the resource.
     *
     * @var array
     */
    protected $mutatedProperties = [];

    /**
     * The resource's path part.
     *
     * @var string
     */
    protected $resourcePath = null;

    /**
     * The resource's name, such as "product"
     *
     * @var string
     */
    protected $resourceName = null;

    /**
     * The resource's plural name, such as "products"
     *
     * @var string
     */
    protected $resourceNamePlural = null;

    /**
     * The resource's primary key.
     *
     * @var string
     */
    protected $resourcePk = 'id';

    /**
     * The resource's relationships
     *
     * @var array
     */
    protected $relationships = [];

    /**
     * Gets the API instance.
     *
     * @return \OhMyBrew\BasicShopifyAPI
     */
    protected static function getConnection()
    {
        return Connection::get();
    }

    /**
     * Creates an instance of the calling static class.
     *
     * @return object
     */
    protected static function getInstance()
    {
        // Get the calling class
        $class = get_called_class();

        return new $class();
    }

    /**
     * Request handler which forms the request makes
     * a call through the API and parses the result into
     * either a model, or a collection of models.
     *
     * @param bool        $type       If this is a private or public app call.
     * @param int|null    $resourceId The ID of the resource to target.
     * @param array       $params     Additional parameters to pass with the request.
     * @param object|null $through    To form this request through another resource.
     *
     * @return Collection|object|null
     */
    protected static function request(string $type, $resourceId = null, array $params = [], $through = null)
    {
        // Create and get the current instance of this model
        $resource = self::getInstance();
        $resourceName = $resource->resourceName;
        $resourceNamePlural = $resource->resourceNamePlural;
        $resourcePath = $resource->resourcePath;

        // Form the request URL
        $path = ['/admin'];
        if ($through) {
            // If we're going through, form this part first
            if ($through instanceof Resource) {
                // Build from existing resource
                $path[] = $through->resourcePath;
                $path[] = $through->{$through->resourcePk};
            } else {
                // Build from string, like "products/1234"
                $path[] = $through;
            }
        }
        $path[] = $resourcePath;

        if ($resourceId) {
            // Add the targeted resource ID
            $path[] = $resourceId;
        }

        // Create the request and get the response
        $path = implode('/', $path).'.json';
        $response = self::getConnection()
            ->rest($type, $path, $params)
            ->body
            ->{$resourceId ? $resourceName : $resourceNamePlural}
        ;

        if ($type !== 'DELETE') {
            if ($resourceId) {
                // If singular, build a single model
                return self::buildResource($resource, $response);
            }

            // Multiple, build many models
            return self::buildResourceCollection($resource, $response);
        }

        return null;
    }

    /**
     * Creates a model based on the response.
     *
     * @param object $resource The model.
     * @param object $data     The data for the model.
     *
     * @return object
     */
    protected static function buildResource($resource, $data)
    {
        if (!$resource instanceof Resource) {
            // Not yet initialized
            $resource = new $resource();
        }

        // Loop the public properties of the response, add them to the properties of the model
        foreach (get_object_vars($data) as $property => $value) {
            $resource->properties[$property] = $value;
        }

        return $resource;
    }

    /**
     * Creates a collection of models based on the response.
     *
     * @param object $resource The model.
     * @param object $data     The data for the model.
     *
     * @return Collection
     */
    protected static function buildResourceCollection($resource, $data)
    {
        $collection = new Collection();
        foreach ($data as $object) {
            $collection[] = self::buildResource($resource, $object);
        }

        return $collection;
    }

    /**
     * Finds all records of a resource.
     *
     * @param array $params Additional param to pass with the request.
     *
     * @return Collection
     */
    public static function all(array $params = [])
    {
        // Simple GET call
        return self::request('GET');
    }

    /**
     * Finds all records of a resource through another resource.
     *
     * @param array $params Additional param to pass with the request.
     *
     * @return Collection
     */
    public static function allThrough($resource, array $params = [])
    {
        // GET call with no resource ID, through a another resource
        return self::request('GET', null, $params, $resource);
    }

    /**
     * Finds a single record of a resource.
     *
     * @param int   $resourceId The ID of the resource.
     * @param array $params     Additional param to pass with the request.
     *
     * @return object
     */
    public static function find($resourceId, array $params = [])
    {
        // GET call with a resource ID
        return self::request('GET', $resourceId, $params);
    }

    /**
     * Finds a single record of a resource through another resource.
     *
     * @param int           $resourceId      The ID of the resource.
     * @param object|string $throughResource The resource to loop through
     * @param array         $params          Additional param to pass with the request.
     *
     * @return object
     */
    public static function findThrough($resourceId, $throughResource, array $params = [])
    {
        // GET call with a resource ID through a resource
        return self::request('GET', $resourceId, $params, $throughResource);
    }

    /**
     * Relationship of includesOne.
     * This resource includes a single nested resource.
     *
     * @param string       $resource The class name of the resource.
     * @param array        $params   Additional param to pass with the request.
     * @param string|null  $key      The key to link.
     *
     * @return object
     */
    public function includesOne($resource, array $params = [], $key = null)
    {
        // Create an instance of the resource
        $instance = new $resource();
        if (!$key) {
            // If no key is entered, build an assumed one
            $key = "{$instance->resourceName}_id";
        }

        return $instance::find($this->{$key}, $params);
    }

    /**
     * Relationship of includesMany.
     * This resource includes many nested resources.
     *
     * @param string $resource The class name of the resource.
     * @param array  $params   Additional param to pass with the request.
     *
     * @return Collection
     */
    public function includesMany($resource, array $params = [])
    {
        $instance = new $resource();

        return $instance::allThrough($this, $params);
    }

    /**
     * Relationship of hasMany.
     * This resource has many resources through another resource.
     *
     * @param string $resource The class name of the resource.
     * @param array  $params   Additional param to pass with the request.
     *
     * @return Collection
     */
    public function hasMany($resource, array $params)
    {
        // Create an instance of the resource
        $instance = new $resource();

        return $instance::all($params);
    }

    /**
     * Relationship of hasOne.
     * This resource has a single resource through another resource.
     *
     * @param string       $resource The class name of the resource.
     * @param array        $params   Additional param to pass with the request.
     *
     * @return object
     */
    public function hasOne($resource, array $params)
    {
        // Create an instance of the resource
        $instance = new $resource();

        return $instance::all($params)->first();
    }

    /**
     * Saves (or creates) a record.
     *
     * @return void
     */
    public function save()
    {
        $type = $this->isNew() ? 'POST' : 'PUT';
        $id = $this->isNew() ? null : $this->{$this->resourcePk};
        $params = [$this->resourceName => $this->mutatedProperties];

        // Create the request to create or save the record, params will turn into
        // something like: ['product' => ['title' => 'New Title', ...]]
        $record = self::request($type, $id, $params);

        // Refresh the record's properties and clear out old mutations
        $this->refreshProperties($record->properties);
        $this->resetProperties();
    }

    /**
     * Destroys a record.
     *
     * @return void
     */
    public function destroy()
    {
        self::request('DELETE', $this->{$this->resourcePk});
    }

    /**
     * Determines if this is a new record or not.
     *
     * @return boolean
     */
    public function isNew()
    {
        return !isset($this->properties[$this->resourcePk]);
    }

    /**
     * Determines if this is an existing record or not.
     *
     * @return boolean
     */
    public function isExisting()
    {
        return !$this->isNew();
    }

    /**
     * Magic getter to ensure we can only grab the record's properties.
     *
     * @param string $property The property name.
     *
     * @return mixed
     */
    public function __get($property)
    {
        if ($this->getRelationalProperty($property)) {
            // Is relational property, get the relationship
            return $this->getRelationship($property);
        } elseif (array_key_exists($property, $this->mutatedProperties)) {
            // Its mutated, get the mutated property version
            return $this->mutatedProperties[$property];
        } elseif (array_key_exists($property, $this->properties)) {
            // Its not mutated, get the property
            return $this->properties[$property];
        }

        // Its not mutated or a property, kill
        return null;
    }

    /**
     * Magic setter to ensure we only set properties of the record.
     *
     * @param string $property The property name (such as "title").
     * @param mixed  $value    The value to set for the property
     *
     * @return void
     */
    public function __set($property, $value)
    {
        $this->mutatedProperties[$property] = $value;
    }

    /**
     * Checks if a property of a record is defined as a relationship.
     * Returns relationship if exists.
     *
     * @param string $property The property to search.
     *
     * @return boolean|array
     */
    protected function getRelationalProperty($property)
    {
        $relationships = $this->relationships;
        if (!isset($relationships[$property])) {
            // Doesn't exist
            return false;
        }

        // Exists
        return $relationships[$property];
    }

    /**
     * Parses the property and returns the relational results.
     *
     * @param string $property The property to use.
     *
     * @return null|Collection|object
     */
    protected function getRelationship($property)
    {
        // Determine if its a relational property
        $relationship = $this->getRelationalProperty($property);
        if (!$relationship) {
            throw new Exception('Property is not defined as relational');
        }

        // Its a relationship property, see if we've already binded
        if (isset($this->properties[$property]) && ($this->properties[$property] instanceof Resource || $this->properties[$property] instanceof Collection)) {
            // Already binded, simply return the result
            return $this->properties[$property];
        }

        // Get the relationship; 0 = type, 1 = class, 2 = params, 3 = linking key
        $type = $relationship[0];
        $class = $relationship[1];
        $params = isset($relationship[2]) ? $relationship[2]() : [];
        $linking = $relationship[3] ?? null;

        switch ($type) {
            // Includes many nested
            case self::INCLUDES_MANY: {
                if (isset($this->properties[$property])) {
                    // Data is present from initial resource call, simply bind it to the model
                    $this->properties[$property] = self::buildResourceCollection($class, $this->properties[$property]);
                } else {
                    // No data is present, make an API call
                    $this->properties[$property] = $this->includesMany($class, $params);
                }
                break;
            }

            // Includes a single nested resource
            case self::INCLUDES_ONE: {
                if (isset($this->properties[$property])) {
                    // Data is present from initial resource call, simply bind it to the model
                    $this->properties[$property] = self::buildResource($class, $this->properties[$property]);
                } else {
                    // No data is present, make an API call
                    $this->properties[$property] = $this->includesOne($class, $params, $linking);
                }
                break;
            }

            // Has many resources through
            case self::HAS_MANY: {
                $this->properties[$property] = $this->hasMany($class, $params);
                break;
            }

            // Has a single resource through
            case self::HAS_ONE: {
                $this->properties[$property] = $this->hasOne($class, $params);
                break;
            }
        }

        return $this->properties[$property];
    }

    /**
     * Refreshes the properties with a new set (usually after a save).
     *
     * @param array $properties The properties to set
     *
     * @return void
     */
    protected function refreshProperties(array $properties)
    {
        $this->properties = $properties;
    }

    /**
     * Resets the mutated properties (usually after a save).
     *
     * @return void
     */
    public function resetProperties()
    {
        $this->mutatedProperties = [];
    }

    /**
     * Forcefully get the original property even if its mutated.
     *
     * @param string $property The property to get.
     *
     * @return mixed
     */
    public function originalProperty($property)
    {
        return $this->properties[$property];
    }
}
