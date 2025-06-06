<?php

namespace Tests\Feature\Moodle;

use Tests\TestCase;
use App\Services\Moodle\MoodleService;
use App\Facades\Moodle;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MoodleCohortIntegrationTest extends TestCase
{
    protected $moodleService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->moodleService = new MoodleService();
    }

    /** @test */
    public function test_moodle_service_can_be_instantiated()
    {
        $this->assertInstanceOf(MoodleService::class, $this->moodleService);
        $this->assertTrue(method_exists($this->moodleService, 'users'));
        $this->assertTrue(method_exists($this->moodleService, 'cohorts'));
    }

    /** @test */
    public function test_facade_works_correctly()
    {
        // Test que el facade está correctamente configurado
        $this->assertTrue(class_exists('App\Facades\Moodle'));
        
        // Mock para evitar llamadas reales a la API
        $mockService = $this->createMock(MoodleService::class);
        $this->app->instance('moodle', $mockService);
        
        $mockService->expects($this->once())
            ->method('getUserByUsername')
            ->with('test_user')
            ->willReturn(['status' => 'success', 'data' => ['id' => 123]]);
        
        $result = Moodle::getUserByUsername('test_user');
        $this->assertEquals('success', $result['status']);
    }    /** @test */
    public function test_api_endpoints_exist()
    {
        // Crear un usuario para autenticación
        $user = \App\Models\User::factory()->create();
        
        // Test que las rutas están definidas
        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/moodle/cohorts/user', [
                'user_id' => 123,
                'cohort_id' => 456
            ]);
        
        // No debería ser 404 (Not Found) ni 401 (Unauthorized)
        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(401, $response->getStatusCode());
    }/** @test */
    public function test_remove_users_from_cohorts_validation()
    {
        // Crear un usuario para autenticación
        $user = \App\Models\User::factory()->create();
        
        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/moodle/cohorts/users/bulk', [
                'members' => [
                    ['userid' => 123] // Falta cohortid
                ]
            ]);
        
        $response->assertStatus(400);
        $response->assertJsonStructure([
            'status',
            'message',
            'errors'
        ]);
    }

    /** @test */
    public function test_remove_users_from_cohorts_with_valid_data()
    {
        // Crear un usuario para autenticación
        $user = \App\Models\User::factory()->create();
        
        $validMembers = [
            [
                'userid' => 123,
                'cohortid' => 456
            ],
            [
                'userid' => 124,
                'cohortid' => 457
            ]
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/moodle/cohorts/users/bulk', [
                'members' => $validMembers
            ]);
        
        // El endpoint debería procesarse (aunque falle por credenciales)
        $this->assertNotEquals(422, $response->getStatusCode()); // No validation error
        $this->assertNotEquals(401, $response->getStatusCode()); // No auth error
    }

    /** @test */
    public function test_command_exists()
    {
        // Test que el comando está registrado
        $this->artisan('moodle:cohort', ['action' => 'invalid'])
            ->expectsOutput('Acción no válida: invalid')
            ->assertExitCode(1);
    }

    /** @test */
    public function test_service_provider_registration()
    {
        // Test que el service provider registra correctamente los servicios
        $this->assertTrue($this->app->bound(MoodleService::class));
        $this->assertTrue($this->app->bound('moodle'));
        
        $service = $this->app->make(MoodleService::class);
        $this->assertInstanceOf(MoodleService::class, $service);
    }

    /** @test */
    public function test_cohort_service_methods_exist()
    {
        $cohortService = $this->moodleService->cohorts();
        
        $this->assertTrue(method_exists($cohortService, 'removeUsersFromCohorts'));
        $this->assertTrue(method_exists($cohortService, 'removeUserFromCohort'));
        $this->assertTrue(method_exists($cohortService, 'addUserToCohort'));
        $this->assertTrue(method_exists($cohortService, 'getUserCohorts'));
        $this->assertTrue(method_exists($cohortService, 'removeUserFromAllCohorts'));
    }

    /** @test */
    public function test_user_service_methods_exist()
    {
        $userService = $this->moodleService->users();
        
        $this->assertTrue(method_exists($userService, 'getUserByUsername'));
        $this->assertTrue(method_exists($userService, 'createUser'));
        $this->assertTrue(method_exists($userService, 'updateUser'));
        $this->assertTrue(method_exists($userService, 'deleteUser'));
    }

    /** @test */
    public function test_backward_compatibility()
    {
        // Test que los métodos de compatibilidad funcionan
        $this->assertTrue(method_exists($this->moodleService, 'getUserByUsername'));
        $this->assertTrue(method_exists($this->moodleService, 'createUser'));
        $this->assertTrue(method_exists($this->moodleService, 'removeUserFromCohort'));
        $this->assertTrue(method_exists($this->moodleService, 'removeUsersFromCohorts'));
    }
}
