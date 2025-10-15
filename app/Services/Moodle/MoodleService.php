<?php

namespace App\Services\Moodle;

use App\Services\Moodle\MoodleUserService;
use App\Services\Moodle\MoodleCohortService;
use App\Services\Moodle\MoodleCourseService;
use App\Services\Moodle\MoodleGradeService;

class MoodleService
{
    protected MoodleUserService $userService;
    protected MoodleCohortService $cohortService;
    protected MoodleCourseService $courseService;
    protected MoodleGradeService $gradeService;

    public function __construct()
    {
        $this->userService = new MoodleUserService();
        $this->cohortService = new MoodleCohortService();
        $this->courseService = new MoodleCourseService();
        $this->gradeService = new MoodleGradeService();
    }

    /**
     * Obtener el servicio de usuarios.
     */
    public function users(): MoodleUserService
    {
        return $this->userService;
    }

    /**
     * Obtener el servicio de cohorts.
     */
    public function cohorts(): MoodleCohortService
    {
        return $this->cohortService;
    }

    /**
     * Obtener el servicio de cursos.
     */
    public function courses(): MoodleCourseService
    {
        return $this->courseService;
    }

    /**
     * Obtener el servicio de calificaciones.
     */
    public function grades(): MoodleGradeService
    {
        return $this->gradeService;
    }

    /**
     * Métodos de conveniencia para mantener compatibilidad
     */
    public function getUserByUsername($username)
    {
        return $this->userService->getUserByUsername($username);
    }

    public function createUser(array $users)
    {
        return $this->userService->createUser($users);
    }

    public function updateUser(array $users)
    {
        return $this->userService->updateUser($users);
    }

    public function suspendUser(array $users)
    {
        return $this->userService->suspendUser($users);
    }

    public function deleteUser($userId)
    {
        return $this->userService->deleteUser($userId);
    }

    public function getCohortIdByName($cohortName)
    {
        return $this->cohortService->getCohortIdByName($cohortName);
    }

    public function createCohorts(array $data)
    {
        return $this->cohortService->createCohorts($data);
    }

    public function updateCohorts(array $data)
    {
        return $this->cohortService->updateCohorts($data);
    }

    public function addUserToCohort($members)
    {
        return $this->cohortService->addUserToCohort($members);
    }

    public function removeUserFromCohort($userId, $cohortId)
    {
        return $this->cohortService->removeUserFromCohort($userId, $cohortId);
    }

    public function removeUsersFromCohorts(array $members)
    {
        return $this->cohortService->removeUsersFromCohorts($members);
    }

    public function getUserCohorts($userId)
    {
        return $this->cohortService->getUserCohorts($userId);
    }

    public function removeUserFromAllCohorts($username)
    {
        return $this->cohortService->removeUserFromAllCohorts($username);
    }
}
