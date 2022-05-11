<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Classes;


class AttributeSource
{

    /** @var string $name */
    private $name;

    /** @var string $value */
    private $value;

    /** @var int $order */
    private $order = 100;

    /**
     * AttributeSource constructor
     *
     * @param string $name
     * @param string $value
     */
    public function __construct(string $name, string $value)
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
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Set value
     *
     * @param string $value
     */
    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    /**
     * Get order
     *
     * @return int
     */
    public function getOrder(): int
    {
        return $this->order;
    }

    /**
     * Set order
     *
     * @param int $order
     */
    public function setOrder(int $order): void
    {
        $this->order = $order;
    }
}