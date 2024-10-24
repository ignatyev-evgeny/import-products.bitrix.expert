<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Import extends Model {
    protected $table = 'log_imports';

    protected $fillable = [
        'uuid',
        'domain',
        'status',
        'file_name',
        'file_size',
        'file_count_rows',
        'product_count_rows',
        'events_history',
    ];

    protected $casts = [
        'events_history' => 'array',
    ];
}
