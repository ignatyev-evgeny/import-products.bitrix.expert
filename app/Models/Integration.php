<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Integration extends Model {
    protected $fillable = [
        'domain',
        'access_key',
        'refresh_key',
        'product_field_article',
        'product_field_brand',
    ];
}
