<?php

namespace App\Models;

use App\Models\Concerns\TraduceCampos;
use App\Support\Idiomas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Carta extends Model
{
    use TraduceCampos;

    // nombre, descripcion e imagen_url ya no son columnas: viven una por idioma
    // (ver la migración). Siguen aquí porque un admin da de alta una carta con
    // UN nombre, y el mutador del trait lo escribe en la columna del idioma
    // activo. El sync escribe las columnas concretas, que sí sabe cuál toca.
    protected $fillable = [
        'tcgdex_id',
        'nombre',
        'nombre_es',
        'nombre_en',
        'tipo_key',
        'rareza_key',
        'set_id',
        'numero',
        'imagen_url',
        'imagen_es',
        'imagen_en',
        'descripcion',
        'descripcion_es',
        'descripcion_en',
        'ilustrador',
        'hp',
        'precio_cardmarket',
        'detalle_synced_at',
        'idiomas_detallados',
    ];

    // Tipado de atributos al serializar a JSON
    protected $casts = [
        'hp'                 => 'integer',
        'precio_cardmarket'  => 'float',
        'detalle_synced_at'  => 'datetime',
        'idiomas_detallados' => 'array',
    ];

    // El set viaja siempre con la carta: es de donde sale el nombre de la
    // expansión ya traducido, que antes era una copia denormalizada en la
    // propia carta. Precargarlo aquí, y no consulta a consulta, es lo que
    // impide que se cuele un N+1 en cualquiera de los sitios donde se serializa
    // una carta — grid, detalle, inventario, tradeos, búsqueda.
    protected $with = ['set'];

    // El set entero no se serializa (el frontend solo necesita su nombre), ni
    // las columnas por idioma: fuera se ve un único campo ya traducido.
    protected $hidden = [
        'set',
        'nombre_es', 'nombre_en',
        'descripcion_es', 'descripcion_en',
        'imagen_es', 'imagen_en',
        'idiomas_detallados',
    ];

    // Atributos calculados que el frontend recibe en el JSON.
    //
    // Todos conservan el nombre que tenían cuando eran columnas: el frontend
    // sigue pintando carta.nombre, carta.imagen_url o carta.set_expansion sin
    // enterarse de nada, y ahora salen en el idioma de la petición.
    protected $appends = [
        'nombre',
        'descripcion',
        'imagen_url',
        'set_expansion',
        'imagen_low',
        'imagen_high',
        'tipo',
        'rareza',
    ];

    // Set al que pertenece la carta. La relación va por el ID de TCGdex:
    // cartas.set_id guarda "sv03.5", no el id interno
    public function set(): BelongsTo
    {
        return $this->belongsTo(Set::class, 'set_id', 'tcgdex_id');
    }

    // --- Los campos traducidos ---
    public function getNombreAttribute(): ?string
    {
        return $this->traducido('nombre');
    }

    public function getDescripcionAttribute(): ?string
    {
        return $this->traducido('descripcion');
    }

    // La imagen también es un texto traducido, aunque no lo parezca: el asset
    // lleva el idioma en la ruta porque la ilustración incluye el texto impreso
    // de la carta. Un inglés que abre "151" ve el escaneo inglés.
    public function getImagenUrlAttribute(): ?string
    {
        return $this->traducido('imagen');
    }

    public function setNombreAttribute($valor): void
    {
        $this->traducir('nombre', $valor);
    }

    public function setDescripcionAttribute($valor): void
    {
        $this->traducir('descripcion', $valor);
    }

    public function setImagenUrlAttribute($valor): void
    {
        $this->traducir('imagen', $valor);
    }

    // Nombre del set, traducido. Era una columna copiada de sets.nombre; ahora
    // sale de la relación, que es donde vive el dato de verdad.
    public function getSetExpansionAttribute(): ?string
    {
        return $this->set?->nombre;
    }

    // --- Tipo y rareza, en el idioma de la petición ---
    // Estos no necesitan columna por idioma: son un conjunto cerrado, así que
    // la BD guarda la clave canónica y el texto sale del diccionario.
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

    // --- Búsqueda por nombre, en todos los idiomas a la vez ---
    // No solo en el del idioma activo: los nombres de Pokémon coinciden en los
    // dos catálogos, pero los de Entrenador no ("Investigación del Profesor" /
    // "Professor's Research"), y una carta que aún no se ha hidratado en inglés
    // solo tiene el nombre español. Buscar únicamente en la columna activa la
    // dejaría invisible para quien navega en inglés.
    public function scopeNombreParecidoA(Builder $query, string $texto): Builder
    {
        // PostgreSQL distingue mayúsculas con LIKE, por eso ahí usamos ILIKE.
        // SQLite y MySQL ya son insensibles con LIKE y no soportan ILIKE.
        $like = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return $query->where(function (Builder $q) use ($like, $texto) {
            foreach (Idiomas::SOPORTADOS as $idioma) {
                $q->orWhere("nombre_{$idioma}", $like, "%{$texto}%");
            }
        });
    }

    // ¿Ya hemos pedido a TCGdex el detalle de esta carta en ese idioma?
    // Guarda el intento, no el resultado: de una carta de un set clásico no
    // hay versión española, y sin esta marca la pediríamos en cada visita.
    public function detalladoEn(string $idioma): bool
    {
        return in_array($idioma, $this->idiomas_detallados ?? [], true);
    }

    // Construye la URL final de la imagen. Si el valor guardado ya es una URL
    // completa con extensión (filas anteriores a TCGdex, o una carta dada de
    // alta a mano), se devuelve tal cual.
    private function urlImagen(string $calidad): ?string
    {
        $imagen = $this->imagen_url;

        if (!$imagen) {
            return null;
        }
        if (preg_match('/\.(png|jpe?g|webp)$/i', $imagen)) {
            return $imagen;
        }

        return "{$imagen}/{$calidad}.webp";
    }
}
