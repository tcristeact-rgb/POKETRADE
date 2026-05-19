<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tradeo extends Model
{
    protected $fillable = [
        'user_id',
        'descripcion',
        'estado',
    ];

    // Relación con el usuario
    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Cartas que ofrece
    public function cartasOfrece()
    {
        return $this->belongsToMany(Carta::class, 'tradeo_cartas_ofrece');
    }

    // Cartas que busca
    public function cartasBusca()
    {
        return $this->belongsToMany(Carta::class, 'tradeo_cartas_busca');
    }
}