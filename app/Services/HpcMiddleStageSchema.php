<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class HpcMiddleStageSchema
{
    /**
     * Grouping of all 223 columns into 14 distinct assessment sections
     */
    public static function getSections(): array
    {
        return [
            ['title' => '🔒 Part 1: Student Identity (Locked)', 'start' => 0, 'end' => 9, 'bg' => 'FF0F172A'],
            ['title' => '👤 Part 2: Medium & Guardian Profile', 'start' => 10, 'end' => 14, 'bg' => 'FF1E40AF'],
            ['title' => '📅 Part 3: Monthly Working Days (Apr–Mar)', 'start' => 15, 'end' => 26, 'bg' => 'FF0369A1'],
            ['title' => '📅 Part 4: Monthly Present Days (Apr–Mar)', 'start' => 27, 'end' => 38, 'bg' => 'FF0284C7'],
            ['title' => '📈 Part 5: Attendance Summary & Leave (Auto)', 'start' => 39, 'end' => 44, 'bg' => 'FF0D9488'],
            ['title' => '🌱 Part 6: Part-A2 All About Me & Goal Setting', 'start' => 45, 'end' => 70, 'bg' => 'FF6D28D9'],
            ['title' => '🚀 Part 7: Part-A3 My Ambition Card', 'start' => 71, 'end' => 80, 'bg' => 'FF7C3AED'],
            ['title' => '🏡 Part 8: Part-A4 Home Learning Resources', 'start' => 81, 'end' => 90, 'bg' => 'FFBE185D'],
            ['title' => '🤝 Part 9: Part-A4 Parent Partnership & Feedback', 'start' => 91, 'end' => 105, 'bg' => 'FFB91C1C'],
            ['title' => '🎨 Part 10: Part-B Holistic Activity 1 & Rubrics', 'start' => 106, 'end' => 128, 'bg' => 'FFD97706'],
            ['title' => '🌟 Part 11: Part-B Self/Peer Reflection & Feedback', 'start' => 129, 'end' => 154, 'bg' => 'FFEA580C'],
            ['title' => '📊 Part 12: Part-C Curricular Domains (11 Subj x 4)', 'start' => 155, 'end' => 198, 'bg' => 'FF059669'],
            ['title' => '🎯 Part 13: Part-D Summative Assessment Marks (SA)', 'start' => 199, 'end' => 216, 'bg' => 'FF047857'],
            ['title' => '✍️ Part 14: Part-E HPC Credit Points & Declaration', 'start' => 217, 'end' => 222, 'bg' => 'FF334155'],
        ];
    }

    /**
     * Complete, authoritative definition of all 223 Middle Stage columns
     */
    public static function getColumns(): array
    {
        $rubricOpts = '"Beginner,Proficient,Advanced"';
        $yesNoOpts = '"Yes,No"';
        $freqOpts = '"all the time,most times,sometimes"';
        $parentQOpts = '"Yes,Sometimes,No,Not Sure"';

        return [
            ['key' => 'Student_Name', 'label' => '🔒 [LOCKED] Student Name', 'type' => 'locked', 'width' => 24, 'note' => 'Official full name of student (for identification)', 'classification' => 'locked'],
            ['key' => 'School_Admission_No', 'label' => '🔒 [LOCKED] School Admission No', 'type' => 'locked', 'width' => 20, 'note' => 'Official school admission / roll number', 'classification' => 'locked'],
            ['key' => 'Date_of_Birth', 'label' => '🔒 [LOCKED] Date of Birth', 'type' => 'locked', 'width' => 20, 'note' => 'Student Date of Birth (YYYY-MM-DD)', 'classification' => 'locked'],
            ['key' => 'Gender', 'label' => '🔒 [LOCKED] Gender', 'type' => 'list', 'options' => "Male,Female,Other", 'width' => 20, 'note' => 'Student Gender (Male / Female / Other)', 'classification' => 'locked'],
            ['key' => 'APAAR_ID', 'label' => '🔒 [LOCKED] APAAR ID', 'type' => 'locked', 'width' => 20, 'note' => 'Official 12-digit APAAR ID / Aadhaar for student identification', 'classification' => 'locked'],
            ['key' => 'HPC_Login_ID', 'label' => '🔒 [LOCKED] HPC Login ID', 'type' => 'locked', 'width' => 20, 'note' => 'Unique student login ID assigned by PARAKH portal', 'classification' => 'locked'],
            ['key' => 'UDID_Disability_ID', 'label' => '🔒 [LOCKED] UDID Disability ID', 'type' => 'locked', 'width' => 20, 'note' => 'Disability ID if applicable (or blank)', 'classification' => 'locked'],
            ['key' => 'Grade', 'label' => '🔒 [LOCKED] Grade', 'type' => 'list', 'options' => "Grade 6,Grade 7,Grade 8", 'width' => 20, 'note' => 'Class / Grade (Grade 6, Grade 7, Grade 8)', 'classification' => 'locked'],
            ['key' => 'Section', 'label' => '🔒 [LOCKED] Section', 'type' => 'list', 'options' => "A,B,C,D,E", 'width' => 20, 'note' => 'Assigned Section (A, B, C, D)', 'classification' => 'locked'],
            ['key' => 'Student_Photograph_Uploaded', 'label' => '🔒 [LOCKED] Student Photograph Uploaded', 'type' => 'list', 'width' => 20, 'note' => 'Yes / No status on portal', 'classification' => 'locked'],
            ['key' => 'Medium_of_Instruction', 'label' => '⚡ [AUTO] Medium of Instruction', 'type' => 'list', 'options' => '"English,Urdu,Hindi,Kashmiri,Dogri,Other"', 'width' => 22, 'note' => 'Instructional medium', 'classification' => 'auto'],
            ['key' => 'Area_Type', 'label' => '⚡ [AUTO] Area Type', 'type' => 'list', 'options' => '"Rural,Urban"', 'width' => 16, 'note' => 'Rural or Urban', 'classification' => 'auto'],
            ['key' => 'Mother_Tongue', 'label' => '⚡ [AUTO] Mother Tongue', 'type' => 'list', 'options' => '"Kashmiri,Urdu,Hindi,English,Dogri,Punjabi,Other"', 'width' => 18, 'note' => 'Student mother tongue', 'classification' => 'auto'],
            ['key' => 'Parent_Guardian_Role', 'label' => '⚡ [AUTO] Parent Guardian Role', 'type' => 'list', 'options' => '"Father,Mother,Guardian (Other than Parents)"', 'width' => 22, 'note' => 'Father / Mother / Guardian', 'classification' => 'auto'],
            ['key' => 'Guardian_Name_Detail', 'label' => '⚡ [AUTO] Guardian Name Detail', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Working_Days_Apr', 'label' => '⚡ [AUTO] Working Days - Apr', 'type' => 'integer', 'width' => 16, 'note' => 'Official Apr working days', 'classification' => 'auto'],
            ['key' => 'Working_Days_May', 'label' => '⚡ [AUTO] Working Days - May', 'type' => 'integer', 'width' => 16, 'note' => 'Official May working days', 'classification' => 'auto'],
            ['key' => 'Working_Days_Jun', 'label' => '⚡ [AUTO] Working Days - Jun', 'type' => 'integer', 'width' => 16, 'note' => 'Official Jun working days', 'classification' => 'auto'],
            ['key' => 'Working_Days_Jul', 'label' => '⚡ [AUTO] Working Days - Jul', 'type' => 'integer', 'width' => 16, 'note' => 'Official Jul working days', 'classification' => 'auto'],
            ['key' => 'Working_Days_Aug', 'label' => '⚡ [AUTO] Working Days - Aug', 'type' => 'integer', 'width' => 16, 'note' => 'Official Aug working days', 'classification' => 'auto'],
            ['key' => 'Working_Days_Sep', 'label' => '⚡ [AUTO] Working Days - Sep', 'type' => 'integer', 'width' => 16, 'note' => 'Official Sep working days', 'classification' => 'auto'],
            ['key' => 'Working_Days_Oct', 'label' => '⚡ [AUTO] Working Days - Oct', 'type' => 'integer', 'width' => 16, 'note' => 'Official Oct working days', 'classification' => 'auto'],
            ['key' => 'Working_Days_Nov', 'label' => '⚡ [AUTO] Working Days - Nov', 'type' => 'integer', 'width' => 16, 'note' => 'Official Nov working days', 'classification' => 'auto'],
            ['key' => 'Working_Days_Dec', 'label' => '⚡ [AUTO] Working Days - Dec', 'type' => 'integer', 'width' => 16, 'note' => 'Official Dec working days', 'classification' => 'auto'],
            ['key' => 'Working_Days_Jan', 'label' => '⚡ [AUTO] Working Days - Jan', 'type' => 'integer', 'width' => 16, 'note' => 'Official Jan working days', 'classification' => 'auto'],
            ['key' => 'Working_Days_Feb', 'label' => '⚡ [AUTO] Working Days - Feb', 'type' => 'integer', 'width' => 16, 'note' => 'Official Feb working days', 'classification' => 'auto'],
            ['key' => 'Working_Days_Mar', 'label' => '⚡ [AUTO] Working Days - Mar', 'type' => 'integer', 'width' => 16, 'note' => 'Official Mar working days', 'classification' => 'auto'],
            ['key' => 'Present_Days_Apr', 'label' => '⚡ [AUTO] Present Days - Apr', 'type' => 'integer', 'width' => 16, 'note' => 'Student Apr present days (<= Working Days)', 'classification' => 'auto'],
            ['key' => 'Present_Days_May', 'label' => '⚡ [AUTO] Present Days - May', 'type' => 'integer', 'width' => 16, 'note' => 'Student May present days (<= Working Days)', 'classification' => 'auto'],
            ['key' => 'Present_Days_Jun', 'label' => '⚡ [AUTO] Present Days - Jun', 'type' => 'integer', 'width' => 16, 'note' => 'Student Jun present days (<= Working Days)', 'classification' => 'auto'],
            ['key' => 'Present_Days_Jul', 'label' => '⚡ [AUTO] Present Days - Jul', 'type' => 'integer', 'width' => 16, 'note' => 'Student Jul present days (<= Working Days)', 'classification' => 'auto'],
            ['key' => 'Present_Days_Aug', 'label' => '⚡ [AUTO] Present Days - Aug', 'type' => 'integer', 'width' => 16, 'note' => 'Student Aug present days (<= Working Days)', 'classification' => 'auto'],
            ['key' => 'Present_Days_Sep', 'label' => '⚡ [AUTO] Present Days - Sep', 'type' => 'integer', 'width' => 16, 'note' => 'Student Sep present days (<= Working Days)', 'classification' => 'auto'],
            ['key' => 'Present_Days_Oct', 'label' => '⚡ [AUTO] Present Days - Oct', 'type' => 'integer', 'width' => 16, 'note' => 'Student Oct present days (<= Working Days)', 'classification' => 'auto'],
            ['key' => 'Present_Days_Nov', 'label' => '⚡ [AUTO] Present Days - Nov', 'type' => 'integer', 'width' => 16, 'note' => 'Student Nov present days (<= Working Days)', 'classification' => 'auto'],
            ['key' => 'Present_Days_Dec', 'label' => '⚡ [AUTO] Present Days - Dec', 'type' => 'integer', 'width' => 16, 'note' => 'Student Dec present days (<= Working Days)', 'classification' => 'auto'],
            ['key' => 'Present_Days_Jan', 'label' => '⚡ [AUTO] Present Days - Jan', 'type' => 'integer', 'width' => 16, 'note' => 'Student Jan present days (<= Working Days)', 'classification' => 'auto'],
            ['key' => 'Present_Days_Feb', 'label' => '⚡ [AUTO] Present Days - Feb', 'type' => 'integer', 'width' => 16, 'note' => 'Student Feb present days (<= Working Days)', 'classification' => 'auto'],
            ['key' => 'Present_Days_Mar', 'label' => '⚡ [AUTO] Present Days - Mar', 'type' => 'integer', 'width' => 16, 'note' => 'Student Mar present days (<= Working Days)', 'classification' => 'auto'],
            ['key' => 'Total_Working_Days', 'label' => '⚙️ [FORMULA] Total Working Days', 'type' => 'calc', 'width' => 22, 'note' => 'Formula: =SUM(O{row}:Z{row})', 'classification' => 'calc'],
            ['key' => 'Total_Present_Days', 'label' => '⚙️ [FORMULA] Total Present Days', 'type' => 'calc', 'width' => 22, 'note' => 'Formula: =SUM(AA{row}:AL{row})', 'classification' => 'calc'],
            ['key' => 'Overall_Attendance_Percentage', 'label' => '⚙️ [FORMULA] Overall Attendance Percentage', 'type' => 'calc', 'width' => 22, 'note' => 'Formula: =IF(AM{row}>0, ROUND((AN{row}/AM{row})*100, 1) & "%", "")', 'classification' => 'calc'],
            ['key' => 'Low_Attendance_Reason', 'label' => '⚡ [AUTO] Low Attendance Reason', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Total_Absence_Count', 'label' => '⚙️ [FORMULA] Total Absence Count', 'type' => 'calc', 'width' => 22, 'note' => 'Formula: =MAX(0, AM{row}-AN{row})', 'classification' => 'calc'],
            ['key' => 'Student_Interests', 'label' => '⚡ [AUTO] Student Interests', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'AboutMe_Live_With', 'label' => '⚡ [AUTO] AboutMe Live With', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'AboutMe_Stay_At', 'label' => '⚡ [AUTO] AboutMe Stay At', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'AboutMe_Free_Time_Doing', 'label' => '⚡ [AUTO] AboutMe Free Time Doing', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'AboutMe_Do_Very_Well', 'label' => '⚡ [AUTO] AboutMe Do Very Well', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'AboutMe_Responsible_Frequency', 'label' => '⚡ [AUTO] AboutMe Responsible Frequency', 'type' => 'list', 'options' => $freqOpts, 'width' => 24, 'note' => 'all the time, most times, sometimes', 'classification' => 'auto'],
            ['key' => 'AboutMe_Could_Do_Better_Specially', 'label' => '✏️ [INPUT] AboutMe Could Do Better Specially', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'input'],
            ['key' => 'AboutMe_Care_About_Others_By', 'label' => '⚡ [AUTO] AboutMe Care About Others By', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'AboutMe_Proud_Of_Myself_When', 'label' => '⚡ [AUTO] AboutMe Proud Of Myself When', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'AboutMe_Student_Name_Tag', 'label' => '⚡ [AUTO] AboutMe Student Name Tag', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'AboutMe_Student_Age_Tag', 'label' => '⚡ [AUTO] AboutMe Student Age Tag', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Academic_Goal', 'label' => '⚡ [AUTO] Academic Goal', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Academic_Goal_Importance', 'label' => '⚡ [AUTO] Academic Goal Importance', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Academic_Goal_Step_1', 'label' => '⚡ [AUTO] Academic Goal Step 1', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Academic_Goal_Step_2', 'label' => '⚡ [AUTO] Academic Goal Step 2', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Personal_Goal', 'label' => '⚡ [AUTO] Personal Goal', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Personal_Goal_Importance', 'label' => '⚡ [AUTO] Personal Goal Importance', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Personal_Goal_Step_1', 'label' => '⚡ [AUTO] Personal Goal Step 1', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Personal_Goal_Step_2', 'label' => '⚡ [AUTO] Personal Goal Step 2', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Learned_At_School_1', 'label' => '⚡ [AUTO] Learned At School 1', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Learned_At_School_2', 'label' => '⚡ [AUTO] Learned At School 2', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Learned_At_School_3', 'label' => '⚡ [AUTO] Learned At School 3', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Learned_Outside_School_1', 'label' => '⚡ [AUTO] Learned Outside School 1', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Learned_Outside_School_2', 'label' => '⚡ [AUTO] Learned Outside School 2', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Learned_Outside_School_3', 'label' => '⚡ [AUTO] Learned Outside School 3', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'For_Teacher_Help_Me_With', 'label' => '⚡ [AUTO] For Teacher Help Me With', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'For_Teacher_Like_Them_To_Know', 'label' => '⚡ [AUTO] For Teacher Like Them To Know', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Ambition_Goal', 'label' => '⚡ [AUTO] Ambition Goal', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Ambition_Achieve_Strategy', 'label' => '⚡ [AUTO] Ambition Achieve Strategy', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Ambition_5_Skills_Needed', 'label' => '⚡ [AUTO] Ambition 5 Skills Needed', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Ambition_Subjects_To_Focus', 'label' => '⚡ [AUTO] Ambition Subjects To Focus', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Ambition_Habits_Required', 'label' => '⚡ [AUTO] Ambition Habits Required', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Ambition_Guidance_From_Person', 'label' => '⚡ [AUTO] Ambition Guidance From Person', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Ambition_How_Person_Will_Help', 'label' => '⚡ [AUTO] Ambition How Person Will Help', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Ambition_Learn_New', 'label' => '⚡ [AUTO] Ambition Learn New', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Ambition_How_Student_Will_Feel', 'label' => '⚡ [AUTO] Ambition How Student Will Feel', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Ambition_How_Parents_Will_Feel', 'label' => '⚡ [AUTO] Ambition How Parents Will Feel', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Home_Resource_Books_Magazines', 'label' => '⚡ [AUTO] Home Resource: Books Magazines', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Resource availability at home (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Home_Resource_Newspaper', 'label' => '⚡ [AUTO] Home Resource: Newspaper', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Resource availability at home (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Home_Resource_Toys_Games_Sports', 'label' => '⚡ [AUTO] Home Resource: Toys Games Sports', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Resource availability at home (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Home_Resource_Phone_Computer', 'label' => '⚡ [AUTO] Home Resource: Phone Computer', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Resource availability at home (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Home_Resource_Internet', 'label' => '⚡ [AUTO] Home Resource: Internet', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Resource availability at home (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Home_Resource_Radio', 'label' => '⚡ [AUTO] Home Resource: Radio', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Resource availability at home (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Home_Resource_Broadcast', 'label' => '⚡ [AUTO] Home Resource: Broadcast', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Resource availability at home (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Home_Resource_TV', 'label' => '⚡ [AUTO] Home Resource: TV', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Resource availability at home (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Home_Resource_For_CwD', 'label' => '⚡ [AUTO] Home Resource: For CwD', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Resource availability at home (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Home_Resource_Other_Specify', 'label' => '⚡ [AUTO] Home Resource Other Specify', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Parent_Q1_Motivated_To_Learn', 'label' => '⚡ [AUTO] Parent Q1 Motivated To Learn', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Parent rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Parent_Q2_Follows_Daily_Schedule', 'label' => '⚡ [AUTO] Parent Q2 Follows Daily Schedule', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Parent rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Parent_Q3_Finds_Curriculum_Diff', 'label' => '⚡ [AUTO] Parent Q3 Finds Curriculum Diff', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Parent rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Parent_Q4_Making_Good_Progress', 'label' => '⚡ [AUTO] Parent Q4 Making Good Progress', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Parent rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Parent_NeedSupport_Languages', 'label' => '⚡ [AUTO] Parent Need Support: Languages', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Support needed from school in Languages (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Parent_NeedSupport_Mathematics', 'label' => '⚡ [AUTO] Parent Need Support: Mathematics', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Support needed from school in Mathematics (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Parent_NeedSupport_Science', 'label' => '⚡ [AUTO] Parent Need Support: Science', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Support needed from school in Science (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Parent_NeedSupport_SocialScience', 'label' => '⚡ [AUTO] Parent Need Support: SocialScience', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Support needed from school in SocialScience (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Parent_NeedSupport_SelfBelief', 'label' => '⚡ [AUTO] Parent Need Support: SelfBelief', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Support needed from school in SelfBelief (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Parent_NeedSupport_SocialSkills', 'label' => '⚡ [AUTO] Parent Need Support: SocialSkills', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Support needed from school in SocialSkills (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Parent_NeedSupport_ManagingEmotions', 'label' => '⚡ [AUTO] Parent Need Support: ManagingEmotions', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Support needed from school in ManagingEmotions (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Parent_NeedSupport_StudySkills', 'label' => '⚡ [AUTO] Parent Need Support: StudySkills', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Support needed from school in StudySkills (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Parent_NeedSupport_SkillGuidance', 'label' => '⚡ [AUTO] Parent Need Support: SkillGuidance', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Support needed from school in SkillGuidance (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Parent_NeedSupport_AnyOther', 'label' => '⚡ [AUTO] Parent NeedSupport AnyOther', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Parent_Support_At_Home_ActionPlan', 'label' => '⚡ [AUTO] Parent Support At Home ActionPlan', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Act_Name', 'label' => '⚡ [AUTO] Act Name', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Act_Subject_Domain', 'label' => '⚡ [AUTO] Act Subject Domain', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Act_Curricular_Goal_Code', 'label' => '⚡ [AUTO] Act Curricular Goal Code', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Act_Competency_Codes', 'label' => '⚡ [AUTO] Act Competency Codes', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Act_Approach_Art_Integrated', 'label' => '⚡ [AUTO] Act Approach Art Integrated', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Confirmation (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Act_Approach_Sports_Integrated', 'label' => '⚡ [AUTO] Act Approach Sports Integrated', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Confirmation (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Act_Approach_Toy_Based', 'label' => '⚡ [AUTO] Act Approach Toy Based', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Confirmation (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Act_Approach_Tech_Integrated', 'label' => '⚡ [AUTO] Act Approach Tech Integrated', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Confirmation (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Act_Approach_Any_Other', 'label' => '⚡ [AUTO] Act Approach Any Other', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Confirmation (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Act_Description_Instructions', 'label' => '⚡ [AUTO] Act Description Instructions', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Act_Assessment_Questions', 'label' => '⚡ [AUTO] Act Assessment Questions', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Act_Rubric_Awareness_Beg_Desc', 'label' => '⚡ [AUTO] Act Rubric Awareness Beg Desc', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Act_Rubric_Awareness_Prof_Desc', 'label' => '⚡ [AUTO] Act Rubric Awareness Prof Desc', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Act_Rubric_Awareness_Adv_Desc', 'label' => '⚡ [AUTO] Act Rubric Awareness Adv Desc', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Act_Rubric_Awareness_Level', 'label' => '⚡ [AUTO] Act Rubric Awareness Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Act_Rubric_Sensitivity_Beg_Desc', 'label' => '⚡ [AUTO] Act Rubric Sensitivity Beg Desc', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Act_Rubric_Sensitivity_Prof_Desc', 'label' => '⚡ [AUTO] Act Rubric Sensitivity Prof Desc', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Act_Rubric_Sensitivity_Adv_Desc', 'label' => '⚡ [AUTO] Act Rubric Sensitivity Adv Desc', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Act_Rubric_Sensitivity_Level', 'label' => '⚡ [AUTO] Act Rubric Sensitivity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Act_Rubric_Creativity_Beg_Desc', 'label' => '⚡ [AUTO] Act Rubric Creativity Beg Desc', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Act_Rubric_Creativity_Prof_Desc', 'label' => '⚡ [AUTO] Act Rubric Creativity Prof Desc', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Act_Rubric_Creativity_Adv_Desc', 'label' => '⚡ [AUTO] Act Rubric Creativity Adv Desc', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Act_Rubric_Creativity_Level', 'label' => '⚡ [AUTO] Act Rubric Creativity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'SelfReflect_ProudOfEffort', 'label' => '⚡ [AUTO] SelfReflect ProudOfEffort', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Confirmation (Yes / No)', 'classification' => 'auto'],
            ['key' => 'SelfReflect_ApplyToRealLife', 'label' => '⚡ [AUTO] SelfReflect ApplyToRealLife', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Confirmation (Yes / No)', 'classification' => 'auto'],
            ['key' => 'SelfReflect_MotivatedLearnFurther', 'label' => '⚡ [AUTO] SelfReflect MotivatedLearnFurther', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Confirmation (Yes / No)', 'classification' => 'auto'],
            ['key' => 'SelfReflect_Grid_Awareness_Count', 'label' => '⚡ [AUTO] SelfReflect Grid Awareness Count', 'type' => 'integer', 'width' => 20, 'note' => 'Rating scale count (1 to 5)', 'classification' => 'auto'],
            ['key' => 'SelfReflect_Grid_Sensitivity_Count', 'label' => '⚡ [AUTO] SelfReflect Grid Sensitivity Count', 'type' => 'integer', 'width' => 20, 'note' => 'Rating scale count (1 to 5)', 'classification' => 'auto'],
            ['key' => 'SelfReflect_Grid_Creativity_Count', 'label' => '⚡ [AUTO] SelfReflect Grid Creativity Count', 'type' => 'integer', 'width' => 20, 'note' => 'Rating scale count (1 to 5)', 'classification' => 'auto'],
            ['key' => 'SelfReflect_Learnt_Text', 'label' => '⚡ [AUTO] SelfReflect Learnt Text', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'SelfReflect_MostInteresting_Text', 'label' => '⚡ [AUTO] SelfReflect MostInteresting Text', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'SelfReflect_NeedPracticeOn_Text', 'label' => '⚡ [AUTO] SelfReflect NeedPracticeOn Text', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'SelfReflect_NeedHelpWith_Text', 'label' => '⚡ [AUTO] SelfReflect NeedHelpWith Text', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Peer_Student_Name', 'label' => '⚡ [AUTO] Peer Student Name', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Peer_Target_Friend_Name', 'label' => '⚡ [AUTO] Peer Target Friend Name', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Peer_Engaged_Motivated', 'label' => '⚡ [AUTO] Peer Engaged Motivated', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Confirmation (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Peer_Shared_Thoughts_Ideas', 'label' => '⚡ [AUTO] Peer Shared Thoughts Ideas', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Confirmation (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Peer_Grid_Awareness_Count', 'label' => '⚡ [AUTO] Peer Grid Awareness Count', 'type' => 'integer', 'width' => 20, 'note' => 'Rating scale count (1 to 5)', 'classification' => 'auto'],
            ['key' => 'Peer_Grid_Sensitivity_Count', 'label' => '⚡ [AUTO] Peer Grid Sensitivity Count', 'type' => 'integer', 'width' => 20, 'note' => 'Rating scale count (1 to 5)', 'classification' => 'auto'],
            ['key' => 'Peer_Grid_Creativity_Count', 'label' => '⚡ [AUTO] Peer Grid Creativity Count', 'type' => 'integer', 'width' => 20, 'note' => 'Rating scale count (1 to 5)', 'classification' => 'auto'],
            ['key' => 'Peer_Needs_Practice_Text', 'label' => '✏️ [INPUT] Peer Needs Practice Text', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'input'],
            ['key' => 'Peer_Needs_Help_With_Text', 'label' => '✏️ [INPUT] Peer Needs Help With Text', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'input'],
            ['key' => 'Wheel_Awareness_Level', 'label' => '⚡ [AUTO] Wheel Awareness Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Wheel_Sensitivity_Level', 'label' => '⚡ [AUTO] Wheel Sensitivity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Wheel_Creativity_Level', 'label' => '⚡ [AUTO] Wheel Creativity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Strengths_MultiSelect', 'label' => '⚡ [AUTO] Strengths MultiSelect', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Barriers_MultiSelect', 'label' => '✏️ [INPUT] Barriers MultiSelect', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'input'],
            ['key' => 'TeacherFeedback_Progress_Further', 'label' => '⚡ [AUTO] TeacherFeedback Progress Further', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'TeacherFeedback_Observations_Rec', 'label' => '⚡ [AUTO] TeacherFeedback Observations Rec', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'L1_Awareness_Level', 'label' => '⚡ [AUTO] L1 Awareness Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'L1_Sensitivity_Level', 'label' => '⚡ [AUTO] L1 Sensitivity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'L1_Creativity_Level', 'label' => '⚡ [AUTO] L1 Creativity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'L1_Observational_Note', 'label' => '✏️ [INPUT] L1 Observational Note', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'input'],
            ['key' => 'L2_Awareness_Level', 'label' => '⚡ [AUTO] L2 Awareness Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'L2_Sensitivity_Level', 'label' => '⚡ [AUTO] L2 Sensitivity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'L2_Creativity_Level', 'label' => '⚡ [AUTO] L2 Creativity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'L2_Observational_Note', 'label' => '✏️ [INPUT] L2 Observational Note', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'input'],
            ['key' => 'L3_Awareness_Level', 'label' => '⚡ [AUTO] L3 Awareness Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'L3_Sensitivity_Level', 'label' => '⚡ [AUTO] L3 Sensitivity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'L3_Creativity_Level', 'label' => '⚡ [AUTO] L3 Creativity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'L3_Observational_Note', 'label' => '✏️ [INPUT] L3 Observational Note', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'input'],
            ['key' => 'Math_Awareness_Level', 'label' => '⚡ [AUTO] Math Awareness Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Math_Sensitivity_Level', 'label' => '⚡ [AUTO] Math Sensitivity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Math_Creativity_Level', 'label' => '⚡ [AUTO] Math Creativity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Math_Observational_Note', 'label' => '✏️ [INPUT] Math Observational Note', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'input'],
            ['key' => 'Science_Awareness_Level', 'label' => '⚡ [AUTO] Science Awareness Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Science_Sensitivity_Level', 'label' => '⚡ [AUTO] Science Sensitivity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Science_Creativity_Level', 'label' => '⚡ [AUTO] Science Creativity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Science_Observational_Note', 'label' => '✏️ [INPUT] Science Observational Note', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'input'],
            ['key' => 'Social_Science_Awareness_Level', 'label' => '⚡ [AUTO] Social Science Awareness Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Social_Science_Sensitivity_Level', 'label' => '⚡ [AUTO] Social Science Sensitivity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Social_Science_Creativity_Level', 'label' => '⚡ [AUTO] Social Science Creativity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Social_Science_Observational_Note', 'label' => '✏️ [INPUT] Social Science Observational Note', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'input'],
            ['key' => 'Art_Education_Awareness_Level', 'label' => '⚡ [AUTO] Art Education Awareness Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Art_Education_Sensitivity_Level', 'label' => '⚡ [AUTO] Art Education Sensitivity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Art_Education_Creativity_Level', 'label' => '⚡ [AUTO] Art Education Creativity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Art_Education_Observational_Note', 'label' => '✏️ [INPUT] Art Education Observational Note', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'input'],
            ['key' => 'Physical_Education_Awareness_Level', 'label' => '⚡ [AUTO] Physical Education Awareness Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Physical_Education_Sensitivity_Level', 'label' => '⚡ [AUTO] Physical Education Sensitivity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Physical_Education_Creativity_Level', 'label' => '⚡ [AUTO] Physical Education Creativity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Physical_Education_Observational_Note', 'label' => '✏️ [INPUT] Physical Education Observational Note', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'input'],
            ['key' => 'Vocational_Skill_Edu_Awareness_Level', 'label' => '⚡ [AUTO] Vocational Skill Edu Awareness Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Vocational_Skill_Edu_Sensitivity_Level', 'label' => '⚡ [AUTO] Vocational Skill Edu Sensitivity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Vocational_Skill_Edu_Creativity_Level', 'label' => '⚡ [AUTO] Vocational Skill Edu Creativity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Vocational_Skill_Edu_Observational_Note', 'label' => '✏️ [INPUT] Vocational Skill Edu Observational Note', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'input'],
            ['key' => 'Other_Learning_Activities_Awareness_Level', 'label' => '⚡ [AUTO] Other Learning Activities Awareness Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Other_Learning_Activities_Sensitivity_Level', 'label' => '⚡ [AUTO] Other Learning Activities Sensitivity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Other_Learning_Activities_Creativity_Level', 'label' => '⚡ [AUTO] Other Learning Activities Creativity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Other_Learning_Activities_Observational_Note', 'label' => '✏️ [INPUT] Other Learning Activities Observational Note', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'input'],
            ['key' => 'Bagless_Days_Experience_Awareness_Level', 'label' => '⚡ [AUTO] Bagless Days Experience Awareness Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Bagless_Days_Experience_Sensitivity_Level', 'label' => '⚡ [AUTO] Bagless Days Experience Sensitivity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Bagless_Days_Experience_Creativity_Level', 'label' => '⚡ [AUTO] Bagless Days Experience Creativity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Bagless_Days_Experience_Observational_Note', 'label' => '✏️ [INPUT] Bagless Days Experience Observational Note', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'input'],
            ['key' => 'L1_Max_Marks_SA', 'label' => '🎯 [AUTO] L1 Max Marks SA', 'type' => 'integer', 'width' => 18, 'note' => 'Summative Assessment Maximum Marks (Default 50)', 'classification' => 'auto'],
            ['key' => 'L1_Marks_Obtained_SA', 'label' => '✏️ [INPUT] L1 Marks Obtained SA', 'type' => 'integer', 'width' => 18, 'note' => 'Summative Assessment Marks Obtained (Teacher Entry / 0 to Max)', 'classification' => 'input'],
            ['key' => 'L2_Max_Marks_SA', 'label' => '🎯 [AUTO] L2 Max Marks SA', 'type' => 'integer', 'width' => 18, 'note' => 'Summative Assessment Maximum Marks (Default 50)', 'classification' => 'auto'],
            ['key' => 'L2_Marks_Obtained_SA', 'label' => '✏️ [INPUT] L2 Marks Obtained SA', 'type' => 'integer', 'width' => 18, 'note' => 'Summative Assessment Marks Obtained (Teacher Entry / 0 to Max)', 'classification' => 'input'],
            ['key' => 'L3_Max_Marks_SA', 'label' => '🎯 [AUTO] L3 Max Marks SA', 'type' => 'integer', 'width' => 18, 'note' => 'Summative Assessment Maximum Marks (Default 50)', 'classification' => 'auto'],
            ['key' => 'L3_Marks_Obtained_SA', 'label' => '✏️ [INPUT] L3 Marks Obtained SA', 'type' => 'integer', 'width' => 18, 'note' => 'Summative Assessment Marks Obtained (Teacher Entry / 0 to Max)', 'classification' => 'input'],
            ['key' => 'Math_Max_Marks_SA', 'label' => '🎯 [AUTO] Math Max Marks SA', 'type' => 'integer', 'width' => 18, 'note' => 'Summative Assessment Maximum Marks (Default 50)', 'classification' => 'auto'],
            ['key' => 'Math_Marks_Obtained_SA', 'label' => '✏️ [INPUT] Math Marks Obtained SA', 'type' => 'integer', 'width' => 18, 'note' => 'Summative Assessment Marks Obtained (Teacher Entry / 0 to Max)', 'classification' => 'input'],
            ['key' => 'Science_Max_Marks_SA', 'label' => '🎯 [AUTO] Science Max Marks SA', 'type' => 'integer', 'width' => 18, 'note' => 'Summative Assessment Maximum Marks (Default 50)', 'classification' => 'auto'],
            ['key' => 'Science_Marks_Obtained_SA', 'label' => '✏️ [INPUT] Science Marks Obtained SA', 'type' => 'integer', 'width' => 18, 'note' => 'Summative Assessment Marks Obtained (Teacher Entry / 0 to Max)', 'classification' => 'input'],
            ['key' => 'SocialScience_Max_Marks_SA', 'label' => '🎯 [AUTO] SocialScience Max Marks SA', 'type' => 'integer', 'width' => 18, 'note' => 'Summative Assessment Maximum Marks (Default 50)', 'classification' => 'auto'],
            ['key' => 'SocialScience_Marks_Obtained_SA', 'label' => '✏️ [INPUT] SocialScience Marks Obtained SA', 'type' => 'integer', 'width' => 18, 'note' => 'Summative Assessment Marks Obtained (Teacher Entry / 0 to Max)', 'classification' => 'input'],
            ['key' => 'Art_Max_Marks_SA', 'label' => '🎯 [AUTO] Art Max Marks SA', 'type' => 'integer', 'width' => 18, 'note' => 'Summative Assessment Maximum Marks (Default 50)', 'classification' => 'auto'],
            ['key' => 'Art_Marks_Obtained_SA', 'label' => '✏️ [INPUT] Art Marks Obtained SA', 'type' => 'integer', 'width' => 18, 'note' => 'Summative Assessment Marks Obtained (Teacher Entry / 0 to Max)', 'classification' => 'input'],
            ['key' => 'PE_Max_Marks_SA', 'label' => '🎯 [AUTO] PE Max Marks SA', 'type' => 'integer', 'width' => 18, 'note' => 'Summative Assessment Maximum Marks (Default 50)', 'classification' => 'auto'],
            ['key' => 'PE_Marks_Obtained_SA', 'label' => '✏️ [INPUT] PE Marks Obtained SA', 'type' => 'integer', 'width' => 18, 'note' => 'Summative Assessment Marks Obtained (Teacher Entry / 0 to Max)', 'classification' => 'input'],
            ['key' => 'Total_SA_Max_Marks', 'label' => '⚙️ [FORMULA] Total SA Max Marks', 'type' => 'calc', 'width' => 22, 'note' => 'Formula: =SUM(GQ{row},GS{row},GU{row},GW{row},GY{row},HA{row},HC{row},HE{row})', 'classification' => 'calc'],
            ['key' => 'Total_SA_Marks_Obtained', 'label' => '⚙️ [FORMULA] Total SA Marks Obtained', 'type' => 'calc', 'width' => 22, 'note' => 'Formula: =SUM(GR{row},GT{row},GV{row},GX{row},GZ{row},HB{row},HD{row},HF{row})', 'classification' => 'calc'],
            ['key' => 'Total_Credit_Earned_HPC_Formative', 'label' => '⚙️ [FORMULA] Total Credit Earned HPC Formative', 'type' => 'calc', 'width' => 22, 'note' => 'Formula: =ROUND((HH{row}/HG{row})*20, 1)', 'classification' => 'calc'],
            ['key' => 'Credit_Earned_Summative_SA', 'label' => '⚙️ [FORMULA] Credit Earned Summative SA', 'type' => 'calc', 'width' => 22, 'note' => 'Formula: =ROUND((HH{row}/HG{row})*20, 1)', 'classification' => 'calc'],
            ['key' => 'Total_Credit_Earned_Final', 'label' => '⚙️ [FORMULA] Total Credit Earned Final', 'type' => 'calc', 'width' => 22, 'note' => 'Formula: =HI{row}+HJ{row}', 'classification' => 'calc'],
            ['key' => 'Total_Credit_Points_Earned', 'label' => '⚙️ [FORMULA] Total Credit Points Earned', 'type' => 'calc', 'width' => 22, 'note' => 'Formula: =HK{row}', 'classification' => 'calc'],
            ['key' => 'Total_Marks_Obtained_HPC_Credit', 'label' => '⚙️ [FORMULA] Total Marks Obtained HPC Credit', 'type' => 'calc', 'width' => 22, 'note' => 'Formula: =HH{row}', 'classification' => 'calc'],
            ['key' => 'Teacher_Declaration_Agreed', 'label' => '⚡ [AUTO] Teacher Declaration Agreed', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Confirmation (Yes / No)', 'classification' => 'auto'],
        ];
    }

    /**
     * Build starter row values for Middle Stage (exactly 222 values)
     */
    public static function buildStarterRow(int $sIdx, array $st, array $defaultWorkMap, array $defaultMax, int $rowNum, bool $autoFill = true, array $options = []): array
    {
        $apaarId = !empty($st['apaar_id']) ? $st['apaar_id'] : '';
        $loginId = !empty($st['login_id']) ? $st['login_id'] : (!empty($st['hpc_id']) ? $st['hpc_id'] : '');
        $name    = !empty($st['name']) ? $st['name'] : ('Student ' . ($sIdx + 1));
        $status  = !empty($st['status']) ? $st['status'] : 'HPC In Progress';
        $admNo   = !empty($st['admission_no']) ? $st['admission_no'] : (!empty($st['roll_no']) ? $st['roll_no'] : '');
        $grade   = !empty($st['grade']) ? $st['grade'] : 'Grade 6';
        $section = !empty($st['section']) ? $st['section'] : 'A';
        $gender  = !empty($st['gender']) ? $st['gender'] : ($sIdx % 2 === 0 ? 'Male' : 'Female');
        $dob     = !empty($st['dob']) ? $st['dob'] : '2012-05-15';
        $udid    = !empty($st['udid']) ? $st['udid'] : '';

        $peers = ['Ayaan Ahmad', 'Zaid Farooq', 'Fatima Zahra', 'Maryam Jan', 'Sahil Bashir', 'Iqra Parveen', 'Arsalan Mir', 'Insha Tariq'];
        $peerName = $peers[$sIdx % count($peers)];

        $interests = ['Science Experiments & Model Making', 'Mathematics & Logic Puzzles', 'Reading Storybooks & Novels', 'Drawing & Environmental Sketching', 'Sports (Football & Cricket)', 'Creative Writing & Poetry', 'Gardening & Nature Care', 'Origami & Paper Craft'];
        $interest = $interests[$sIdx % count($interests)];

        $ambitions = [
            ['Software Engineer', 'Consistent math problem solving and computer programming practice', 'Logical Thinking, Coding, Collaboration, Mathematics, Problem Solving', 'Mathematics, Science, English, Computer Science'],
            ['Medical Doctor', 'Dedication to biology and chemistry concepts with disciplined daily revision', 'Scientific Temper, Empathy, Observation, Memory, Active Listening', 'Science, Mathematics, English, Health Education'],
            ['Environmental Scientist', 'Studying local biodiversity and participating in environmental youth projects', 'Inquiry, Research, Data Analysis, Critical Thinking, Field Observation', 'Science, Social Science, Mathematics, Geography'],
            ['Civil Servant (IAS)', 'Deep study of constitutional history, general knowledge, and public policy', 'Leadership, Integrity, Public Speaking, Analytical Reasoning, Time Management', 'Social Science, Languages, General Studies, Mathematics'],
            ['Aerospace Engineer', 'Mastering physics mechanics and 3D geometric modeling', 'Calculus, Spatial Reasoning, Physics, Innovation, Design Thinking', 'Mathematics, Science, English, Design & Technology']
        ];
        $amb = $ambitions[$sIdx % count($ambitions)];

        $months = ['apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec', 'jan', 'feb', 'mar'];
        $defaultWorkDays = [20, 22, 10, 20, 22, 21, 19, 20, 15, 15, 20, 18];

        $colKeys = array_column(self::getColumns(), 'key');
        $k2c = [];
        foreach ($colKeys as $idx => $k) {
            $k2c[$k] = Coordinate::stringFromColumnIndex($idx + 1);
        }

        $vals = [];

        // 1 to 10: Locked Identity (Including Student Name & APAAR ID for clear identification)
        $vals[] = $name;
        $vals[] = $admNo;
        $vals[] = $dob;
        $vals[] = $gender;
        $vals[] = $apaarId;
        $vals[] = $loginId;
        $vals[] = $udid;
        $vals[] = $grade;
        $vals[] = $section;
        $vals[] = 'Yes';

        // 11 to 15: Profile & Medium
        $vals[] = 'English';
        $vals[] = 'Rural';
        $vals[] = 'Kashmiri';
        $vals[] = ($sIdx % 2 === 0) ? 'Father' : 'Mother';
        $vals[] = 'Guardian of ' . explode(' ', trim($name))[0];

        // 16 to 27: Working Days (Apr to Mar)
        foreach ($months as $mIdx => $m) {
            $w = $defaultWorkMap[$m] ?? $defaultWorkDays[$mIdx];
            $vals[] = $w;
        }

        // 28 to 39: Present Days (Apr to Mar)
        foreach ($months as $mIdx => $m) {
            $w = $defaultWorkMap[$m] ?? $defaultWorkDays[$mIdx];
            $p = $autoFill ? max(0, $w - ($sIdx % 3 === 0 ? 1 : 2)) : '';
            $vals[] = $p;
        }

        // 40 to 45: Attendance Summary
        $vals[] = "=SUM({$k2c['Working_Days_Apr']}{$rowNum}:{$k2c['Working_Days_Mar']}{$rowNum})";
        $vals[] = "=SUM({$k2c['Present_Days_Apr']}{$rowNum}:{$k2c['Present_Days_Mar']}{$rowNum})";
        $vals[] = "=IF({$k2c['Total_Working_Days']}{$rowNum}>0, ROUND(({$k2c['Total_Present_Days']}{$rowNum}/{$k2c['Total_Working_Days']}{$rowNum})*100, 1) & \"%\", \"\")";
        $vals[] = $autoFill ? 'Satisfactory regular attendance maintained throughout session' : '';
        $vals[] = "=MAX(0, {$k2c['Total_Working_Days']}{$rowNum}-{$k2c['Total_Present_Days']}{$rowNum})";
        $vals[] = $autoFill ? $interest : '';

        // 45 to 70: Part-A2 All About Me & Goal Setting
        $vals[] = $autoFill ? 'Parents and Grandparents' : '';
        $vals[] = $autoFill ? 'Family home in rural district' : '';
        $vals[] = $autoFill ? 'Reading storybooks, drawing landscapes, and playing sports' : '';
        $vals[] = $autoFill ? 'Solving numerical puzzles and explaining concepts to classmates' : '';
        $vals[] = $autoFill ? 'all the time' : '';
        $vals[] = $autoFill ? 'Revision planning and cursive handwriting speed' : '';
        $vals[] = $autoFill ? 'Sharing study notes and listening patiently to peers' : '';
        $vals[] = $autoFill ? 'Solving difficult science and math problems independently' : '';
        $vals[] = $name;
        $vals[] = '12';
        $vals[] = $autoFill ? 'Master algebraic concepts and score above 90% in Science and Math' : '';
        $vals[] = $autoFill ? 'Builds strong academic foundation for higher secondary education' : '';
        $vals[] = $autoFill ? 'Spend 45 minutes daily practicing numerical problem solving' : '';
        $vals[] = $autoFill ? 'Clarify concept doubts with subject teachers immediately' : '';
        $vals[] = $autoFill ? 'Maintain disciplined daily physical exercise and reading habit' : '';
        $vals[] = $autoFill ? 'Improves stamina, mental focus, and overall confidence' : '';
        $vals[] = $autoFill ? 'Wake up at 6:00 AM daily for morning yoga and brisk walk' : '';
        $vals[] = $autoFill ? 'Read 5 pages of an educational book every evening' : '';
        $vals[] = $autoFill ? 'Scientific observation and hypothesis testing in science' : '';
        $vals[] = $autoFill ? 'Geometric constructions, perimeter, and area calculations' : '';
        $vals[] = $autoFill ? 'Democratic values, empathy, and collaborative teamwork' : '';
        $vals[] = $autoFill ? 'Plant life cycles and seasonal agriculture in our village' : '';
        $vals[] = $autoFill ? 'Budgeting pocket money and helping parents with groceries' : '';
        $vals[] = $autoFill ? 'Traditional handicrafts and regional folktales from elders' : '';
        $vals[] = $autoFill ? 'Providing advanced Olympiad worksheets and science guidance' : '';
        $vals[] = $autoFill ? 'I am deeply enthusiastic about space exploration and robotics' : '';

        // 71 to 80: Part-A3 My Ambition Card
        $vals[] = $autoFill ? $amb[0] : '';
        $vals[] = $autoFill ? $amb[1] : '';
        $vals[] = $autoFill ? $amb[2] : '';
        $vals[] = $autoFill ? $amb[3] : '';
        $vals[] = $autoFill ? 'Punctuality, daily revision, and continuous curiosity' : '';
        $vals[] = $autoFill ? 'Science & Mathematics Teachers' : '';
        $vals[] = $autoFill ? 'Recommending reference books and mentoring laboratory projects' : '';
        $vals[] = $autoFill ? 'Advanced programming basics and scientific modeling methods' : '';
        $vals[] = $autoFill ? 'Extremely proud, confident, and motivated to serve society' : '';
        $vals[] = $autoFill ? 'Immensely joyful, supportive, and proud of my hard work' : '';

        // 81 to 90: Part-A4 Home Learning Resources
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'No' : '';
        $vals[] = $autoFill ? 'Educational charts and world map' : '';

        // 91 to 105: Part-A4 Parent Partnership & Feedback
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Sometimes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'No' : '';
        $vals[] = $autoFill ? 'No' : '';
        $vals[] = $autoFill ? 'No' : '';
        $vals[] = $autoFill ? 'No' : '';
        $vals[] = $autoFill ? 'No' : '';
        $vals[] = $autoFill ? 'No' : '';
        $vals[] = $autoFill ? 'No' : '';
        $vals[] = $autoFill ? 'No' : '';
        $vals[] = $autoFill ? 'No' : '';
        $vals[] = $autoFill ? 'No' : '';
        $vals[] = $autoFill ? 'Provide 2 hours of quiet evening study space and monitor regular revision' : '';

        // 106 to 128: Part-B Activity 1 & Rubrics
        $vals[] = $autoFill ? 'Solar System 3D Model & Daily Life Geometry' : '';
        $vals[] = $autoFill ? 'Science' : '';
        $vals[] = $autoFill ? 'CG-SCI-03' : '';
        $vals[] = $autoFill ? 'C-3.1, C-3.2' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'No' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'No' : '';
        $vals[] = $autoFill ? 'Inquiry-Based Learning' : '';
        $vals[] = $autoFill ? 'Constructed scale models of planetary orbits, computed relative ratios, and demonstrated to class' : '';
        $vals[] = $autoFill ? '1. How do planetary distances compare? 2. How did you compute scaling ratios for the model?' : '';
        $vals[] = $autoFill ? 'Recognizes basic planets with assistance' : '';
        $vals[] = $autoFill ? 'Accurately explains planetary order, characteristics, and scale distances' : '';
        $vals[] = $autoFill ? 'Formulates complex gravitational principles and explains planetary mechanics insightfully' : '';
        $vals[] = $autoFill ? (($sIdx % 3 === 0) ? 'Advanced' : 'Proficient') : '';
        $vals[] = $autoFill ? 'Participates in group work with reminders' : '';
        $vals[] = $autoFill ? 'Actively shares materials, listens respectfully, and values team opinions' : '';
        $vals[] = $autoFill ? 'Demonstrates exceptional leadership, inspires peers, and ensures inclusive team participation' : '';
        $vals[] = $autoFill ? (($sIdx % 2 === 0) ? 'Advanced' : 'Proficient') : '';
        $vals[] = $autoFill ? 'Replicates given model templates' : '';
        $vals[] = $autoFill ? 'Uses recycled materials innovatively to build dynamic scale models' : '';
        $vals[] = $autoFill ? 'Designs original multi-axis rotating planetary gear system with LED illumination' : '';
        $vals[] = $autoFill ? (($sIdx % 3 === 0) ? 'Advanced' : 'Proficient') : '';

        // 129 to 154: Part-B Self/Peer Reflection & Feedback
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 4 : '';
        $vals[] = $autoFill ? 4 : '';
        $vals[] = $autoFill ? 5 : '';
        $vals[] = $autoFill ? 'Learned celestial orbital distances, 3D scale geometry, and team coordination' : '';
        $vals[] = $autoFill ? 'Fabricating rotating planetary models using recycled materials and LED lighting' : '';
        $vals[] = $autoFill ? 'Time management during fine scale calibrations' : '';
        $vals[] = $autoFill ? 'Precision measuring tools and astronomical ratio conversions' : '';
        $vals[] = $peerName;
        $vals[] = $name;
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 5 : '';
        $vals[] = $autoFill ? 5 : '';
        $vals[] = $autoFill ? 4 : '';
        $vals[] = $autoFill ? 'Speaking with louder projection during final presentations' : '';
        $vals[] = $autoFill ? 'Fine scale calibrations' : '';
        $vals[] = $autoFill ? 'Proficient' : '';
        $vals[] = $autoFill ? 'Proficient' : '';
        $vals[] = $autoFill ? 'Advanced' : '';
        $vals[] = $autoFill ? 'Curiosity, Teamwork, Logical Reasoning' : '';
        $vals[] = $autoFill ? 'None' : '';
        $vals[] = $autoFill ? 'Consistently exhibits scientific inquiry, exemplary teamwork, and methodical project execution' : '';
        $vals[] = $autoFill ? 'Recommend participation in district science congress and mentorship of junior students' : '';

        // 155 to 198: Part-C Curricular Domains (11 domains x 4)
        $domainNotes = [
            'L1' => 'Fluent reading comprehension and expressive creative writing with rich regional vocabulary',
            'L2' => 'Confident sentence formation and steady improvement in conversational fluency',
            'L3' => 'Good recognition of scripts and enthusiasm during classroom reading drills',
            'Math' => 'Demonstrates strong grasp of fractions, algebraic equations, and geometric proofs',
            'Science' => 'Displays keen observational habits and safe handling of laboratory apparatus',
            'Social_Science' => 'Shows deep empathy for historical timelines and geographical environment preservation',
            'Art_Education' => 'Highly creative in visual sketching, water-color painting, and traditional crafts',
            'Physical_Education' => 'Exemplary sportsmanship, stamina, and agility in indigenous team games',
            'Vocational_Skill_Edu' => 'Responsible handling of gardening tools, paper crafts, and pre-vocational models',
            'Other_Learning_Activities' => 'Active participant in morning assemblies, debate club, and cultural presentations',
            'Bagless_Days_Experience' => 'Engaged enthusiastically during outdoor field trips, heritage walks, and pottery workshops'
        ];

        foreach ($domainNotes as $dKey => $dNote) {
            $vals[] = $autoFill ? (($sIdx % 3 === 0) ? 'Advanced' : 'Proficient') : '';
            $vals[] = $autoFill ? 'Proficient' : '';
            $vals[] = $autoFill ? (($sIdx % 2 === 0) ? 'Advanced' : 'Proficient') : '';
            $vals[] = $autoFill ? $dNote : '';
        }

        // 199 to 216: Part-D Summative Assessment Marks (SA)
        // 8 Subjects: L1, L2, L3, Math, Science, SocialScience, Art, PE
        $marksList = [
            ['key' => 'l1', 'max' => 50, 'obt' => 43],
            ['key' => 'l2', 'max' => 50, 'obt' => 41],
            ['key' => 'l3', 'max' => 50, 'obt' => 42],
            ['key' => 'math', 'max' => 50, 'obt' => 45],
            ['key' => 'sci', 'max' => 50, 'obt' => 44],
            ['key' => 'soc', 'max' => 50, 'obt' => 42],
            ['key' => 'art', 'max' => 50, 'obt' => 46],
            ['key' => 'pe', 'max' => 50, 'obt' => 47],
        ];

        foreach ($marksList as $subIdx => $mInfo) {
            $maxM = $defaultMax[$mInfo['key']] ?? $mInfo['max'];
            $obtM = $autoFill ? min($maxM, max(30, $mInfo['obt'] + (($sIdx * 3 + $subIdx) % 7) - 3)) : '';
            $vals[] = $maxM;
            $vals[] = $obtM;
        }

        // Total SA Max & Obtained Formulas
        $vals[] = "=SUM({$k2c['L1_Max_Marks_SA']}{$rowNum},{$k2c['L2_Max_Marks_SA']}{$rowNum},{$k2c['L3_Max_Marks_SA']}{$rowNum},{$k2c['Math_Max_Marks_SA']}{$rowNum},{$k2c['Science_Max_Marks_SA']}{$rowNum},{$k2c['SocialScience_Max_Marks_SA']}{$rowNum},{$k2c['Art_Max_Marks_SA']}{$rowNum},{$k2c['PE_Max_Marks_SA']}{$rowNum})";
        $vals[] = "=SUM({$k2c['L1_Marks_Obtained_SA']}{$rowNum},{$k2c['L2_Marks_Obtained_SA']}{$rowNum},{$k2c['L3_Marks_Obtained_SA']}{$rowNum},{$k2c['Math_Marks_Obtained_SA']}{$rowNum},{$k2c['Science_Marks_Obtained_SA']}{$rowNum},{$k2c['SocialScience_Marks_Obtained_SA']}{$rowNum},{$k2c['Art_Marks_Obtained_SA']}{$rowNum},{$k2c['PE_Marks_Obtained_SA']}{$rowNum})";

        // Part-E HPC Credit Points & Declaration
        $vals[] = "=ROUND(({$k2c['Total_SA_Marks_Obtained']}{$rowNum}/{$k2c['Total_SA_Max_Marks']}{$rowNum})*20, 1)";
        $vals[] = "=ROUND(({$k2c['Total_SA_Marks_Obtained']}{$rowNum}/{$k2c['Total_SA_Max_Marks']}{$rowNum})*20, 1)";
        $vals[] = "={$k2c['Total_Credit_Earned_HPC_Formative']}{$rowNum}+{$k2c['Credit_Earned_Summative_SA']}{$rowNum}";
        $vals[] = "={$k2c['Total_Credit_Earned_Final']}{$rowNum}";
        $vals[] = "={$k2c['Total_SA_Marks_Obtained']}{$rowNum}";
        $vals[] = 'Yes';

        return $vals;
    }

    /**
     * Normalize parsed row into student structure with both exact keys and legacy aliases
     */
    public static function normalizeStudentRow(array $row): array
    {
        $normalized = $row;

        // Populate convenient aliases for dashboard and automation extension
        $normalized['name']         = $row['Student_Name'] ?? ($row['name'] ?? ($row['AboutMe_Student_Name_Tag'] ?? 'Student'));
        $normalized['Student_Name'] = $normalized['name'];
        $normalized['admission_no'] = $row['School_Admission_No'] ?? ($row['admission_no'] ?? '');
        $normalized['dob']          = $row['Date_of_Birth'] ?? ($row['dob'] ?? '');
        $normalized['gender']       = $row['Gender'] ?? ($row['gender'] ?? '');
        $normalized['apaar_id']     = $row['APAAR_ID'] ?? ($row['apaar_id'] ?? ($row['aadhaar'] ?? ''));
        $normalized['APAAR_ID']     = $normalized['apaar_id'];
        $normalized['login_id']     = $row['HPC_Login_ID'] ?? ($row['login_id'] ?? '');
        $normalized['udid']         = $row['UDID_Disability_ID'] ?? ($row['udid'] ?? '');
        $normalized['grade']        = $row['Grade'] ?? ($row['grade'] ?? '');
        $normalized['section']      = $row['Section'] ?? ($row['section'] ?? '');

        // Attendance aliases
        $months = ['apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec', 'jan', 'feb', 'mar'];
        foreach ($months as $m) {
            $cap = ucfirst($m);
            $normalized['work_' . $m] = $row['Working_Days_' . $cap] ?? ($row['work_' . $m] ?? 20);
            $normalized['pres_' . $m] = $row['Present_Days_' . $cap] ?? ($row['pres_' . $m] ?? 19);
        }

        // Marks map
        $normalized['marks'] = [
            'l1'   => ['max' => $row['L1_Max_Marks_SA'] ?? 50, 'obt' => $row['L1_Marks_Obtained_SA'] ?? 42],
            'l2'   => ['max' => $row['L2_Max_Marks_SA'] ?? 50, 'obt' => $row['L2_Marks_Obtained_SA'] ?? 40],
            'l3'   => ['max' => $row['L3_Max_Marks_SA'] ?? 50, 'obt' => $row['L3_Marks_Obtained_SA'] ?? 41],
            'math' => ['max' => $row['Math_Max_Marks_SA'] ?? 50, 'obt' => $row['Math_Marks_Obtained_SA'] ?? 44],
            'm'    => ['max' => $row['Math_Max_Marks_SA'] ?? 50, 'obt' => $row['Math_Marks_Obtained_SA'] ?? 44],
            'sci'  => ['max' => $row['Science_Max_Marks_SA'] ?? 50, 'obt' => $row['Science_Marks_Obtained_SA'] ?? 43],
            'soc'  => ['max' => $row['SocialScience_Max_Marks_SA'] ?? 50, 'obt' => $row['SocialScience_Marks_Obtained_SA'] ?? 42],
            'art'  => ['max' => $row['Art_Max_Marks_SA'] ?? 50, 'obt' => $row['Art_Marks_Obtained_SA'] ?? 46],
            'pe'   => ['max' => $row['PE_Max_Marks_SA'] ?? 50, 'obt' => $row['PE_Marks_Obtained_SA'] ?? 47],
        ];

        // Part A2/A3/A4 aliases
        $normalized['live_with']         = $row['AboutMe_Live_With'] ?? '';
        $normalized['stay_at']           = $row['AboutMe_Stay_At'] ?? '';
        $normalized['free_time']         = $row['AboutMe_Free_Time_Doing'] ?? '';
        $normalized['do_well']           = $row['AboutMe_Do_Very_Well'] ?? '';
        $normalized['do_better']         = $row['AboutMe_Could_Do_Better_Specially'] ?? '';
        $normalized['care_others']       = $row['AboutMe_Care_About_Others_By'] ?? '';
        $normalized['feel_proud']        = $row['AboutMe_Proud_Of_Myself_When'] ?? '';
        $normalized['acad_goal']         = $row['Academic_Goal'] ?? '';
        $normalized['pers_goal']         = $row['Personal_Goal'] ?? '';
        $normalized['ambition']          = $row['Ambition_Goal'] ?? '';
        $normalized['achieve_how']       = $row['Ambition_Achieve_Strategy'] ?? '';
        $normalized['skills_needed']     = $row['Ambition_5_Skills_Needed'] ?? '';
        $normalized['focus_subjects']    = $row['Ambition_Subjects_To_Focus'] ?? '';
        $normalized['habits_i_will']     = $row['Ambition_Habits_Required'] ?? '';
        $normalized['mentor']            = $row['Ambition_Guidance_From_Person'] ?? '';
        $normalized['mentor_help']       = $row['Ambition_How_Person_Will_Help'] ?? '';
        $normalized['parent_feedback']   = $row['Parent_Support_At_Home_ActionPlan'] ?? '';
        $normalized['act_subject']       = $row['Act_Subject_Domain'] ?? 'Science';
        $normalized['act_title']         = $row['Act_Name'] ?? 'Solar System 3D Model & Daily Life Geometry';
        $normalized['act_desc']          = $row['Act_Description_Instructions'] ?? '';
        $normalized['act_rubric_awareness']   = $row['Act_Rubric_Awareness_Level'] ?? 'Proficient';
        $normalized['act_rubric_sensitivity'] = $row['Act_Rubric_Sensitivity_Level'] ?? 'Proficient';
        $normalized['act_rubric_creativity']  = $row['Act_Rubric_Creativity_Level'] ?? 'Proficient';
        $normalized['act_peer_name']     = $row['Peer_Student_Name'] ?? '';
        $normalized['act_learnt']        = $row['SelfReflect_Learnt_Text'] ?? '';
        $normalized['act_interesting']   = $row['SelfReflect_MostInteresting_Text'] ?? '';
        $normalized['act_support_plan']  = $row['TeacherFeedback_Progress_Further'] ?? '';

        return $normalized;
    }
}
