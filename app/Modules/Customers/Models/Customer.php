<?php

namespace App\Modules\Customers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'phone', 'address'];
}
