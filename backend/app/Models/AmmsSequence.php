<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmmsSequence extends Model
{
    protected $table = 'amms_sequences';
    protected $primaryKey = 'name';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['name','value'];
}
