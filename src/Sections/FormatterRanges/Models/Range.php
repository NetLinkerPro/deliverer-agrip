<?php

namespace NetLinker\DelivererAgrip\Sections\FormatterRanges\Models;

use Cog\Laravel\Ownership\Traits\HasOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Ramsey\Uuid\Uuid;
use Cog\Contracts\Ownership\Ownable as OwnableContract;

class Range extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    public $fillable = ['name', 'value'];
}

