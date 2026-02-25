<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\TenantContextController;

use App\Http\Controllers\Api\SuperAdmin\SchoolController;
use App\Http\Controllers\Api\SuperAdmin\SchoolFeatureController;
use App\Http\Controllers\Api\SuperAdmin\DashboardController;
use App\Http\Controllers\Api\SuperAdmin\UserController;
use App\Http\Controllers\Api\SuperAdmin\LoginDetailsController as SuperAdminLoginDetailsController;
use App\Http\Controllers\Api\SuperAdmin\PaymentsController as SuperAdminPaymentsController;

use App\Http\Controllers\Api\SchoolAdmin\RegistrationController;
use App\Http\Controllers\Api\SchoolAdmin\UserManagementController;
use App\Http\Controllers\Api\SchoolAdmin\LoginDetailsController;
use App\Http\Controllers\Api\SchoolAdmin\AcademicSessionController;
use App\Http\Controllers\Api\SchoolAdmin\AcademicStructureController;
use App\Http\Controllers\Api\SchoolAdmin\DashboardController as SchoolAdminDashboardController;
use App\Http\Controllers\Api\School\FeatureAccessController;
use App\Http\Controllers\Api\SchoolAdmin\ClassManagementController;
use App\Http\Controllers\Api\SchoolAdmin\EnrollmentController;
use App\Http\Controllers\Api\SchoolAdmin\AcademicsController;
use App\Http\Controllers\Api\SchoolAdmin\PaymentsController as SchoolAdminPaymentsController;
use App\Http\Controllers\Api\SchoolAdmin\PromotionController;
use App\Http\Controllers\Api\SchoolAdmin\ReportsController;
use App\Http\Controllers\Api\SchoolAdmin\TranscriptController;
use App\Http\Controllers\Api\SchoolAdmin\AnnouncementController as SchoolAdminAnnouncementController;

use App\Http\Controllers\Api\Staff\TeacherResultsController;
use App\Http\Controllers\Api\Staff\StaffProfileController;

use App\Http\Controllers\Api\Staff\TeacherTopicsController;
use App\Http\Controllers\Api\Staff\ELibraryController as StaffELibraryController;
use App\Http\Controllers\Api\Staff\AnnouncementController as StaffAnnouncementController;
use App\Http\Controllers\Api\Student\ELibraryController as StudentELibraryController;
use App\Http\Controllers\Api\Staff\VirtualClassesController as StaffVirtualClassesController;
use App\Http\Controllers\Api\Staff\QuestionBankController as StaffQuestionBankController;
use App\Http\Controllers\Api\Staff\CbtController as StaffCbtController;
use App\Http\Controllers\Api\Staff\AttendanceController as StaffAttendanceController;
use App\Http\Controllers\Api\Staff\BehaviourRatingController as StaffBehaviourRatingController;

use App\Http\Controllers\Api\Staff\ClassActivitiesController as StaffClassActivitiesController;
use App\Http\Controllers\Api\Student\ClassActivitiesController as StudentClassActivitiesController;
use App\Http\Controllers\Api\Student\ResultsController as StudentResultsController;
use App\Http\Controllers\Api\Student\TopicsController as StudentTopicsController;
use App\Http\Controllers\Api\Student\VirtualClassesController as StudentVirtualClassesController;
use App\Http\Controllers\Api\Student\CbtController as StudentCbtController;
use App\Http\Controllers\Api\Student\SchoolFeesController as StudentSchoolFeesController;
use App\Http\Controllers\Api\Student\AnnouncementController as StudentAnnouncementController;



/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/tenant/context', [TenantContextController::class, 'show']);

