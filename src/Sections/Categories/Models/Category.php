<?php

namespace NetLinker\DelivererAgrip\Sections\Categories\Models;

use Cog\Laravel\Ownership\Traits\HasOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use NetLinker\DelivererAgrip\Sections\CategoryRanges\Models\CategoryRange;
use Ramsey\Uuid\Uuid;
use Cog\Contracts\Ownership\Ownable as OwnableContract;

class Category extends Model implements OwnableContract
{

    use HasOwner;

    protected $ownerPrimaryKey = 'uuid';
    protected $ownerForeignKey = 'owner_uuid';

    protected $withDefaultOwnerOnCreate = true;


    /**
     * Get owner model name.
     *
     * @return string
     */
    protected function getOwnerModel()
    {
        return config('deliverer-agrip.owner.model');
    }

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'deliverer_agrip_categories';

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'active' => 'boolean',
        'table_number' => 'integer',
        'data'=> 'array',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    public $fillable = ['uuid', 'owner_uuid', 'name', 'description', 'active',
        'uri', 'ctx', 'ctr', 'item_id', 'table_number', 't', 'data'];

    public $orderable = [];

    protected $encryptable = [];

    /**
     * Binds creating/saving events to create UUIDs (and also prevent them from being overwritten).
     *
     * @return void
     */
    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = Uuid::uuid4()->toString();
        });

        static::saving(function ($model) {
            $original_uuid = $model->getOriginal('uuid');
            if ($original_uuid !== $model->uuid) {
                $model->uuid = $original_uuid;
            }
        });
    }

    /**
     * If the attribute is in the encryptable array
     * then decrypt it.
     *
     * @param  $key
     *
     * @return $value
     */
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);
        if (in_array($key, $this->encryptable) && $value !== '') {
            $value = decrypt($value);
        }
        return $value;
    }

    /**
     * If the attribute is in the encryptable array
     * then encrypt it.
     *
     * @param $key
     * @param $value
     * @return mixed
     */
    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->encryptable)) {
            $value = encrypt($value);
        }
        return parent::setAttribute($key, $value);
    }

    /**
     * When need to make sure that we iterate through
     * all the keys.
     *
     * @return array
     */
    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();
        foreach ($this->encryptable as $key) {
            if (isset($attributes[$key])) {
                $attributes[$key] = decrypt($attributes[$key]);
            }
        }
        return $attributes;
    }

    /**
     * Resolve entity default owner.
     *
     * @return null|\Cog\Contracts\Ownership\CanBeOwner
     */
    public function resolveDefaultOwner()
    {
        $fieldUuid = config('deliverer-agrip.owner.field_auth_user_owner_uuid');
        $model = $this->getOwnerModel();
        return $model::where('uuid', Auth::user()->$fieldUuid)->first();
    }
}

