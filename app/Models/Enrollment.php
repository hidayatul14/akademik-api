<?php
namespace App\Models;

use App\Models\Course;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Enrollment extends Model
{

    use SoftDeletes;
    protected $fillable = [
        'student_id',
        'course_id',
        'academic_year',
        'semester',
        'status',
    ];
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
