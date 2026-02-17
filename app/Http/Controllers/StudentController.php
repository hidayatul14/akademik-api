<?php
namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        return Student::query()
            ->when($request->search, function ($q) use ($request) {
                $q->where('nim', 'ilike', "%{$request->search}%")
                    ->orWhere('name', 'ilike', "%{$request->search}%");
            })
            ->paginate(10);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nim'   => 'required|digits_between:8,12|unique:students,nim',
            'name'  => 'required|string|max:100',
            'email' => 'required|email|unique:students,email',
        ]);

        return Student::create($validated);
    }

    public function update(Request $request, $id)
    {
        $student = Student::findOrFail($id);

        $validated = $request->validate([
            'nim'   => [
                'required',
                'digits_between:8,12',
                Rule::unique('students')->ignore($student->id),
            ],
            'name'  => 'required|string|max:100',
            'email' => [
                'required',
                'email',
                Rule::unique('students')->ignore($student->id),
            ],
        ]);

        $student->update($validated);

        return $student;
    }

    public function destroy($id)
    {
        $student = Student::findOrFail($id);
        $student->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }

    public function search(Request $request)
    {
        return Student::query()
            ->when($request->search, function ($q) use ($request) {
                $q->where('nim', 'ilike', "%{$request->search}%")
                    ->orWhere('name', 'ilike', "%{$request->search}%");
            })
            ->limit(10)
            ->get(['id', 'nim', 'name']);
    }
}
