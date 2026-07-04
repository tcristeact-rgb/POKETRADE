<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Carta extends Model
{
    // Campos que se pueden rellenar
    protected $fillable = [
        'tcgdex_id',
        'nombre',
        'tipo',
        'rareza',
        'set_expansion',
        'set_id',
        'numero',
        'imagen_url',
        'descripcion',
        'ilustrador',
        'hp',
        'precio_cardmarket',
    ];

    // Tipado de atributos al serializar a JSON
    protected $casts = [
        'hp'                => 'integer',
        'precio_cardmarket' => 'float',
    ];

    // URLs de imagen listas para el frontend. TCGdex sirve la imagen
    // base SIN extensión y hay que añadir calidad y formato:
    //   low.webp  → listados y grids
    //   high.webp → vista detalle
    protected $appends = ['imagen_low', 'imagen_high'];

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
