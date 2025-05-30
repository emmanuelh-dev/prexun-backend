<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade para acceder fácilmente a los servicios de Moodle
 * 
 * @method static \App\Services\Moodle\MoodleUserService users()
 * @method static \App\Services\Moodle\MoodleCohortService cohorts()
 * @method static array getUserByUsername(string $username)
 * @method static array createUser(array $users)
 * @method static array updateUser(array $users)
 * @method static array deleteUser($userId)
 * @method static int|null getCohortIdByName(string $cohortName)
 * @method static array createCohorts(array $data)
 * @method static array updateCohorts(array $data)
 * @method static array addUserToCohort($members)
 * @method static array removeUserFromCohort($userId, $cohortId)
 * @method static array removeUsersFromCohorts(array $members)
 * @method static array getUserCohorts($userId)
 * @method static array removeUserFromAllCohorts(string $username)
 */
class Moodle extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'moodle';
    }
}
