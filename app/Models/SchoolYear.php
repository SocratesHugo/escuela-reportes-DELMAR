<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolYear extends Model
{
    protected $fillable = ['name','active'];

    // Conveniencia: scope para el activo
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    protected static function booted(): void
    {
        // Si este queda activo, desactiva todos los demás
        static::saved(function (SchoolYear $year) {
            if ($year->active) {
                static::where('id', '!=', $year->id)->update(['active' => false]);
            }
        });
    }

    // Relaciones que ya tengas (opcional)
    public function groups(){ return $this->hasMany(Group::class); }
    public function subjects(){ return $this->hasMany(Subject::class); }


}
