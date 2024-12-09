<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\User;
use App\Models\Student;

class DashboardController extends Controller
{
    public function getData()
    {
        $campusesCount = Campus::count();
        $usersCount = User::count();
        $studentsCount = Student::count();

        return response()->json([
            'campuses' => $campusesCount,
            'users' => $usersCount,
            'students' => $studentsCount,
        ]);
    }
} 