/*
|--------------------------------------------------------------------------
| SUPER ADMIN ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:super_admin'])->group(function () {

    Route::get('/super-admin/dashboard', [DashboardController::class, 'index']);
    Route::get('/super-admin/stats', [DashboardController::class, 'stats']);

    Route::get('/super-admin/users', [UserController::class, 'index']);
    Route::post('/super-admin/users', [UserController::class, 'store']);
    Route::post('/super-admin/users/{user}/reset-password', [UserController::class, 'resetSchoolAdminPassword']);
    Route::get('/super-admin/users/login-details', [SuperAdminLoginDetailsController::class, 'index']);
    Route::get('/super-admin/users/login-details/download', [SuperAdminLoginDetailsController::class, 'download']);
    Route::get('/super-admin/schools/{school}/students-by-level', [UserController::class, 'studentsByLevel']);

    Route::get('/super-admin/schools', [SchoolController::class, 'index']);
    Route::post('/super-admin/schools', [SchoolController::class, 'store']);
    Route::put('/super-admin/schools/{school}', [SchoolController::class, 'update']);
    Route::delete('/super-admin/schools/{school}', [SchoolController::class, 'destroy']);
    Route::patch('/super-admin/schools/{school}/toggle', [SchoolController::class, 'toggle']);
    Route::patch('/super-admin/schools/{school}/toggle-results', [SchoolController::class, 'toggleResultsPublish']);
    Route::get('/super-admin/schools/{school}/academic-sessions', [SchoolController::class, 'academicSessions']);
    Route::patch('/super-admin/schools/{school}/academic-sessions/{session}/status', [SchoolController::class, 'updateAcademicSessionStatus']);
    Route::delete('/super-admin/schools/{school}/academic-sessions/{session}', [SchoolController::class, 'destroyAcademicSession']);

    Route::post('/super-admin/schools/create-with-admin', [SchoolController::class, 'createWithAdmin']);
    Route::post('/super-admin/schools/{school}/assign-admin', [SchoolController::class, 'assignAdmin']);

    Route::get('/super-admin/schools/{school}/features', [SchoolFeatureController::class, 'index']);
    Route::post('/super-admin/schools/{school}/features/toggle', [SchoolFeatureController::class, 'toggle']);

    Route::get('/super-admin/payments/schools', [SuperAdminPaymentsController::class, 'schools']);
    Route::get('/super-admin/payments', [SuperAdminPaymentsController::class, 'index']);
});

/*
|--------------------------------------------------------------------------
| SCHOOL ADMIN ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'role:school_admin'])->group(function () {
    Route::get('/school-admin/stats', [SchoolAdminDashboardController::class, 'stats']);
    Route::post('/school-admin/logo', [SchoolAdminDashboardController::class, 'uploadLogo']);
    Route::post('/school-admin/branding', [SchoolAdminDashboardController::class, 'upsertBranding']);
    Route::get('/school-admin/exam-record', [SchoolAdminDashboardController::class, 'examRecord']);
    Route::put('/school-admin/exam-record', [SchoolAdminDashboardController::class, 'upsertExamRecord']);
    Route::get('/school-admin/department-templates', [SchoolAdminDashboardController::class, 'departmentTemplates']);
    Route::post('/school-admin/department-templates', [SchoolAdminDashboardController::class, 'storeDepartmentTemplate']);
    Route::patch('/school-admin/department-templates', [SchoolAdminDashboardController::class, 'updateDepartmentTemplate']);
    Route::delete('/school-admin/department-templates', [SchoolAdminDashboardController::class, 'deleteDepartmentTemplate']);
    Route::get('/school-admin/class-templates', [SchoolAdminDashboardController::class, 'classTemplates']);
    Route::put('/school-admin/class-templates', [SchoolAdminDashboardController::class, 'upsertClassTemplates']);

    // ✅ School features (school admin only)
    Route::get('/schools/features', [SchoolFeatureController::class, 'index']);
    Route::post('/schools/features/toggle', [SchoolFeatureController::class, 'toggle']);

    Route::get('/school-admin/payments/config', [SchoolAdminPaymentsController::class, 'config'])
        ->middleware('feature:school fees');
    Route::put('/school-admin/payments/config', [SchoolAdminPaymentsController::class, 'upsertConfig'])
        ->middleware('feature:school fees');
    Route::get('/school-admin/payments', [SchoolAdminPaymentsController::class, 'index'])
        ->middleware('feature:school fees');

    Route::get('/school-admin/announcements', [SchoolAdminAnnouncementController::class, 'index'])
        ->middleware('feature:announcements');
    Route::post('/school-admin/announcements', [SchoolAdminAnnouncementController::class, 'store'])
        ->middleware('feature:announcements');
    Route::patch('/school-admin/announcements/{announcement}', [SchoolAdminAnnouncementController::class, 'update'])
        ->middleware('feature:announcements');
    Route::delete('/school-admin/announcements/{announcement}', [SchoolAdminAnnouncementController::class, 'destroy'])
        ->middleware('feature:announcements');

    // ✅ Registration
    Route::post('/school-admin/register/preview', [RegistrationController::class, 'preview'])
        ->middleware('feature:register');

    Route::post('/school-admin/register/confirm', [RegistrationController::class, 'confirm'])
        ->middleware('feature:register');

    Route::post('/school-admin/register', [RegistrationController::class, 'register'])
        ->middleware('feature:register');

    // ✅ Users management
    Route::get('/school-admin/users', [UserManagementController::class, 'index'])
        ->middleware('feature:users');

    Route::get('/school-admin/users/login-details', [LoginDetailsController::class, 'index'])
        ->middleware('feature:users');
    Route::get('/school-admin/users/login-details/download', [LoginDetailsController::class, 'download'])
        ->middleware('feature:users');

    Route::get('/school-admin/users/{user}', [UserManagementController::class, 'show'])
        ->middleware('feature:users');

    Route::get('/school-admin/users/{user}/edit-data', [UserManagementController::class, 'editData'])
        ->middleware('feature:users');

    Route::post('/school-admin/users/{user}/update', [UserManagementController::class, 'update'])
        ->middleware('feature:users');

    Route::patch('/school-admin/users/{user}/toggle', [UserManagementController::class, 'toggle'])
        ->middleware('feature:users');
    Route::post('/school-admin/users/{user}/reset-password', [UserManagementController::class, 'resetPassword'])
        ->middleware('feature:users');

    // ✅ Academic Sessions
    Route::get('/school-admin/academic-sessions', [AcademicSessionController::class, 'index'])
        ->middleware('feature:academic_session');

    Route::post('/school-admin/academic-sessions', [AcademicSessionController::class, 'store'])
        ->middleware('feature:academic_session');

    Route::put('/school-admin/academic-sessions/{session}', [AcademicSessionController::class, 'update'])
        ->middleware('feature:academic_session');


    // ✅ Your controller uses setStatus() so keep this name

    // ✅ Academic Structure (Details page + Departments per level)
    Route::get('/school-admin/academic-sessions/{session}/details', [AcademicStructureController::class, 'details'])
        ->middleware('feature:academic_session');

    Route::post('/school-admin/academic-sessions/{session}/level-departments', [AcademicStructureController::class, 'createLevelDepartment'])
        ->middleware('feature:academic_session');
    Route::patch('/school-admin/academic-sessions/{session}/level-departments/{department}', [AcademicStructureController::class, 'updateLevelDepartment'])
        ->middleware('feature:academic_session');
    Route::delete('/school-admin/academic-sessions/{session}/level-departments/{department}', [AcademicStructureController::class, 'deleteLevelDepartment'])
        ->middleware('feature:academic_session');

    // ✅ Class Terms page
    Route::get('/school-admin/classes/{class}/terms', [AcademicStructureController::class, 'classTerms'])
        ->middleware('feature:academic_session');

    Route::put('/school-admin/terms/{term}', [AcademicStructureController::class, 'updateTerm'])
        ->middleware('feature:academic_session');

    Route::patch('/school-admin/terms/{term}/set-current', [AcademicStructureController::class, 'setCurrentTerm'])
        ->middleware('feature:academic_session');

    Route::delete('/school-admin/terms/{term}', [AcademicStructureController::class, 'deleteTerm'])
        ->middleware('feature:academic_session');

    Route::get('/school-admin/promotion/classes', [PromotionController::class, 'classes'])
        ->middleware('feature:academic_session');
    Route::get('/school-admin/promotion/classes/{class}/students', [PromotionController::class, 'classStudents'])
        ->middleware('feature:academic_session');
    Route::post('/school-admin/promotion/classes/{class}/students/{student}/promote', [PromotionController::class, 'promote'])
        ->middleware('feature:academic_session');

    Route::get('/school-admin/reports/teacher', [ReportsController::class, 'teacher'])
        ->middleware('feature:teacher_report');
    Route::get('/school-admin/reports/teacher/download', [ReportsController::class, 'teacherDownload'])
        ->middleware('feature:teacher_report');
    Route::get('/school-admin/reports/student', [ReportsController::class, 'student'])
        ->middleware('feature:student_report');
    Route::get('/school-admin/reports/student/download', [ReportsController::class, 'studentDownload'])
        ->middleware('feature:student_report');
    Route::get('/school-admin/reports/student-result/options', [ReportsController::class, 'studentResultOptions'])
        ->middleware('feature:student_result');
    Route::get('/school-admin/reports/student-result', [ReportsController::class, 'studentResult'])
        ->middleware('feature:student_result');
    Route::get('/school-admin/reports/student-result/download', [ReportsController::class, 'studentResultDownload'])
        ->middleware('feature:student_result');
    Route::get('/school-admin/reports/broadsheet/options', [ReportsController::class, 'broadsheetOptions'])
        ->middleware('feature:broadsheet');
    Route::get('/school-admin/reports/broadsheet', [ReportsController::class, 'broadsheet'])
        ->middleware('feature:broadsheet');
    Route::get('/school-admin/reports/broadsheet/download', [ReportsController::class, 'broadsheetDownload'])
        ->middleware('feature:broadsheet');
    Route::get('/school-admin/transcript/options', [TranscriptController::class, 'options'])
        ->middleware('feature:transcript');
    Route::get('/school-admin/transcript', [TranscriptController::class, 'show'])
        ->middleware('feature:transcript');
    Route::get('/school-admin/transcript/download', [TranscriptController::class, 'download'])
        ->middleware('feature:transcript');

       
    Route::get('/school-admin/classes/{class}/eligible-teachers', [ClassManagementController::class, 'eligibleTeachers']);
    Route::patch('/school-admin/classes/{class}/assign-teacher', [ClassManagementController::class, 'assignTeacher']);
    Route::patch('/school-admin/classes/{class}/unassign-teacher', [ClassManagementController::class, 'unassignTeacher']);

    Route::get('/school-admin/classes/{class}/students', [ClassManagementController::class, 'listStudents']);
    Route::post('/school-admin/classes/{class}/enroll', [ClassManagementController::class, 'enroll']);

    Route::get('/school-admin/classes/{class}/terms/{term}/courses', [ClassManagementController::class, 'termCourses']);

    // Enroll students in term (bulk or single)
    Route::post(
        '/school-admin/classes/{class}/terms/{term}/enroll/bulk',
        [EnrollmentController::class, 'bulkEnroll']
    )->middleware(['feature:academics']);

    // Legacy endpoint for bulk-enroll (backward compat)
    Route::post(
        '/school-admin/classes/{class}/terms/{term}/bulk-enroll',
        [EnrollmentController::class, 'bulkEnroll']
    )->middleware(['feature:academics']);

    // List enrolled students for a class+term
    Route::get('/school-admin/classes/{class}/terms/{term}/students', [EnrollmentController::class, 'listEnrolled'])
        ->middleware(['feature:academics']);

    // List departments available for class+term enrollment
    Route::get('/school-admin/classes/{class}/terms/{term}/departments', [EnrollmentController::class, 'classDepartments'])
        ->middleware(['feature:academics']);

    // Unenroll students from class+term
    Route::delete('/school-admin/classes/{class}/terms/{term}/enrollments/bulk', [EnrollmentController::class, 'bulkUnenroll'])
        ->middleware(['feature:academics']);
 

    Route::get('/school-admin/academics', [AcademicsController::class, 'index'])
        ->middleware('feature:academics');

    Route::get('/school-admin/classes/{class}/terms/{term}/subjects', [AcademicsController::class, 'termSubjects'])
        ->middleware('feature:academics');

    Route::post('/school-admin/classes/{class}/subjects', [AcademicsController::class, 'createSubjects'])
        ->middleware('feature:academics');

    Route::patch('/school-admin/subjects/{subject}', [AcademicsController::class, 'updateSubject'])
        ->middleware('feature:academics');

    Route::get('/school-admin/classes/{class}/terms/{term}/subjects/{subject}/cbt-exams', [AcademicsController::class, 'cbtExamsForSubject'])
        ->middleware('feature:academics');

    Route::patch('/school-admin/cbt/exams/{exam}/publish', [AcademicsController::class, 'publishCbtExam'])
        ->middleware('feature:academics');

    // ✅ Alias for termCourses (courses = subjects)
    Route::get('/school-admin/classes/{class}/terms/{term}/courses', [AcademicsController::class, 'termCourses'])
        ->middleware('feature:academics');

        Route::get('/school-admin/classes/{class}/terms/{term}/courses', [AcademicsController::class, 'termCourses']);

// Assign teacher to subject in a term
Route::patch(
    '/school-admin/classes/{class}/terms/{term}/subjects/{subject}/assign-teacher',
    [AcademicsController::class, 'assignTeacherToSubject']
)->middleware('feature:academics');

// Unassign teacher from subject in a term
Route::patch(
    '/school-admin/classes/{class}/terms/{term}/subjects/{subject}/unassign-teacher',
    [AcademicsController::class, 'unassignTeacherFromSubject']
)->middleware('feature:academics');




});

/*
|--------------------------------------------------------------------------
| STAFF + STUDENT ROUTES
|--------------------------------------------------------------------------
*/

