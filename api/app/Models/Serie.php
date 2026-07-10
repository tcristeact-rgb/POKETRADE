<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Serie extends Model
{
    // Nombre explícito: el pluralizador de Eloquent trabaja en inglés
    // y no es fiable con "serie" → "series"
    protected $table = 'series';

    // Campos que se pueden rellenar
    protected $fillable = [
        'tcgdex_id',
        'nombre',
        'logo_url',
    ];

    // URL del logo lista para el frontend (ver getLogoAttribute)
    protected $appends = ['logo'];

    // Sets de expansión que pertenecen a esta serie
    public function sets(): HasMany
    {
        return $this->hasMany(Set::class);
    }

    // Los logos de TCGdex son assets SIN extensión, igual que las
    // imágenes de carta (ver modelo Carta): hay que añadir el formato
    public function getLogoAttribute(): ?string
    {
        return $this->logo_url ? "{$this->logo_url}.webp" : null;
    }
}
