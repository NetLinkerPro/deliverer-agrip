<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Classes;

class PropertySource
{
    /** @var string $name */
    private $name;

    /** @var $value */
    private $value;

    /**
     * PropertySource constructor
     *
     * @param string $name
     * @param $value
     */
    public function __construct(string $name, $value)
    {
        $this->name = $name;
        $this->value = $value;
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
     * Get value
     *
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Set value
     *
     * @param mixed $value
     */
    public function setValue($value): void
    {
        $this->value = $value;
    }

}