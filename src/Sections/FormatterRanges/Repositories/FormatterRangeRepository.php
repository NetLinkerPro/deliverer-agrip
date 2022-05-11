<?php

namespace NetLinker\DelivererAgrip\Sections\FormatterRanges\Repositories;

use AwesIO\Repository\Eloquent\BaseRepository;
use Illuminate\Support\Facades\Auth;
use NetLinker\DelivererAgrip\Sections\FormatterRanges\Models\FormatterRange;
use NetLinker\DelivererAgrip\Sections\FormatterRanges\Scopes\FormatterRangeScopes;

class FormatterRangeRepository extends BaseRepository
{
    protected $searchable = [];

    public function entity()
    {
        return FormatterRange::class;
    }

    public function scope($request)
    {
        // apply build-in scopes
        parent::scope($request);

        // apply custom scopes
        $this->entity = (new FormatterRangeScopes($request))->scope($this->entity);

        return $this;
    }

    public function scopeOwner()
    {
        $fieldUuid = config('deliverer-agrip.owner.field_auth_user_owner_uuid');
        $ownerUuid = Auth::user()->$fieldUuid;

        $this->entity = $this->entity->where('owner_uuid', $ownerUuid);

        return $this;
    }

    /**
     * Delete a record by id.
     *
     * @param  int  $id
     *
     * @return mixed
     */
    public function destroy($id)
    {
        $results = $this->entity->where('id', $id)->delete();

        $this->reset();

        return $results;
    }
}
