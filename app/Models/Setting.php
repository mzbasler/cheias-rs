<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $guarded = [];

    /** Linha única: sempre a de id 1, criada na primeira leitura se não existir. */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }
}
