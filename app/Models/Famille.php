<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Famille extends Model
{
    protected $table = 'familles';

    protected $fillable = ['code', 'intitule', 'intitule_court'];
}
