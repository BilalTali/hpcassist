<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class HpcPreparatoryStageSchema
{
    /**
     * Grouping of all 177 columns into 12 distinct assessment sections
     */
    public static function getSections(): array
    {
        return [
            ['title' => '🔒 Part 1: Student Identity (Locked)', 'start' => 0, 'end' => 9, 'bg' => 'FF0F172A'],
            ['title' => '👤 Part 2: Medium & Guardian Profile', 'start' => 10, 'end' => 14, 'bg' => 'FF1E40AF'],
            ['title' => '📅 Part 3: Monthly Attendance (Working vs Present Days Apr–Mar)', 'start' => 15, 'end' => 38, 'bg' => 'FF0369A1'],
            ['title' => '📈 Part 4: Attendance Summary & Leave (Auto)', 'start' => 39, 'end' => 44, 'bg' => 'FF0D9488'],
            ['title' => '🖐️ Part 5: Part-A2 Hand of Self-Reflection & Feelings', 'start' => 45, 'end' => 66, 'bg' => 'FF6D28D9'],
            ['title' => '👥 Part 6: Part-A3 Peer 1 & Peer 2 Reviews', 'start' => 67, 'end' => 82, 'bg' => 'FF7C3AED'],
            ['title' => '🏡 Part 7: Part-A4 Home Learning Resources', 'start' => 83, 'end' => 90, 'bg' => 'FFBE185D'],
            ['title' => '🤝 Part 8: Part-A4 Parent Feedback & Support Areas', 'start' => 91, 'end' => 109, 'bg' => 'FFB91C1C'],
            ['title' => '🎨 Part 9: Part-B Holistic Activity 1 & Rubrics', 'start' => 110, 'end' => 130, 'bg' => 'FFD97706'],
            ['title' => '📊 Part 10: Part-C Curricular Domains (7 Subj x 4)', 'start' => 131, 'end' => 158, 'bg' => 'FF059669'],
            ['title' => '🎯 Part 11: Part-D Summative Assessment Marks (SA)', 'start' => 159, 'end' => 172, 'bg' => 'FF047857'],
            ['title' => '✍️ Part 12: Part-E HPC Credits & Teacher Declaration', 'start' => 173, 'end' => 176, 'bg' => 'FF334155'],
        ];
    }

    /**
     * Complete, authoritative definition of all 177 Preparatory Stage columns
     */
    public static function getColumns(): array
    {
        $rubricOpts = '"Beginner,Proficient,Advanced"';
        $yesNoOpts = '"Yes,No"';
        $parentQOpts = '"Yes,Sometimes,No,Not Sure"';

        return [
            ['key' => 'Student_Name', 'label' => '🔒 [LOCKED] Student Name', 'type' => 'locked', 'width' => 24, 'note' => 'Official full name of student (for identification)', 'classification' => 'locked'],
            ['key' => 'APAAR_ID', 'label' => '🔒 [LOCKED] APAAR ID', 'type' => 'locked', 'width' => 20, 'note' => 'Official 12-digit APAAR ID / Aadhaar for student identification', 'classification' => 'locked'],
            ['key' => 'School_Admission_No', 'label' => '🔒 [LOCKED] School Admission No', 'type' => 'locked', 'width' => 20, 'note' => 'Official school admission / roll number', 'classification' => 'locked'],
            ['key' => 'Date_of_Birth', 'label' => '🔒 [LOCKED] Date of Birth', 'type' => 'locked', 'width' => 20, 'note' => 'Student Date of Birth (YYYY-MM-DD)', 'classification' => 'locked'],
            ['key' => 'Gender', 'label' => '🔒 [LOCKED] Gender', 'type' => 'list', 'options' => "Male,Female,Other", 'width' => 20, 'note' => 'Student Gender (Male / Female / Other)', 'classification' => 'locked'],
            ['key' => 'HPC_Login_ID', 'label' => '🔒 [LOCKED] HPC Login ID', 'type' => 'locked', 'width' => 20, 'note' => 'Unique student login ID assigned by PARAKH portal', 'classification' => 'locked'],
            ['key' => 'UDID_Disability_ID', 'label' => '🔒 [LOCKED] UDID Disability ID', 'type' => 'locked', 'width' => 20, 'note' => 'Disability ID if applicable (or blank)', 'classification' => 'locked'],
            ['key' => 'Grade', 'label' => '🔒 [LOCKED] Grade', 'type' => 'list', 'options' => "Grade 3,Grade 4,Grade 5", 'width' => 20, 'note' => 'Class / Grade (Grade 3, Grade 4, Grade 5)', 'classification' => 'locked'],
            ['key' => 'Section', 'label' => '🔒 [LOCKED] Section', 'type' => 'list', 'options' => "A,B,C,D,E", 'width' => 20, 'note' => 'Assigned Section (A, B, C, D)', 'classification' => 'locked'],
            ['key' => 'Student_Photograph_Uploaded', 'label' => '🔒 [LOCKED] Student Photograph Uploaded', 'type' => 'list', 'options' => "Yes,No", 'width' => 20, 'note' => 'Yes / No status on portal', 'classification' => 'locked'],
            ['key' => 'Medium_of_Instruction', 'label' => '⚡ [AUTO] Medium of Instruction', 'type' => 'list', 'options' => '"English,Urdu,Hindi,Kashmiri,Dogri,Other"', 'width' => 22, 'note' => 'Instructional medium', 'classification' => 'auto'],
            ['key' => 'Area_Type', 'label' => '⚡ [AUTO] Area Type', 'type' => 'list', 'options' => '"Rural,Urban"', 'width' => 16, 'note' => 'Rural or Urban', 'classification' => 'auto'],
            ['key' => 'Mother_Tongue', 'label' => '⚡ [AUTO] Mother Tongue', 'type' => 'list', 'options' => '"Kashmiri,Urdu,Hindi,English,Dogri,Punjabi,Other"', 'width' => 18, 'note' => 'Student mother tongue', 'classification' => 'auto'],
            ['key' => 'Parent_Guardian_Role', 'label' => '⚡ [AUTO] Parent Guardian Role', 'type' => 'list', 'options' => '"Father,Mother,Guardian (Other than Parents)"', 'width' => 22, 'note' => 'Father / Mother / Guardian', 'classification' => 'auto'],
            ['key' => 'Guardian_Name_Detail', 'label' => '⚡ [AUTO] Guardian Name Detail', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],

            // Monthly Attendance: Working Days vs Present Days side-by-side for each month (Apr–Mar)
            ['key' => 'Working_Days_Apr', 'label' => '⚡ [AUTO] Working Days - Apr', 'type' => 'integer', 'width' => 16, 'note' => 'Official Apr working days', 'classification' => 'auto'],
            ['key' => 'Present_Days_Apr', 'label' => '⚡ [AUTO] Present Days - Apr', 'type' => 'integer', 'width' => 16, 'note' => 'Student Apr present days', 'classification' => 'auto'],
            ['key' => 'Working_Days_May', 'label' => '⚡ [AUTO] Working Days - May', 'type' => 'integer', 'width' => 16, 'note' => 'Official May working days', 'classification' => 'auto'],
            ['key' => 'Present_Days_May', 'label' => '⚡ [AUTO] Present Days - May', 'type' => 'integer', 'width' => 16, 'note' => 'Student May present days', 'classification' => 'auto'],
            ['key' => 'Working_Days_Jun', 'label' => '⚡ [AUTO] Working Days - Jun', 'type' => 'integer', 'width' => 16, 'note' => 'Official Jun working days', 'classification' => 'auto'],
            ['key' => 'Present_Days_Jun', 'label' => '⚡ [AUTO] Present Days - Jun', 'type' => 'integer', 'width' => 16, 'note' => 'Student Jun present days', 'classification' => 'auto'],
            ['key' => 'Working_Days_Jul', 'label' => '⚡ [AUTO] Working Days - Jul', 'type' => 'integer', 'width' => 16, 'note' => 'Official Jul working days', 'classification' => 'auto'],
            ['key' => 'Present_Days_Jul', 'label' => '⚡ [AUTO] Present Days - Jul', 'type' => 'integer', 'width' => 16, 'note' => 'Student Jul present days', 'classification' => 'auto'],
            ['key' => 'Working_Days_Aug', 'label' => '⚡ [AUTO] Working Days - Aug', 'type' => 'integer', 'width' => 16, 'note' => 'Official Aug working days', 'classification' => 'auto'],
            ['key' => 'Present_Days_Aug', 'label' => '⚡ [AUTO] Present Days - Aug', 'type' => 'integer', 'width' => 16, 'note' => 'Student Aug present days', 'classification' => 'auto'],
            ['key' => 'Working_Days_Sep', 'label' => '⚡ [AUTO] Working Days - Sep', 'type' => 'integer', 'width' => 16, 'note' => 'Official Sep working days', 'classification' => 'auto'],
            ['key' => 'Present_Days_Sep', 'label' => '⚡ [AUTO] Present Days - Sep', 'type' => 'integer', 'width' => 16, 'note' => 'Student Sep present days', 'classification' => 'auto'],
            ['key' => 'Working_Days_Oct', 'label' => '⚡ [AUTO] Working Days - Oct', 'type' => 'integer', 'width' => 16, 'note' => 'Official Oct working days', 'classification' => 'auto'],
            ['key' => 'Present_Days_Oct', 'label' => '⚡ [AUTO] Present Days - Oct', 'type' => 'integer', 'width' => 16, 'note' => 'Student Oct present days', 'classification' => 'auto'],
            ['key' => 'Working_Days_Nov', 'label' => '⚡ [AUTO] Working Days - Nov', 'type' => 'integer', 'width' => 16, 'note' => 'Official Nov working days', 'classification' => 'auto'],
            ['key' => 'Present_Days_Nov', 'label' => '⚡ [AUTO] Present Days - Nov', 'type' => 'integer', 'width' => 16, 'note' => 'Student Nov present days', 'classification' => 'auto'],
            ['key' => 'Working_Days_Dec', 'label' => '⚡ [AUTO] Working Days - Dec', 'type' => 'integer', 'width' => 16, 'note' => 'Official Dec working days', 'classification' => 'auto'],
            ['key' => 'Present_Days_Dec', 'label' => '⚡ [AUTO] Present Days - Dec', 'type' => 'integer', 'width' => 16, 'note' => 'Student Dec present days', 'classification' => 'auto'],
            ['key' => 'Working_Days_Jan', 'label' => '⚡ [AUTO] Working Days - Jan', 'type' => 'integer', 'width' => 16, 'note' => 'Official Jan working days', 'classification' => 'auto'],
            ['key' => 'Present_Days_Jan', 'label' => '⚡ [AUTO] Present Days - Jan', 'type' => 'integer', 'width' => 16, 'note' => 'Student Jan present days', 'classification' => 'auto'],
            ['key' => 'Working_Days_Feb', 'label' => '⚡ [AUTO] Working Days - Feb', 'type' => 'integer', 'width' => 16, 'note' => 'Official Feb working days', 'classification' => 'auto'],
            ['key' => 'Present_Days_Feb', 'label' => '⚡ [AUTO] Present Days - Feb', 'type' => 'integer', 'width' => 16, 'note' => 'Student Feb present days', 'classification' => 'auto'],
            ['key' => 'Working_Days_Mar', 'label' => '⚡ [AUTO] Working Days - Mar', 'type' => 'integer', 'width' => 16, 'note' => 'Official Mar working days', 'classification' => 'auto'],
            ['key' => 'Present_Days_Mar', 'label' => '⚡ [AUTO] Present Days - Mar', 'type' => 'integer', 'width' => 16, 'note' => 'Student Mar present days', 'classification' => 'auto'],
            ['key' => 'Total_Working_Days', 'label' => '⚙️ [FORMULA] Total Working Days', 'type' => 'calc', 'width' => 22, 'note' => 'Total academic working days', 'classification' => 'calc'],
            ['key' => 'Total_Present_Days', 'label' => '⚙️ [FORMULA] Total Present Days', 'type' => 'calc', 'width' => 22, 'note' => 'Total student present days', 'classification' => 'calc'],
            ['key' => 'Overall_Attendance_Percentage', 'label' => '⚙️ [FORMULA] Overall Attendance Percentage', 'type' => 'calc', 'width' => 22, 'note' => 'Formula: =(Present/Working)*100', 'classification' => 'calc'],
            ['key' => 'Low_Attendance_Reason', 'label' => '⚡ [AUTO] Low Attendance Reason', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Total_Absence_Count', 'label' => '⚙️ [FORMULA] Total Absence Count', 'type' => 'calc', 'width' => 22, 'note' => 'Formula: =MAX(0, Working-Present)', 'classification' => 'calc'],
            ['key' => 'Student_Interests', 'label' => '⚡ [AUTO] Student Interests', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Hand_Reflection_Thumb_GoodAt', 'label' => '⚡ [AUTO] Hand Reflection Thumb GoodAt', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Hand_Reflection_Index_NotGoodAt', 'label' => '⚡ [AUTO] Hand Reflection Index NotGoodAt', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Hand_Reflection_Middle_ImproveSkill', 'label' => '⚡ [AUTO] Hand Reflection Middle ImproveSkill', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Hand_Reflection_Ring_LikeTo', 'label' => '⚡ [AUTO] Hand Reflection Ring LikeTo', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Hand_Reflection_Little_Aspiration', 'label' => '⚡ [AUTO] When I grow up I want to be (Aspiration)', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'General_Reflection_Prompt_1', 'label' => '⚡ [AUTO] One Person Who Inspires Me (My Idol)', 'type' => 'string', 'width' => 30, 'note' => 'Person who inspires student (e.g. My class teacher and parents)', 'classification' => 'auto'],
            ['key' => 'General_Reflection_Prompt_2', 'label' => '⚡ [AUTO] Want to Learn This Year - 1', 'type' => 'string', 'width' => 30, 'note' => 'Skill/topic student wants to learn (e.g. Reading storybooks fluently)', 'classification' => 'auto'],
            ['key' => 'General_Reflection_Prompt_3', 'label' => '⚡ [AUTO] Want to Learn This Year - 2', 'type' => 'string', 'width' => 30, 'note' => 'Skill/topic student wants to learn (e.g. Mental math calculations)', 'classification' => 'auto'],
            ['key' => 'General_Reflection_Prompt_4', 'label' => '⚡ [AUTO] Want to Learn This Year - 3', 'type' => 'string', 'width' => 30, 'note' => 'Skill/topic student wants to learn (e.g. Scientific plant experiments)', 'classification' => 'auto'],
            ['key' => 'Favorite_Food', 'label' => '⚡ [AUTO] Favorite Food', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Favorite_Games', 'label' => '⚡ [AUTO] Favorite Games', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Favorite_Festival', 'label' => '⚡ [AUTO] Favorite Festival', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Observation_Statement_Row_1', 'label' => '⚡ [AUTO] Observation Statement Row 1', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Observation_Statement_Row_2', 'label' => '⚡ [AUTO] Observation Statement Row 2', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Observation_Statement_Row_3', 'label' => '⚡ [AUTO] Observation Statement Row 3', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Self_Feel_Talk_About_Feelings', 'label' => '⚡ [AUTO] Self Feel Talk About Feelings', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Self_Feel_Calm_Difficult_Situations', 'label' => '⚡ [AUTO] Self Feel Calm Difficult Situations', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Self_Feel_Understand_Friends', 'label' => '⚡ [AUTO] Self Feel Understand Friends', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Self_Feel_Respect_Others_Views', 'label' => '⚡ [AUTO] Self Feel Respect Others Views', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Self_Feel_Help_Friends_Make_Up', 'label' => '⚡ [AUTO] Self Feel Help Friends Make Up', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Self_Feel_Make_Sad_Friends_Better', 'label' => '⚡ [AUTO] Self Feel Make Sad Friends Better', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Self_Feel_Do_Well_At_School', 'label' => '⚡ [AUTO] Self Feel Do Well At School', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Peer1_Student_Name', 'label' => '⚡ [AUTO] Peer1 Student Name', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Peer1_Target_Friend_Name', 'label' => '⚡ [AUTO] Peer1 Target Friend Name', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Peer1_Q1_Talks_About_Feelings', 'label' => '⚡ [AUTO] Peer1 Q1 Talks About Feelings', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Peer1_Q2_Calms_Down_Difficulties', 'label' => '⚡ [AUTO] Peer1 Q2 Calms Down Difficulties', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Peer1_Q3_Understands_Friends', 'label' => '⚡ [AUTO] Peer1 Q3 Understands Friends', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Peer1_Q4_Respects_Views_Ideas', 'label' => '⚡ [AUTO] Peer1 Q4 Respects Views Ideas', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Peer1_Q5_Helps_Make_Up_Fights', 'label' => '⚡ [AUTO] Peer1 Q5 Helps Make Up Fights', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Peer1_Q6_Makes_Sad_Others_Better', 'label' => '⚡ [AUTO] Peer1 Q6 Makes Sad Others Better', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Peer2_Student_Name', 'label' => '⚡ [AUTO] Peer2 Student Name', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Peer2_Target_Friend_Name', 'label' => '⚡ [AUTO] Peer2 Target Friend Name', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Peer2_Q1_Talks_About_Feelings', 'label' => '⚡ [AUTO] Peer2 Q1 Talks About Feelings', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Peer2_Q2_Calms_Down_Difficulties', 'label' => '⚡ [AUTO] Peer2 Q2 Calms Down Difficulties', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Peer2_Q3_Understands_Friends', 'label' => '⚡ [AUTO] Peer2 Q3 Understands Friends', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Peer2_Q4_Respects_Views_Ideas', 'label' => '⚡ [AUTO] Peer2 Q4 Respects Views Ideas', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Peer2_Q5_Helps_Make_Up_Fights', 'label' => '⚡ [AUTO] Peer2 Q5 Helps Make Up Fights', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Peer2_Q6_Makes_Sad_Others_Better', 'label' => '⚡ [AUTO] Peer2 Q6 Makes Sad Others Better', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Home_Resource_Books_Magazines', 'label' => '⚡ [AUTO] Home Resource: Books Magazines', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Resource availability at home (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Home_Resource_Newspaper', 'label' => '⚡ [AUTO] Home Resource: Newspaper', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Resource availability at home (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Home_Resource_Toys_Games_Sports', 'label' => '⚡ [AUTO] Home Resource: Toys Games Sports', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Resource availability at home (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Home_Resource_Phone_Computer', 'label' => '⚡ [AUTO] Home Resource: Phone Computer', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Resource availability at home (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Home_Resource_Internet', 'label' => '⚡ [AUTO] Home Resource: Internet', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Resource availability at home (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Home_Resource_Radio_TV_Broadcast', 'label' => '⚡ [AUTO] Home Resource: Radio TV Broadcast', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Resource availability at home (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Home_Resource_For_CwD', 'label' => '⚡ [AUTO] Home Resource: For CwD', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Resource availability at home (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Home_Resource_Other_Specify', 'label' => '⚡ [AUTO] Home Resource Other Specify', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Parent_Q01_Welcoming_Safe_Space', 'label' => '⚡ [AUTO] Parent Q01 Welcoming Safe Space', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Parent rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Parent_Q02_Participates_Activities', 'label' => '⚡ [AUTO] Parent Q02 Participates Activities', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Parent rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Parent_Q03_Finds_Curriculum_Diff', 'label' => '⚡ [AUTO] Parent Q03 Finds Curriculum Diff', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Parent rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Parent_Q04_Making_Good_Progress', 'label' => '⚡ [AUTO] Parent Q04 Making Good Progress', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Parent rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Parent_Q05_Gets_School_Support', 'label' => '⚡ [AUTO] Parent Q05 Gets School Support', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Parent rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Parent_Q06_Expresses_Feelings', 'label' => '⚡ [AUTO] Parent Q06 Expresses Feelings', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Parent rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Parent_Q07_Calms_Difficult_Situations', 'label' => '⚡ [AUTO] Parent Q07 Calms Difficult Situations', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Parent rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Parent_Q08_Understands_Friends', 'label' => '⚡ [AUTO] Parent Q08 Understands Friends', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Parent rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Parent_Q09_Respects_Others_Views', 'label' => '⚡ [AUTO] Parent Q09 Respects Others Views', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Parent rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Parent_Q10_Helps_Friends_Make_Up', 'label' => '⚡ [AUTO] Parent Q10 Helps Friends Make Up', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Parent rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Parent_Q11_Comforts_Sad_Friends', 'label' => '⚡ [AUTO] Parent Q11 Comforts Sad Friends', 'type' => 'list', 'options' => $parentQOpts, 'width' => 24, 'note' => 'Parent rating: Yes, Sometimes, No, Not Sure', 'classification' => 'auto'],
            ['key' => 'Parent_Support_Oral_Communication', 'label' => '⚡ [AUTO] Parent Support: Oral_Communication', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Support needed in Oral_Communication (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Parent_Support_Reading', 'label' => '⚡ [AUTO] Parent Support: Reading', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Support needed in Reading (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Parent_Support_Numbers_Math', 'label' => '⚡ [AUTO] Parent Support: Numbers_Math', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Support needed in Numbers_Math (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Parent_Support_Self_Confidence', 'label' => '⚡ [AUTO] Parent Support: Self_Confidence', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Support needed in Self_Confidence (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Parent_Support_Working_Children', 'label' => '⚡ [AUTO] Parent Support: Working_Children', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Support needed in Working_Children (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Parent_Support_Working_Independently', 'label' => '⚡ [AUTO] Parent Support: Working_Independently', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Support needed in Working_Independently (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Parent_Support_Other_Subject_Areas', 'label' => '⚡ [AUTO] Parent Support: Other_Subject_Areas', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Support needed in Other_Subject_Areas (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Parent_Support_Other_Specify_Text', 'label' => '✏️ [INPUT] Parent Support Other Specify Text', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'input'],
            ['key' => 'Act1_Name', 'label' => '⚡ [AUTO] Act1 Name', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Act1_Subject_Domain', 'label' => '⚡ [AUTO] Act1 Subject Domain', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Act1_Curricular_Goal_Code', 'label' => '⚡ [AUTO] Act1 Curricular Goal Code', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Act1_Competency_Codes', 'label' => '⚡ [AUTO] Act1 Competency Codes', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Act1_Description_Instructions', 'label' => '⚡ [AUTO] Act1 Description Instructions', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Act1_Assessment_Questions', 'label' => '⚡ [AUTO] Act1 Assessment Questions', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Act1_Rubric_Awareness_Desc', 'label' => '⚡ [AUTO] Act1 Rubric Awareness Desc', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Act1_Rubric_Awareness_Level', 'label' => '⚡ [AUTO] Act1 Rubric Awareness Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Act1_Rubric_Sensitivity_Desc', 'label' => '⚡ [AUTO] Act1 Rubric Sensitivity Desc', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Act1_Rubric_Sensitivity_Level', 'label' => '⚡ [AUTO] Act1 Rubric Sensitivity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Act1_Rubric_Creativity_Desc', 'label' => '⚡ [AUTO] Act1 Rubric Creativity Desc', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'auto'],
            ['key' => 'Act1_Rubric_Creativity_Level', 'label' => '⚡ [AUTO] Act1 Rubric Creativity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Act1_Observational_Notes', 'label' => '✏️ [INPUT] Act1 Observational Notes', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'input'],
            ['key' => 'Act1_SelfAssess_FollowDirections', 'label' => '⚡ [AUTO] Act1 SelfAssess FollowDirections', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Confirmation (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Act1_SelfAssess_LikedTask', 'label' => '⚡ [AUTO] Act1 SelfAssess LikedTask', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Confirmation (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Act1_SelfAssess_AskedHelpTeacher', 'label' => '⚡ [AUTO] Act1 SelfAssess AskedHelpTeacher', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Confirmation (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Act1_SelfAssess_TriedBestEffort', 'label' => '⚡ [AUTO] Act1 SelfAssess TriedBestEffort', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Confirmation (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Act1_SelfAssess_ProudOfWork', 'label' => '⚡ [AUTO] Act1 SelfAssess ProudOfWork', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Confirmation (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Act1_SelfAssess_DoTaskAgain', 'label' => '⚡ [AUTO] Act1 SelfAssess DoTaskAgain', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Confirmation (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Act1_SelfAssess_LikedWorkingPeers', 'label' => '⚡ [AUTO] Act1 SelfAssess LikedWorkingPeers', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Confirmation (Yes / No)', 'classification' => 'auto'],
            ['key' => 'Act1_SelfAssess_PeerSupportGiven', 'label' => '⚡ [AUTO] Act1 SelfAssess PeerSupportGiven', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Confirmation (Yes / No)', 'classification' => 'auto'],
            ['key' => 'L1_Awareness_Level', 'label' => '⚡ [AUTO] L1 Awareness Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'L1_Sensitivity_Level', 'label' => '⚡ [AUTO] L1 Sensitivity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'L1_Creativity_Level', 'label' => '⚡ [AUTO] L1 Creativity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'L1_Observational_Note', 'label' => '✏️ [INPUT] L1 Observational Note', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'input'],
            ['key' => 'L2_Awareness_Level', 'label' => '⚡ [AUTO] L2 Awareness Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'L2_Sensitivity_Level', 'label' => '⚡ [AUTO] L2 Sensitivity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'L2_Creativity_Level', 'label' => '⚡ [AUTO] L2 Creativity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'L2_Observational_Note', 'label' => '✏️ [INPUT] L2 Observational Note', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'input'],
            ['key' => 'Math_Awareness_Level', 'label' => '⚡ [AUTO] Math Awareness Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Math_Sensitivity_Level', 'label' => '⚡ [AUTO] Math Sensitivity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Math_Creativity_Level', 'label' => '⚡ [AUTO] Math Creativity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Math_Observational_Note', 'label' => '✏️ [INPUT] Math Observational Note', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'input'],
            ['key' => 'TWC_Awareness_Level', 'label' => '⚡ [AUTO] TWC Awareness Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'TWC_Sensitivity_Level', 'label' => '⚡ [AUTO] TWC Sensitivity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'TWC_Creativity_Level', 'label' => '⚡ [AUTO] TWC Creativity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'TWC_Observational_Note', 'label' => '✏️ [INPUT] TWC Observational Note', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'input'],
            ['key' => 'Art_Awareness_Level', 'label' => '⚡ [AUTO] Art Awareness Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Art_Sensitivity_Level', 'label' => '⚡ [AUTO] Art Sensitivity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Art_Creativity_Level', 'label' => '⚡ [AUTO] Art Creativity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Art_Observational_Note', 'label' => '✏️ [INPUT] Art Observational Note', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'input'],
            ['key' => 'PE_Awareness_Level', 'label' => '⚡ [AUTO] PE Awareness Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'PE_Sensitivity_Level', 'label' => '⚡ [AUTO] PE Sensitivity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'PE_Creativity_Level', 'label' => '⚡ [AUTO] PE Creativity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'PE_Observational_Note', 'label' => '✏️ [INPUT] PE Observational Note', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'input'],
            ['key' => 'Other_Awareness_Level', 'label' => '⚡ [AUTO] Other Awareness Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Other_Sensitivity_Level', 'label' => '⚡ [AUTO] Other Sensitivity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Other_Creativity_Level', 'label' => '⚡ [AUTO] Other Creativity Level', 'type' => 'list', 'options' => $rubricOpts, 'width' => 22, 'note' => 'Select: Beginner, Proficient, Advanced (Default: Proficient)', 'classification' => 'auto'],
            ['key' => 'Other_Observational_Note', 'label' => '✏️ [INPUT] Other Observational Note', 'type' => 'string', 'width' => 30, 'note' => 'Learner voice or teacher observational statement', 'classification' => 'input'],
            ['key' => 'L1_Max_Marks_SA', 'label' => '🎯 [AUTO] L1 Max Marks SA', 'type' => 'integer', 'width' => 18, 'note' => 'Summative Assessment Maximum Marks (Default 50)', 'classification' => 'auto'],
            ['key' => 'L1_Marks_Obtained_SA', 'label' => '✏️ [INPUT] L1 Marks Obtained SA', 'type' => 'integer', 'width' => 18, 'note' => 'Summative Assessment Marks Obtained (Teacher Entry / 0 to Max)', 'classification' => 'input'],
            ['key' => 'L2_Max_Marks_SA', 'label' => '🎯 [AUTO] L2 Max Marks SA', 'type' => 'integer', 'width' => 18, 'note' => 'Summative Assessment Maximum Marks (Default 50)', 'classification' => 'auto'],
            ['key' => 'L2_Marks_Obtained_SA', 'label' => '✏️ [INPUT] L2 Marks Obtained SA', 'type' => 'integer', 'width' => 18, 'note' => 'Summative Assessment Marks Obtained (Teacher Entry / 0 to Max)', 'classification' => 'input'],
            ['key' => 'Math_Max_Marks_SA', 'label' => '🎯 [AUTO] Math Max Marks SA', 'type' => 'integer', 'width' => 18, 'note' => 'Summative Assessment Maximum Marks (Default 50)', 'classification' => 'auto'],
            ['key' => 'Math_Marks_Obtained_SA', 'label' => '✏️ [INPUT] Math Marks Obtained SA', 'type' => 'integer', 'width' => 18, 'note' => 'Summative Assessment Marks Obtained (Teacher Entry / 0 to Max)', 'classification' => 'input'],
            ['key' => 'TWC_Max_Marks_SA', 'label' => '🎯 [AUTO] TWC Max Marks SA', 'type' => 'integer', 'width' => 18, 'note' => 'Summative Assessment Maximum Marks (Default 50)', 'classification' => 'auto'],
            ['key' => 'TWC_Marks_Obtained_SA', 'label' => '✏️ [INPUT] TWC Marks Obtained SA', 'type' => 'integer', 'width' => 18, 'note' => 'Summative Assessment Marks Obtained (Teacher Entry / 0 to Max)', 'classification' => 'input'],
            ['key' => 'Art_Max_Marks_SA', 'label' => '🎯 [AUTO] Art Max Marks SA', 'type' => 'integer', 'width' => 18, 'note' => 'Summative Assessment Maximum Marks (Default 50)', 'classification' => 'auto'],
            ['key' => 'Art_Marks_Obtained_SA', 'label' => '✏️ [INPUT] Art Marks Obtained SA', 'type' => 'integer', 'width' => 18, 'note' => 'Summative Assessment Marks Obtained (Teacher Entry / 0 to Max)', 'classification' => 'input'],
            ['key' => 'PE_Max_Marks_SA', 'label' => '🎯 [AUTO] PE Max Marks SA', 'type' => 'integer', 'width' => 18, 'note' => 'Summative Assessment Maximum Marks (Default 50)', 'classification' => 'auto'],
            ['key' => 'PE_Marks_Obtained_SA', 'label' => '✏️ [INPUT] PE Marks Obtained SA', 'type' => 'integer', 'width' => 18, 'note' => 'Summative Assessment Marks Obtained (Teacher Entry / 0 to Max)', 'classification' => 'input'],
            ['key' => 'Total_SA_Max_Marks', 'label' => '⚙️ [FORMULA] Total SA Max Marks', 'type' => 'calc', 'width' => 22, 'note' => 'Formula: =SUM(FC{row},FE{row},FG{row},FI{row},FK{row},FM{row})', 'classification' => 'calc'],
            ['key' => 'Total_SA_Marks_Obtained', 'label' => '⚙️ [FORMULA] Total SA Marks Obtained', 'type' => 'calc', 'width' => 22, 'note' => 'Formula: =SUM(FD{row},FF{row},FH{row},FJ{row},FL{row},FN{row})', 'classification' => 'calc'],
            ['key' => 'Total_Credit_Earned_HPC_Formative', 'label' => '⚙️ [FORMULA] Total Credit Earned HPC Formative', 'type' => 'calc', 'width' => 22, 'note' => 'Formula: =ROUND((FP{row}/FO{row})*20, 1)', 'classification' => 'calc'],
            ['key' => 'Credit_Earned_Summative_SA', 'label' => '⚙️ [FORMULA] Credit Earned Summative SA', 'type' => 'calc', 'width' => 22, 'note' => 'Formula: =ROUND((FP{row}/FO{row})*20, 1)', 'classification' => 'calc'],
            ['key' => 'Total_Credit_Points_Earned', 'label' => '⚙️ [FORMULA] Total Credit Points Earned', 'type' => 'calc', 'width' => 22, 'note' => 'Formula: =FQ{row}+FR{row}', 'classification' => 'calc'],
            ['key' => 'Teacher_Declaration_Agreed', 'label' => '⚡ [AUTO] Teacher Declaration Agreed', 'type' => 'list', 'options' => $yesNoOpts, 'width' => 22, 'note' => 'Confirmation (Yes / No)', 'classification' => 'auto'],
        ];
    }

    /**
     * Build starter row values for Preparatory Stage (exactly 176 values)
     */
    public static function buildStarterRow(int $sIdx, array $st, array $defaultWorkMap, array $defaultMax, int $rowNum, bool $autoFill = true, array $options = []): array
    {
        $apaarId = !empty($st['apaar_id']) ? $st['apaar_id'] : '';
        $loginId = !empty($st['login_id']) ? $st['login_id'] : (!empty($st['hpc_id']) ? $st['hpc_id'] : '');
        $name    = !empty($st['name']) ? $st['name'] : ('Student ' . ($sIdx + 1));
        $status  = !empty($st['status']) ? $st['status'] : 'HPC In Progress';
        $admNo   = !empty($st['admission_no']) ? $st['admission_no'] : (!empty($st['roll_no']) ? $st['roll_no'] : '');
        $grade   = !empty($st['grade']) ? $st['grade'] : 'Grade 4';
        $section = !empty($st['section']) ? $st['section'] : 'A';
        $gender  = !empty($st['gender']) ? $st['gender'] : ($sIdx % 2 === 0 ? 'Male' : 'Female');
        $dob     = !empty($st['dob']) ? $st['dob'] : '2015-06-20';
        $udid    = !empty($st['udid']) ? $st['udid'] : '';

        $peers = ['Ayaan Ahmad', 'Zaid Farooq', 'Fatima Zahra', 'Maryam Jan', 'Sahil Bashir', 'Iqra Parveen', 'Arsalan Mir', 'Insha Tariq'];
        $peerName  = $peers[$sIdx % count($peers)];
        $peer2Name = $peers[($sIdx + 1) % count($peers)];

        $interests = ['Drawing and Coloring', 'Storytelling and Rhymes', 'Clay Toys & Origami', 'Outdoor Running and Kho-Kho', 'Plant Seed Germination', 'Building Blocks & Toy Clocks'];
        $interest  = $interests[$sIdx % count($interests)];

        $months = ['apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec', 'jan', 'feb', 'mar'];
        $defaultWorkDays = [20, 22, 10, 20, 22, 21, 19, 20, 15, 15, 20, 18];

        $colKeys = array_column(self::getColumns(), 'key');
        $k2c = [];
        foreach ($colKeys as $idx => $k) {
            $k2c[$k] = Coordinate::stringFromColumnIndex($idx + 1);
        }

        $vals = [];

        // 1 to 10: Locked Identity (Col A: Student Name, Col B: APAAR ID)
        $vals[] = $name;
        $vals[] = $apaarId;
        $vals[] = $admNo;
        $vals[] = $dob;
        $vals[] = $gender;
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

        // 16 to 39: Monthly Attendance (Working Days vs Present Days side-by-side for each month)
        foreach ($months as $mIdx => $m) {
            $w = $defaultWorkMap[$m] ?? $defaultWorkDays[$mIdx];
            $p = $autoFill ? max(0, $w - ($sIdx % 3 === 0 ? 1 : 2)) : '';
            $vals[] = $w;
            $vals[] = $p;
        }

        // 40 to 45: Attendance Summary Formulas
        $vals[] = "=SUM(" . implode(',', array_map(fn($m) => "{$k2c['Working_Days_' . ucfirst($m)]}{$rowNum}", $months)) . ")";
        $vals[] = "=SUM(" . implode(',', array_map(fn($m) => "{$k2c['Present_Days_' . ucfirst($m)]}{$rowNum}", $months)) . ")";
        $vals[] = "=IF({$k2c['Total_Working_Days']}{$rowNum}>0, ROUND(({$k2c['Total_Present_Days']}{$rowNum}/{$k2c['Total_Working_Days']}{$rowNum})*100, 1) & \"%\", \"\")";
        $vals[] = $autoFill ? 'Satisfactory regular attendance maintained throughout academic session' : '';
        $vals[] = "=MAX(0, {$k2c['Total_Working_Days']}{$rowNum}-{$k2c['Total_Present_Days']}{$rowNum})";
        $vals[] = $autoFill ? $interest : '';

        // 45 to 66: Part-A2 Hand of Self-Reflection & Feelings (22 columns)
        $vals[] = $autoFill ? 'Arithmetic calculations and drawing flowers' : '';
        $vals[] = $autoFill ? 'Fast cursive handwriting and long spellings' : '';
        $vals[] = $autoFill ? 'Reading storybooks fluently without hesitation' : '';
        $vals[] = $autoFill ? 'Singing rhymes, origami folding, and outdoor games' : '';
        $vals[] = $autoFill ? 'Become a helpful school teacher or scientist' : '';
        $vals[] = $autoFill ? 'My class teacher and parents' : '';
        $vals[] = $autoFill ? 'Reading storybooks fluently and learning new words' : '';
        $vals[] = $autoFill ? 'Mental math calculations and geometry shapes' : '';
        $vals[] = $autoFill ? 'Scientific plant experiments and art and craft' : '';
        $vals[] = $autoFill ? 'Dal Chawal, Apples, and seasonal fresh fruits' : '';
        $vals[] = $autoFill ? 'Kho-Kho, Hide and Seek, and Toy-Clock building' : '';
        $vals[] = $autoFill ? 'Eid-ul-Fitr, Diwali, and National Days' : '';
        $vals[] = $autoFill ? 'Active participant in morning assembly rhymes and physical exercises' : '';
        $vals[] = $autoFill ? 'Shows gentle care for plants and living creatures in school garden' : '';
        $vals[] = $autoFill ? 'Always greets teachers and classmates with genuine warmth and respect' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Sometimes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';

        // 67 to 82: Part-A3 Peer 1 & Peer 2 Reviews (16 columns)
        $vals[] = $peerName;
        $vals[] = $name;
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Sometimes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $peer2Name;
        $vals[] = $name;
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';

        // 83 to 90: Part-A4 Home Resources (8 columns)
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'No' : '';
        $vals[] = $autoFill ? 'Educational charts and alphabet cards' : '';

        // 91 to 109: Part-A4 Parent Feedback & Support Areas (19 columns)
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Sometimes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'No' : '';
        $vals[] = $autoFill ? 'No' : '';
        $vals[] = $autoFill ? 'No' : '';
        $vals[] = $autoFill ? 'No' : '';
        $vals[] = $autoFill ? 'No' : '';
        $vals[] = $autoFill ? 'No' : '';
        $vals[] = $autoFill ? 'No' : '';
        $vals[] = $autoFill ? 'Provide 30 minutes of guided reading and arithmetic practice at home' : '';

        // 110 to 130: Part-B Holistic Activity 1 & Rubrics (21 columns)
        $vals[] = $autoFill ? 'Nature Observation & Plant Seed Germination Study' : '';
        $vals[] = $autoFill ? 'The World Around Us' : '';
        $vals[] = $autoFill ? 'CG-EVS-02' : '';
        $vals[] = $autoFill ? 'C-2.1, C-2.3' : '';
        $vals[] = $autoFill ? 'Planted local seeds, tracked daily sprout growth in leaf journal, and compared soil conditions' : '';
        $vals[] = $autoFill ? '1. What does a seed need to sprout? 2. How did sprout height change over 7 days?' : '';
        $vals[] = $autoFill ? 'Identifies basic plant parts with guidance' : '';
        $vals[] = $autoFill ? 'Proficient' : '';
        $vals[] = $autoFill ? 'Cares for classroom seedlings and waters plants responsibly' : '';
        $vals[] = $autoFill ? 'Proficient' : '';
        $vals[] = $autoFill ? 'Draws colorful and accurate seed growth sketches in notebook' : '';
        $vals[] = $autoFill ? (($sIdx % 2 === 0) ? 'Advanced' : 'Proficient') : '';
        $vals[] = $autoFill ? 'Showed exceptional enthusiasm during garden study and helped peers record leaf measurements' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';
        $vals[] = $autoFill ? 'Yes' : '';

        // 131 to 158: Part-C Curricular Competency Domains (7 domains x 4 = 28 columns)
        // Domains: L1, L2, Math, TWC, Art, PE, Other
        $prepDomains = [
            'L1' => 'Expressive story reading and lively character dialogue recitation in mother tongue',
            'L2' => 'Reads simple text sentences confidently and matches picture words accurately',
            'Math' => 'Solves basic addition and subtraction with counters and recognizes toy clock shapes',
            'TWC' => 'Observes nature with curiosity and records seasonal weather changes in notebook',
            'Art' => 'Enjoys finger painting, clay toy modeling, and paper craft origami folding',
            'PE' => 'Demonstrates active agility, fairness, and cheerful participation in games like Kho-Kho',
            'Other' => 'Listens patiently during circle time and helps keep learning toys in order'
        ];

        foreach ($prepDomains as $pDom => $pNote) {
            $vals[] = $autoFill ? (($sIdx % 3 === 0) ? 'Advanced' : 'Proficient') : '';
            $vals[] = $autoFill ? 'Proficient' : '';
            $vals[] = $autoFill ? (($sIdx % 2 === 0) ? 'Advanced' : 'Proficient') : '';
            $vals[] = $autoFill ? $pNote : '';
        }

        // 159 to 172: Part-D Summative Assessment Marks (SA) (6 subjects x 2 = 12 + 2 formulas = 14 columns)
        // Subjects: L1, L2, Math, TWC, Art, PE
        $prepMarks = [
            ['key' => 'lang1', 'max' => 50, 'obt' => 43],
            ['key' => 'lang2', 'max' => 50, 'obt' => 41],
            ['key' => 'math',  'max' => 50, 'obt' => 45],
            ['key' => 'twc',   'max' => 50, 'obt' => 44],
            ['key' => 'art',   'max' => 50, 'obt' => 46],
            ['key' => 'pe',    'max' => 50, 'obt' => 47],
        ];

        foreach ($prepMarks as $subIdx => $mInfo) {
            $maxM = $defaultMax[$mInfo['key']] ?? $mInfo['max'];
            $obtM = $autoFill ? min($maxM, max(30, $mInfo['obt'] + (($sIdx * 3 + $subIdx) % 7) - 3)) : '';
            $vals[] = $maxM;
            $vals[] = $obtM;
        }

        // Formulas for Preparatory SA Totals
        $vals[] = "=SUM({$k2c['L1_Max_Marks_SA']}{$rowNum},{$k2c['L2_Max_Marks_SA']}{$rowNum},{$k2c['Math_Max_Marks_SA']}{$rowNum},{$k2c['TWC_Max_Marks_SA']}{$rowNum},{$k2c['Art_Max_Marks_SA']}{$rowNum},{$k2c['PE_Max_Marks_SA']}{$rowNum})";
        $vals[] = "=SUM({$k2c['L1_Marks_Obtained_SA']}{$rowNum},{$k2c['L2_Marks_Obtained_SA']}{$rowNum},{$k2c['Math_Marks_Obtained_SA']}{$rowNum},{$k2c['TWC_Marks_Obtained_SA']}{$rowNum},{$k2c['Art_Marks_Obtained_SA']}{$rowNum},{$k2c['PE_Marks_Obtained_SA']}{$rowNum})";

        // Part-E Credits & Certification (4 columns)
        $vals[] = "=ROUND(({$k2c['Total_SA_Marks_Obtained']}{$rowNum}/{$k2c['Total_SA_Max_Marks']}{$rowNum})*20, 1)";
        $vals[] = "=ROUND(({$k2c['Total_SA_Marks_Obtained']}{$rowNum}/{$k2c['Total_SA_Max_Marks']}{$rowNum})*20, 1)";
        $vals[] = "={$k2c['Total_Credit_Earned_HPC_Formative']}{$rowNum}+{$k2c['Credit_Earned_Summative_SA']}{$rowNum}";
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
        $normalized['name']         = $row['Student_Name'] ?? ($row['name'] ?? 'Student');
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
        $l1Max = $row['L1_Max_Marks_SA'] ?? 50;
        $l1Obt = $row['L1_Marks_Obtained_SA'] ?? 43;
        $l2Max = $row['L2_Max_Marks_SA'] ?? 50;
        $l2Obt = $row['L2_Marks_Obtained_SA'] ?? 41;
        $mathMax = $row['Math_Max_Marks_SA'] ?? 50;
        $mathObt = $row['Math_Marks_Obtained_SA'] ?? 45;
        $evsMax = $row['TWC_Max_Marks_SA'] ?? 50;
        $evsObt = $row['TWC_Marks_Obtained_SA'] ?? 44;
        $artMax = $row['Art_Max_Marks_SA'] ?? 50;
        $artObt = $row['Art_Marks_Obtained_SA'] ?? 46;
        $peMax  = $row['PE_Max_Marks_SA'] ?? 50;
        $peObt  = $row['PE_Marks_Obtained_SA'] ?? 47;

        $normalized['marks'] = [
            'lang1' => ['max' => $l1Max, 'obt' => $l1Obt],
            'l1'    => ['max' => $l1Max, 'obt' => $l1Obt],
            'lang2' => ['max' => $l2Max, 'obt' => $l2Obt],
            'l2'    => ['max' => $l2Max, 'obt' => $l2Obt],
            'math'  => ['max' => $mathMax, 'obt' => $mathObt],
            'm'     => ['max' => $mathMax, 'obt' => $mathObt],
            'evs'   => ['max' => $evsMax, 'obt' => $evsObt],
            'twc'   => ['max' => $evsMax, 'obt' => $evsObt],
            'sci'   => ['max' => $evsMax, 'obt' => $evsObt],
            'art'   => ['max' => $artMax, 'obt' => $artObt],
            'pe'    => ['max' => $peMax, 'obt' => $peObt],
        ];

        // Part A2 aliases
        $normalized['my_name']         = $row['name'] ?? 'Student';
        $normalized['my_age']          = '9';
        $normalized['dont_like']       = 'Fighting, shouting, and loud noises';
        $normalized['like_to']         = $row['Hand_Reflection_Ring_LikeTo'] ?? '';
        $normalized['want_to_improve'] = $row['Hand_Reflection_Middle_ImproveSkill'] ?? '';
        $normalized['not_good_at']     = $row['Hand_Reflection_Index_NotGoodAt'] ?? '';
        $normalized['good_at']         = $row['Hand_Reflection_Thumb_GoodAt'] ?? '';
        $normalized['fav_food']        = $row['Favorite_Food'] ?? '';
        $normalized['fav_festival']    = $row['Favorite_Festival'] ?? '';
        $normalized['grow_up_be']      = $row['Hand_Reflection_Little_Aspiration'] ?? '';

        // Part B aliases
        $normalized['act_subject']     = $row['Act1_Subject_Domain'] ?? 'The World Around Us';
        $normalized['act_title']       = $row['Act1_Name'] ?? 'Nature Observation & Plant Seed Germination Study';
        $normalized['act_desc']        = $row['Act1_Description_Instructions'] ?? '';
        $normalized['act_rubric_awareness']   = $row['Act1_Rubric_Awareness_Level'] ?? 'Proficient';
        $normalized['act_rubric_sensitivity'] = $row['Act1_Rubric_Sensitivity_Level'] ?? 'Proficient';
        $normalized['act_rubric_creativity']  = $row['Act1_Rubric_Creativity_Level'] ?? 'Proficient';

        return $normalized;
    }
}
