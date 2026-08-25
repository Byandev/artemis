<?php

namespace Modules\Courses\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Modules\Courses\Models\Course;
use Modules\Courses\Models\CourseModule;

/**
 * Modules are edited from the course detail page. They have no permissions of
 * their own — editing a course's structure is editing the course.
 */
class CourseModuleController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, Workspace $workspace, Course $course)
    {
        $this->guard($request, $workspace, $course);
        $this->authorize(Permission::CreateCourses->value, $workspace);

        // Optional on create: the UI appends the row first and lets the user
        // name it in place, so a blank submit is the normal path here.
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $course->modules()->create([
            'name' => ($validated['name'] ?? null) ?: 'Untitled module',
            // Append: one past the current highest, so a new module lands last.
            'position' => (int) $course->modules()->max('position') + 1,
        ]);

        return back()->with('success', 'Module added.');
    }

    public function update(Request $request, Workspace $workspace, Course $course, CourseModule $module)
    {
        $this->guard($request, $workspace, $course, $module);
        $this->authorize(Permission::EditCourses->value, $workspace);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $module->update($validated);

        return back()->with('success', 'Module updated.');
    }

    public function destroy(Request $request, Workspace $workspace, Course $course, CourseModule $module)
    {
        $this->guard($request, $workspace, $course, $module);
        $this->authorize(Permission::DeleteCourses->value, $workspace);

        // Lessons cascade with the module.
        $module->delete();

        return back()->with('success', 'Module deleted.');
    }

    /**
     * Same shape as CoursesController@guard, plus the module-belongs-to-course
     * check: route-model binding resolves the module by id alone.
     */
    private function guard(Request $request, Workspace $workspace, Course $course, ?CourseModule $module = null): void
    {
        abort_unless($workspace->courses_module_enabled, 404);

        if (! $request->user()->isMemberOf($workspace)) {
            abort(403);
        }

        abort_unless($course->workspace_id === $workspace->id, 404);

        if ($module) {
            abort_unless($module->course_id === $course->id, 404);
        }
    }
}
