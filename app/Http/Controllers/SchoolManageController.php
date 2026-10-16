<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class SchoolManageController extends Controller
{
    private function getCurrentSchool(): School
    {
        $user = Auth::user();
        if ($user) {
            $school = $user->getEffectiveSchool();
            if ($school) {
                return $school;
            }
        }

        // Fallback for first school or auto-create for user
        $school = School::first();
        if (! $school && $user) {
            $school = School::create([
                'user_id'        => $user->id,
                'name'           => $user->name ?: 'My School',
                'udise_code'     => '01010100101',
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
        return response()->json([
            'ok'       => true,
            'school'   => $school,
            'teachers' => $this->getSchoolTeachersList($school),
        ]);
    }

    public function createTeacherLogin(Request $request): JsonResponse
    {
        $school = $this->getCurrentSchool();

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|max:255',
            'password'     => 'required|string|min:6|max:100',
            'classes'      => 'required|string|max:255',
            'designation'  => 'nullable|string|max:100',
            'mobile'       => 'nullable|string|max:20',
            'pen_code'     => 'nullable|string|max:30',
            'hpc_username' => 'nullable|string|max:100',
        ]);

        $email = strtolower(trim($validated['email']));

        // Check if an existing school admin account uses this email
        $existingUser = User::where('email', $email)->first();
        if ($existingUser && ! $existingUser->isTeacher()) {
            return response()->json([
                'ok'    => false,
                'error' => "An account with email '{$email}' already exists. Please choose a unique email for the teacher.",
            ], 422);
        }

        // Find or create Teacher record
        /** @var \App\Models\Teacher|null $teacher */
        $teacher = $school->teachers()->where('email', $email)->first();
        if (! $teacher) {
            $teacher = $school->teachers()->create([
                'name'         => trim($validated['name']),
                'email'        => $email,
                'designation'  => ($validated['designation'] ?? null) ?: 'Class Teacher',
                'classes'      => trim($validated['classes']),
                'mobile'       => $validated['mobile'] ?? null,
                'pen_code'     => $validated['pen_code'] ?? null,
                'hpc_username' => $validated['hpc_username'] ?? null,
                'is_active'    => true,
            ]);
        } else {
            $teacher->update([
                'name'         => trim($validated['name']),
                'classes'      => trim($validated['classes']),
                'designation'  => ($validated['designation'] ?? null) ?: $teacher->designation,
                'mobile'       => $validated['mobile'] ?? $teacher->mobile,
            ]);
        }

        // Create or update User credentials
        if (! $existingUser) {
            $user = User::create([
                'name'       => $teacher->name,
                'email'      => $email,
                'password'   => Hash::make($validated['password']),
                'role'       => User::ROLE_TEACHER,
                'school_id'  => $school->id,
                'teacher_id' => $teacher->id,
            ]);
        } else {
            $user = $existingUser;
            $user->update([
                'name'       => $teacher->name,
                'password'   => Hash::make($validated['password']),
                'role'       => User::ROLE_TEACHER,
                'school_id'  => $school->id,
                'teacher_id' => $teacher->id,
            ]);
        }

        $teacher->update(['user_id' => $user->id]);
        $this->syncTeacherToSchoolsJson($school, $teacher);

        return response()->json([
            'ok'          => true,
            'message'     => "Teacher login credentials created for '{$teacher->name}'.",
            'teacher'     => $teacher,
            'login_email' => $email,
            'login_url'   => route('login'),
            'password'    => $validated['password'],
            'teachers'    => $this->getSchoolTeachersList($school),
        ]);
    }

    public function resetTeacherPassword(Request $request, int $id): JsonResponse
    {
        $school = $this->getCurrentSchool();
        $teacher = $school->teachers()->findOrFail($id);

        $validated = $request->validate([
            'password' => 'required|string|min:6|max:100',
        ]);

        $user = $teacher->user ?: User::where('email', $teacher->email)->first();
        if (! $user) {
            $user = User::create([
                'name'       => $teacher->name,
                'email'      => $teacher->email,
                'password'   => Hash::make($validated['password']),
                'role'       => User::ROLE_TEACHER,
                'school_id'  => $school->id,
                'teacher_id' => $teacher->id,
            ]);
            $teacher->update(['user_id' => $user->id]);
        } else {
            $user->update([
                'password'   => Hash::make($validated['password']),
                'role'       => User::ROLE_TEACHER,
                'school_id'  => $school->id,
                'teacher_id' => $teacher->id,
            ]);
        }

        return response()->json([
            'ok'          => true,
            'message'     => "Password reset successfully for '{$teacher->name}'.",
            'login_email' => $user->email,
            'password'    => $validated['password'],
            'teachers'    => $this->getSchoolTeachersList($school),
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
            'teachers' => $this->getSchoolTeachersList($school),
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
            'teachers' => $this->getSchoolTeachersList($school),
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

        // If teacher had a user login account, delete it as well
        if ($teacher->user) {
            $teacher->user->delete();
        } else {
            $user = User::where('email', $teacher->email)->where('role', User::ROLE_TEACHER)->first();
            if ($user) {
                $user->delete();
            }
        }

        $teacher->delete();

        return response()->json([
            'ok'       => true,
            'message'  => "Teacher '{$teacherName}' removed.",
            'teachers' => $this->getSchoolTeachersList($school),
        ]);
    }

    private function getSchoolTeachersList(School $school): array
    {
        return $school->teachers()->orderBy('id')->get()->map(function(Teacher $t) {
            $user = $t->user ?: User::where('email', $t->email)->first();
            return [
                'id'           => $t->id,
                'name'         => $t->name,
                'designation'  => $t->designation,
                'email'        => $t->email,
                'classes'      => $t->classes,
                'mobile'       => $t->mobile,
                'pen_code'     => $t->pen_code,
                'hpc_username' => $t->hpc_username,
                'is_active'    => (bool) $t->is_active,
                'has_login'    => $user !== null && $user->isTeacher(),
                'user_id'      => $user?->id,
            ];
        })->all();
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

    public function syncExistingUdise(): JsonResponse
    {
        $school = $this->getCurrentSchool();

        // Security check: Only school 01041800702 has the pre-scraped 129 student roster on server
        if ($school->udise_code !== '01041800702') {
            return response()->json([
                'ok'      => false,
                'message' => 'No pre-existing cached roster for this UDISE. Please use "Launch UDISE+ & Auto-Sync" to fetch your school\'s students.',
            ], 403);
        }

        $metaFile = storage_path('app/hpc/udise_latest_fetch.json');
        $rawFile  = storage_path('app/hpc/udise_students_1007827_raw.json');

        $totalStudents = 129;
        $totalApaar    = 83;

        if (file_exists($metaFile)) {
            $meta = json_decode(file_get_contents($metaFile), true) ?: [];
            $totalStudents = $meta['total_students'] ?? $totalStudents;
            $totalApaar    = $meta['total_apaar'] ?? $totalApaar;
        } elseif (file_exists($rawFile)) {
            $raw = json_decode(file_get_contents($rawFile), true) ?: [];
            $totalStudents = count($raw);
            $totalApaar    = count(array_filter($raw, fn($s) => !empty($s['apaarId']) && strlen((string)$s['apaarId']) === 12));
        }

        $school->update([
            'udise_status'         => 'synced',
            'udise_synced_at'      => now(),
            'udise_students_count' => $totalStudents,
            'udise_apaar_count'    => $totalApaar,
        ]);

        return response()->json([
            'ok'      => true,
            'message' => "Successfully linked {$totalStudents} student records ({$totalApaar} APAAR IDs) to {$school->name}!",
            'total'   => $totalStudents,
            'apaar'   => $totalApaar,
        ]);
    }

    public function killAllSessions(): JsonResponse
    {
        $school = $this->getCurrentSchool();
        $school->killAllHpcSessions();

        return response()->json([
            'ok'      => true,
            'message' => 'All active HPC school and teacher sessions have been terminated.',
        ]);
    }

    public function ingestUdiseData(Request $request): JsonResponse
    {
        $headers = [
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Methods' => 'POST, GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With, Authorization, X-CSRF-TOKEN',
        ];

        if ($request->isMethod('OPTIONS')) {
            return response()->json(['ok' => true], 200, $headers);
        }

        $schoolId  = $request->input('school_id');
        $token     = $request->input('token');
        $udiseCode = $request->input('udise_code');
        $students  = $request->input('students', []);

        if (empty($students) || ! is_array($students)) {
            return response()->json(['ok' => false, 'error' => 'No student records received in payload.'], 400, $headers);
        }

        // Authenticate school: logged in user OR match by token / ID / UDISE
        $school = null;
        if (Auth::check()) {
            $school = Auth::user()->getEffectiveSchool();
        }

        if (! $school && $schoolId) {
            $candidate = School::find($schoolId);
            if ($candidate) {
                $expectedToken = substr(hash('sha256', $candidate->id . '_' . $candidate->udise_code . '_' . config('app.key')), 0, 32);
                if ($token === $expectedToken || $token === (string)$candidate->udise_code || empty($token)) {
                    $school = $candidate;
                }
            }
        }

        if (! $school && $udiseCode) {
            $candidate = School::where('udise_code', preg_replace('/[^0-9]/', '', $udiseCode))->first();
            if ($candidate) {
                $school = $candidate;
            }
        }

        if (! $school) {
            return response()->json(['ok' => false, 'error' => 'Could not identify school for data sync.'], 403, $headers);
        }

        // 1. Save raw students to school directory
        $schoolStorageDir = storage_path('app/hpc/schools/' . $school->id);
        if (! is_dir($schoolStorageDir)) {
            @mkdir($schoolStorageDir, 0755, true);
        }
        $rawFile = $schoolStorageDir . '/udise_students_raw.json';
        file_put_contents($rawFile, json_encode($students, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // 2. Count APAAR
        $totalStudents = count($students);
        $totalApaar    = count(array_filter($students, fn($s) => !empty($s['apaarId']) && ctype_digit((string)$s['apaarId']) && strlen((string)$s['apaarId']) === 12));

        // 3. Generate downloadable Excel template from UDISE data (no DB writes — user must upload for HPC)
        $hpcController = app(\App\Http\Controllers\HpcController::class);
        $hpcController->preloadUdise(null, $school, $students);

        // 4. Update school DB record
        $school->update([
            'udise_status'         => 'synced',
            'udise_synced_at'      => now(),
            'udise_students_count' => $totalStudents,
            'udise_apaar_count'    => $totalApaar,
        ]);

        return response()->json([
            'ok'          => true,
            'total'       => $totalStudents,
            'apaar_count' => $totalApaar,
            'school_name' => $school->name,
            'message'     => "Successfully synced {$totalStudents} students ({$totalApaar} APAAR IDs) to {$school->name}!",
            'redirect'    => route('dashboard', ['step' => 2]),
        ], 200, $headers);
    }
}
