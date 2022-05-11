<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Classes;


use Illuminate\Support\Str;
use NetLinker\DelivererAgrip\Exceptions\DelivererAgripException;
use NetLinker\DelivererAgrip\Sections\Logger\Services\DelivererLogger;

class ProductSource
{
    /** @var PropertySource[] $properties */
    private $properties;

    /** @var string $id */
    private $id;

    /** @var string $url */
    private $url;

    /** @var float $price */
    private $price;

    /** @var int $tax */
    private $tax;

    /** @var AttributeSource[] $attributes */
    private $attributes;

    /** @var int $stock */
    private $stock;

    /** @var int $availability */
    private $availability;

    /** @var CategorySource[] $categories */
    private $categories;

    /** @var string $name */
    private $name;

    /** @var ImageSource[] $images */
    private $images;

    /** @var string $description */
    private $description;

    /** @var string $language */
    private $language;

    /** @var string $currency */
    private $currency;

    /** @var string $country */
    private $country;

    /**
     * ProductSource constructor
     *
     * @param string $id
     * @param string $url
     * @param string $language
     * @param string $currency
     * @param string $country
     */
    public function __construct(string $id, string $url, string $language='pl', string $currency='pln', string $country = 'pl')
    {
        $this->properties = [];
        $this->id = $id;
        $this->url = $url;
        $this->attributes = [];
        $this->categories = [];
        $this->images = [];
        $this->language = $language;
        $this->currency = $currency;
        $this->country = $country;
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
     * Get attributes
     *
     * @return AttributeSource[]
     */
    public function getAttributes(): array
    {
        $this->sortAttributes();
        return $this->attributes;
    }

    /**
     * Set attributes
     *
     * @param AttributeSource[] $attributes
     */
    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
        $this->sortAttributes();
    }

    /**
     * Add attribute
     *
     * @param string $name
     * @param string $value
     * @param int $order
     */
    public function addAttribute(string $name, string $value, int $order = 100)
    {
        $attribute = new AttributeSource($name, $value);
        $attribute->setOrder($order);
        array_push($this->attributes, $attribute);
        $this->sortAttributes();
    }

    /**
     * Get attribute value
     * @param string $name
     * @return string|null
     */
    public function getAttributeValue(string $name)
    {
        foreach ($this->attributes as $attribute){
            if ($name === $attribute->getName()){
                return $attribute->getValue();
            }
        }
        return null;
    }

    /**
     * Sort attributes
     */
    public function sortAttributes()
    {
        $attributes = [];
        $lastOrder = null;
        foreach ($this->attributes as $attribute){
            $lastOrder = $lastOrder ?? $attribute->getOrder();
            if ($attribute->getOrder() < $lastOrder){
               array_unshift($attributes, $attribute);
            } else {
                array_push($attributes, $attribute);
            }
        }
        $this->attributes = $attributes;
    }

    /**
     * Remove long attributes
     */
    public function removeLongAttributes(): void
    {
        $attributes = $this->getAttributes();
        foreach ($attributes as $index => $attribute){
            if (mb_strlen($attribute->getName()) > 50 || Str::contains($attribute->getValue(), '<br>') || Str::contains($attribute->getValue(), 'class=')){
                unset($attributes[$index]);
            }
        }
        $this->setAttributes($attributes);
    }

    /**
     * Get price
     *
     * @return float|null
     */
    public function getPrice(): ?float
    {
        return $this->price;
    }

    /**
     * Set price
     *
     * @param float $price
     */
    public function setPrice(float $price): void
    {
        $this->price = round($price, 5);
    }

    /**
     * Get tax
     *
     * @return int|null
     */
    public function getTax(): ?int
    {
        return $this->tax;
    }

    /**
     * Set tax
     *
     * @param int $tax
     */
    public function setTax(int $tax): void
    {
        $this->tax = $tax;
    }

    /**
     * Get stock
     *
     * @return int
     */
    public function getStock(): int
    {
        return $this->stock;
    }

    /**
     * Set stock
     *
     * @param int $stock
     */
    public function setStock(int $stock): void
    {
        $this->stock = $stock;
    }

    /**
     * Get availability
     *
     * @return int|null
     */
    public function getAvailability(): ?int
    {
        return $this->availability;
    }

    /**
     * Set availability
     *
     * @param int $availability
     */
    public function setAvailability(int $availability): void
    {
        $this->availability = $availability;
    }

    /**
     * Get categories
     *
     * @return CategorySource[]
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /**
     * Set categories
     *
     * @param CategorySource[] $categories
     */
    public function setCategories(array $categories): void
    {
        $this->categories = $categories;
    }

