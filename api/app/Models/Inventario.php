<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventario extends Model
{
    protected $table = 'inventario';

    protected $fillable = [
        'user_id',
        'carta_id',
        'cantidad',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function carta()
    {
        return $this->belongsTo(Carta::class, 'carta_id');
    }
}