// staff
Route::middleware(['auth:sanctum', 'role:staff'])->group(function () {
    Route::get('/staff/dashboard', fn () => response()->json(['message' => 'Staff Dashboard']));
    Route::get('/staff/announcements', [StaffAnnouncementController::class, 'index'])
        ->middleware('feature:announcements');
    Route::get('/staff/profile', [StaffProfileController::class, 'show']);
    Route::get('/staff/profile/photo', [StaffProfileController::class, 'photo']);
    Route::post('/staff/profile/photo', [StaffProfileController::class, 'uploadPhoto']);
    Route::get('/staff/features', [FeatureAccessController::class, 'staffFeatures']);
    Route::get('/staff/results/subjects', [TeacherResultsController::class, 'mySubjects']);
    Route::get('/staff/results/subjects/{termSubject}/students', [TeacherResultsController::class, 'subjectStudents']);
    Route::post('/staff/results/subjects/{termSubject}/scores', [TeacherResultsController::class, 'saveScores']);
    Route::get('/staff/attendance/status', [StaffAttendanceController::class, 'status']);
    Route::get('/staff/attendance', [StaffAttendanceController::class, 'index']);
    Route::post('/staff/attendance', [StaffAttendanceController::class, 'save']);
    Route::get('/staff/behaviour-rating/status', [StaffBehaviourRatingController::class, 'status']);
    Route::get('/staff/behaviour-rating', [StaffBehaviourRatingController::class, 'index']);
    Route::post('/staff/behaviour-rating', [StaffBehaviourRatingController::class, 'save']);
    
    Route::get('/staff/topics/subjects', [TeacherTopicsController::class, 'myAssignedSubjects']);
    Route::get('/staff/topics/subjects/{termSubject}/materials', [TeacherTopicsController::class, 'materials']);
    Route::post('/staff/topics/subjects/{termSubject}/materials', [TeacherTopicsController::class, 'upload']);
    Route::delete('/staff/topics/subjects/{termSubject}/materials/{material}', [TeacherTopicsController::class, 'destroy']);
    Route::get('/staff/e-library/assigned-subjects', [StaffELibraryController::class, 'assignedSubjects']);
    Route::get('/staff/e-library', [StaffELibraryController::class, 'myUploads']);
    Route::post('/staff/e-library', [StaffELibraryController::class, 'upload']);
    Route::delete('/staff/e-library/{book}', [StaffELibraryController::class, 'destroy']);
    Route::get('/staff/virtual-classes/subjects', [StaffVirtualClassesController::class, 'myAssignedSubjects']);
    Route::get('/staff/virtual-classes/assigned-subjects', [StaffVirtualClassesController::class, 'assignedSubjects']);
    Route::get('/staff/virtual-classes', [StaffVirtualClassesController::class, 'index']);
    Route::post('/staff/virtual-classes', [StaffVirtualClassesController::class, 'store']);
    Route::delete('/staff/virtual-classes/{virtualClass}', [StaffVirtualClassesController::class, 'destroy']);
    Route::get('/staff/question-bank/subjects', [StaffQuestionBankController::class, 'subjects']);
    Route::get('/staff/question-bank', [StaffQuestionBankController::class, 'index']);
    Route::post('/staff/question-bank', [StaffQuestionBankController::class, 'store']);
    Route::post('/staff/question-bank/ai-generate', [StaffQuestionBankController::class, 'aiGenerate']);
    Route::delete('/staff/question-bank/{question}', [StaffQuestionBankController::class, 'destroy']);
    Route::get('/staff/cbt/subjects', [StaffCbtController::class, 'subjects']);
    Route::get('/staff/cbt/exams', [StaffCbtController::class, 'index']);
    Route::post('/staff/cbt/exams', [StaffCbtController::class, 'store']);
    Route::get('/staff/cbt/exams/{exam}/questions', [StaffCbtController::class, 'examQuestions']);
    Route::post('/staff/cbt/exams/{exam}/export-question-bank', [StaffCbtController::class, 'exportFromQuestionBank']);
    Route::delete('/staff/cbt/exams/{exam}', [StaffCbtController::class, 'destroy']);

    Route::get('/staff/class-activities/subjects', [StaffClassActivitiesController::class, 'myAssignedSubjects']);
    Route::get('/staff/class-activities/assigned-subjects', [StaffClassActivitiesController::class, 'assignedSubjects']);
    Route::get('/staff/class-activities', [StaffClassActivitiesController::class, 'index']);
    Route::post('/staff/class-activities', [StaffClassActivitiesController::class, 'upload']);
    Route::delete('/staff/class-activities/{activity}', [StaffClassActivitiesController::class, 'destroy']);
    Route::get('/staff/class-activities/{activity}/download', [StaffClassActivitiesController::class, 'download']);






    });




