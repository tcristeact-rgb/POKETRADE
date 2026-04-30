<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Carta extends Model
{
    // Campos que se pueden rellenar
    protected $fillable = [
        'nombre',
        'tipo',
        'rareza',
        'set_expansion',
        'numero',
        'imagen_url',
        'descripcion',
    ];
}