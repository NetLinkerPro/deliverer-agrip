<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Classes;


use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;

class CategorySource
{
    /** @var PropertySource[] $properties */
    private $properties;

    /** @var string $id */
    private $id;

    /** @var string $name */
    private $name;

    /** @var string $url */
    private $url;

    /** @var CategorySource[] $children */
    private $children;

    /**
     * CategorySource constructor.
     * @param string $id
     * @param string $name
     * @param string $url
     */
    public function __construct(string $id, string $name, string $url)
    {
        $this->properties = [];
        $this->id = $id;
        $this->name = $name;
        $this->url = $url;
        $this->children = [];
        $this->check();
    }

    /**
     * Get properties
     *
     * @return array|PropertySource[]
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * Set properties
     *
     * @param array|PropertySource[] $properties
     */
    public function setProperties(array $properties): void
    {
        $this->properties = $properties;
    }

    /**
     * Set property
     *
     * @param string $name
     * @param mixed $value
     */
    public function setProperty(string $name, $value): void
    {
        $property = new PropertySource($name, $value);
        array_push($this->properties, $property);
    }

    /**
     * Get property
     *
     * @param string $name
     * @return mixed|null
     */
    public function getProperty(string $name)
    {
        foreach ($this->properties as $property) {
            if ($name === $property->getName()) {
                return $property->getValue();
            }
        }
        return null;
    }

    /**
     * Has property
     *
     * @param string $name
     * @return mixed|null
     */
    public function hasProperty(string $name)
    {
        foreach ($this->properties as $property) {
            if ($name === $property->getName()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get ID
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Set ID
     *
     * @param string $id
     */
    public function setId(string $id): void
    {
        $this->id = $id;
        $this->check();
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set name
     *
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Get URL
     *
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Set URL
     *
     * @param string $url
     */
    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    /**
     * Get children
     *
     * @return CategorySource[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * Set children
     *
     * @param CategorySource[] $children
     */
    public function setChildren(array $children): void
    {
        $this->children = $children;
    }

    /**
     * Add child
     *
     * @param CategorySource $category
     */
    public function addChild(CategorySource $category)
    {
        array_push($this->children, $category);
    }

    /**
     * Check
     *
     * @throws DelivererAgripException
     */
    private function check(): void
    {
        if (!$this->id){
            throw new DelivererAgripException('ID category is empty.');
        }
        if (mb_strlen($this->id) > 64){
            throw new DelivererAgripException('Name category product it\'s too long.');
        }
    }

    /**
     * Clone
     *
     * @return CategorySource
     */
    public function clone(): CategorySource
    {
        return clone $this;
    }
}