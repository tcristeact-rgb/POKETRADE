<?php

namespace App\Models;

use App\Support\CatalogoTcg;
use Illuminate\Database\Eloquent\Model;

class Carta extends Model
{
    // Campos que se pueden rellenar. tipo y rareza NO están: en la BD viven
    // como clave canónica (tipo_key, rareza_key) y en el JSON salen ya
    // traducidas, desde los accesores de abajo.
    protected $fillable = [
        'tcgdex_id',
        'nombre',
        'tipo_key',
        'rareza_key',
        'set_expansion',
        'set_id',
        'numero',
        'imagen_url',
        'descripcion',
        'ilustrador',
        'hp',
        'precio_cardmarket',
        'detalle_synced_at',
    ];

    // Tipado de atributos al serializar a JSON
    protected $casts = [
        'hp'                => 'integer',
        'precio_cardmarket' => 'float',
        'detalle_synced_at' => 'datetime',
    ];

    // Atributos calculados que el frontend recibe en el JSON.
    //
    // tipo y rareza ya no son columnas: se derivan de la clave canónica y del
    // idioma de la petición. Conservan el nombre de antes a propósito — el
    // frontend sigue pintando carta.tipo y carta.rareza sin enterarse de
    // nada, y ahora salen traducidos.
    protected $appends = ['imagen_low', 'imagen_high', 'tipo', 'rareza'];

    // --- Tipo y rareza, en el idioma de la petición ---
    // El idioma lo fijó el middleware EstablecerIdioma desde Accept-Language.
    // La clave viaja igualmente en el JSON (tipo_key / rareza_key): es lo que
    // usa el frontend para filtrar, porque no depende del idioma.
    public function getTipoAttribute(): ?string
    {
        return $this->tipo_key ? __("tcg.tipos.{$this->tipo_key}") : null;
    }

    public function getRarezaAttribute(): ?string
    {
        return $this->rareza_key ? __("tcg.rarezas.{$this->rareza_key}") : null;
    }

    public function getImagenLowAttribute(): ?string
    {
        return $this->urlImagen('low');
    }

    public function getImagenHighAttribute(): ?string
    {
        return $this->urlImagen('high');
    }

    // Construye la URL final de la imagen. Si imagen_url ya es una URL
    // completa con extensión (filas anteriores a TCGdex), se devuelve
    // tal cual para no romper datos antiguos.
    private function urlImagen(string $calidad): ?string
    {
        if (!$this->imagen_url) {
            return null;
        }
        if (preg_match('/\.(png|jpe?g|webp)$/i', $this->imagen_url)) {
            return $this->imagen_url;
        }
        return "{$this->imagen_url}/{$calidad}.webp";
    }
}
