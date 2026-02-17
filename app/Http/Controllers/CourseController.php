<?php
namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{

    public function index(Request $request)
    {
        return Course::query()
            ->when($request->search, function ($q) use ($request) {
                $q->where('code', 'ilike', "%{$request->search}%")
                    ->orWhere('name', 'ilike', "%{$request->search}%");
            })
            ->paginate(10);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'    => 'required|alpha_num|max:10|unique:courses,code',
            'name'    => 'required|string|max:100',
            'credits' => 'required|integer|min:1|max:6',
        ]);

        return Course::create($validated);
    }

    public function update(Request $request, $id)
    {
        $course = Course::findOrFail($id);

        $validated = $request->validate([
            'code'    => [
                'required',
                'alpha_num',
                Rule::unique('courses')->ignore($course->id),
            ],
            'name'    => 'required|string|max:100',
            'credits' => 'required|integer|min:1|max:6',
        ]);

        $course->update($validated);

        return $course;
    }

    public function destroy($id)
    {
        $course = Course::findOrFail($id);
        $course->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }

    public function search(Request $request)
    {
        return Course::query()
            ->when($request->search, function ($q) use ($request) {
                $q->where('code', 'ilike', "%{$request->search}%")
                    ->orWhere('name', 'ilike', "%{$request->search}%");
            })
            ->limit(10)
            ->get(['id', 'code', 'name']);
    }

}
