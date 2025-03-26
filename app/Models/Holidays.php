<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holidays extends Model
{
    use HasFactory;

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array
     */
    protected $fillable = [
        'date',
        'local_name',
        'name',
        'country_code',
        'fixed',
        'global',
        'counties',
        'types',
        'year',
    ];

    /**
     * Los atributos que deben convertirse.
     *
     * @var array
     */
    protected $casts = [
        'date' => 'date',
        'fixed' => 'boolean',
        'global' => 'boolean',
        'counties' => 'array',
        'types' => 'array',
        'year' => 'integer',
    ];

    /**
     * Scope para filtrar por año.
     */
    public function scopeOfYear($query, $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Scope para filtrar por país.
     */
    public function scopeOfCountry($query, $countryCode)
    {
        return $query->where('country_code', $countryCode);
    }

    /**
     * Obtener festivos entre dos fechas.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }
}
