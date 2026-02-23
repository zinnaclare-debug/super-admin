<?php

return [
    'definitions' => [
        // GENERAL
        [ 'key' => 'attendance', 'label' => 'Attendance', 'category' => 'general' ],
        [ 'key' => 'results', 'label' => 'Results', 'category' => 'general' ],
        [ 'key' => 'profile', 'label' => 'Student Profiles', 'category' => 'general' ],
        [ 'key' => 'subjects', 'label' => 'Subjects', 'category' => 'general' ],
        [ 'key' => 'topics', 'label' => 'Topics', 'category' => 'general' ],
        [ 'key' => 'e-library', 'label' => 'E-Library', 'category' => 'general' ],
        [ 'key' => 'class activities', 'label' => 'Class Activities', 'category' => 'general' ],
        [ 'key' => 'cbt', 'label' => 'CBT', 'category' => 'general' ],
        [ 'key' => 'virtual class', 'label' => 'Virtual Class', 'category' => 'general' ],
        [ 'key' => 'question bank', 'label' => 'Question Bank', 'category' => 'general' ],
        [ 'key' => 'behaviour rating', 'label' => 'Behaviour Rating', 'category' => 'general' ],
        [ 'key' => 'school fees', 'label' => 'School Fees', 'category' => 'general' ],

        // SCHOOL ADMIN
        [ 'key' => 'register', 'label' => 'Register', 'category' => 'admin' ],
        [ 'key' => 'users', 'label' => 'Users', 'category' => 'admin' ],
        [ 'key' => 'academics', 'label' => 'Academics', 'category' => 'admin' ],
        [ 'key' => 'academic_session', 'label' => 'Academic Session', 'category' => 'admin' ],
        [ 'key' => 'promotion', 'label' => 'Promotion', 'category' => 'admin' ],
        [ 'key' => 'broadsheet', 'label' => 'Broadsheet', 'category' => 'admin' ],
        [ 'key' => 'transcript', 'label' => 'Transcript', 'category' => 'admin' ],
        [ 'key' => 'teacher_report', 'label' => 'Teacher Report', 'category' => 'admin' ],
        [ 'key' => 'student_report', 'label' => 'Student Report', 'category' => 'admin' ],
        [ 'key' => 'announcements', 'label' => 'Announcement Desk', 'category' => 'admin' ],
    ],

    // Legacy synonyms (map legacy key => canonical key)
    'legacy_map' => [
        'elearning' => 'e-library',
        'announcement' => 'announcements',
        'announcements' => 'announcements',
        'result' => 'results',
        'fees' => 'school fees',
        'exam' => 'results',
        'exams' => 'results',
    ],
];
