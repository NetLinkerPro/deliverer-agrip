<?php


namespace NetLinker\DelivererAgrip;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

trait Ownerable
{

    /**
     * Get auth owner uuid
     *
     * @return mixed
     */
    public function getAuthOwnerUuid(){

        /** @var Model $owner */
        $owner = $this->resolveDefaultOwner();
        return $owner->uuid;
    }

    /**
     * Get owners
     *
     * @return Collection
     */
    public function getOwners()
    {
        $model = $this->getOwnerModel();
        return $model::all();
    }

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
     * Resolve entity default owner.
     *
     * @return null|\Cog\Contracts\Ownership\CanBeOwner
     */
    public function resolveDefaultOwner()
    {
        $fieldUuid = config('deliverer-agrip.owner.field_auth_user_owner_uuid');
        $uuid = Auth::user()->$fieldUuid;
        return $this->getOwner($uuid);
    }

    /**
     * Get owner
     *
     * @param string $uuid
     * @return mixed
     */
    public function getOwner(string $uuid){
        $model = $this->getOwnerModel();
        return $model::where('uuid',$uuid)->first();
    }
}