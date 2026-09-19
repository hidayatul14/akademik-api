<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $fillable = [
        'nim',
        'name',
        'email',
    ];

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);

    }
}
