<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Set extends Model
{
    // Campos que se pueden rellenar
    protected $fillable = [
        'tcgdex_id',
        'serie_id',
        'nombre',
        'logo_url',
        'simbolo_url',
        'numero_cartas',
        'fecha_lanzamiento',
        'synced_at',
    ];

    // Tipado de atributos al serializar a JSON
    protected $casts = [
        'numero_cartas'     => 'integer',
        'fecha_lanzamiento' => 'date:Y-m-d',
        'synced_at'         => 'datetime',
    ];

    // URLs de logo y símbolo listas para el frontend
    protected $appends = ['logo', 'simbolo'];

    // Serie a la que pertenece el set
    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }

    // Cartas cacheadas del set. La relación usa el ID de TCGdex, no el
    // interno: cartas.set_id guarda "sv03.5" desde la migración a TCGdex
    public function cartas(): HasMany
    {
        return $this->hasMany(Carta::class, 'set_id', 'tcgdex_id');
    }

    // Los logos y símbolos de TCGdex son assets SIN extensión, igual que
    // las imágenes de carta (ver modelo Carta): hay que añadir el formato
    public function getLogoAttribute(): ?string
    {
        return $this->logo_url ? "{$this->logo_url}.webp" : null;
    }

    public function getSimboloAttribute(): ?string
    {
        return $this->simbolo_url ? "{$this->simbolo_url}.webp" : null;
    }
}
