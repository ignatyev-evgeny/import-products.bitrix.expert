<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationField extends Model {
    protected $fillable = [
        'domain',
        'article',
        'brand',
    ];
}