    /**
     * Add category
     *
     * @param CategorySource $category
     */
    public function addCategory(CategorySource $category): void
    {
        array_push($this->categories, $category);
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
     * Get images
     *
     * @return ImageSource[]
     */
    public function getImages(): array
    {
        return $this->images;
    }

    /**
     * Set images
     *
     * @param ImageSource[] $images
     */
    public function setImages(array $images): void
    {
        $this->images = $images;
    }

    /**
     * Add image
     *
     * @param bool $main
     * @param string $id
     * @param string $url
     * @param string $filenameUnique
     * @param string $extension
     * @param string|null $backgroundFill
     * @param string|null $contents
     * @throws DelivererAgripException
     */
    public function addImage(bool $main, string $id, string $url, string $filenameUnique , string $extension = 'jpg', ?string $backgroundFill = null, ?string $contents = null)
    {
        $filenameUnique = str_replace('&', '-', $filenameUnique);
        if (strlen($id)>50 ){
            throw new DelivererAgripException('ID image is to long %s.', $id);
        }
        $image = new ImageSource($main, $id, $url, $filenameUnique, $extension, $backgroundFill);
        if ($contents){
            $image->setProperty('contents', $contents);
        }
        array_push($this->images, $image);
    }

    /**
     * Add image as object
     *
     * @param ImageSource $image
     */
    public function addImageAsObject(ImageSource $image): void
    {
        array_push($this->images, $image);
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Set description
     *
     * @param string $description
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * Get language
     *
     * @return string
     */
    public function getLanguage(): string
    {
        return mb_strtolower($this->language);
    }

    /**
     * Set language
     *
     * @param string $language
     */
    public function setLanguage(string $language): void
    {
        $this->language = $language;
    }

    /**
     * Get currency
     *
     * @return string
     */
    public function getCurrency(): string
    {
        return mb_strtolower($this->currency);
    }

    /**
     * Set currency
     *
     * @param string $currency
     */
    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    /**
     * Get country
     *
     * @return string
     */
    public function getCountry(): string
    {
        return mb_strtolower($this->country);
    }

    /**
     * Set country
     *
     * @param string $country
     */
    public function setCountry(string $country): void
    {
        $this->country = $country;
    }

    /**
     * Check
     *
     * @throws DelivererAgripException
     */
    public function check()
    {
        if (!$this->getId()){
            throw new DelivererAgripException('Not found ID product');
        } else if (!$this->getUrl()){
            throw new DelivererAgripException('Not found URL product');
        }else if (!$this->getPrice() === null){
            throw new DelivererAgripException('Not found price product');
        } else if ($this->getStock() === null){
            throw new DelivererAgripException('Not found stock product');
        } else if ($this->getAvailability() === null){
            throw new DelivererAgripException('Not found availability product');
        } else if ($this->getTax() === null){
            throw new DelivererAgripException('Not found tax product');
        } else if (!$this->getName()){
            throw new DelivererAgripException('Not found name product');
        }else if (!$this->getCategories()){
            throw new DelivererAgripException('Not found categories product');
        }else if (!$this->getLanguage()){
            throw new DelivererAgripException('Not found language product.');
        }else if (!$this->getCurrency()){
            throw new DelivererAgripException('Not found currency product.');
        }else if (!$this->getCountry()){
            throw new DelivererAgripException('Not found country product.');
        }
        if (strlen($this->getName()) > 255){
            throw new DelivererAgripException('Name products is too long.');
        }
        if ($this->getDescription() === null){
            throw new DelivererAgripException('Not found description.');
        }
        foreach ($this->getAttributes() as $attribute){
            if (mb_strlen($attribute->getName()) > 50){
                throw new DelivererAgripException('Name attribute product it\'s too long.');
            }
        }
        if (!$this->getAttributeValue('EAN')){
            DelivererLogger::log(sprintf('Not found EAN %s', $this->getId()));
        }
        if (!$this->getAttributeValue('SKU')){
            DelivererLogger::log(sprintf('Not found SKU %s', $this->getId()));
        }
    }

    /**
     * Clone
     *
     * @return ProductSource
     */
    public function clone(): ProductSource
    {
        $product = clone $this;
        $properties = $this->getProperties();
        $attributes = $this->getAttributes();
        $categories = $this->getCategories();
        $images = $this->getImages();
        $product->setProperties([]);
        $product->setAttributes([]);
        $product->setAttributes([]);
        $product->setImages([]);
        foreach ($properties as $property){
            $product->setProperty($property->getName(), $property->getValue());
        }
        foreach ($attributes as $attribute){
            $product->addAttribute($attribute->getName(), $attribute->getValue(), $attribute->getOrder());
        }
        foreach ($categories as $category){
            $product->addCategory($category->clone());
        }
        foreach ($images as $image){
            $product->addImageAsObject($image->clone());
        }
        return $product;
    }
}