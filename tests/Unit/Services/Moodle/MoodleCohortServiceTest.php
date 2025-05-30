<?php

namespace Tests\Unit\Services\Moodle;

use Tests\TestCase;
use App\Services\Moodle\MoodleCohortService;
use App\Services\Moodle\MoodleUserService;
use Illuminate\Support\Facades\Log;

class MoodleCohortServiceTest extends TestCase
{
    protected $cohortService;
    protected $userService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cohortService = new MoodleCohortService();
        $this->userService = new MoodleUserService();
    }

    /** @test */
    public function test_remove_users_from_cohorts_validates_input()
    {
        // Test con datos inválidos (falta cohortid)
        $invalidMembers = [
            ['userid' => 123] // Falta cohortid
        ];

        $result = $this->cohortService->removeUsersFromCohorts($invalidMembers);

        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('userid y cohortid', $result['message']);
    }

    /** @test */
    public function test_remove_users_from_cohorts_validates_complete_input()
    {
        // Test con datos válidos
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

        // Mock del método sendRequest para evitar llamadas reales a la API
        $mockService = $this->getMockBuilder(MoodleCohortService::class)
            ->onlyMethods(['sendRequest'])
            ->getMock();

        $mockService->expects($this->once())
            ->method('sendRequest')
            ->with('core_cohort_delete_cohort_members', [
                'members' => $validMembers
            ])
            ->willReturn([
                'status' => 'success',
                'data' => []
            ]);

        $result = $mockService->removeUsersFromCohorts($validMembers);

        $this->assertEquals('success', $result['status']);
    }

    /** @test */
    public function test_service_methods_exist()
    {
        $this->assertTrue(method_exists($this->cohortService, 'removeUsersFromCohorts'));
        $this->assertTrue(method_exists($this->cohortService, 'removeUserFromCohort'));
        $this->assertTrue(method_exists($this->cohortService, 'addUserToCohort'));
        $this->assertTrue(method_exists($this->cohortService, 'getUserCohorts'));
        $this->assertTrue(method_exists($this->cohortService, 'removeUserFromAllCohorts'));
    }
}
