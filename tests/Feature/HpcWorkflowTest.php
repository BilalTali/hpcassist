<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HpcWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function createSchoolUser(): array
    {
        $user = User::factory()->create([
            'name' => 'Govt Middle School Test',
            'email' => 'principal.test@example.com',
        ]);

        $school = School::create([
            'user_id' => $user->id,
            'name' => 'Govt Middle School Test',
            'udise_code' => '01041800999',
            'principal_name' => 'Principal Test',
            'phone' => '9419000111',
            'district' => 'Pulwama',
            'state' => 'Jammu & Kashmir',
            'udise_status' => 'not_attempted',
            'is_active' => true,
        ]);

        $teacher = Teacher::create([
            'school_id' => $school->id,
            'name' => 'Lead Teacher',
            'designation' => 'Master',
            'classes' => 'Grade 6, Grade 7, Grade 8',
            'is_active' => true,
        ]);

        return [$user, $school, $teacher];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');

        $responseHpc = $this->get('/dashboard/hpc');
        $responseHpc->assertRedirect('/login');

        $responseTeacher = $this->get('/dashboard/hpc/teacher');
        $responseTeacher->assertRedirect('/login');
    }

    public function test_school_user_can_view_clean_initial_dashboard_with_options(): void
    {
        [$user, $school] = $this->createSchoolUser();

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Govt Middle School Test');
        $response->assertSee('UDISE+');
        $response->assertSee('Skip UDISE');
    }

    public function test_school_can_skip_udise_decision(): void
    {
        [$user, $school] = $this->createSchoolUser();

        $response = $this->actingAs($user)->postJson('/school/udise/skip');
        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'redirect' => route('hpc.dashboard'),
        ]);

        $school->refresh();
        $this->assertEquals('skipped', $school->udise_status);

        // When visiting dashboard again, shows skipped state
        $dashResponse = $this->actingAs($user)->get('/dashboard');
        $dashResponse->assertStatus(200);
        $dashResponse->assertSee('Manual &amp; Template Mode Active', false);
    }

    public function test_school_can_reset_udise_decision(): void
    {
        [$user, $school] = $this->createSchoolUser();
        $school->update(['udise_status' => 'skipped']);

        $response = $this->actingAs($user)->postJson('/school/udise/reset');
        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);

        $school->refresh();
        $this->assertEquals('not_attempted', $school->udise_status);
    }

    public function test_school_can_update_profile(): void
    {
        [$user, $school] = $this->createSchoolUser();

        $response = $this->actingAs($user)->postJson('/school/update', [
            'name' => 'Updated School Name',
            'udise_code' => '01041800888',
            'principal_name' => 'New Principal',
            'phone' => '9419123456',
            'district' => 'Srinagar',
            'category' => 'High School',
            'state' => 'Jammu & Kashmir',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);

        $school->refresh();
        $this->assertEquals('Updated School Name', $school->name);
        $this->assertEquals('01041800888', $school->udise_code);
        $this->assertEquals('New Principal', $school->principal_name);
    }

    public function test_school_can_create_and_manage_teachers(): void
    {
        [$user, $school] = $this->createSchoolUser();

        // Add new teacher
        $response = $this->actingAs($user)->postJson('/school/teachers', [
            'name' => 'Assistant Teacher',
            'designation' => 'Teacher',
            'pen_code' => 'TCH999',
            'mobile' => '9876543210',
            'classes' => 'Grade 6, Grade 7',
            'email' => 'assistant@school.gov.in',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
        $this->assertDatabaseHas('teachers', [
            'name' => 'Assistant Teacher',
            'school_id' => $school->id,
        ]);

        $newTeacher = Teacher::where('name', 'Assistant Teacher')->first();

        // Update teacher
        $updateResponse = $this->actingAs($user)->postJson("/school/teachers/{$newTeacher->id}/update", [
            'name' => 'Updated Teacher Name',
            'designation' => 'Master',
            'pen_code' => 'TCH999',
            'mobile' => '9876543210',
            'classes' => 'All Middle Grades',
        ]);
        $updateResponse->assertStatus(200);
        $newTeacher->refresh();
        $this->assertEquals('Updated Teacher Name', $newTeacher->name);

        // Delete teacher
        $deleteResponse = $this->actingAs($user)->postJson("/school/teachers/{$newTeacher->id}/delete");
        $deleteResponse->assertStatus(200);
        $this->assertDatabaseMissing('teachers', ['id' => $newTeacher->id]);
    }

    public function test_template_download_routes(): void
    {
        [$user] = $this->createSchoolUser();

        $responseBlank = $this->actingAs($user)->get('/hpc/template/download');
        $responseBlank->assertStatus(200);
        $responseBlank->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $responseUdise = $this->actingAs($user)->get('/hpc/template/download/udise');
        $responseUdise->assertStatus(200);
        $responseUdise->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_student_records_save_and_delete(): void
    {
        [$user] = $this->createSchoolUser();

        // Save a new student record
        $saveResponse = $this->actingAs($user)->postJson('/hpc/students/save', [
            'index' => -1,
            'first_name' => 'AARAV',
            'last_name' => 'KUMAR',
            'grade' => 'Grade 6',
            'section' => 'A',
            'dob' => '15/08/2012',
            'email' => 'aarav.123@bms.school.gov.in',
            'apaar_id' => '123456789012',
        ]);

        $saveResponse->assertStatus(200);
        $saveResponse->assertJson(['ok' => true]);
        $this->assertGreaterThanOrEqual(1, $saveResponse->json('total'));

        // Delete the student record
        $deleteResponse = $this->actingAs($user)->postJson('/hpc/students/delete', [
            'index' => 0,
        ]);
        $deleteResponse->assertStatus(200);
        $deleteResponse->assertJson(['ok' => true]);
    }

    public function test_udise_bridge_and_poll(): void
    {
        [$user, $school] = $this->createSchoolUser();

        // Bridge page renders publicly
        $bridgeResponse = $this->get('/udise/bridge?school_id=' . $school->id);
        $bridgeResponse->assertStatus(200);

        // Poll endpoint for authenticated user
        $pollResponse = $this->actingAs($user)->getJson('/udise/ingest-poll');
        $pollResponse->assertStatus(200);
        $pollResponse->assertJson(['ok' => true, 'synced' => false]);
    }

    public function test_school_can_kill_all_sessions(): void
    {
        [$user, $school] = $this->createSchoolUser();

        $response = $this->actingAs($user)->postJson('/school/sessions/kill-all');
        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
    }
}
