<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Models\Student;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        return Student::query()
            ->when($request->search, function ($q) use ($request) {
                $q->whereLike('nim', "%{$request->search}%")
                    ->orWhereLike('name', "%{$request->search}%")
                    ->orWhereLike('email', "%{$request->search}%");
            })
            ->paginate(10);
    }

    public function store(StoreStudentRequest $request)
    {
        $validated = $request->validated();

        return response()->json([
            'message' => 'Mahasiswa berhasil ditambahkan.',
            'data' => Student::create($validated),
        ], 201);
    }

    public function update(UpdateStudentRequest $request, $id)
    {
        $student = Student::findOrFail($id);

        $validated = $request->validated();

        $student->update($validated);

        return response()->json([
            'message' => 'Data mahasiswa berhasil diperbarui.',
            'data' => $student,
        ]);
    }

    public function destroy($id)
    {
        $student = Student::findOrFail($id);

        if ($student->enrollments()->exists()) {
            return response()->json([
                'message' => 'Mahasiswa tidak dapat dihapus karena masih digunakan pada data KRS.',
            ], 409);
        }

        $student->delete();

        return response()->json(['message' => 'Mahasiswa berhasil dihapus.']);
    }

    public function search(Request $request)
    {
        return Student::query()
            ->when($request->search, function ($q) use ($request) {
                $q->whereLike('nim', "%{$request->search}%")
                    ->orWhereLike('name', "%{$request->search}%");
            })
            ->limit(10)
            ->get(['id', 'nim', 'name']);
    }
}
