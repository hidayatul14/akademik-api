<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        return Course::query()
            ->when($request->search, function ($q) use ($request) {
                $q->whereLike('code', "%{$request->search}%")
                    ->orWhereLike('name', "%{$request->search}%");
            })
            ->paginate(10);
    }

    public function store(StoreCourseRequest $request)
    {
        $validated = $request->validated();

        return response()->json([
            'message' => 'Course created successfully',
            'data' => Course::create($validated),
        ], 201);
    }

    public function update(UpdateCourseRequest $request, $id)
    {
        $course = Course::findOrFail($id);

        $validated = $request->validated();

        $course->update($validated);

        return response()->json([
            'message' => 'Course updated successfully',
            'data' => $course,
        ]);
    }

    public function destroy($id)
    {
        $course = Course::findOrFail($id);

        if ($course->enrollments()->exists()) {
            return response()->json([
                'message' => 'Course cannot be deleted while enrollment records still reference it.',
            ], 409);
        }

        $course->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }

    public function search(Request $request)
    {
        return Course::query()
            ->when($request->search, function ($q) use ($request) {
                $q->whereLike('code', "%{$request->search}%")
                    ->orWhereLike('name', "%{$request->search}%");
            })
            ->limit(10)
            ->get(['id', 'code', 'name']);
    }
}
