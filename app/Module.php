<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'module_code', 'module_name', 'module_term',
    ];
}
