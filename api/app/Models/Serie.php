<?php

namespace App\Models;

use App\Models\Concerns\TraduceCampos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Serie extends Model
{
    use TraduceCampos;

    // Nombre explícito: el pluralizador de Eloquent trabaja en inglés
    // y no es fiable con "serie" → "series"
    protected $table = 'series';

    // Campos que se pueden rellenar
    protected $fillable = [
        'tcgdex_id',
        'nombre',
        'nombre_es',
        'nombre_en',
        'logo_url',
    ];

    // Nombre ya traducido y URL del logo lista para el frontend
    protected $appends = ['nombre', 'logo'];

    // Las columnas por idioma no salen al JSON: fuera se ve un único nombre
    protected $hidden = ['nombre_es', 'nombre_en'];

    // Sets de expansión que pertenecen a esta serie
    public function sets(): HasMany
    {
        return $this->hasMany(Set::class);
    }

    public function getNombreAttribute(): ?string
    {
        return $this->traducido('nombre');
    }

    public function setNombreAttribute($valor): void
    {
        $this->traducir('nombre', $valor);
    }

    // Los logos de TCGdex son assets SIN extensión, igual que las
    // imágenes de carta (ver modelo Carta): hay que añadir el formato
    public function getLogoAttribute(): ?string
    {
        return $this->logo_url ? "{$this->logo_url}.webp" : null;
    }
}
