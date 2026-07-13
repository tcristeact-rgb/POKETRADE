<?php

namespace App\Models;

use App\Models\Concerns\TraduceCampos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Set extends Model
{
    use TraduceCampos;

    // Campos que se pueden rellenar
    protected $fillable = [
        'tcgdex_id',
        'serie_id',
        'nombre',
        'nombre_es',
        'nombre_en',
        'logo_url',
        'simbolo_url',
        'numero_cartas',
        'fecha_lanzamiento',
        'synced_at',
        'idiomas_sincronizados',
    ];

    // Tipado de atributos al serializar a JSON
    protected $casts = [
        'numero_cartas'         => 'integer',
        'fecha_lanzamiento'     => 'date:Y-m-d',
        'synced_at'             => 'datetime',
        'idiomas_sincronizados' => 'array',
    ];

    // Nombre ya traducido, y URLs de logo y símbolo listas para el frontend
    protected $appends = ['nombre', 'logo', 'simbolo'];

    // Las columnas por idioma no salen al JSON: fuera se ve un único nombre
    protected $hidden = ['nombre_es', 'nombre_en'];

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

    public function getNombreAttribute(): ?string
    {
        return $this->traducido('nombre');
    }

    public function setNombreAttribute($valor): void
    {
        $this->traducir('nombre', $valor);
    }

    // --- Cache-aside por idioma ---
    // El catálogo de un set se vuelca a la tabla cartas una vez por idioma: la
    // primera visita en español trae los nombres españoles, y la primera en
    // inglés los ingleses. Cada una es UNA petición a TCGdex; las siguientes
    // visitas en ese idioma ya no salen de la BD.
    public function cacheadoEn(string $idioma): bool
    {
        return in_array($idioma, $this->idiomas_sincronizados ?? [], true);
    }

    // Deja constancia de que ese catálogo ya se ha PEDIDO, haya traído cartas o
    // no. Es la diferencia entre guardar el intento y guardar el resultado: de
    // los sets clásicos (Neo Genesis, Gym Challenge...) no existe versión
    // española, y sin esta marca la volveríamos a pedir en cada visita.
    public function marcarCacheadoEn(string $idioma): void
    {
        if ($this->cacheadoEn($idioma)) {
            return;
        }

        $this->update([
            'idiomas_sincronizados' => [...($this->idiomas_sincronizados ?? []), $idioma],
        ]);
    }

    // Los logos y símbolos de TCGdex son assets SIN extensión, igual que las
    // imágenes de carta (ver modelo Carta): hay que añadir el formato. Estos sí
    // son neutros: no llevan texto, y el mismo asset vale para todos los idiomas
    public function getLogoAttribute(): ?string
    {
        return $this->logo_url ? "{$this->logo_url}.webp" : null;
    }

    public function getSimboloAttribute(): ?string
    {
        return $this->simbolo_url ? "{$this->simbolo_url}.webp" : null;
    }
}
