<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SchoolManageController extends Controller
{
    private function getCurrentSchool(): School
    {
        $user = Auth::user();
        if ($user && $user->school) {
            return $user->school;
        }

        // Fallback for first school or auto-create for user
        $school = School::first();
        if (! $school && $user) {
            $school = School::create([
                'user_id'        => $user->id,
                'name'           => $user->name ?: 'My School',
                'udise_code'     => '01041800702',
                'principal_name' => $user->name ?: 'Head Incharge',
                'state'          => 'Jammu & Kashmir',
                'is_active'      => true,
            ]);
        }

        return $school;
    }

    public function updateSchool(Request $request): JsonResponse
    {
        $school = $this->getCurrentSchool();

        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'udise_code'     => 'required|string|max:15',
            'principal_name' => 'nullable|string|max:255',
            'phone'          => 'nullable|string|max:20',
            'district'       => 'nullable|string|max:100',
            'category'       => 'nullable|string|max:100',
            'state'          => 'nullable|string|max:100',
        ]);

        $validated['udise_code'] = preg_replace('/[^0-9]/', '', $validated['udise_code']);

        $school->update($validated);

        return response()->json([
            'ok'      => true,
            'message' => 'School profile updated successfully.',
            'school'  => $school,
        ]);
    }

    public function getTeachers(): JsonResponse
    {
        $school = $this->getCurrentSchool();
        $teachers = $school->teachers()->orderBy('id')->get();

        return response()->json([
            'ok'       => true,
            'school'   => $school,
            'teachers' => $teachers,
        ]);
    }

    public function storeTeacher(Request $request): JsonResponse
    {
        $school = $this->getCurrentSchool();

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'designation'  => 'nullable|string|max:100',
            'pen_code'     => 'nullable|string|max:30',
            'mobile'       => 'nullable|string|max:20',
            'email'        => 'nullable|email|max:255',
            'classes'      => 'nullable|string|max:255',
            'hpc_username' => 'nullable|string|max:100',
        ]);

        $teacher = $school->teachers()->create([
            'name'         => trim($validated['name']),
            'designation'  => ($validated['designation'] ?? null) ?: 'Teacher',
            'pen_code'     => $validated['pen_code'] ?? null,
            'mobile'       => $validated['mobile'] ?? null,
            'email'        => $validated['email'] ?? null,
            'classes'      => ($validated['classes'] ?? null) ?: 'Grade 6, Grade 7, Grade 8',
            'hpc_username' => $validated['hpc_username'] ?? null,
            'is_active'    => true,
        ]);

        // Also sync into schools.json for automation consistency
        $this->syncTeacherToSchoolsJson($school, $teacher);

        return response()->json([
            'ok'       => true,
            'message'  => "Teacher '{$teacher->name}' registered successfully.",
            'teacher'  => $teacher,
            'teachers' => $school->teachers()->get(),
        ]);
    }

    public function updateTeacher(Request $request, int $id): JsonResponse
    {
        $school = $this->getCurrentSchool();
        $teacher = $school->teachers()->findOrFail($id);

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'designation'  => 'nullable|string|max:100',
            'pen_code'     => 'nullable|string|max:30',
            'mobile'       => 'nullable|string|max:20',
            'email'        => 'nullable|email|max:255',
            'classes'      => 'nullable|string|max:255',
            'hpc_username' => 'nullable|string|max:100',
        ]);

        $teacher->update($validated);

        return response()->json([
            'ok'       => true,
            'message'  => "Teacher '{$teacher->name}' updated.",
            'teacher'  => $teacher,
            'teachers' => $school->teachers()->get(),
        ]);
    }

    public function deleteTeacher(int $id): JsonResponse
    {
        $school = $this->getCurrentSchool();
        $teacher = $school->teachers()->findOrFail($id);

        if ($school->teachers()->count() <= 1) {
            return response()->json([
                'ok'    => false,
                'error' => 'A school must have at least one registered teacher or incharge.',
            ], 422);
        }

        $teacherName = $teacher->name;
        $teacher->delete();

        return response()->json([
            'ok'       => true,
            'message'  => "Teacher '{$teacherName}' removed.",
            'teachers' => $school->teachers()->get(),
        ]);
    }

    private function syncTeacherToSchoolsJson(School $school, Teacher $teacher): void
    {
        $file = storage_path('app/hpc/schools.json');
        if (! file_exists($file)) {
            return;
        }

        $data = json_decode(file_get_contents($file), true) ?: [];
        $activeId = $data['active'] ?? 'default';

        foreach ($data['schools'] ?? [] as &$sch) {
            if ($sch['id'] === $activeId || ($sch['udise'] ?? '') === $school->udise_code) {
                if (! isset($sch['teachers'])) {
                    $sch['teachers'] = [];
                }
                $sch['teachers'][] = [
                    'id'   => 'teacher_' . $teacher->id,
                    'name' => $teacher->name,
                    'dir'  => 'teacher-profiles/teacher_' . $teacher->id,
                ];
                break;
            }
        }

        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function skipUdise(): JsonResponse
    {
        $school = $this->getCurrentSchool();
        $school->update([
            'udise_status' => 'skipped',
        ]);

        return response()->json([
            'ok'       => true,
            'message'  => 'UDISE synchronization skipped. Proceeding to HPC dashboard.',
            'redirect' => route('hpc.dashboard'),
        ]);
    }

    public function resetUdise(): JsonResponse
    {
        $school = $this->getCurrentSchool();
        $school->update([
            'udise_status' => 'not_attempted',
        ]);

        return response()->json([
            'ok'      => true,
            'message' => 'UDISE synchronization decision reset.',
        ]);
    }
}
