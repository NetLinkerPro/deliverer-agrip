<?php

namespace NetLinker\DelivererAgrip\Sections\FormatterRanges\Models;

use Illuminate\Database\Eloquent\Model;

class Action extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    public $fillable = ['name', 'value'];
}