// student
Route::middleware(['auth:sanctum', 'role:student'])->get('/student/dashboard',
    fn () => response()->json(['message' => 'Student Dashboard'])
);


Route::middleware(['auth:sanctum', 'role:student'])->group(function () {
    Route::get('/student/announcements', [StudentAnnouncementController::class, 'index'])
        ->middleware('feature:announcements');
    Route::get('/student/features', [FeatureAccessController::class, 'studentFeatures']);
    Route::get('/student/profile', [\App\Http\Controllers\Api\Student\ProfileController::class, 'me']);
    Route::get('/student/school-fees', [StudentSchoolFeesController::class, 'index'])
        ->middleware('feature:school fees');
    Route::post('/student/school-fees/initialize', [StudentSchoolFeesController::class, 'initialize'])
        ->middleware('feature:school fees');
    Route::get('/student/school-fees/verify', [StudentSchoolFeesController::class, 'verify'])
        ->middleware('feature:school fees');
    Route::get('/student/results/classes', [StudentResultsController::class, 'classes']);
    Route::get('/student/results', [StudentResultsController::class, 'index']);
    Route::get('/student/results/download', [StudentResultsController::class, 'download']);
    Route::get('/student/topics/subjects', [StudentTopicsController::class, 'mySubjects']);
    Route::get('/student/topics', [StudentTopicsController::class, 'index']);
    Route::get('/student/e-library/subjects', [StudentELibraryController::class, 'mySubjects']);
    Route::get('/student/virtual-classes/subjects', [StudentVirtualClassesController::class, 'mySubjects']);
    Route::get('/student/virtual-classes', [StudentVirtualClassesController::class, 'index']);
    Route::get('/student/cbt/subjects', [StudentCbtController::class, 'subjects']);
    Route::get('/student/cbt/exams', [StudentCbtController::class, 'exams']);
    Route::get('/student/cbt/exams/{exam}/questions', [StudentCbtController::class, 'questions']);
    Route::get('/student/class-activities/subjects', [StudentClassActivitiesController::class, 'mySubjects']);
    Route::get('/student/class-activities', [StudentClassActivitiesController::class, 'index']);
    Route::get('/student/class-activities/{activity}/download', [StudentClassActivitiesController::class, 'download']);
    Route::get('/student/e-library', [StudentELibraryController::class, 'index']);
});
