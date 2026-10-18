<?php

return [

    /*
    |--------------------------------------------------------------------------
    | HPC Student Entry Tool — Configuration
    |--------------------------------------------------------------------------
    |
    | Values are read from .env so you never need to edit this file directly.
    | Read the guide (Part A, Step 4) for why config files are the only safe
    | place to call env().
    |
    */

    // The portal page that contains the login form.
    'login_url' => env('HPC_LOGIN_URL', 'https://hpc.parakh.ncert.gov.in/en/login/SCHOOL,TEACHER'),

    // The Add Student form URL — where hpc:discover and hpc:run navigate to.
    'add_student_url' => env('HPC_ADD_STUDENT_URL', 'https://hpc.parakh.ncert.gov.in/en/students/add'),

    // Path to the Chrome profile folder, relative to the project root.
    // Storing cookies here keeps your session alive between runs.
    'profile_dir' => env('HPC_PROFILE_DIR', 'storage/app/hpc/chrome-profile'),

    // Milliseconds to pause between actions, giving the portal time to react.
    // Cast to int because every .env value arrives as a string.
    'delay_ms' => (int) env('HPC_DELAY_MS', 700),

    // Absolute path to the folder where fields.json, templates and results live.
    'storage_path' => storage_path('app/hpc'),

    // Absolute path to ChromeDriver binary.
    'chromedriver_path' => base_path('drivers/chromedriver'),

    // HPC Portal Assessment Home URL
    'hpc_home_url' => env('HPC_HOME_URL', 'https://hpc.parakh.ncert.gov.in/en/hpc'),

    // Teacher Profile Base Directory
    'teacher_profile_dir' => env('HPC_TEACHER_PROFILE_DIR', 'storage/app/hpc/teacher-profiles'),

    // Shared secret token used by the Chrome extension to authenticate
    // runProgress and getStudentQueue calls. Set in .env as HPC_PROGRESS_TOKEN.
    'progress_token' => env('HPC_PROGRESS_TOKEN', ''),

    // Chrome Extension ID used to lock CORS origin for queue/progress endpoints.
    // Find this in chrome://extensions after loading the extension unpacked.
    // Set in .env as HPC_EXTENSION_ID. Leave blank to allow app domain only.
    'extension_id' => env('HPC_EXTENSION_ID', ''),

];
