<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Classes;


class ImageSource
{
    /** @var PropertySource[] $properties */
    private $properties;

    /** @var bool $main */
    private $main;

    /** @var string $id */
    private $id;

    /** @var string $url */
    private $url;

    /** @var string $filenameUnique */
    private $filenameUnique;

    /** @var string $extension */
    private $extension;

    /** @var string $backgroundFill */
    private $backgroundFill;

    /**
     * ImageSource constructor
     *
     * @param bool $main
     * @param string $id
     * @param string $url
     * @param string $filenameUnique
     * @param string $extension
     * @param string|null $backgroundFill
     */
    public function __construct(bool $main, string $id, string $url, string $filenameUnique, string $extension = 'jpg', ?string $backgroundFill = null)
    {
        $this->properties = [];
        $this->main = $main;
        $this->id = $id;
        $this->url = $url;
        $this->filenameUnique = $filenameUnique;
        $this->extension = $extension;
        $this->backgroundFill = $backgroundFill;
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
        foreach ($this->properties as $property) {
            if ($name === $property->getName()) {
                $property->setValue($value);
                return;
            }
        }
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
     * Is main
     *
     * @return bool
     */
    public function isMain(): bool
    {
        return $this->main;
    }

    /**
     * Set main
     *
     * @param bool $main
     */
    public function setMain(bool $main): void
    {
        $this->main = $main;
    }

    /**
     * Get id
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Set id
     *
     * @param string $id
     */
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /**
     * Get url
     *
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Set url
     *
     * @param string $url
     */
    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    /**
     * Get filename unique
     *
     * @return string
     */
    public function getFilenameUnique(): string
    {
        return $this->filenameUnique;
    }

    /**
     * Set filename unique
     *
     * @param string $filenameUnique
     */
    public function setFilenameUnique(string $filenameUnique): void
    {
        $this->filenameUnique = $filenameUnique;
    }

    /**
     * Get extension
     *
     * @return string
     */
    public function getExtension(): string
    {
        return $this->extension;
    }

    /**
     * Set extension
     *
     * @param string $extension
     */
    public function setExtension(string $extension): void
    {
        $this->extension = $extension;
    }

    /**
     * Get background fill
     *
     * @return string
     */
    public function getBackgroundFill(): ?string
    {
        return $this->backgroundFill;
    }

    /**
     * Set background fill
     *
     * @param string|null $backgroundFill
     */
    public function setBackgroundFill(?string $backgroundFill): void
    {
        $this->backgroundFill = $backgroundFill;
    }

    /**
     * Clone
     *
     * @return ImageSource
     */
    public function clone(): ImageSource
    {
        $image = clone $this;
        $properties = $this->getProperties();
        $image->setProperties([]);
        foreach ($properties as $property){
            $image->setProperty($property->getName(), $property->getValue());
        }
        return $image;
    }


}