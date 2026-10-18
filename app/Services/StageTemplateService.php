<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use App\Models\SchoolWorkingDay;
use App\Models\SchoolMaxMark;

class StageTemplateService
{
    public const STAGE_FOUNDATIONAL = 'foundational-stage';
    public const STAGE_PREPARATORY  = 'preparatory-stage';
    public const STAGE_MIDDLE       = 'middle-stage';
    public const STAGE_SECONDARY    = 'secondary-stage';

    public static function getStages(): array
    {
        return [
            self::STAGE_FOUNDATIONAL => [
                'slug'        => self::STAGE_FOUNDATIONAL,
                'title'       => 'Foundational Stage',
                'grades'      => ['Balvatika 1', 'Balvatika 2', 'Balvatika 3', 'Grade 1', 'Grade 2'],
                'grade_range' => 'Balvatika 1 - Class 2',
                'portal_url'  => 'https://hpc.parakh.ncert.gov.in/en/hpc/foundational-stage',
                'color'       => '#10b981',
                'accent_bg'   => 'FF059669',
                'description' => 'Developmental assessment across 5 NCF domains, activity rubrics, and joyful learning milestones.',
            ],
            self::STAGE_PREPARATORY => [
                'slug'        => self::STAGE_PREPARATORY,
                'title'       => 'Preparatory Stage',
                'grades'      => ['Grade 3', 'Grade 4', 'Grade 5'],
                'grade_range' => 'Class 3 - Class 5',
                'portal_url'  => 'https://hpc.parakh.ncert.gov.in/en/hpc/preparatory-stage',
                'color'       => '#3b82f6',
                'accent_bg'   => 'FF2563EB',
                'description' => 'Interactive All About Me, Peer (Peer 1 & 2) feedback, Language R1/R2, Maths, EVS, and Art/Work Ed.',
            ],
            self::STAGE_MIDDLE => [
                'slug'        => self::STAGE_MIDDLE,
                'title'       => 'Middle Stage',
                'grades'      => ['Grade 6', 'Grade 7', 'Grade 8'],
                'grade_range' => 'Class 6 - Class 8',
                'portal_url'  => 'https://hpc.parakh.ncert.gov.in/en/hpc/middle-stage',
                'color'       => '#f97316',
                'accent_bg'   => 'FFEA580C',
                'description' => 'All About Me, Ambition Card, Parent Partnership, 8 Curricular Subjects, HPC Credit Marks & Rubrics.',
            ],
            self::STAGE_SECONDARY => [
                'slug'        => self::STAGE_SECONDARY,
                'title'       => 'Secondary Stage',
                'grades'      => ['Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'],
                'grade_range' => 'Class 9 - Class 12',
                'portal_url'  => 'https://hpc.parakh.ncert.gov.in/en/hpc/secondary-stage',
                'color'       => '#8b5cf6',
                'accent_bg'   => 'FF7C3AED',
                'description' => 'Self-Evaluation, Time Management Map, Career Plans, Group Projects, Problem-Based Inquiry & Disciplines.',
            ],
        ];
    }

    public static function normalizeStage(?string $stage): string
    {
        $raw = strtolower(trim((string)$stage));
        if (str_contains($raw, 'foundat') || str_contains($raw, 'balvatika') || str_contains($raw, 'kg') || str_contains($raw, 'nurser')) {
            return self::STAGE_FOUNDATIONAL;
        }
        if (str_contains($raw, 'prep')) {
            return self::STAGE_PREPARATORY;
        }
        if (str_contains($raw, 'sec') || str_contains($raw, 'high') || str_contains($raw, '9') || str_contains($raw, '10') || str_contains($raw, '11') || str_contains($raw, '12')) {
            return self::STAGE_SECONDARY;
        }
        return self::STAGE_MIDDLE;
    }

    public static function getStageForGrade(?string $grade): string
    {
        $g = strtolower(trim((string)$grade));
        if (empty($g)) return self::STAGE_MIDDLE;

        if (str_contains($g, 'balvatika') || str_contains($g, 'nursery') || str_contains($g, 'lkg') || str_contains($g, 'ukg') || $g === 'grade 1' || $g === 'grade 2' || $g === '1' || $g === '2' || $g === 'class 1' || $g === 'class 2') {
            return self::STAGE_FOUNDATIONAL;
        }
        if ($g === 'grade 3' || $g === 'grade 4' || $g === 'grade 5' || in_array($g, ['3', '4', '5', 'class 3', 'class 4', 'class 5'])) {
            return self::STAGE_PREPARATORY;
        }
        if ($g === 'grade 9' || $g === 'grade 10' || $g === 'grade 11' || $g === 'grade 12' || in_array($g, ['9', '10', '11', '12', 'class 9', 'class 10', 'class 11', 'class 12'])) {
            return self::STAGE_SECONDARY;
        }
        return self::STAGE_MIDDLE;
    }

    public static function getGradesForStage(string $stage): array
    {
        $stage = self::normalizeStage($stage);
        $stages = self::getStages();
        return $stages[$stage]['grades'] ?? ['Grade 6', 'Grade 7', 'Grade 8'];
    }

    /**
     * Get Sections header metadata for a given stage
     */
    public function getStageSections(string $stage): array
    {
        $stage = self::normalizeStage($stage);
        return match($stage) {
            self::STAGE_FOUNDATIONAL => $this->getFoundationalSections(),
            self::STAGE_PREPARATORY  => $this->getPreparatorySections(),
            self::STAGE_SECONDARY    => $this->getSecondarySections(),
            default                  => $this->getMiddleSections(),
        };
    }

    /**
     * Get Column definitions for a given stage
     */
    public function getStageColumns(string $stage): array
    {
        $stage = self::normalizeStage($stage);
        return match($stage) {
            self::STAGE_FOUNDATIONAL => $this->getFoundationalColumns(),
            self::STAGE_PREPARATORY  => $this->getPreparatoryColumns(),
            self::STAGE_SECONDARY    => $this->getSecondaryColumns(),
            default                  => $this->getMiddleColumns(),
        };
    }

    /**
     * Helper to return standard locked metadata + attendance columns for all stages
     */
    protected function getBaseMetadataColumns(string $gradeOptions): array
    {
        return [
            // Student Metadata (Locked)
            ['key' => 'apaar_id',               'label' => 'APAAR ID (Locked)',                      'width' => 20, 'type' => 'locked',  'note' => 'Official APAAR ID if assigned'],
            ['key' => 'login_id',               'label' => 'HPC Login ID (Locked) *',                'width' => 22, 'type' => 'locked',  'note' => 'Unique student ID on portal'],
            ['key' => 'name',                   'label' => 'Student Name (Locked) *',                'width' => 26, 'type' => 'locked',  'note' => 'Locked name from portal'],
            ['key' => 'status',                 'label' => 'Status (Locked)',                        'width' => 18, 'type' => 'locked',  'note' => 'HPC Completed (skipped) / Pending'],

            // Student Profile & General Info
            ['key' => 'admission_no',           'label' => 'School Admission Number *',              'width' => 22, 'type' => 'string',  'note' => 'Official school admission / roll no.'],
            ['key' => 'grade',                  'label' => 'Class / Grade *',                        'width' => 16, 'type' => 'list',    'options' => $gradeOptions, 'note' => 'Select grade'],
            ['key' => 'section',                'label' => 'Section',                                'width' => 12, 'type' => 'list',    'options' => '"A,B,C,D,E"', 'note' => 'Select section'],
            ['key' => 'location',               'label' => 'Area / Location',                        'width' => 14, 'type' => 'list',    'options' => '"Rural,Urban"', 'note' => 'Rural or Urban'],
            ['key' => 'medium_of_instruction',  'label' => 'Medium of Instruction *',                'width' => 22, 'type' => 'list',    'options' => '"English,Urdu,Hindi,Kashmiri,Dogri,Other"', 'note' => 'Instructional medium'],
            ['key' => 'mother_tongue',          'label' => 'Mother Tongue *',                        'width' => 18, 'type' => 'list',    'options' => '"Kashmiri,Urdu,Hindi,English,Dogri,Punjabi,Other"', 'note' => 'Student mother tongue'],
            ['key' => 'relation',               'label' => 'Parent Relation *',                      'width' => 22, 'type' => 'list',    'options' => '"Father,Mother,Guardian (Other than Parents)"', 'note' => 'Select relation'],
            ['key' => 'parent_name',            'label' => 'Parent / Guardian Name *',               'width' => 24, 'type' => 'string',  'note' => 'Full parent / guardian name'],

            // Monthly Attendance (Apr to Mar)
            ['key' => 'work_apr', 'label' => 'Working Days - Apr *', 'width' => 16, 'type' => 'integer', 'note' => 'April working days'],
            ['key' => 'pres_apr', 'label' => 'Present Days - Apr *', 'width' => 16, 'type' => 'integer', 'note' => 'April present days'],
            ['key' => 'work_may', 'label' => 'Working Days - May *', 'width' => 16, 'type' => 'integer', 'note' => 'May working days'],
            ['key' => 'pres_may', 'label' => 'Present Days - May *', 'width' => 16, 'type' => 'integer', 'note' => 'May present days'],
            ['key' => 'work_jun', 'label' => 'Working Days - Jun *', 'width' => 16, 'type' => 'integer', 'note' => 'June working days'],
            ['key' => 'pres_jun', 'label' => 'Present Days - Jun *', 'width' => 16, 'type' => 'integer', 'note' => 'June present days'],
            ['key' => 'work_jul', 'label' => 'Working Days - Jul *', 'width' => 16, 'type' => 'integer', 'note' => 'July working days'],
            ['key' => 'pres_jul', 'label' => 'Present Days - Jul *', 'width' => 16, 'type' => 'integer', 'note' => 'July present days'],
            ['key' => 'work_aug', 'label' => 'Working Days - Aug *', 'width' => 16, 'type' => 'integer', 'note' => 'August working days'],
            ['key' => 'pres_aug', 'label' => 'Present Days - Aug *', 'width' => 16, 'type' => 'integer', 'note' => 'August present days'],
            ['key' => 'work_sep', 'label' => 'Working Days - Sep *', 'width' => 16, 'type' => 'integer', 'note' => 'September working days'],
            ['key' => 'pres_sep', 'label' => 'Present Days - Sep *', 'width' => 16, 'type' => 'integer', 'note' => 'September present days'],
            ['key' => 'work_oct', 'label' => 'Working Days - Oct *', 'width' => 16, 'type' => 'integer', 'note' => 'October working days'],
            ['key' => 'pres_oct', 'label' => 'Present Days - Oct *', 'width' => 16, 'type' => 'integer', 'note' => 'October present days'],
            ['key' => 'work_nov', 'label' => 'Working Days - Nov *', 'width' => 16, 'type' => 'integer', 'note' => 'November working days'],
            ['key' => 'pres_nov', 'label' => 'Present Days - Nov *', 'width' => 16, 'type' => 'integer', 'note' => 'November present days'],
            ['key' => 'work_dec', 'label' => 'Working Days - Dec *', 'width' => 16, 'type' => 'integer', 'note' => 'December working days'],
            ['key' => 'pres_dec', 'label' => 'Present Days - Dec *', 'width' => 16, 'type' => 'integer', 'note' => 'December present days'],
            ['key' => 'work_jan', 'label' => 'Working Days - Jan *', 'width' => 16, 'type' => 'integer', 'note' => 'January working days'],
            ['key' => 'pres_jan', 'label' => 'Present Days - Jan *', 'width' => 16, 'type' => 'integer', 'note' => 'January present days'],
            ['key' => 'work_feb', 'label' => 'Working Days - Feb *', 'width' => 16, 'type' => 'integer', 'note' => 'February working days'],
            ['key' => 'pres_feb', 'label' => 'Present Days - Feb *', 'width' => 16, 'type' => 'integer', 'note' => 'February present days'],
            ['key' => 'work_mar', 'label' => 'Working Days - Mar *', 'width' => 16, 'type' => 'integer', 'note' => 'March working days'],
            ['key' => 'pres_mar', 'label' => 'Present Days - Mar *', 'width' => 16, 'type' => 'integer', 'note' => 'March present days'],
            ['key' => 'total_work', 'label' => 'Total Working Days (Auto)', 'width' => 20, 'type' => 'calc', 'note' => 'Auto-calculated sum'],
            ['key' => 'total_pres', 'label' => 'Total Present Days (Auto)', 'width' => 20, 'type' => 'calc', 'note' => 'Auto-calculated sum'],
            ['key' => 'attendance_pct', 'label' => 'Attendance % (Auto)', 'width' => 18, 'type' => 'calc', 'note' => 'Auto percentage'],
            ['key' => 'absent_count', 'label' => 'Absent Times Count', 'width' => 18, 'type' => 'integer', 'note' => 'Total times absent'],
            ['key' => 'absence_reason', 'label' => 'Reason for Absence (A1)', 'width' => 26, 'type' => 'string', 'note' => 'e.g. Seasonal viral illness'],
            ['key' => 'interest', 'label' => 'Primary Interest / Activity (A1) *', 'width' => 30, 'type' => 'list', 'options' => '"Reading,Dancing or Singing,Drawing and Colouring,Outdoor Games,Building Blocks or Toys,Storytelling,Gardening"', 'note' => 'Favorite activity'],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. FOUNDATIONAL STAGE (Balvatika 1-3, Class 1-2)
    // ─────────────────────────────────────────────────────────────────────────
    protected function getFoundationalSections(): array
    {
        return [
            ['title' => '🔒 Locked Metadata',                                   'start' => 0,   'end' => 3,   'bg' => 'FF334155'],
            ['title' => '📋 Part A(1): Student Profile & Attendance',          'start' => 4,   'end' => 41,  'bg' => 'FF1E293B'],
            ['title' => '🎨 Part A(2): Me and My Surroundings',                 'start' => 42,  'end' => 52,  'bg' => 'FF0D9488'],
            ['title' => '🏃 Domain 1: Physical Development (Shareerik)',       'start' => 53,  'end' => 59,  'bg' => 'FF059669'],
            ['title' => '🤝 Domain 2: Socio-Emotional (Mansik/Samajik)',       'start' => 60,  'end' => 66,  'bg' => 'FF2563EB'],
            ['title' => '🧠 Domain 3: Cognitive Development (Bodhik)',          'start' => 67,  'end' => 73,  'bg' => 'FF7C3AED'],
            ['title' => '🗣️ Domain 4: Language & Literacy (Bhasha)',            'start' => 74,  'end' => 80,  'bg' => 'FFD97706'],
            ['title' => '🎭 Domain 5: Aesthetic & Cultural (Kala Vikas)',       'start' => 81,  'end' => 87,  'bg' => 'FFE11D48'],
            ['title' => '⭐ Positive Learning Habits & Self/Peer Evaluation',     'start' => 88,  'end' => 95,  'bg' => 'FF4F46E5'],
            ['title' => '📝 Part C: Holistic Summary & Teacher Remarks',        'start' => 96,  'end' => 98,  'bg' => 'FF1E293B'],
        ];
    }

    protected function getFoundationalColumns(): array
    {
        $cols = $this->getBaseMetadataColumns('"Balvatika 1,Balvatika 2,Balvatika 3,Grade 1,Grade 2"');

        // Part A(2) Me and My Surroundings (Pages 23-25 of Guide)
        $cols[] = ['key' => 'live_with', 'label' => 'I live with (A2)', 'width' => 24, 'type' => 'string', 'note' => 'e.g. Parents, Grandparents and Sister'];
        $cols[] = ['key' => 'fav_food', 'label' => 'My Favourite Food / Dish (A2)', 'width' => 26, 'type' => 'string', 'note' => 'e.g. Khichdi, Fruits, Roti'];
        $cols[] = ['key' => 'fav_toy', 'label' => 'My Favourite Toy / Game (A2)', 'width' => 26, 'type' => 'string', 'note' => 'e.g. Wooden blocks, Clay toys, Hide & Seek'];
        $cols[] = ['key' => 'fav_colour', 'label' => 'My Favourite Colour (A2)', 'width' => 20, 'type' => 'string', 'note' => 'e.g. Red, Blue, Yellow'];
        $cols[] = ['key' => 'like_to_do', 'label' => 'What I like to do most at school (A2)', 'width' => 32, 'type' => 'string', 'note' => 'e.g. Singing action songs and drawing'];
        $cols[] = ['key' => 'helps_me', 'label' => 'Person who helps me at home (A2)', 'width' => 26, 'type' => 'string', 'note' => 'e.g. Mother / Father'];
        $cols[] = ['key' => 'friend_1', 'label' => 'My School Friend: 1 (A2)', 'width' => 22, 'type' => 'string', 'note' => 'e.g. Ayesha'];
        $cols[] = ['key' => 'friend_2', 'label' => 'My School Friend: 2 (A2)', 'width' => 22, 'type' => 'string', 'note' => 'e.g. Zaid'];
        $cols[] = ['key' => 'feel_happy', 'label' => 'I feel happiest when (A2)', 'width' => 28, 'type' => 'string', 'note' => 'e.g. Playing with sand and listening to stories'];
        $cols[] = ['key' => 'curious_about', 'label' => 'I am curious to learn about (A2)', 'width' => 30, 'type' => 'string', 'note' => 'e.g. Birds, trees, and animals'];
        $cols[] = ['key' => 'special_habit', 'label' => 'Special Good Habit (A2)', 'width' => 26, 'type' => 'string', 'note' => 'e.g. Washing hands before eating'];

        // 5 Domains of Foundational Stage (NCF-FS): Each with Activity, Goal, Competency, Rubrics (Awareness, Sensitivity, Creativity), Teacher Observation
        $rubricOpts = '"Beginner,Proficient,Advanced"';
        $domains = [
            ['key' => 'd1', 'name' => 'Physical Development (Gross/Fine Motor & Hygiene)'],
            ['key' => 'd2', 'name' => 'Socio-Emotional & Ethical Development (Sharing & Empathy)'],
            ['key' => 'd3', 'name' => 'Cognitive Development (Pattern, Shapes, Numbers & Memory)'],
            ['key' => 'd4', 'name' => 'Language & Early Literacy (Phonology, Vocabulary & Storytelling)'],
            ['key' => 'd5', 'name' => 'Aesthetic & Cultural Expression (Art, Music & Free Play)'],
        ];

        foreach ($domains as $d) {
            $k = $d['key'];
            $n = $d['name'];
            $cols[] = ['key' => "{$k}_act", 'label' => "Activity Title - {$n} *", 'width' => 30, 'type' => 'string', 'note' => "e.g. Circle time movement / Origami craft"];
            $cols[] = ['key' => "{$k}_comp", 'label' => "Competency Evidenced - {$n} *", 'width' => 32, 'type' => 'string', 'note' => "e.g. Fine motor coordination / Active listening"];
            $cols[] = ['key' => "{$k}_aware", 'label' => "Awareness Level - {$n} *", 'width' => 22, 'type' => 'list', 'options' => $rubricOpts, 'note' => 'Select: Beginner, Proficient, Advanced'];
            $cols[] = ['key' => "{$k}_sens", 'label' => "Sensitivity Level - {$n} *", 'width' => 22, 'type' => 'list', 'options' => $rubricOpts, 'note' => 'Select: Beginner, Proficient, Advanced'];
            $cols[] = ['key' => "{$k}_creat", 'label' => "Creativity Level - {$n} *", 'width' => 22, 'type' => 'list', 'options' => $rubricOpts, 'note' => 'Select: Beginner, Proficient, Advanced'];
            $cols[] = ['key' => "{$k}_level", 'label' => "Overall Performance Level - {$n} *", 'width' => 24, 'type' => 'list', 'options' => $rubricOpts, 'note' => 'Milestone status'];
            $cols[] = ['key' => "{$k}_notes", 'label' => "Teacher Observational Notes - {$n}", 'width' => 36, 'type' => 'string', 'note' => 'Specific strengths & developmental needs'];
        }

        // Positive Learning Habits & Self/Peer Evaluation
        $cols[] = ['key' => 'habit_attention', 'label' => 'Habit: Sustained Attention *', 'width' => 24, 'type' => 'list', 'options' => '"Always,Most Times,Sometimes"', 'note' => 'Focus during play and tasks'];
        $cols[] = ['key' => 'habit_sharing', 'label' => 'Habit: Sharing & Turn Taking *', 'width' => 24, 'type' => 'list', 'options' => '"Always,Most Times,Sometimes"', 'note' => 'Sharing toys & resources'];
        $cols[] = ['key' => 'habit_cleanliness', 'label' => 'Habit: Orderliness & Cleanliness *', 'width' => 24, 'type' => 'list', 'options' => '"Always,Most Times,Sometimes"', 'note' => 'Keeping shoes/bags in place'];
        $cols[] = ['key' => 'self_enjoyment', 'label' => 'Self: How much I enjoyed activities *', 'width' => 28, 'type' => 'list', 'options' => '"Loved It,Liked It,Found It Difficult"', 'note' => 'Smiley 3-point rating'];
        $cols[] = ['key' => 'self_effort', 'label' => 'Self: I tried my best *', 'width' => 24, 'type' => 'list', 'options' => '"Yes Fully,Tried Hard,Needed Teacher Help"', 'note' => 'Child effort reflection'];
        $cols[] = ['key' => 'peer_name', 'label' => 'Peer Reviewer Name *', 'width' => 22, 'type' => 'string', 'note' => 'Classmate who reviewed'];
        $cols[] = ['key' => 'peer_feedback', 'label' => 'Peer Impression of Child *', 'width' => 26, 'type' => 'list', 'options' => '"Very Helpful Friend,Good Playmate,Plays Nicely"', 'note' => 'Peer impression'];
        $cols[] = ['key' => 'parent_involvement', 'label' => 'Parental Home Involvement *', 'width' => 26, 'type' => 'list', 'options' => '"Highly Involved,Regularly Supporting,Needs Encouragement"', 'note' => 'Home engagement'];

        // Part C Holistic Summary & Remarks
        $cols[] = ['key' => 'foundational_milestone', 'label' => 'Year-End Developmental Milestone *', 'width' => 30, 'type' => 'list', 'options' => '"Exceeding Age Expectations,Age Appropriate Mastery,Needs Guided Support"', 'note' => 'Overall foundational milestone'];
        $cols[] = ['key' => 'teacher_declaration', 'label' => 'Teacher Certification *', 'width' => 22, 'type' => 'list', 'options' => '"Yes,No"', 'note' => 'Declaration of authenticity'];
        $cols[] = ['key' => 'holistic_remarks', 'label' => 'Comprehensive Holistic Remarks *', 'width' => 45, 'type' => 'string', 'note' => 'Individualized narrative on child joy and growth'];

        return $cols;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. PREPARATORY STAGE (Class 3 - 5)
    // ─────────────────────────────────────────────────────────────────────────
    protected function getPreparatorySections(): array
    {
        return [
            ['title' => '🔒 Locked Metadata',                                   'start' => 0,   'end' => 3,   'bg' => 'FF334155'],
            ['title' => '📋 Part A(1): Student Profile & Attendance',          'start' => 4,   'end' => 41,  'bg' => 'FF1E293B'],
            ['title' => '🌟 Part A(2): All About Me (Learner Profile)',         'start' => 42,  'end' => 52,  'bg' => 'FF0284C7'],
            ['title' => '👥 Part A(3): Peer 1, Peer 2 & Parent Voice',          'start' => 53,  'end' => 64,  'bg' => 'FF0891B2'],
            ['title' => '📖 Part B: Language 1 (R1) Progress',                 'start' => 65,  'end' => 71,  'bg' => 'FF059669'],
            ['title' => '🗣️ Part B: Language 2 (R2) Progress',                 'start' => 72,  'end' => 78,  'bg' => 'FF16A34A'],
            ['title' => '📐 Part B: Mathematics Progress',                     'start' => 79,  'end' => 85,  'bg' => 'FF2563EB'],
            ['title' => '🌍 Part B: The World Around Us (EVS)',                 'start' => 86,  'end' => 92,  'bg' => 'FF7C3AED'],
            ['title' => '🎨 Part B: Art Education & Physical Well-being',       'start' => 93,  'end' => 99,  'bg' => 'FFD97706'],
            ['title' => '🛠️ Part B: Work Pre-Skills & Vocational Exposure',     'start' => 100, 'end' => 105, 'bg' => 'FFDC2626'],
            ['title' => '📊 Part C: Annual Competency Summary & Marks',         'start' => 106, 'end' => 119, 'bg' => 'FF047857'],
            ['title' => '✍️ Part C: Declaration & Final Narrative',             'start' => 120, 'end' => 121, 'bg' => 'FF334155'],
        ];
    }

    protected function getPreparatoryColumns(): array
    {
        $cols = $this->getBaseMetadataColumns('"Grade 3,Grade 4,Grade 5"');

        // Part A(2) All About Me (Learner Profile)
        $cols[] = ['key' => 'live_with', 'label' => 'I live with (A2)', 'width' => 24, 'type' => 'string', 'note' => 'e.g. My parents, siblings, and grandparents'];
        $cols[] = ['key' => 'fav_dish', 'label' => 'My Favorite Food / Indian Dish (A2) *', 'width' => 30, 'type' => 'string', 'note' => 'e.g. Dal Chawal, Rajma, Khichdi'];
        $cols[] = ['key' => 'fav_game', 'label' => 'My Favorite Indigenous / Traditional Game (A2) *', 'width' => 34, 'type' => 'string', 'note' => 'e.g. Kho-Kho, Kabaddi, Gilli-Danda'];
        $cols[] = ['key' => 'free_time', 'label' => 'In my free time I love (A2)', 'width' => 30, 'type' => 'string', 'note' => 'e.g. Reading Panchatantra stories and sketching'];
        $cols[] = ['key' => 'do_well', 'label' => 'Something I do really well (A2) *', 'width' => 30, 'type' => 'string', 'note' => 'e.g. Solving math puzzles and recitation'];
        $cols[] = ['key' => 'want_to_improve', 'label' => 'Something I want to improve in (A2)', 'width' => 32, 'type' => 'string', 'note' => 'e.g. Neat handwriting and spelling'];
        $cols[] = ['key' => 'responsible', 'label' => 'I am responsible (A2) *', 'width' => 24, 'type' => 'list', 'options' => '"all the time,most times,sometimes"', 'note' => 'all the time, most times, sometimes'];
        $cols[] = ['key' => 'proud_of', 'label' => 'I feel proud of myself when (A2)', 'width' => 30, 'type' => 'string', 'note' => 'e.g. Helping my classmate with reading'];
        $cols[] = ['key' => 'goal_acad', 'label' => 'My Learning Goal this year (A2) *', 'width' => 32, 'type' => 'string', 'note' => 'e.g. Read 15 storybooks in English and Urdu'];
        $cols[] = ['key' => 'goal_step', 'label' => 'Steps I will take to reach my goal (A2)', 'width' => 34, 'type' => 'string', 'note' => 'e.g. Read 2 pages every evening before bed'];
        $cols[] = ['key' => 'good_deed', 'label' => 'A kind deed I did recently (A2)', 'width' => 30, 'type' => 'string', 'note' => 'e.g. Shared my lunch with a friend who forgot hers'];

        // Part A(3) Peer 1, Peer 2 & Parent Feedback
        $cols[] = ['key' => 'peer1_name', 'label' => 'Peer 1: Reviewer Name *', 'width' => 22, 'type' => 'string', 'note' => 'First assigned peer'];
        $cols[] = ['key' => 'peer1_helpful', 'label' => 'Peer 1: Is helpful and cooperative *', 'width' => 26, 'type' => 'list', 'options' => '"Always,Usually,Sometimes"', 'note' => 'Peer 1 feedback'];
        $cols[] = ['key' => 'peer1_listens', 'label' => 'Peer 1: Listens to others in group *', 'width' => 26, 'type' => 'list', 'options' => '"Always,Usually,Sometimes"', 'note' => 'Peer 1 collaboration'];
        $cols[] = ['key' => 'peer1_praise', 'label' => 'Peer 1: What I like most about friend', 'width' => 32, 'type' => 'string', 'note' => 'e.g. Very cheerful and always shares pencils'];
        $cols[] = ['key' => 'peer2_name', 'label' => 'Peer 2: Reviewer Name *', 'width' => 22, 'type' => 'string', 'note' => 'Second assigned peer'];
        $cols[] = ['key' => 'peer2_helpful', 'label' => 'Peer 2: Works well in team activities *', 'width' => 26, 'type' => 'list', 'options' => '"Always,Usually,Sometimes"', 'note' => 'Peer 2 feedback'];
        $cols[] = ['key' => 'peer2_praise', 'label' => 'Peer 2: Positive comment for friend', 'width' => 30, 'type' => 'string', 'note' => 'e.g. Explains maths questions very patiently'];
        $cols[] = ['key' => 'parent_reads_with', 'label' => 'Parent: Reads / discusses school at home *', 'width' => 30, 'type' => 'list', 'options' => '"Daily,Weekly,Occasionally,Rarely"', 'note' => 'Parent-teacher card'];
        $cols[] = ['key' => 'parent_study_space', 'label' => 'Parent: Child has quiet space for study *', 'width' => 28, 'type' => 'list', 'options' => '"Yes,No"', 'note' => 'Home environment'];
        $cols[] = ['key' => 'parent_social_habits', 'label' => 'Parent: Child displays respectful behavior *', 'width' => 30, 'type' => 'list', 'options' => '"Always,Usually,Needs Guidance"', 'note' => 'Parent observation'];
        $cols[] = ['key' => 'parent_support_needed', 'label' => 'Parent: Support requested from school', 'width' => 32, 'type' => 'string', 'note' => 'e.g. Regular spelling practice worksheets'];
        $cols[] = ['key' => 'parent_signature_status', 'label' => 'Parent PTA Consultation Done *', 'width' => 24, 'type' => 'list', 'options' => '"Completed,In Progress,Pending"', 'note' => 'SMC/PTA meeting status'];

        // Part B Progress across 6 Curricular Learning Standards
        $rubricOpts = '"Beginner,Proficient,Advanced"';
        $prepSubjects = [
            ['key' => 'lang1', 'name' => 'Language 1 (R1 - First Language)'],
            ['key' => 'lang2', 'name' => 'Language 2 (R2 - Second Language)'],
            ['key' => 'math',  'name' => 'Mathematics (Numbers, Geometry, Measurement)'],
            ['key' => 'evs',   'name' => 'The World Around Us (EVS / Environment)'],
            ['key' => 'art_pe','name' => 'Art Education & Physical Well-being'],
        ];

        foreach ($prepSubjects as $s) {
            $k = $s['key'];
            $n = $s['name'];
            $cols[] = ['key' => "{$k}_act", 'label' => "Activity Title - {$n} *", 'width' => 30, 'type' => 'string', 'note' => 'e.g. Roleplay dialogue / Measurement project'];
            $cols[] = ['key' => "{$k}_approach", 'label' => "Pedagogy Approach - {$n} *", 'width' => 26, 'type' => 'list', 'options' => '"Toy-Based Pedagogy,Art-Integrated,Sports-Integrated,Experiential Learning,Storytelling"', 'note' => 'Pedagogy used'];
            $cols[] = ['key' => "{$k}_aware", 'label' => "Awareness - {$n} *", 'width' => 20, 'type' => 'list', 'options' => $rubricOpts, 'note' => 'Beginner, Proficient, Advanced'];
            $cols[] = ['key' => "{$k}_sens", 'label' => "Sensitivity - {$n} *", 'width' => 20, 'type' => 'list', 'options' => $rubricOpts, 'note' => 'Beginner, Proficient, Advanced'];
            $cols[] = ['key' => "{$k}_creat", 'label' => "Creativity - {$n} *", 'width' => 20, 'type' => 'list', 'options' => $rubricOpts, 'note' => 'Beginner, Proficient, Advanced'];
            $cols[] = ['key' => "{$k}_self_rate", 'label' => "Student Self-Rating - {$n} *", 'width' => 24, 'type' => 'list', 'options' => '"Enjoyed Fully,Good Effort,Faced Challenge"', 'note' => 'Learner self reflection'];
            $cols[] = ['key' => "{$k}_teacher_feedback", 'label' => "Teacher Strength & Guidance - {$n}", 'width' => 35, 'type' => 'string', 'note' => 'Observational feedback'];
        }

        // Work Pre-Skills / Vocational Exposure (Bagless Days & Hands-on Crafts)
        $cols[] = ['key' => 'work_skill_act', 'label' => 'Work Pre-Skill Activity Title *', 'width' => 30, 'type' => 'string', 'note' => 'e.g. Clay pottery / Paper craft / Kitchen garden'];
        $cols[] = ['key' => 'work_skill_hours', 'label' => 'Bagless Days / Activity Hours *', 'width' => 24, 'type' => 'integer', 'note' => 'Total hours engaged (e.g. 10)'];
        $cols[] = ['key' => 'work_skill_aware', 'label' => 'Awareness: Tool & Material Handling *', 'width' => 24, 'type' => 'list', 'options' => $rubricOpts, 'note' => 'Beginner, Proficient, Advanced'];
        $cols[] = ['key' => 'work_skill_sens', 'label' => 'Sensitivity: Care for Materials *', 'width' => 24, 'type' => 'list', 'options' => $rubricOpts, 'note' => 'Beginner, Proficient, Advanced'];
        $cols[] = ['key' => 'work_skill_creat', 'label' => 'Creativity: Originality in Craft *', 'width' => 24, 'type' => 'list', 'options' => $rubricOpts, 'note' => 'Beginner, Proficient, Advanced'];
        $cols[] = ['key' => 'work_skill_notes', 'label' => 'Craft Observations & Pride', 'width' => 30, 'type' => 'string', 'note' => 'Joy of making'];

        // Part C Annual Marks & Summative Assessment (6 Subjects: Max & Obtained)
        $marksSubjs = [
            ['key' => 'lang1', 'name' => 'Language 1 (R1)'],
            ['key' => 'lang2', 'name' => 'Language 2 (R2)'],
            ['key' => 'math',  'name' => 'Mathematics'],
            ['key' => 'evs',   'name' => 'The World Around Us (EVS)'],
            ['key' => 'art',   'name' => 'Art Education'],
            ['key' => 'pe',    'name' => 'Physical Education'],
            ['key' => 'work',  'name' => 'Work Experience'],
        ];

        foreach ($marksSubjs as $ms) {
            $k = $ms['key'];
            $n = $ms['name'];
            $cols[] = ['key' => "sub_{$k}_max", 'label' => "Max Marks - {$n} *", 'width' => 16, 'type' => 'integer', 'note' => 'Default 50'];
            $cols[] = ['key' => "sub_{$k}_obt", 'label' => "Marks Obtained - {$n} *", 'width' => 18, 'type' => 'integer', 'note' => 'Marks scored (0 to Max)'];
        }

        // Declaration & Final Narrative
        $cols[] = ['key' => 'teacher_declaration', 'label' => 'Teacher Certification *', 'width' => 22, 'type' => 'list', 'options' => '"Yes,No"', 'note' => 'Declaration of authenticity'];
        $cols[] = ['key' => 'holistic_remarks', 'label' => 'Overall Annual Progress Remarks *', 'width' => 45, 'type' => 'string', 'note' => 'Holistic progress summary across all dimensions'];

        return $cols;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. MIDDLE STAGE (Class 6 - 8) - Current Complete Production Model
    // ─────────────────────────────────────────────────────────────────────────
    protected function getMiddleSections(): array
    {
        return [
            ['title' => '🔒 Locked Metadata',                                   'start' => 0,   'end' => 3,   'bg' => 'FF334155'],
            ['title' => '📋 Part A(1): Student Profile & Attendance',          'start' => 4,   'end' => 41,  'bg' => 'FF1E293B'],
            ['title' => '🌟 Part A(2): All About Me & Goal Setting',            'start' => 42,  'end' => 67,  'bg' => 'FF0284C7'],
            ['title' => '🎯 Part A(3): Ambition Card (Career Exploration)',      'start' => 68,  'end' => 77,  'bg' => 'FF0D9488'],
            ['title' => '🤝 Part A(4): Parent Partnership & Home Support',     'start' => 78,  'end' => 87,  'bg' => 'FFD97706'],
            ['title' => '🔬 Part B: Activity 1 (Academic Domain)',              'start' => 88,  'end' => 91,  'bg' => 'FF059669'],
            ['title' => '🎨 Part B: Activity 2 (Co-Curricular / Creative)',      'start' => 92,  'end' => 102, 'bg' => 'FF2563EB'],
            ['title' => '📊 Part C: Annual Curricular Assessment (Levels)',     'start' => 103, 'end' => 128, 'bg' => 'FF7C3AED'],
            ['title' => '🎯 Part D: HPC Credit Subject Marks (8 Subj)',          'start' => 129, 'end' => 144, 'bg' => 'FF047857'],
            ['title' => '✍️ Part E: Final Declaration & Remarks',               'start' => 145, 'end' => 146, 'bg' => 'FF334155'],
        ];
    }

    protected function getMiddleColumns(): array
    {
        // Use existing complete 147-column model from HpcTeacherController
        $cols = $this->getBaseMetadataColumns('"Grade 6,Grade 7,Grade 8"');

        // Part-A(2) All About Me & Goal Setting
        $cols[] = ['key' => 'live_with', 'label' => 'I live with my (A2)', 'width' => 24, 'type' => 'string', 'note' => 'e.g. Family / Parents and Grandparents'];
        $cols[] = ['key' => 'stay_at', 'label' => 'We stay at (A2)', 'width' => 22, 'type' => 'string', 'note' => 'e.g. Sugin, Budgam'];
        $cols[] = ['key' => 'free_time', 'label' => 'I spend my free time doing (A2)', 'width' => 30, 'type' => 'string', 'note' => 'e.g. Reading books and drawing'];
        $cols[] = ['key' => 'do_well', 'label' => 'I ... very well (A2)', 'width' => 30, 'type' => 'string', 'note' => 'e.g. Learning new skills and actively participating'];
        $cols[] = ['key' => 'responsible', 'label' => 'I am responsible (A2) *', 'width' => 24, 'type' => 'list', 'options' => '"all the time,most times,sometimes"', 'note' => 'Select: all the time, most times, sometimes'];
        $cols[] = ['key' => 'do_better', 'label' => 'I could do better specially when it comes to (A2)', 'width' => 38, 'type' => 'string', 'note' => 'e.g. Time management in exams'];
        $cols[] = ['key' => 'care_others', 'label' => 'I care about others. I show it by (A2)', 'width' => 34, 'type' => 'string', 'note' => 'e.g. Helping peers with notes'];
        $cols[] = ['key' => 'feel_proud', 'label' => 'I feel proud of myself when (A2)', 'width' => 32, 'type' => 'string', 'note' => 'e.g. Solving challenging problems independently'];
        $cols[] = ['key' => 'friend_name', 'label' => 'My Friend: Name (A2)', 'width' => 22, 'type' => 'string', 'note' => 'e.g. Ayaan Ahmad'];
        $cols[] = ['key' => 'friend_age', 'label' => 'My Friend: Age (A2)', 'width' => 18, 'type' => 'integer', 'note' => 'e.g. 12'];
        $cols[] = ['key' => 'acad_goal', 'label' => 'My Academic Goal (A2)', 'width' => 28, 'type' => 'string', 'note' => 'e.g. Score above 90% in Mathematics and Science'];
        $cols[] = ['key' => 'acad_goal_why', 'label' => 'Academic: This goal is important to me because (A2) *', 'width' => 38, 'type' => 'string', 'note' => 'e.g. Builds solid foundation for engineering'];
        $cols[] = ['key' => 'acad_step1', 'label' => 'Academic: Step 1 (A2)', 'width' => 28, 'type' => 'string', 'note' => 'e.g. Dedicate 45 minutes daily to numerical problems'];
        $cols[] = ['key' => 'acad_step2', 'label' => 'Academic: Step 2 (A2)', 'width' => 28, 'type' => 'string', 'note' => 'e.g. Clarify doubts with subject teachers promptly'];
        $cols[] = ['key' => 'pers_goal', 'label' => 'My Personal Goal (A2)', 'width' => 28, 'type' => 'string', 'note' => 'e.g. Develop regular exercise and reading routine'];
        $cols[] = ['key' => 'pers_goal_why', 'label' => 'Personal: This goal is important to me because (A2) *', 'width' => 38, 'type' => 'string', 'note' => 'e.g. Keeps mind alert and improves concentration'];
        $cols[] = ['key' => 'pers_step1', 'label' => 'Personal: Step 1 (A2)', 'width' => 28, 'type' => 'string', 'note' => 'e.g. Wake up at 6:00 AM every morning'];
        $cols[] = ['key' => 'pers_step2', 'label' => 'Personal: Step 2 (A2)', 'width' => 28, 'type' => 'string', 'note' => 'e.g. Read at least 15 pages before bedtime'];
        $cols[] = ['key' => 'sch_learn1', 'label' => 'Learnt at school: 1 (A2)', 'width' => 28, 'type' => 'string', 'note' => 'e.g. Laws of reflection in science laboratory'];
        $cols[] = ['key' => 'sch_learn2', 'label' => 'Learnt at school: 2 (A2)', 'width' => 28, 'type' => 'string', 'note' => 'e.g. Solving linear equations in mathematics'];
        $cols[] = ['key' => 'sch_learn3', 'label' => 'Learnt at school: 3 (A2)', 'width' => 28, 'type' => 'string', 'note' => 'e.g. Constitutional fundamental rights'];
        $cols[] = ['key' => 'out_learn1', 'label' => 'Learnt outside school: 1 (A2)', 'width' => 28, 'type' => 'string', 'note' => 'e.g. Organic kitchen gardening and soil care'];
        $cols[] = ['key' => 'out_learn2', 'label' => 'Learnt outside school: 2 (A2)', 'width' => 28, 'type' => 'string', 'note' => 'e.g. Bicycle maintenance and tyre inflation'];
        $cols[] = ['key' => 'out_learn3', 'label' => 'Learnt outside school: 3 (A2)', 'width' => 28, 'type' => 'string', 'note' => 'e.g. Digital payments and basic banking literacy'];
        $cols[] = ['key' => 'ask_teach1', 'label' => 'I would like to ask my teachers: 1 (A2)', 'width' => 34, 'type' => 'string', 'note' => 'e.g. How telescopes photograph distant galaxies'];
        $cols[] = ['key' => 'ask_teach2', 'label' => 'I would like to ask my teachers: 2 (A2)', 'width' => 34, 'type' => 'string', 'note' => 'e.g. Tips to improve fast mental arithmetic'];

        // Part-A(3) Ambition Card
        $cols[] = ['key' => 'ambition', 'label' => 'My Career Ambition (A3)', 'width' => 26, 'type' => 'string', 'note' => 'e.g. Software Engineer / Data Scientist'];
        $cols[] = ['key' => 'ambition_path', 'label' => 'Path to achieve this Ambition (A3)', 'width' => 38, 'type' => 'string', 'note' => 'e.g. Focus on Maths and Computer Science'];
        $cols[] = ['key' => 'skill_acquire', 'label' => 'Skills I want to acquire (5 skills) (A3) *', 'width' => 45, 'type' => 'string', 'note' => 'e.g. Python Programming, Critical Thinking, Logic'];
        $cols[] = ['key' => 'focus_subjects', 'label' => 'Subjects I need to focus on (A3)', 'width' => 32, 'type' => 'string', 'note' => 'e.g. Mathematics, Science, English'];
        $cols[] = ['key' => 'habits_cultivate', 'label' => 'Habits I will cultivate (A3)', 'width' => 34, 'type' => 'string', 'note' => 'e.g. Daily revision and time-blocked study'];
        $cols[] = ['key' => 'mentor_name', 'label' => 'Teacher Mentor Name (A3)', 'width' => 24, 'type' => 'string', 'note' => 'e.g. Mohammad Shafi'];
        $cols[] = ['key' => 'mentor_guidance', 'label' => 'Mentor Guidance & Advice (A3)', 'width' => 36, 'type' => 'string', 'note' => 'e.g. Build solid mathematical logic'];
        $cols[] = ['key' => 'feel_calm', 'label' => 'I feel calm when (A3)', 'width' => 26, 'type' => 'string', 'note' => 'e.g. Walking in nature and listening to birds'];
        $cols[] = ['key' => 'feel_anxious', 'label' => 'I feel anxious when (A3)', 'width' => 28, 'type' => 'string', 'note' => 'e.g. Sudden unannounced viva examinations'];
        $cols[] = ['key' => 'cope_anxiety', 'label' => 'How I cope with anxiety (A3)', 'width' => 30, 'type' => 'string', 'note' => 'e.g. Deep breathing exercises and taking short walks'];

        // Part-A(4) Parent Partnership
        $cols[] = ['key' => 'res_books', 'label' => 'Resources: Books & Reading Material *', 'width' => 28, 'type' => 'list', 'options' => '"Yes,No"', 'note' => 'Select Yes / No'];
        $cols[] = ['key' => 'res_play', 'label' => 'Resources: Play & Sports Equipment *', 'width' => 28, 'type' => 'list', 'options' => '"Yes,No"', 'note' => 'Select Yes / No'];
        $cols[] = ['key' => 'res_digital', 'label' => 'Resources: Digital Device / Internet *', 'width' => 28, 'type' => 'list', 'options' => '"Yes,No"', 'note' => 'Select Yes / No'];
        $cols[] = ['key' => 'child_q1', 'label' => 'Parent: Child shares school experiences *', 'width' => 34, 'type' => 'list', 'options' => '"Yes,Sometimes,No,Not Sure"', 'note' => 'Radio smiley response'];
        $cols[] = ['key' => 'child_q2', 'label' => 'Parent: Child handles emotional feelings *', 'width' => 34, 'type' => 'list', 'options' => '"Yes,Sometimes,No,Not Sure"', 'note' => 'Radio smiley response'];
        $cols[] = ['key' => 'child_q3', 'label' => 'Parent: Child completes tasks responsibly *', 'width' => 34, 'type' => 'list', 'options' => '"Yes,Sometimes,No,Not Sure"', 'note' => 'Radio smiley response'];
        $cols[] = ['key' => 'child_q4', 'label' => 'Parent: Child interacts warmly with peers *', 'width' => 34, 'type' => 'list', 'options' => '"Yes,Sometimes,No,Not Sure"', 'note' => 'Radio smiley response'];
        $cols[] = ['key' => 'need_time', 'label' => 'Need: Dedicated Time with Child *', 'width' => 26, 'type' => 'list', 'options' => '"Yes,No"', 'note' => 'Select Yes / No'];
        $cols[] = ['key' => 'need_resources', 'label' => 'Need: More Learning Resources *', 'width' => 26, 'type' => 'list', 'options' => '"Yes,No"', 'note' => 'Select Yes / No'];
        $cols[] = ['key' => 'parent_plan', 'label' => 'Home Support Action Plan *', 'width' => 38, 'type' => 'string', 'note' => 'e.g. Schedule dedicated 1-hour quiet study'];

        // Part-B Activity 1 & 2
        $rubricOpts = '"Beginner,Proficient,Advanced"';
        $cols[] = ['key' => 'act1_subject', 'label' => 'Activity 1: Subject / Domain *', 'width' => 26, 'type' => 'list', 'options' => '"Science,Mathematics,Social Science,Languages,Art Education,Physical Education"', 'note' => 'Subject for Activity 1'];
        $cols[] = ['key' => 'act1_pedagogy', 'label' => 'Activity 1: Pedagogical Approach *', 'width' => 28, 'type' => 'list', 'options' => '"Art-Integrated,Sports-Integrated,Experiential Learning,Toy-Based Pedagogy,Inquiry-Based"', 'note' => 'Pedagogy used'];
        $cols[] = ['key' => 'act1_title', 'label' => 'Activity 1: Activity Title *', 'width' => 30, 'type' => 'string', 'note' => 'e.g. Investigating Heat Transfer'];
        $cols[] = ['key' => 'act1_desc', 'label' => 'Activity 1: Description & Objectives *', 'width' => 45, 'type' => 'string', 'note' => 'Hands-on lab experiment'];

        $cols[] = ['key' => 'act2_subject', 'label' => 'Activity 2: Subject / Domain *', 'width' => 26, 'type' => 'list', 'options' => '"Languages,Art Education,Physical Education and Well-being,Vocational Education,Social Science"', 'note' => 'Subject for Activity 2'];
        $cols[] = ['key' => 'act2_pedagogy', 'label' => 'Activity 2: Pedagogical Approach *', 'width' => 28, 'type' => 'list', 'options' => '"Art-Integrated,Sports-Integrated,Experiential Learning,Toy-Based Pedagogy,Inquiry-Based"', 'note' => 'Pedagogy used'];
        $cols[] = ['key' => 'act2_title', 'label' => 'Activity 2: Activity Title *', 'width' => 30, 'type' => 'string', 'note' => 'e.g. Creative Monologue on Environmental Conservation'];
        $cols[] = ['key' => 'act2_desc', 'label' => 'Activity 2: Description & Objectives *', 'width' => 45, 'type' => 'string', 'note' => 'Dramatic presentation on water crisis'];
        $cols[] = ['key' => 'act_aware', 'label' => 'Rubric: Awareness Level *', 'width' => 22, 'type' => 'list', 'options' => $rubricOpts, 'note' => 'Beginner, Proficient, Advanced'];
        $cols[] = ['key' => 'act_sens', 'label' => 'Rubric: Sensitivity Level *', 'width' => 22, 'type' => 'list', 'options' => $rubricOpts, 'note' => 'Beginner, Proficient, Advanced'];
        $cols[] = ['key' => 'act_creat', 'label' => 'Rubric: Creativity Level *', 'width' => 22, 'type' => 'list', 'options' => $rubricOpts, 'note' => 'Beginner, Proficient, Advanced'];
        $cols[] = ['key' => 'act_self', 'label' => 'Student Self-Reflection on Activity *', 'width' => 36, 'type' => 'string', 'note' => 'e.g. I enjoyed researching local water sources'];
        $cols[] = ['key' => 'act_peer', 'label' => 'Peer Review Feedback *', 'width' => 34, 'type' => 'string', 'note' => 'e.g. Very confident voice and expressive acting'];
        $cols[] = ['key' => 'act_teach_strength', 'label' => 'Teacher Observed Strengths *', 'width' => 36, 'type' => 'string', 'note' => 'e.g. Articulated scientific concepts clearly'];
        $cols[] = ['key' => 'act_teach_barrier', 'label' => 'Teacher Observed Barriers & Next Steps *', 'width' => 38, 'type' => 'string', 'note' => 'e.g. Needs to manage time better during group discussions'];

        // Part-C Curricular Competencies (8 Subjects x 3-4 Competencies = 26 items)
        $midCurricular = [
            'r1_c1' => 'Lang 1 (R1): Reading Comprehension',
            'r1_c2' => 'Lang 1 (R1): Creative Writing & Grammar',
            'r1_c3' => 'Lang 1 (R1): Oral Expression & Vocabulary',
            'r2_c1' => 'Lang 2 (R2): Text Reading & Comprehension',
            'r2_c2' => 'Lang 2 (R2): Sentence Construction & Writing',
            'r2_c3' => 'Lang 2 (R2): Listening & Conversational Fluency',
            'r3_c1' => 'Lang 3 (R3): Basic Script & Word Recognition',
            'r3_c2' => 'Lang 3 (R3): Elementary Writing & Dialogue',
            'mat_c1' => 'Maths: Number Operations & Fractions',
            'mat_c2' => 'Maths: Algebraic Equations & Patterns',
            'mat_c3' => 'Maths: Geometry, Mensuration & Data',
            'mat_c4' => 'Maths: Mathematical Reasoning & Problem Solving',
            'sci_c1' => 'Science: Scientific Inquiry & Observation',
            'sci_c2' => 'Science: Living World & Natural Phenomena',
            'sci_c3' => 'Science: Matter, Forces & Energy Concepts',
            'sci_c4' => 'Science: Lab Experimentation & Safe Handling',
            'soc_c1' => 'Social Sci: Historical Timelines & Heritage',
            'soc_c2' => 'Social Sci: Geography, Maps & Environment',
            'soc_c3' => 'Social Sci: Democratic Institutions & Civic Duty',
            'art_c1' => 'Art: Visual Arts Expression & Media',
            'art_c2' => 'Art: Performing Arts & Cultural Heritage',
            'pe_c1'  => 'PE: Physical Fitness, Agility & Stamina',
            'pe_c2'  => 'PE: Teamwork, Fair Play & Game Rules',
            'pe_c3'  => 'PE: Health, Nutrition & Personal Hygiene',
            'voc_c1' => 'Vocational: Pre-vocational Skills & Tools',
            'voc_c2' => 'Vocational: Work Ethics, Safety & Quality',
        ];

        foreach ($midCurricular as $k => $label) {
            $cols[] = ['key' => "c_{$k}", 'label' => "{$label} *", 'width' => 26, 'type' => 'list', 'options' => $rubricOpts, 'note' => 'Beginner, Proficient, Advanced'];
        }

        // Part-D Subject Marks (8 subjects: Max & Obtained)
        $midMarks = [
            ['key' => 'lang1', 'name' => 'Language 1 (R1)'],
            ['key' => 'lang2', 'name' => 'Language 2 (R2)'],
            ['key' => 'lang3', 'name' => 'Language 3 (R3)'],
            ['key' => 'math',  'name' => 'Mathematics'],
            ['key' => 'sci',   'name' => 'Science'],
            ['key' => 'soc',   'name' => 'Social Science'],
            ['key' => 'art',   'name' => 'Art Education'],
            ['key' => 'pe',    'name' => 'Physical Education'],
        ];

        foreach ($midMarks as $m) {
            $k = $m['key'];
            $n = $m['name'];
            $cols[] = ['key' => "sub_{$k}_max", 'label' => "Max Marks - {$n} *", 'width' => 16, 'type' => 'integer', 'note' => 'Default 50'];
            $cols[] = ['key' => "sub_{$k}_obt", 'label' => "Marks Obtained - {$n} *", 'width' => 18, 'type' => 'integer', 'note' => 'Marks scored (0 to Max)'];
        }

        // Declaration & Remarks
        $cols[] = ['key' => 'teacher_declaration', 'label' => 'Teacher Certification *', 'width' => 22, 'type' => 'list', 'options' => '"Yes,No"', 'note' => 'Declaration of authenticity'];
        $cols[] = ['key' => 'holistic_remarks', 'label' => 'Comprehensive Holistic Remarks *', 'width' => 45, 'type' => 'string', 'note' => 'Holistic summary observation'];

        return $cols;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. SECONDARY STAGE (Class 9 - 12)
    // ─────────────────────────────────────────────────────────────────────────
    protected function getSecondarySections(): array
    {
        return [
            ['title' => '🔒 Locked Metadata',                                   'start' => 0,   'end' => 3,   'bg' => 'FF334155'],
            ['title' => '📋 Part A(1): Student Profile & Attendance',          'start' => 4,   'end' => 41,  'bg' => 'FF1E293B'],
            ['title' => '🎯 Part A(2): Self-Evaluation & Goal Reflection',      'start' => 42,  'end' => 49,  'bg' => 'FF6366F1'],
            ['title' => '⏰ Part A(3): Time Management Map & Daily Routine',     'start' => 50,  'end' => 57,  'bg' => 'FF0D9488'],
            ['title' => '🚀 Part A(4): Future Planning & Career Pathways',      'start' => 58,  'end' => 64,  'bg' => 'FF0284C7'],
            ['title' => '🏆 Part A(5 & 6): Accomplishments & Skills for Life',  'start' => 65,  'end' => 72,  'bg' => 'FFD97706'],
            ['title' => '👥 Part B: Collaborative Group Project Work',          'start' => 73,  'end' => 81,  'bg' => 'FF059669'],
            ['title' => '🔬 Part C: Problem-Based Inquiry (Individual)',        'start' => 82,  'end' => 90,  'bg' => 'FF2563EB'],
            ['title' => '🗣️ Part D: Classroom Interactions (Debates/Lab/Sim)',  'start' => 91,  'end' => 98,  'bg' => 'FFE11D48'],
            ['title' => '📚 Part E: Academic Disciplines & Competencies',        'start' => 99,  'end' => 114, 'bg' => 'FF7C3AED'],
            ['title' => '🎯 HPC Credit Marks: Core Disciplines',               'start' => 115, 'end' => 126, 'bg' => 'FF047857'],
            ['title' => '✍️ Final Declaration & Principal Remarks',             'start' => 127, 'end' => 128, 'bg' => 'FF334155'],
        ];
    }

    protected function getSecondaryColumns(): array
    {
        $cols = $this->getBaseMetadataColumns('"Grade 9,Grade 10,Grade 11,Grade 12"');

        // Part A(2) Self-Evaluation Sheet (Pages 16-18)
        $cols[] = ['key' => 'prev_goal_review', 'label' => 'Review of Goals set in Previous Grade (A2)', 'width' => 35, 'type' => 'string', 'note' => 'e.g. Successfully achieved distinction in science and improved algebra fundamentals'];
        $cols[] = ['key' => 'acad_goal_current', 'label' => 'Academic Goal for this Secondary Year (A2) *', 'width' => 38, 'type' => 'string', 'note' => 'e.g. Master advanced physics mechanics and score >90% in boards'];
        $cols[] = ['key' => 'acad_goal_importance', 'label' => 'Why this Academic Goal matters to me (A2) *', 'width' => 36, 'type' => 'string', 'note' => 'e.g. Essential for cracking competitive engineering entrance'];
        $cols[] = ['key' => 'acad_goal_actions', 'label' => 'Concrete Action Steps for Academic Goal (A2)', 'width' => 38, 'type' => 'string', 'note' => 'e.g. 2 hours daily dedicated problem solving and weekly self-mock tests'];
        $cols[] = ['key' => 'pers_strength', 'label' => 'Personal Strengths Identified (A2) *', 'width' => 32, 'type' => 'string', 'note' => 'e.g. Analytical thinking, resilience, and calm under exam pressure'];
        $cols[] = ['key' => 'pers_improvement', 'label' => 'Areas of Personal Growth / Improvement (A2)', 'width' => 34, 'type' => 'string', 'note' => 'e.g. Reducing screen distraction during study hours'];
        $cols[] = ['key' => 'community_goal', 'label' => 'Family / Community Goal (A2)', 'width' => 32, 'type' => 'string', 'note' => 'e.g. Volunteering weekly at local village library'];
        $cols[] = ['key' => 'feedback_interpretation', 'label' => 'Reflection on Teacher Feedback Received (A2)', 'width' => 36, 'type' => 'string', 'note' => 'e.g. Working on structuring long-form descriptive answers'];

        // Part A(3) Time Management Skills (Pages 19-21)
        $cols[] = ['key' => 'time_study_daily', 'label' => 'Daily Self-Study Hours (A3) *', 'width' => 24, 'type' => 'integer', 'note' => 'Hours per day (e.g. 3)'];
        $cols[] = ['key' => 'time_exercise_daily', 'label' => 'Daily Sports / Physical Exercise (A3) *', 'width' => 26, 'type' => 'list', 'options' => '"30 mins,1 hour,1.5 hours,Less than 30 mins"', 'note' => 'Physical routine'];
        $cols[] = ['key' => 'time_screen_daily', 'label' => 'Daily Screen Time (Mobile/TV) (A3) *', 'width' => 26, 'type' => 'list', 'options' => '"Under 1 hour,1-2 hours,2-3 hours,Over 3 hours"', 'note' => 'Screen habit'];
        $cols[] = ['key' => 'time_sleep_avg', 'label' => 'Average Night Sleep Hours (A3) *', 'width' => 24, 'type' => 'integer', 'note' => 'Hours of sleep (e.g. 7)'];
        $cols[] = ['key' => 'time_spend_more', 'label' => 'Activity I should spend MORE time on (A3) *', 'width' => 35, 'type' => 'string', 'note' => 'e.g. Numerical problem practice and outdoor running'];
        $cols[] = ['key' => 'time_spend_less', 'label' => 'Activity I should spend LESS time on (A3) *', 'width' => 35, 'type' => 'string', 'note' => 'e.g. Mindless social media browsing and idle scrolling'];
        $cols[] = ['key' => 'time_management_rating', 'label' => 'Self-Rating on Time Discipline (A3) *', 'width' => 28, 'type' => 'list', 'options' => '"Highly Disciplined,Moderately Disciplined,Needs Constant Effort"', 'note' => 'Discipline rating'];
        $cols[] = ['key' => 'exam_stress_coping', 'label' => 'How I manage exam time & deadline stress (A3)', 'width' => 38, 'type' => 'string', 'note' => 'e.g. Pomodoro 25-min focus sessions and morning meditation'];

        // Part A(4) Future Planning & Career Exploration (Pages 22-24)
        $cols[] = ['key' => 'career_pathway', 'label' => 'Post-Secondary Plan (Grade 10/12) (A4) *', 'width' => 32, 'type' => 'list', 'options' => '"Higher Education (University),Vocational Education & Training,Direct Employment / Apprenticeship,Entrepreneurship / Family Business"', 'note' => 'Chosen pathway'];
        $cols[] = ['key' => 'aspirational_career', 'label' => 'Aspirational Career / Profession (A4) *', 'width' => 32, 'type' => 'string', 'note' => 'e.g. Biomedical Engineer / Civil Services / Data Analyst'];
        $cols[] = ['key' => 'career_motivation', 'label' => 'Why this career inspires me (A4)', 'width' => 36, 'type' => 'string', 'note' => 'e.g. Combines healthcare technology with saving lives'];
        $cols[] = ['key' => 'stream_preference', 'label' => 'Preferred Stream / Elective Cluster (A4) *', 'width' => 30, 'type' => 'list', 'options' => '"Science (PCM),Science (PCB),Commerce with Maths,Commerce,Humanities & Social Sciences,Vocational / Skill Track"', 'note' => 'Elective cluster'];
        $cols[] = ['key' => 'societal_impact', 'label' => 'How I hope to impact society in future (A4)', 'width' => 38, 'type' => 'string', 'note' => 'e.g. Developing low-cost water purification solutions for rural areas'];
        $cols[] = ['key' => 'mentor_consulted', 'label' => 'Teacher / Career Mentor Name (A4)', 'width' => 26, 'type' => 'string', 'note' => 'e.g. Mohammad Shafi'];
        $cols[] = ['key' => 'mentor_action_advice', 'label' => 'Mentor Advice for Career Realization (A4)', 'width' => 38, 'type' => 'string', 'note' => 'e.g. Strengthen calculus and join state science exhibitions'];

        // Part A(5 & 6) Accomplishments & Skills for Life (Pages 25-28)
        $cols[] = ['key' => 'major_accomplishment', 'label' => 'Significant Accomplishment / Certificate (A5) *', 'width' => 38, 'type' => 'string', 'note' => 'e.g. 1st Prize in Inter-School Science Exhibition'];
        $cols[] = ['key' => 'extracurricular_initiative', 'label' => 'Extracurricular / Club Participation (A5)', 'width' => 36, 'type' => 'string', 'note' => 'e.g. Eco-Club President / Debating Society Member'];
        $cols[] = ['key' => 'life_skill_name', 'label' => 'Skills for Life: Primary Skill Identified (A6) *', 'width' => 35, 'type' => 'string', 'note' => 'e.g. Conflict resolution and collaborative team leadership'];
        $cols[] = ['key' => 'life_skill_context', 'label' => 'Where and how I learned this skill (A6) *', 'width' => 38, 'type' => 'string', 'note' => 'e.g. Managing the annual school sports festival committee'];
        $cols[] = ['key' => 'life_skill_application', 'label' => 'How this skill helps me in adult life (A6)', 'width' => 36, 'type' => 'string', 'note' => 'e.g. Enables me to navigate diverse workplaces constructively'];
        $cols[] = ['key' => 'online_course_enrolled', 'label' => 'Online Course / MOOC Credit (SWAYAM/DIKSHA) *', 'width' => 35, 'type' => 'string', 'note' => 'e.g. SWAYAM: Introduction to AI & Ethics'];
        $cols[] = ['key' => 'online_course_status', 'label' => 'Online Course Completion Status (A6) *', 'width' => 26, 'type' => 'list', 'options' => '"Completed with Certificate,In Progress,Planned for Next Term"', 'note' => 'Course credit'];
        $cols[] = ['key' => 'vocational_training_hours', 'label' => 'Vocational Internship / Skill Hours (A6) *', 'width' => 26, 'type' => 'integer', 'note' => 'Total hours completed (e.g. 20)'];

        // Part B Group Project Work (Collaborative Inquiry) (Pages 29-30)
        $rubricOpts = '"Beginner,Proficient,Advanced"';
        $cols[] = ['key' => 'grp_proj_title', 'label' => 'Group Project Title (Part B) *', 'width' => 34, 'type' => 'string', 'note' => 'e.g. Local Micro-Climate & Rainwater Harvesting Study'];
        $cols[] = ['key' => 'grp_proj_subject', 'label' => 'Group Project: Integrated Discipline (Part B) *', 'width' => 30, 'type' => 'list', 'options' => '"Science & Technology,Mathematics & Analytics,Social Sciences & Economics,Languages & Humanities,Vocational & Environmental"', 'note' => 'Discipline'];
        $cols[] = ['key' => 'grp_proj_role', 'label' => 'Student Role in Project Team (Part B) *', 'width' => 30, 'type' => 'string', 'note' => 'e.g. Lead Data Collector & Statistical Analysis'];
        $cols[] = ['key' => 'grp_proj_aware', 'label' => 'Awareness: Problem Understanding (Part B) *', 'width' => 26, 'type' => 'list', 'options' => $rubricOpts, 'note' => 'Beginner, Proficient, Advanced'];
        $cols[] = ['key' => 'grp_proj_sens', 'label' => 'Sensitivity: Teamwork & Listening (Part B) *', 'width' => 26, 'type' => 'list', 'options' => $rubricOpts, 'note' => 'Beginner, Proficient, Advanced'];
        $cols[] = ['key' => 'grp_proj_creat', 'label' => 'Creativity: Innovative Solution (Part B) *', 'width' => 26, 'type' => 'list', 'options' => $rubricOpts, 'note' => 'Beginner, Proficient, Advanced'];
        $cols[] = ['key' => 'grp_proj_output', 'label' => 'Tangible Project Deliverable (Part B)', 'width' => 32, 'type' => 'string', 'note' => 'e.g. Functional model and 12-page research report'];
        $cols[] = ['key' => 'grp_proj_peer_rating', 'label' => 'Peer Team Evaluation on Contribution (Part B) *', 'width' => 32, 'type' => 'list', 'options' => '"Outstanding Contributor,Reliable Team Member,Needs Guidance"', 'note' => 'Peer appraisal'];
        $cols[] = ['key' => 'grp_proj_teach_eval', 'label' => 'Teacher Assessment on Group Work (Part B)', 'width' => 36, 'type' => 'string', 'note' => 'Demonstrated excellent collaborative inquiry'];

        // Part C Problem-Based Inquiry (Individual) (Pages 31-33)
        $cols[] = ['key' => 'inq_title', 'label' => 'Individual Inquiry Topic (Part C) *', 'width' => 34, 'type' => 'string', 'note' => 'e.g. Impact of Soil Salinity on Seed Germination Rates'];
        $cols[] = ['key' => 'inq_hypothesis', 'label' => 'Research Hypothesis / Guiding Question (Part C)', 'width' => 38, 'type' => 'string', 'note' => 'Higher salinity reduces germination percentage exponentially'];
        $cols[] = ['key' => 'inq_methodology', 'label' => 'Investigation Methodology (Part C) *', 'width' => 35, 'type' => 'string', 'note' => 'e.g. Controlled lab trial with 5 varying salt concentrations'];
        $cols[] = ['key' => 'inq_aware', 'label' => 'Awareness: Literature & Concepts (Part C) *', 'width' => 26, 'type' => 'list', 'options' => $rubricOpts, 'note' => 'Beginner, Proficient, Advanced'];
        $cols[] = ['key' => 'inq_sens', 'label' => 'Sensitivity: Scientific Rigor & Ethics (Part C) *', 'width' => 26, 'type' => 'list', 'options' => $rubricOpts, 'note' => 'Beginner, Proficient, Advanced'];
        $cols[] = ['key' => 'inq_creat', 'label' => 'Creativity: Analytical Depth (Part C) *', 'width' => 26, 'type' => 'list', 'options' => $rubricOpts, 'note' => 'Beginner, Proficient, Advanced'];
        $cols[] = ['key' => 'inq_key_finding', 'label' => 'Key Conclusion / Result (Part C)', 'width' => 35, 'type' => 'string', 'note' => 'e.g. Confirmed hypothesis with 95% statistical validity'];
        $cols[] = ['key' => 'inq_self_reflection', 'label' => 'Learner Self-Reflection on Inquiry (Part C)', 'width' => 36, 'type' => 'string', 'note' => 'Learned how to design repeatable scientific protocols'];
        $cols[] = ['key' => 'inq_teacher_eval', 'label' => 'Teacher Evaluation on Inquiry Rigor (Part C)', 'width' => 36, 'type' => 'string', 'note' => 'Methodical and critical thinking demonstrated'];

        // Part D Classroom Interactions (Debates / Lab / Roleplay / Discussions) (Pages 34-36)
        $cols[] = ['key' => 'inter_format', 'label' => 'Interaction Format (Part D) *', 'width' => 28, 'type' => 'list', 'options' => '"Organised Debate,Classroom Discussion,Lab Experiment / Demonstration,Simulation or Roleplay,Dramatic Presentation"', 'note' => 'Interaction format'];
        $cols[] = ['key' => 'inter_theme', 'label' => 'Interaction Topic / Theme (Part D) *', 'width' => 35, 'type' => 'string', 'note' => 'e.g. Ethics of Artificial Intelligence in Healthcare'];
        $cols[] = ['key' => 'inter_aware', 'label' => 'Awareness: Factual Command & Evidence (Part D) *', 'width' => 28, 'type' => 'list', 'options' => $rubricOpts, 'note' => 'Beginner, Proficient, Advanced'];
        $cols[] = ['key' => 'inter_sens', 'label' => 'Sensitivity: Respect for Counter-Arguments (Part D) *', 'width' => 30, 'type' => 'list', 'options' => $rubricOpts, 'note' => 'Beginner, Proficient, Advanced'];
        $cols[] = ['key' => 'inter_creat', 'label' => 'Creativity: Articulation & Rebuttal (Part D) *', 'width' => 28, 'type' => 'list', 'options' => $rubricOpts, 'note' => 'Beginner, Proficient, Advanced'];
        $cols[] = ['key' => 'inter_peer_vote', 'label' => 'Peer Reaction / Audience Feedback (Part D) *', 'width' => 28, 'type' => 'list', 'options' => '"Highly Persuasive,Thought Provoking,Clear & Well Argued"', 'note' => 'Audience feedback'];
        $cols[] = ['key' => 'inter_teacher_notes', 'label' => 'Teacher Observational Assessment (Part D)', 'width' => 35, 'type' => 'string', 'note' => 'Articulated strong thesis and answered questions adeptly'];
        $cols[] = ['key' => 'inter_next_step', 'label' => 'Identified Next Step for Public Speaking (Part D)', 'width' => 35, 'type' => 'string', 'note' => 'Focus on slowing pace during rebuttal phase'];

        // Part E Academic Disciplines & Competencies (Core Secondary Subjects)
        $secSubjects = [
            'lang1' => 'Language 1: Critical Literary Analysis & Debate',
            'lang2' => 'Language 2: Composition & Complex Comprehension',
            'math1' => 'Mathematics: Abstract Reasoning & Proofs',
            'math2' => 'Mathematics: Functions, Coordinate Geometry & Calc',
            'sci1'  => 'Science: Hypothesis Testing & Quantitative Laws',
            'sci2'  => 'Science: Applied Physics & Molecular Chemistry',
            'sci3'  => 'Science: Ecological Systems & Genetics',
            'soc1'  => 'Social Science: World History & Democratic Heritage',
            'soc2'  => 'Social Science: Economic Development & Global Trade',
            'soc3'  => 'Social Science: Cartography, Resources & Geopolitics',
            'art1'  => 'Art Education: Cultural Aesthetics & Original Portfolio',
            'pe1'   => 'Physical Education: Competitive Sports & Physiology',
            'voc1'  => 'Vocational Education: Industry Standard Competencies',
            'dig1'  => 'Digital Technology: Computational Thinking & Coding',
            'eth1'  => 'Ethics & Values: Civic Responsibility & Empathy',
            'inn1'  => 'Innovation: Interdisciplinary Problem Solving',
        ];

        foreach ($secSubjects as $k => $label) {
            $cols[] = ['key' => "comp_{$k}", 'label' => "{$label} *", 'width' => 28, 'type' => 'list', 'options' => $rubricOpts, 'note' => 'Beginner, Proficient, Advanced'];
        }

        // Summative Marks: Core Disciplines (6 Subjects: Max & Obtained)
        $secMarks = [
            ['key' => 'lang1', 'name' => 'Language 1'],
            ['key' => 'lang2', 'name' => 'Language 2'],
            ['key' => 'math',  'name' => 'Mathematics'],
            ['key' => 'sci',   'name' => 'Science'],
            ['key' => 'soc',   'name' => 'Social Science'],
            ['key' => 'voc',   'name' => 'Vocational / Skill Elective'],
        ];

        foreach ($secMarks as $sm) {
            $k = $sm['key'];
            $n = $sm['name'];
            $cols[] = ['key' => "sub_{$k}_max", 'label' => "Max Marks - {$n} *", 'width' => 16, 'type' => 'integer', 'note' => 'Default 50 / 100'];
            $cols[] = ['key' => "sub_{$k}_obt", 'label' => "Marks Obtained - {$n} *", 'width' => 18, 'type' => 'integer', 'note' => 'Marks scored (0 to Max)'];
        }

        // Final Declaration & Remarks
        $cols[] = ['key' => 'teacher_declaration', 'label' => 'Teacher Certification *', 'width' => 22, 'type' => 'list', 'options' => '"Yes,No"', 'note' => 'Declaration of authenticity'];
        $cols[] = ['key' => 'holistic_remarks', 'label' => 'Principal / Mentor Comprehensive Assessment *', 'width' => 45, 'type' => 'string', 'note' => 'Comprehensive evaluation of secondary maturity and readiness'];

        return $cols;
    }

    /**
    /**
     * Build the Excel Spreadsheet for a given stage and list of students
     * Generates a 3-sheet workbook:
     * - Sheet 1: Manual Entry (Blank) - for manual student evaluation entry
     * - Sheet 2: Pre-Filled (Auto-Fill) - 100% complete pre-filled evaluation baseline
     * - Sheet 3: Instructions & Rules - guidance, rules, and valid values for each part
     */
    public function buildSpreadsheet(string $stage, array $students = [], array $options = []): Spreadsheet
    {
        $stage = self::normalizeStage($stage);
        $spreadsheet = new Spreadsheet();

        // 1. Sheet 1: Manual Entry (Blank)
        $blankOptions = array_merge($options, ['auto_fill' => false]);
        $sheet1 = $spreadsheet->getActiveSheet();
        $this->buildEvaluationsSheetContent($sheet1, $stage, $students, $blankOptions, 'Manual Entry (Blank)');

        // 2. Sheet 2: Pre-Filled (Auto-Fill)
        $filledOptions = array_merge($options, ['auto_fill' => true]);
        $sheet2 = $spreadsheet->createSheet();
        $this->buildEvaluationsSheetContent($sheet2, $stage, $students, $filledOptions, 'Pre-Filled (Auto-Fill)');

        // 3. Sheet 3: Instructions & Rules for Each Part
        $this->buildInstructionsSheet($spreadsheet, $stage);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    protected function buildInstructionsSheet(Spreadsheet $spreadsheet, string $stage): void
    {
        $sheet = $spreadsheet->createSheet();
        $stageInfo = self::getStages()[$stage];
        $sheet->setTitle('Instructions & Rules');

        $title = "HPCAssist - {$stageInfo['title']} ({$stageInfo['grade_range']}) Assessment Template Instructions";
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(32);

        $rules = [
            ['Part / Section', 'Field Types & Scope', 'Validation Rules & Format', 'Permitted Values / Guidance for Teachers'],
            ['Workbook Overview', '3-Sheet Structure', 'Sheet 1 is for Manual Entry (blank evaluations). Sheet 2 is Pre-Filled (baseline 100% complete data). Sheet 3 is Instructions.', 'Choose Sheet 1 to type your own evaluations, or Sheet 2 to tweak pre-filled evaluations.'],
            ['🔒 Locked Metadata', 'Columns A to D (APAAR ID, Login ID, Name, Status)', 'Unique identifiers synchronized with the PARAKH portal roster. Do not edit Login ID or APAAR ID.', 'Columns A, B, D are collapsed by default in Focus Mode. Click [+] above Student Name in Excel to view them.'],
            ['📋 Part A(1): Profile & Attendance', 'Columns E to AP (Admission No, Profile, Monthly Attendance Apr-Mar)', 'School Admission number is mandatory. Monthly working days and present days are arranged neck-to-neck for each month (Apr to Mar).', 'Present Days must be <= Working Days (0-31). Total Working, Present, and Attendance % calculate automatically.'],
            ['🌟 Part A(2): All About Me', 'Columns AQ to BP (Learner Voice, Strengths, Habits, Goal Setting)', 'Student living arrangement, leisure hobbies, self-responsibility levels, areas of improvement, peer friends, and personal/academic goals.', 'Responsibility: All the time, Most times, Sometimes. Realistic student-voice descriptions.'],
            ['🎯 Part A(3): Ambition Card', 'Columns BQ to BZ (Career Exploration, Mentor Guidance)', 'Student career dream, steps to achieve, 5 key skills, focus subjects, habits to cultivate, mentor teacher name and guidance plan.', 'Realistic career pathways (Doctor, Engineer, Teacher, Scientist, Artist, Civil Servant, Entrepreneur).'],
            ['🤝 Part A(4): Parent Partnership', 'Columns CA to CJ (Home Resources, Child Understanding, Support Plan)', 'Home learning materials (Yes/No), Parent understanding of child (Yes, Sometimes, No, Not Sure), Support areas needed (Yes/No), and Home support plan.', 'Checkboxes: Yes / No; Radio smileys: Yes, Sometimes, No, Not Sure; Home plan text.'],
            ['🔬 Part B: Holistic Activity 1 & 2', 'Columns CK to CY (Academic Domain & Co-Curricular / Creative)', 'Pedagogical approach (Art-Integrated, Sports-Integrated, Toy-Based, Inquiry-Based), activity title, description, NCF rubrics (Awareness, Sensitivity, Creativity), reflection, peer review, teacher feedback.', 'Rubric rating levels: Beginner, Proficient, Advanced. Self-effort: Yes / No. Peer praise and teacher support plan.'],
            ['📊 Part C: Curricular Assessment', 'Columns CZ to DY (Domain Competency Performance Levels)', 'Year-end formative and summative achievement levels across core curriculum learning outcomes.', 'Assessment levels: Beginner, Proficient, Advanced.'],
            ['🎯 Part D: HPC Credit Marks Grid', 'Columns DZ to EO (8 Core Subject Marks)', 'Summative Assessment marks for each of the 8 subjects. Max Marks (default 50) and Marks Obtained.', 'Obtained Marks must be an integer between 0 and Max Marks (e.g. 40 to 48 out of 50).'],
            ['✍️ Part E: Declaration & Remarks', 'Columns EP to EQ (Teacher Certification & Annual Holistic Remarks)', 'Teacher declaration certifying entered evaluation data and annual holistic summary remark.', 'Declaration: Yes / No. Holistic remark summarizing overall development, cognitive growth, and social maturity.'],
        ];

        foreach ($rules as $rIdx => $row) {
            $rowNum = $rIdx + 3;
            foreach ($row as $cIdx => $val) {
                $colLetter = chr(65 + $cIdx);
                $sheet->setCellValue("{$colLetter}{$rowNum}", $val);
            }
            if ($rIdx === 0) {
                $sheet->getStyle("A{$rowNum}:D{$rowNum}")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 10],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF334155']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension($rowNum)->setRowHeight(26);
            } else {
                $sheet->getStyle("A{$rowNum}:D{$rowNum}")->applyFromArray([
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                ]);
                $sheet->getRowDimension($rowNum)->setRowHeight(36);
            }
        }

        $sheet->getColumnDimension('A')->setWidth(26);
        $sheet->getColumnDimension('B')->setWidth(38);
        $sheet->getColumnDimension('C')->setWidth(50);
        $sheet->getColumnDimension('D')->setWidth(55);
    }

    /**
     * Curated catalog of official Global NCERT Activities per stage
     */
    public function getGlobalActivities(string $stage): array
    {
        $stage = self::normalizeStage($stage);

        if ($stage === self::STAGE_PREPARATORY) {
            return [
                'evs_nature' => [
                    'id'          => 'evs_nature',
                    'name'        => '🌿 The World Around Us: Nature Observation & Plant Seed Germination',
                    'subject'     => 'The World Around Us',
                    'approach'    => 'Inquiry-Based',
                    'title'       => 'Nature Observation & Plant Seed Germination Study',
                    'desc'        => 'Planted local seeds, tracked daily sprout growth in leaf journal, and compared soil conditions.',
                    'lang1_act'   => 'Story Recitation & Creative Roleplay',
                    'lang2_act'   => 'Picture Word Association & Dialogue Reading',
                    'math_act'    => 'Measurement, Shapes & Toy-Clock Project',
                    'evs_act'     => 'Nature Observation & Plant Seed Germination Study',
                    'art_pe_act'  => 'Indigenous Games (Kho-Kho) & Clay Pottery Expression',
                    'work_act'    => 'Kitchen Garden Composting & Craft Origami',
                ],
                'math_shapes' => [
                    'id'          => 'math_shapes',
                    'name'        => '📐 Mathematics: Geometric Shapes, Toy-Clock & Classroom Measuring',
                    'subject'     => 'Mathematics',
                    'approach'    => 'Toy-Based Pedagogy',
                    'title'       => 'Geometric Shapes, Toy-Clock & Classroom Measuring',
                    'desc'        => 'Used cardboard toy clocks to calculate time, measured classroom perimeter with footsteps and rulers.',
                    'lang1_act'   => 'Story Recitation & Creative Roleplay',
                    'lang2_act'   => 'Picture Word Association & Dialogue Reading',
                    'math_act'    => 'Geometric Shapes, Toy-Clock & Classroom Measuring',
                    'evs_act'     => 'Nature Observation & Plant Seed Germination Study',
                    'art_pe_act'  => 'Indigenous Games (Kho-Kho) & Clay Pottery Expression',
                    'work_act'    => 'Kitchen Garden Composting & Craft Origami',
                ],
                'lang_folktale' => [
                    'id'          => 'lang_folktale',
                    'name'        => '📖 Language Education: Regional Folktale Dramatization & Puppet Theatre',
                    'subject'     => 'Language 1 (R1 - First Language)',
                    'approach'    => 'Art-Integrated',
                    'title'       => 'Regional Folktale Dramatization & Puppet Theatre',
                    'desc'        => 'Crafted paper-bag puppets and performed traditional Panchatantra fables with lively dialogue.',
                    'lang1_act'   => 'Regional Folktale Dramatization & Puppet Theatre',
                    'lang2_act'   => 'Picture Word Association & Dialogue Reading',
                    'math_act'    => 'Measurement, Shapes & Toy-Clock Project',
                    'evs_act'     => 'Nature Observation & Plant Seed Germination Study',
                    'art_pe_act'  => 'Indigenous Games (Kho-Kho) & Clay Pottery Expression',
                    'work_act'    => 'Kitchen Garden Composting & Craft Origami',
                ],
            ];
        }

        // Middle Stage (Classes 6 to 8)
        return [
            'science_solar' => [
                'id'          => 'science_solar',
                'name'        => '🔬 Science: Solar System 3D Model & Daily Life Geometry',
                'subject'     => 'Science',
                'approach'    => 'Art-Integrated',
                'title'       => 'Solar System 3D Model & Daily Life Geometry',
                'desc'        => 'Constructed scale models of planetary orbits, computed relative distance ratios, and demonstrated to class.',
                'act2_subject'=> 'Languages',
                'act2_approach'=> 'Art-Integrated',
                'act2_title'  => 'Creative Monologue on Environmental Conservation',
                'act2_desc'   => 'Wrote and delivered a theatrical speech on preserving local water bodies and biodiversity.',
                'learnt'      => 'Learned celestial orbital distances, 3D scale geometry, and public presentation skills.',
                'interesting' => 'Fabricating rotating planetary models using recycled materials and LED lighting.',
                'practice'    => 'Time management during hands-on model assembly and fine scale calibrations.',
                'help'        => 'Precision measuring tools and ratio conversions.',
                'peer_feedback' => 'Very supportive and collaborative teammate; explained difficult astronomical concepts clearly.',
                'strength'    => 'Collaboration',
                'barriers'    => 'None',
                'support_plan'=> 'Encourage participation in inter-school science competitions, provide advanced telescope observation sessions, and recommend for student mentorship roles.',
            ],
            'math_geometry' => [
                'id'          => 'math_geometry',
                'name'        => '📐 Mathematics: Geometric Symmetry & Scale Calibration in School Garden',
                'subject'     => 'Mathematics',
                'approach'    => 'Toy-Based Pedagogy',
                'title'       => 'Geometric Symmetry & Scale Calibration in School Garden',
                'desc'        => 'Measured garden plots, mapped geometric patterns, calculated perimeter/area ratios, and presented findings.',
                'act2_subject'=> 'Art Education',
                'act2_approach'=> 'Experiential Learning',
                'act2_title'  => 'Geometric Tessellation & Traditional Tile Mosaics',
                'act2_desc'   => 'Designed recurring polygonal mosaic patterns inspired by regional architecture and Islamic art.',
                'learnt'      => 'Practical area calculation, scale conversions, and visual symmetry.',
                'interesting' => 'Constructing handmade geometric compasses and measuring real-life outdoor plots.',
                'practice'    => 'Geometric compass accuracy and multi-step fraction arithmetic.',
                'help'        => 'Advanced algebraic word problem formulation.',
                'peer_feedback' => 'Outstanding team leader who helped everyone double-check their calculations calmly.',
                'strength'    => 'Solution-Focused Thinking',
                'barriers'    => 'None',
                'support_plan'=> 'Assign challenging real-life optimization word problems and encourage peer math tutoring.',
            ],
            'social_heritage' => [
                'id'          => 'social_heritage',
                'name'        => '🌍 Social Science: Unity in Diversity & Local Community Heritage Mapping',
                'subject'     => 'Social Science',
                'approach'    => 'Experiential Learning',
                'title'       => 'Unity in Diversity & Local Community Heritage Mapping',
                'desc'        => 'Conducted interviews on regional crafts, created pictorial historical timelines, and documented local oral folklore.',
                'act2_subject'=> 'Physical Education and Well-being',
                'act2_approach'=> 'Sports-Integrated',
                'act2_title'  => 'Traditional Indigenous Sports Demonstration (Gilli-Danda / Kho-Kho)',
                'act2_desc'   => 'Researched rules of traditional rural games and organized an exhibition match for junior classes.',
                'learnt'      => 'Historical empathy, interview question framing, and local cultural diversity.',
                'interesting' => 'Recording oral history tales from community elders and sketching traditional architecture.',
                'practice'    => 'Organizing historical source citations and chronological timeline formatting.',
                'help'        => 'Translating traditional dialect phrases into formal descriptive paragraphs.',
                'peer_feedback' => 'Very respectful listener during elder interviews and contributed artistic map illustrations.',
                'strength'    => 'Empathy',
                'barriers'    => 'None',
                'support_plan'=> 'Involve in school museum curating and provide advanced historical biography reading lists.',
            ],
            'languages_story' => [
                'id'          => 'languages_story',
                'name'        => '📖 Language Education: Creative Monologue & Community Roleplay Interview',
                'subject'     => 'Language 1 (R1)',
                'approach'    => 'Art-Integrated',
                'title'       => 'Creative Monologue & Community Roleplay Interview',
                'desc'        => 'Wrote and dramatized an empathetic monologue on river conservation and interviewed village community members.',
                'act2_subject'=> 'Vocational Education',
                'act2_approach'=> 'Experiential Learning',
                'act2_title'  => 'Basic Bookbinding & Calligraphy Workshop',
                'act2_desc'   => 'Learned traditional pamphlet sewing, binding techniques, and calligraphic chapter title design.',
                'learnt'      => 'Voice modulation, empathetic listening, and expressive paragraph writing.',
                'interesting' => 'Performing the environmental dramatic monologue in front of the entire assembly.',
                'practice'    => 'Complex grammatical sentence transitions and stage voice projection.',
                'help'        => 'Expanding descriptive metaphorical vocabulary.',
                'peer_feedback' => 'Very emotive and powerful speaker; captivated everyone\'s attention during the skit.',
                'strength'    => 'Communication',
                'barriers'    => 'None',
                'support_plan'=> 'Encourage participation in inter-school debates and poetry recitation competitions.',
            ],
        ];
    }

    public static function getSectionShortPrefix(string $sectionTitle): string
    {
        // Strip emojis and non-ascii decorative symbols
        $clean = preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{1F900}-\x{1F9FF}\x{1F1E0}-\x{1F1FF}]/u', '', $sectionTitle);
        $clean = trim($clean);

        if (stripos($clean, 'Locked') !== false || stripos($clean, 'Identity') !== false) {
            return '[Locked Identity]';
        }
        if (stripos($clean, 'Part A(2)') !== false || stripos($clean, 'Part-A2') !== false || stripos($clean, 'All About Me') !== false || stripos($clean, 'Surroundings') !== false || stripos($clean, 'Self-Evaluation') !== false) {
            if (stripos($clean, 'Surroundings') !== false) return '[Part A(2) Me & Surroundings]';
            if (stripos($clean, 'Self-Evaluation') !== false) return '[Part A(2) Self-Evaluation]';
            return '[Part A(2) All About Me]';
        }
        if (stripos($clean, 'Part A(1)') !== false || stripos($clean, 'Part-A1') !== false || stripos($clean, 'Profile') !== false) {
            return '[Part A(1) Profile & Attendance]';
        }
        if (stripos($clean, 'Part A(3)') !== false || stripos($clean, 'Ambition') !== false || stripos($clean, 'Peer') !== false || stripos($clean, 'Time Management') !== false) {
            if (stripos($clean, 'Ambition') !== false) return '[Part A(3) Ambition Card]';
            if (stripos($clean, 'Peer') !== false) return '[Part A(3) Peer & Parent Voice]';
            if (stripos($clean, 'Time Management') !== false) return '[Part A(3) Time Management]';
            return '[Part A(3)]';
        }
        if (stripos($clean, 'Part A(4)') !== false || stripos($clean, 'Parent') !== false || stripos($clean, 'Future Planning') !== false) {
            if (stripos($clean, 'Future Planning') !== false) return '[Part A(4) Career Pathways]';
            return '[Part A(4) Parent Partnership]';
        }
        if (stripos($clean, 'Part A(5') !== false || stripos($clean, 'Accomplishments') !== false || stripos($clean, 'Life Skills') !== false) {
            return '[Part A(5 & 6) Life Skills]';
        }
        if (stripos($clean, 'Domain 1') !== false || stripos($clean, 'Physical Development') !== false) {
            return '[Domain 1 Physical Dev]';
        }
        if (stripos($clean, 'Domain 2') !== false || stripos($clean, 'Socio-Emotional') !== false) {
            return '[Domain 2 Socio-Emotional]';
        }
        if (stripos($clean, 'Domain 3') !== false || stripos($clean, 'Cognitive') !== false) {
            return '[Domain 3 Cognitive Dev]';
        }
        if (stripos($clean, 'Domain 4') !== false || stripos($clean, 'Language & Literacy') !== false) {
            return '[Domain 4 Language & Literacy]';
        }
        if (stripos($clean, 'Domain 5') !== false || stripos($clean, 'Aesthetic') !== false) {
            return '[Domain 5 Aesthetic & Cultural]';
        }
        if (stripos($clean, 'Learning Habits') !== false) {
            return '[Learning Habits & Self-Eval]';
        }
        if (stripos($clean, 'Activity 1') !== false) {
            return '[Part B Activity 1]';
        }
        if (stripos($clean, 'Activity 2') !== false) {
            return '[Part B Activity 2]';
        }
        if (stripos($clean, 'Language 1') !== false || stripos($clean, 'Language (R1)') !== false) {
            return '[Part B Language 1]';
        }
        if (stripos($clean, 'Language 2') !== false || stripos($clean, 'Language (R2)') !== false) {
            return '[Part B Language 2]';
        }
        if (stripos($clean, 'Mathematics') !== false) {
            return '[Part B Mathematics]';
        }
        if (stripos($clean, 'World Around Us') !== false || stripos($clean, 'EVS') !== false) {
            return '[Part B World Around Us / EVS]';
        }
        if (stripos($clean, 'Art Education') !== false || stripos($clean, 'Physical Well-being') !== false) {
            return '[Part B Art & Physical Ed]';
        }
        if (stripos($clean, 'Vocational') !== false || stripos($clean, 'Work Pre-Skills') !== false) {
            return '[Part B Vocational Exposure]';
        }
        if (stripos($clean, 'Group Project') !== false) {
            return '[Part B Collaborative Project]';
        }
        if (stripos($clean, 'Problem-Based') !== false || stripos($clean, 'Inquiry') !== false) {
            return '[Part C Problem Inquiry]';
        }
        if (stripos($clean, 'Classroom Interactions') !== false || stripos($clean, 'Debates') !== false) {
            return '[Part D Classroom Interactions]';
        }
        if (stripos($clean, 'Disciplines & Competencies') !== false) {
            return '[Part E Competencies]';
        }
        if (stripos($clean, 'Credit Marks') !== false || stripos($clean, 'HPC Credit') !== false) {
            return '[Part D HPC Credit Marks]';
        }
        if (stripos($clean, 'Part C') !== false && stripos($clean, 'Marks') !== false) {
            return '[Part C Competency & Marks]';
        }
        if (stripos($clean, 'Part C') !== false && stripos($clean, 'Curricular') !== false) {
            return '[Part C Curricular Levels]';
        }
        if (stripos($clean, 'Declaration') !== false || stripos($clean, 'Remarks') !== false) {
            return '[Final Declaration & Remarks]';
        }

        return '[' . substr($clean, 0, 24) . ']';
    }

    protected function buildEvaluationsSheet(Spreadsheet $spreadsheet, string $stage, array $students, array $options = []): void
    {
        $this->buildEvaluationsSheetContent($spreadsheet->getActiveSheet(), $stage, $students, $options, 'Student Evaluations');
    }

    public function buildEvaluationsSheetContent(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $evalSheet, string $stage, array $students, array $options = [], string $title = 'Student Evaluations'): void
    {
        $evalSheet->setTitle($title);

        $columns  = $this->getStageColumns($stage);
        $sections = $this->getStageSections($stage);

        // Map column indices to sections
        $colToSection = [];
        foreach ($sections as $sec) {
            for ($i = $sec['start']; $i <= $sec['end']; $i++) {
                $colToSection[$i] = $sec;
            }
        }

        // Row 1: Fixed Part Headings on EVERY column so heading never scrolls off or disappears
        foreach ($sections as $sec) {
            $secBg = $sec['bg'] ?? 'FF1E293B';
            for ($cIdx = $sec['start']; $cIdx <= $sec['end']; $cIdx++) {
                $colLetter = $this->indexToColumnLetter($cIdx);
                $evalSheet->setCellValue("{$colLetter}1", $sec['title']);
                $evalSheet->getStyle("{$colLetter}1")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 10],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $secBg]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => false],
                    'borders'   => [
                        'top'    => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFFFFFFF']],
                        'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FFFFFFFF']],
                        'left'   => ($cIdx === $sec['start']) ? ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FFFFFFFF']] : ['borderStyle' => Border::BORDER_NONE],
                        'right'  => ($cIdx === $sec['end']) ? ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FFFFFFFF']] : ['borderStyle' => Border::BORDER_NONE],
                    ],
                ]);
            }
        }
        $evalSheet->getRowDimension(1)->setRowHeight(32);

        // Row 2: Field Names & Row 3: Guidance Notes
        foreach ($columns as $idx => $col) {
            $colLetter = $this->indexToColumnLetter($idx);
            $isObt    = str_ends_with($col['key'], '_obt');
            $isLocked = in_array($col['type'], ['locked', 'calc']);

            $label = $isObt ? '✏️ ' . $col['label'] : $col['label'];

            $evalSheet->setCellValue("{$colLetter}2", $label);
            $evalSheet->setCellValue("{$colLetter}3", $col['note'] ?? '');
            $colWidth = max($col['width'], 20);
            $evalSheet->getColumnDimension($colLetter)->setWidth($colWidth);

            $headerBg    = $isObt ? 'FFFEF08A' : ($isLocked ? 'FFE2E8F0' : 'FF1E293B');
            $headerColor = $isObt ? 'FF854D0E' : ($isLocked ? 'FF334155' : 'FFFFFFFF');

            $evalSheet->getStyle("{$colLetter}2")->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['argb' => $headerColor], 'size' => 9.5],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $headerBg]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']]],
            ]);

            $evalSheet->getStyle("{$colLetter}3")->applyFromArray([
                'font'      => ['italic' => true, 'color' => ['argb' => 'FF64748B'], 'size' => 8.5],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8FAFC']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
            ]);
        }
        $evalSheet->getRowDimension(2)->setRowHeight(32);
        $evalSheet->getRowDimension(3)->setRowHeight(24);

        // Rows 4 onwards: Real Enrolled Students (or Starter Sample Students if none synced yet)
        if (empty($students)) {
            $stageInfo = self::getStages()[$stage] ?? ['grades' => ['Class 6']];
            $students = [
                [
                    'apaar_id'            => '998877665544',
                    'hpc_id'              => 'HPCL-001',
                    'name'                => 'Ayaan Ahmad',
                    'grade'               => $stageInfo['grades'][0] ?? 'Class 6',
                    'section'             => 'A',
                    'gender'              => 'Male',
                    'roll_no'             => '1',
                    'status'              => 'HPC In Progress',
                    'school_admission_no' => '2024-001',
                ],
                [
                    'apaar_id'            => '998877665545',
                    'hpc_id'              => 'HPCL-002',
                    'name'                => 'Fatima Bano',
                    'grade'               => $stageInfo['grades'][0] ?? 'Class 6',
                    'section'             => 'A',
                    'gender'              => 'Female',
                    'roll_no'             => '2',
                    'status'              => 'HPC In Progress',
                    'school_admission_no' => '2024-002',
                ],
            ];
        }

        $defaultWd = SchoolWorkingDay::getDefault();
        $defaultWorkMap = $defaultWd->toMonthMap();
        $defaultMax = SchoolMaxMark::getDefault()->toSubjectMap();

        foreach ($students as $sIdx => $st) {
            $rowNum = $sIdx + 4;
            $starterRow = $this->getStarterRowForStage($stage, $sIdx, $st, $defaultWorkMap, $defaultMax, $rowNum, $options);

            foreach ($columns as $cIdx => $col) {
                $colLetter = $this->indexToColumnLetter($cIdx);
                $val = $starterRow[$cIdx] ?? '';

                if (in_array($col['key'], ['admission_no', 'apaar_id', 'login_id'], true)) {
                    $evalSheet->setCellValueExplicit("{$colLetter}{$rowNum}", (string)$val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                } else {
                    $evalSheet->setCellValue("{$colLetter}{$rowNum}", $val);
                }

                if (($col['type'] ?? '') === 'list' && !empty($col['options'])) {
                    $validation = $evalSheet->getCell("{$colLetter}{$rowNum}")->getDataValidation();
                    $validation->setType(DataValidation::TYPE_LIST);
                    $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                    $validation->setAllowBlank(true);
                    $validation->setShowDropDown(true);
                    $validation->setShowInputMessage(true);
                    $validation->setPromptTitle('Select Option');
                    $validation->setPrompt('Click the dropdown arrow to pick an option.');
                    $validation->setFormula1($col['options']);
                }

                $isObt    = str_ends_with($col['key'], '_obt');
                $isLocked = in_array($col['type'], ['locked', 'calc']);

                $cellBg    = $isObt ? 'FFFEFCE8' : ($isLocked ? 'FFF1F5F9' : 'FFFFFFFF');
                $cellColor = $isObt ? 'FF0F172A' : ($isLocked ? 'FF475569' : 'FF1E293B');

                $evalSheet->getStyle("{$colLetter}{$rowNum}")->applyFromArray([
                    'font'      => ['bold' => ($isObt || $isLocked), 'color' => ['argb' => $cellColor]],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $cellBg]],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $isObt ? 'FFFDE047' : 'FFE2E8F0']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
            }
            $evalSheet->getRowDimension($rowNum)->setRowHeight(22);
        }

        $lastColLetter = $this->indexToColumnLetter(count($columns) - 1);
        $maxRow = max(5, count($students) + 4);
        $evalDataRange = "A5:{$lastColLetter}{$maxRow}";

        $blankRule = new Conditional();
        $blankRule->setConditionType(Conditional::CONDITION_CONTAINSBLANKS);
        $blankRule->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
        $blankRule->getStyle()->getFill()->getStartColor()->setARGB('FFFEE2E2');
        $blankRule->getStyle()->getFill()->getEndColor()->setARGB('FFFEE2E2');

        $evalSheet->getStyle($evalDataRange)->setConditionalStyles([$blankRule]);

        // Configure Outline & Focus Mode:
        $evalSheet->setShowSummaryRight(true);
        $evalSheet->setShowSummaryBelow(false);

        // 1. Locked Left Side Focus Mode:
        // Columns A (APAAR ID), B (HPC Login ID), D (Status) collapsed under Outline Level 1.
        // Column C (Student Name) is visible at Outline Level 0 with width 28.
        // In the frozen left pane, ONLY Student Name is displayed, maximizing screen width for data entry!
        // Teachers can click the [+] button above column headers anytime to show all identifiers.
        $evalSheet->getColumnDimension('A')->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
        $evalSheet->getColumnDimension('B')->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
        $evalSheet->getColumnDimension('C')->setOutlineLevel(0)->setVisible(true)->setWidth(28);
        $evalSheet->getColumnDimension('D')->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);

        // 2. Part Outlines: Group assessment sections so teachers can complete one part then collapse it and go next
        foreach ($sections as $sec) {
            if ($sec['start'] >= 4 && $sec['end'] > $sec['start']) {
                for ($c = $sec['start']; $c < $sec['end']; $c++) {
                    $cLtr = $this->indexToColumnLetter($c);
                    $evalSheet->getColumnDimension($cLtr)->setOutlineLevel(1)->setVisible(true)->setCollapsed(false);
                }
                $endLtr = $this->indexToColumnLetter($sec['end']);
                $evalSheet->getColumnDimension($endLtr)->setOutlineLevel(0)->setVisible(true);
            }
        }

        // Freeze pane at E4: Columns A, B, C, D and Rows 1, 2, 3 are frozen.
        // Because A, B, and D are collapsed by default, ONLY Column C (Student Name) occupies screen width!
        $evalSheet->freezePane('E4');
    }

    /**
     * Cache for UDISE / Master Student Roster admission number lookups
     */
    protected static ?array $admissionLookupCache = null;

    /**
     * Build comprehensive admission number lookup map from uploaded Excel, previews, UDISE, and master rosters.
     */
    public function loadAdmissionLookupMap(): array
    {
        $map = [];

        // 1. Scan uploaded Excel files directly (exact what teacher uploaded)
        $uploadedExcels = array_merge(
            glob(storage_path('app/hpc/teacher_assessment_uploaded*.xlsx')) ?: [],
            glob(storage_path('app/hpc/schools/*/teacher_assessment_uploaded*.xlsx')) ?: []
        );
        foreach ($uploadedExcels as $ue) {
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($ue);
                $sheet = $spreadsheet->getActiveSheet();
                $highestRow = min(500, $sheet->getHighestRow());
                $highestCol = min(30, \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn()));
                $loginCol = 2;
                $apaarCol = 1;
                $nameCol  = 3;
                $admCol   = 5;
                for ($c = 1; $c <= $highestCol; $c++) {
                    $header = strtolower(trim((string)$sheet->getCell([$c, 2])->getValue()));
                    if (str_contains($header, 'login id') || str_contains($header, 'hpc login')) $loginCol = $c;
                    elseif (str_contains($header, 'apaar')) $apaarCol = $c;
                    elseif (str_contains($header, 'student name')) $nameCol = $c;
                    elseif (str_contains($header, 'admission')) $admCol = $c;
                }
                for ($r = 4; $r <= $highestRow; $r++) {
                    $login = trim((string)$sheet->getCell([$loginCol, $r])->getValue());
                    $apaar = trim((string)$sheet->getCell([$apaarCol, $r])->getValue());
                    $name  = trim((string)$sheet->getCell([$nameCol, $r])->getValue());
                    $adm   = trim((string)$sheet->getCell([$admCol, $r])->getValue());
                    if ($login === 'DEMO_SAMPLE' || str_contains(strtoupper($name), 'SAMPLE')) continue;
                    if (! empty($adm) && $adm !== $login && $adm !== $apaar) {
                        if (! empty($login)) $map['login:' . $login] = $adm;
                        if (! empty($apaar)) $map['apaar:' . $apaar] = $adm;
                        if (! empty($name)) {
                            $clean = strtolower(preg_replace('/[^a-z0-9]/i', '', $name));
                            if (! empty($clean)) $map['name:' . $clean] = $adm;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        // 2. Scan teacher_preview*.json files in storage
        $previewFiles = array_merge(
            glob(storage_path('app/hpc/teacher_preview*.json')) ?: [],
            glob(storage_path('app/hpc/schools/*/teacher_preview*.json')) ?: []
        );
        foreach ($previewFiles as $pf) {
            $data = json_decode(@file_get_contents($pf), true) ?: [];
            foreach ($data as $item) {
                $adm   = trim((string)($item['admission_no'] ?? ''));
                $login = trim((string)($item['login_id'] ?? ''));
                $apaar = trim((string)($item['apaar_id'] ?? ''));
                if (empty($adm) || $adm === $login || $adm === $apaar) continue;
                if (! empty($login)) $map['login:' . $login] = $adm;
                if (! empty($apaar)) $map['apaar:' . $apaar] = $adm;
                if (! empty($item['name'])) {
                    $clean = strtolower(preg_replace('/[^a-z0-9]/i', '', $item['name']));
                    if (! empty($clean)) $map['name:' . $clean] = $adm;
                }
            }
        }

        // 3. Scan teacher_students.json rosters in storage
        $studentFiles = array_merge(
            glob(storage_path('app/hpc/teacher_students.json')) ?: [],
            glob(storage_path('app/hpc/schools/*/teacher_students.json')) ?: [],
            glob(storage_path('app/hpc/schools/*/teacher-profiles/teacher_*/teacher_students.json')) ?: []
        );
        foreach ($studentFiles as $sf) {
            $data = json_decode(@file_get_contents($sf), true) ?: [];
            foreach ($data as $item) {
                $adm   = trim((string)($item['admission_no'] ?? ''));
                $login = trim((string)($item['login_id'] ?? ''));
                $apaar = trim((string)($item['apaar_id'] ?? ''));
                if (empty($adm) || $adm === $login || $adm === $apaar) continue;
                if (! empty($login)) $map['login:' . $login] = $adm;
                if (! empty($apaar)) $map['apaar:' . $apaar] = $adm;
                if (! empty($item['name'])) {
                    $clean = strtolower(preg_replace('/[^a-z0-9]/i', '', $item['name']));
                    if (! empty($clean)) $map['name:' . $clean] = $adm;
                }
            }
        }

        // 4. Scan UDISE JSON files
        $udiseFiles = array_merge(
            glob(storage_path('app/hpc/udise_students_*.json')) ?: [],
            glob(storage_path('app/hpc/schools/*/udise_students_*.json')) ?: []
        );
        foreach ($udiseFiles as $uf) {
            $data = json_decode(@file_get_contents($uf), true) ?: [];
            foreach ($data as $item) {
                $adm = trim((string)($item['admnNumber'] ?? $item['admissionNo'] ?? $item['admission_no'] ?? ''));
                if (empty($adm)) continue;
                if (! empty($item['apaarId']) && $adm === trim((string)$item['apaarId'])) continue;
                if (! empty($item['studentCodeNat']) && $adm === trim((string)$item['studentCodeNat'])) continue;

                if (! empty($item['apaarId'])) $map['apaar:' . trim($item['apaarId'])] = $adm;
                if (! empty($item['studentCodeNat'])) $map['pen:' . trim($item['studentCodeNat'])] = $adm;
                if (! empty($item['studentName'])) {
                    $clean = strtolower(preg_replace('/[^a-z0-9]/i', '', $item['studentName']));
                    if (! empty($clean)) $map['name:' . $clean] = $adm;
                }
            }
        }

        // 5. Scan Master Excel files (*Students_Full_Details*.xlsx, students.xlsx)
        $excelFiles = array_merge(
            glob(storage_path('app/hpc/*Students_Full_Details*.xlsx')) ?: [],
            glob(storage_path('app/hpc/students.xlsx')) ?: []
        );
        foreach ($excelFiles as $ef) {
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($ef);
                $sheet = $spreadsheet->getActiveSheet();
                $highestRow = min(200, $sheet->getHighestRow());
                for ($r = 4; $r <= $highestRow; $r++) {
                    $apaar = trim((string)$sheet->getCell([2, $r])->getValue());
                    $pen   = trim((string)$sheet->getCell([3, $r])->getValue());
                    $name  = trim((string)$sheet->getCell([4, $r])->getValue());
                    $adm   = trim((string)$sheet->getCell([7, $r])->getValue());
                    if (! empty($adm) && $adm !== $pen && $adm !== $apaar) {
                        if (! empty($apaar) && $apaar !== 'Not Generated') $map['apaar:' . $apaar] = $adm;
                        if (! empty($pen)) $map['pen:' . $pen] = $adm;
                        if (! empty($name)) {
                            $clean = strtolower(preg_replace('/[^a-z0-9]/i', '', $name));
                            if (! empty($clean)) $map['name:' . $clean] = $adm;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Ignore unreadable excel
            }
        }

        return $map;
    }

    /**
     * Robust resolution of a student's school admission number.
     * Supports both pure integers (e.g. 309, 284) and alphanumeric strings (e.g. ADM-105, 2024/01, S-101).
     * Fetches exact uploaded/registered admission number. If student has none, returns empty string (no dummy values).
     */
    public function resolveAdmissionNumber(array $st, int $sIdx = 0): string
    {
        $loginId = trim((string)($st['login_id'] ?? $st['hpc_id'] ?? ''));
        $apaarId = trim((string)($st['apaar_id'] ?? ''));
        $name    = trim((string)($st['name'] ?? ''));

        // 1. Check direct keys on student (integer or integer and string)
        $candidates = [
            $st['admission_no'] ?? null,
            $st['admnNumber'] ?? null,
            $st['admissionNo'] ?? null,
            $st['admission_number'] ?? null,
            $st['adm_no'] ?? null,
        ];

        foreach ($candidates as $cand) {
            if ($cand !== null && trim((string)$cand) !== '') {
                $candStr = trim((string)$cand);
                // Ensure candidate is not accidentally the HPC Login ID or APAAR ID
                $isDuplicateId = (! empty($loginId) && strcasecmp($candStr, $loginId) === 0)
                              || (! empty($apaarId) && strcasecmp($candStr, $apaarId) === 0);
                if (! $isDuplicateId) {
                    return $candStr;
                }
            }
        }

        // 2. Check Database if available
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $school = $user?->getEffectiveSchool();
            if ($school) {
                $match = $school->students()
                    ->when(! empty($apaarId), fn($q) => $q->where('apaar_id', $apaarId))
                    ->when(empty($apaarId) && ! empty($loginId), fn($q) => $q->where('pen_code', $loginId)->orWhere('apaar_id', $loginId))
                    ->when(empty($apaarId) && empty($loginId) && ! empty($name), fn($q) => $q->where('name', $name))
                    ->first();
                if ($match && ! empty($match->admission_no)) {
                    $dbAdm = trim((string)$match->admission_no);
                    if ($dbAdm !== '' && strcasecmp($dbAdm, $loginId) !== 0 && strcasecmp($dbAdm, $apaarId) !== 0) {
                        return $dbAdm;
                    }
                }
            }
        } catch (\Throwable $e) {
            // DB might be offline or not connected, continue cascade
        }

        // 3. Check cached lookup map (loaded from uploaded Excel, teacher preview, and UDISE)
        if (self::$admissionLookupCache === null) {
            self::$admissionLookupCache = $this->loadAdmissionLookupMap();
        }

        if (! empty(self::$admissionLookupCache)) {
            if (! empty($loginId) && isset(self::$admissionLookupCache['login:' . $loginId])) {
                return self::$admissionLookupCache['login:' . $loginId];
            }
            if (! empty($apaarId) && isset(self::$admissionLookupCache['apaar:' . $apaarId])) {
                return self::$admissionLookupCache['apaar:' . $apaarId];
            }
            $cleanName = strtolower(preg_replace('/[^a-z0-9]/i', '', $name));
            if (! empty($cleanName) && isset(self::$admissionLookupCache['name:' . $cleanName])) {
                return self::$admissionLookupCache['name:' . $cleanName];
            }
        }

        // 4. If no admission number was ever uploaded or registered for this student, keep it empty (NO dummy generation)
        return '';
    }

    /**
     * Generate realistic pre-filled starter values for a student row
     */
    public function getStarterRowForStage(string $stage, int $sIdx, array $st, array $defaultWorkMap, array $defaultMax, int $rowNum, array $options = []): array
    {
        $stage = self::normalizeStage($stage);
        $columns = $this->getStageColumns($stage);

        $useGlobal = $options['use_global_activities'] ?? true;
        $presetKey = $options['activity_preset'] ?? null;
        $autoFill  = $options['auto_fill'] ?? true;

        $globalPresets = $this->getGlobalActivities($stage);
        $globalAct = ($presetKey && isset($globalPresets[$presetKey]))
            ? $globalPresets[$presetKey]
            : (reset($globalPresets) ?: []);

        $peers = ['Ayaan Ahmad', 'Zaid Farooq', 'Fatima Zahra', 'Maryam Jan', 'Sahil Bashir', 'Iqra Parveen', 'Arsalan Mir', 'Insha Tariq'];
        $peerName  = $peers[$sIdx % count($peers)];
        $peer2Name = $peers[($sIdx + 1) % count($peers)];

        $rubricAwr   = (($sIdx + 1) % 3 === 0) ? 'Advanced' : 'Proficient';
        $rubricSens  = (($sIdx + 2) % 3 === 0) ? 'Advanced' : 'Proficient';
        $rubricCreat = ($sIdx % 2 === 0) ? 'Advanced' : 'Proficient';

        $actMap = [];
        if ($useGlobal) {
            $actMap = [
                // Middle Stage (StageTemplateService keys)
                'act1_subject'            => $globalAct['subject'] ?? 'Science',
                'act1_pedagogy'           => $globalAct['approach'] ?? 'Art-Integrated',
                'act1_title'              => $globalAct['title'] ?? 'Solar System 3D Model & Daily Life Geometry',
                'act1_desc'               => $globalAct['desc'] ?? 'Constructed scale models of planetary orbits, computed relative ratios, and demonstrated to class',
                'act2_subject'            => $globalAct['act2_subject'] ?? 'Languages',
                'act2_pedagogy'           => $globalAct['act2_approach'] ?? 'Art-Integrated',
                'act2_title'              => $globalAct['act2_title'] ?? 'Creative Monologue on Environmental Conservation',
                'act2_desc'               => $globalAct['act2_desc'] ?? 'Dramatic presentation on local water conservation and community awareness',
                'act_aware'               => $rubricAwr,
                'act_sens'                => $rubricSens,
                'act_creat'               => $rubricCreat,
                'act_self'                => $globalAct['learnt'] ?? 'Learned celestial orbital distances, 3D scale geometry, and public presentation skills',
                'act_peer'                => $globalAct['peer_feedback'] ?? 'Very supportive and collaborative teammate; explained difficult astronomical concepts clearly',
                'act_teach_strength'      => $globalAct['strength'] ?? 'Collaboration',
                'act_teach_barrier'       => $globalAct['barriers'] ?? 'None',

                // Standard / HpcTeacherController keys
                'act_subject'             => $globalAct['subject'] ?? 'Science',
                'act_approach'            => $globalAct['approach'] ?? 'Art-Integrated',
                'act_title'               => $globalAct['title'] ?? 'Solar System 3D Model & Daily Life Geometry',
                'act_desc'                => $globalAct['desc'] ?? 'Constructed scale models of planetary orbits, computed relative ratios, and demonstrated to class',
                'act_rubric_awareness'    => $rubricAwr,
                'act_rubric_sensitivity'  => $rubricSens,
                'act_rubric_creativity'   => $rubricCreat,
                'act_self_effort'         => 'Yes',
                'act_self_apply'          => 'Yes',
                'act_self_motivated'      => 'Yes',
                'act_learnt'              => $globalAct['learnt'] ?? 'Learned celestial orbital distances and 3D scale geometry',
                'act_interesting'         => $globalAct['interesting'] ?? 'Fabricating rotating planetary models using recycled materials and LED lighting',
                'act_practice'            => $globalAct['practice'] ?? 'Time management during model assembly and calibrations',
                'act_help'                => $globalAct['help'] ?? 'Precision measuring tools and ratio conversions',
                'act_peer_name'           => $peerName,
                'act_peer_engaged'        => 'Yes',
                'act_peer_shared'         => 'Yes',
                'act_peer_practice'       => 'Time management during demonstrations',
                'act_peer_help'           => 'Fine scale calibrations',
                'act_peer_feedback'       => $globalAct['peer_feedback'] ?? 'Very supportive teammate during group work',
                'act_strength'            => $globalAct['strength'] ?? 'Collaboration',
                'act_barriers'            => $globalAct['barriers'] ?? 'None',
                'act_support_plan'        => $globalAct['support_plan'] ?? 'Provide advanced learning challenges and continuous positive encouragement',

                // Preparatory Stage keys
                'lang1_act'               => $globalAct['lang1_act'] ?? 'Story Recitation & Creative Roleplay',
                'lang1_approach'          => 'Art-Integrated',
                'lang1_aware'             => $rubricAwr,
                'lang1_sens'              => $rubricSens,
                'lang1_creat'             => $rubricCreat,
                'lang1_self_rate'         => 'Enjoyed Fully',
                'lang1_teacher_feedback'  => 'Expressive voice and enthusiastic story character portrayal',

                'lang2_act'               => $globalAct['lang2_act'] ?? 'Picture Word Association & Dialogue Reading',
                'lang2_approach'          => 'Toy-Based Pedagogy',
                'lang2_aware'             => 'Proficient',
                'lang2_sens'              => 'Proficient',
                'lang2_creat'             => 'Proficient',
                'lang2_self_rate'         => 'Good Effort',
                'lang2_teacher_feedback'  => 'Shows steady growth in vocabulary and sentence pronunciation',

                'math_act'                => $globalAct['math_act'] ?? 'Measurement, Shapes & Toy-Clock Project',
                'math_approach'           => 'Toy-Based Pedagogy',
                'math_aware'              => $rubricCreat,
                'math_sens'               => $rubricSens,
                'math_creat'              => $rubricCreat,
                'math_self_rate'          => 'Enjoyed Fully',
                'math_teacher_feedback'   => 'Quickly recognizes geometric angles and solves addition puzzles with counters',

                'evs_act'                 => $globalAct['evs_act'] ?? 'Nature Observation & Plant Seed Germination Study',
                'evs_approach'            => 'Inquiry-Based',
                'evs_aware'               => $rubricAwr,
                'evs_sens'                => $rubricSens,
                'evs_creat'               => $rubricCreat,
                'evs_self_rate'           => 'Enjoyed Fully',
                'evs_teacher_feedback'    => 'Maintained diligent daily leaf journal and displayed keen curiosity about plant life',

                'art_pe_act'              => $globalAct['art_pe_act'] ?? 'Indigenous Games (Kho-Kho) & Clay Pottery Expression',
                'art_pe_approach'         => 'Sports-Integrated',
                'art_pe_aware'            => 'Proficient',
                'art_pe_sens'             => 'Proficient',
                'art_pe_creat'            => $rubricCreat,
                'art_pe_self_rate'        => 'Enjoyed Fully',
                'art_pe_teacher_feedback' => 'Demonstrates commendable sportsmanship, agility, and imaginative clay modeling',

                'work_skill_act'          => $globalAct['work_act'] ?? 'Kitchen Garden Composting & Craft Origami',
                'work_skill_hours'        => 10,
                'work_skill_aware'        => 'Proficient',
                'work_skill_sens'         => 'Proficient',
                'work_skill_creat'        => $rubricCreat,
                'work_skill_notes'        => 'Took great pride in plant potting and assisted classmates with origami folding',
            ];
        } else {
            // Manual creation requested: clear all activity columns so teacher enters their own
            $actKeys = [
                'act1_subject', 'act1_pedagogy', 'act1_title', 'act1_desc',
                'act2_subject', 'act2_pedagogy', 'act2_title', 'act2_desc',
                'act_aware', 'act_sens', 'act_creat', 'act_self', 'act_peer',
                'act_teach_strength', 'act_teach_barrier',
                'act_subject', 'act_approach', 'act_title', 'act_desc',
                'act_rubric_awareness', 'act_rubric_sensitivity', 'act_rubric_creativity',
                'act_self_effort', 'act_self_apply', 'act_self_motivated',
                'act_learnt', 'act_interesting', 'act_practice', 'act_help',
                'act_peer_name', 'act_peer_engaged', 'act_peer_shared',
                'act_peer_practice', 'act_peer_help', 'act_peer_feedback',
                'act_strength', 'act_barriers', 'act_support_plan',
                'lang1_act', 'lang1_approach', 'lang1_aware', 'lang1_sens', 'lang1_creat', 'lang1_self_rate', 'lang1_teacher_feedback',
                'lang2_act', 'lang2_approach', 'lang2_aware', 'lang2_sens', 'lang2_creat', 'lang2_self_rate', 'lang2_teacher_feedback',
                'math_act', 'math_approach', 'math_aware', 'math_sens', 'math_creat', 'math_self_rate', 'math_teacher_feedback',
                'evs_act', 'evs_approach', 'evs_aware', 'evs_sens', 'evs_creat', 'evs_self_rate', 'evs_teacher_feedback',
                'art_pe_act', 'art_pe_approach', 'art_pe_aware', 'art_pe_sens', 'art_pe_creat', 'art_pe_self_rate', 'art_pe_teacher_feedback',
                'work_skill_act', 'work_skill_hours', 'work_skill_aware', 'work_skill_sens', 'work_skill_creat', 'work_skill_notes'
            ];
            foreach ($actKeys as $ak) {
                $actMap[$ak] = '';
            }
        }

        $apaarId = ! empty($st['apaar_id']) ? $st['apaar_id'] : '';
        $loginId = ! empty($st['login_id']) ? $st['login_id'] : (! empty($st['hpc_id']) ? $st['hpc_id'] : '');
        $name    = $st['name'] ?? ('Student ' . ($sIdx + 1));
        $status  = $st['status'] ?? 'HPC In Progress';
        $admNo   = $this->resolveAdmissionNumber($st, $sIdx);
        $grade   = $st['grade'] ?? self::getGradesForStage($stage)[0];
        $section = $st['section'] ?? 'A';

        $months = ['apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec', 'jan', 'feb', 'mar'];
        $vals = [];

        // Base locked metadata
        $vals[] = $apaarId;
        $vals[] = $loginId;
        $vals[] = $name;
        $vals[] = $status;

        // Profile
        $vals[] = $admNo;
        $vals[] = $grade;
        $vals[] = $section;
        $vals[] = 'Rural';
        $vals[] = 'English';
        $vals[] = 'Kashmiri';
        $vals[] = 'Father';
        $vals[] = 'Guardian of ' . explode(' ', trim($name))[0];

        // Attendance (Neck to Neck)
        $workCols = [];
        $presCols = [];
        $colOffset = 12; // M is index 12

        foreach ($months as $mIdx => $m) {
            $w = $defaultWorkMap[$m] ?? 20;
            $p = $autoFill ? max(0, $w - ($sIdx % 3 == 0 ? 1 : 2)) : '';

            $wCol = $this->indexToColumnLetter($colOffset + ($mIdx * 2));
            $pCol = $this->indexToColumnLetter($colOffset + ($mIdx * 2) + 1);

            $workCols[] = "{$wCol}{$rowNum}";
            $presCols[] = "{$pCol}{$rowNum}";

            $vals[] = $w;
            $vals[] = $p;
        }

        $wSumFormula = '=SUM(' . implode(',', $workCols) . ')';
        $pSumFormula = '=SUM(' . implode(',', $presCols) . ')';
        $wTotalCol = $this->indexToColumnLetter(count($vals));
        $pTotalCol = $this->indexToColumnLetter(count($vals) + 1);

        $vals[] = $wSumFormula;
        $vals[] = $pSumFormula;
        $vals[] = "=IF({$wTotalCol}{$rowNum}>0, ROUND(({$pTotalCol}{$rowNum}/{$wTotalCol}{$rowNum})*100, 1) & \"%\", \"\")";
        $vals[] = $autoFill ? (14 + ($sIdx % 5)) : '';
        $vals[] = $autoFill ? 'Brief seasonal viral cold / domestic family engagement' : '';
        $vals[] = $autoFill ? 'Reading' : '';

        // Stage-Specific Fillers
        $remainingCount = count($columns) - count($vals);
        for ($i = 0; $i < $remainingCount; $i++) {
            $colDef = $columns[count($vals)];
            $k = $colDef['key'];
            $type = $colDef['type'];

            if (! $autoFill) {
                if ($type === 'calc') {
                    $vals[] = '';
                } elseif (str_ends_with($k, '_max')) {
                    $vals[] = 50;
                } else {
                    $vals[] = '';
                }
                continue;
            }

            if (array_key_exists($k, $actMap)) {
                $vals[] = $actMap[$k];
            } elseif ($type === 'calc') {
                $vals[] = '';
            } elseif (str_ends_with($k, '_max')) {
                $vals[] = 50;
            } elseif (str_ends_with($k, '_obt')) {
                $vals[] = 38 + (($sIdx * 3 + $i) % 11); // e.g. 38 to 48 out of 50
            } elseif (($colDef['options'] ?? '') !== '') {
                $firstOpt = trim(explode(',', trim($colDef['options'], '"'))[0]);
                if (str_contains($colDef['options'], 'Proficient')) {
                    $firstOpt = (($sIdx + $i) % 4 === 0) ? 'Advanced' : 'Proficient';
                } elseif (str_contains($colDef['options'], 'Yes')) {
                    $firstOpt = 'Yes';
                }
                $vals[] = $firstOpt;
            } elseif ($type === 'integer') {
                $vals[] = 10 + ($i % 15);
            } else {
                $vals[] = $this->getSampleTextForField($k, $name, $stage);
            }
        }

        return $vals;
    }

    protected function getSampleTextForField(string $key, string $studentName, string $stage): string
    {
        $fn = explode(' ', trim($studentName))[0];
        return match($key) {
            'live_with' => 'Parents, grandparents and sister',
            'stay_at'   => 'Sugin, Budgam',
            'fav_food', 'fav_dish' => 'Dal Chawal and fresh seasonal fruits',
            'fav_game'  => 'Kho-Kho and Badminton',
            'free_time' => 'Reading storybooks and outdoor play',
            'do_well'   => 'Actively participating in classroom discussions',
            'proud_of', 'feel_proud' => 'Solving problems independently and helping friends',
            'friend_1', 'peer1_name', 'friend_name' => 'Ayaan Ahmad',
            'friend_2', 'peer2_name' => 'Zaid Farooq',
            'peer1_praise', 'peer2_praise' => "Very kind classmate who always supports peers",
            'acad_goal', 'goal_acad' => 'Master key concepts in science and mathematics',
            'acad_goal_why' => 'Builds a strong foundation for future career ambitions',
            'acad_step1', 'acad_goal_actions' => 'Dedicate 1 hour daily to revision and problem practice',
            'acad_step2' => 'Clarify doubts promptly with subject teachers',
            'pers_goal' => 'Maintain disciplined daily routine and physical fitness',
            'pers_goal_why' => 'Keeps mind sharp, energetic and focused',
            'pers_step1' => 'Morning exercise and regular reading habit',
            'pers_step2' => 'Read inspiring books before bedtime',
            'sch_learn1' => 'Scientific principles and laboratory safety',
            'sch_learn2' => 'Linear equation applications and mensuration',
            'sch_learn3' => 'Constitutional values and environmental stewardship',
            'out_learn1' => 'Kitchen gardening, composting, and nature appreciation',
            'out_learn2' => 'Basic bicycle maintenance and safety',
            'out_learn3' => 'Community cleanliness and resource conservation',
            'ambition', 'aspirational_career' => 'Software Engineer / Medical Researcher',
            'ambition_path' => 'Pursue science and mathematics rigorously',
            'skill_acquire' => 'Programming logic, analytical thinking, communication',
            'focus_subjects' => 'Science, Mathematics, and English',
            'habits_cultivate' => 'Punctuality, consistency, and active questioning',
            'mentor_name', 'mentor_consulted' => 'Mohammad Shafi',
            'mentor_guidance', 'mentor_action_advice' => 'Maintain steady daily effort and build strong conceptual clarity',
            'feel_calm' => 'Quiet nature walks and reading in library',
            'feel_anxious' => 'Unprepared surprise examinations',
            'cope_anxiety' => 'Deep breathing exercises and positive self-talk',
            'parent_plan' => 'Maintain dedicated distraction-free study corner at home',
            'holistic_remarks' => "{$fn} has demonstrated admirable curiosity, consistent discipline, and holistic growth across both academic learning standards and co-curricular dimensions.",
            default => 'Active and positive participation demonstrated throughout the year.',
        };
    }

    public function indexToColumnLetter(int $index): string
    {
        $letter = '';
        while ($index >= 0) {
            $letter = chr($index % 26 + 65) . $letter;
            $index = intval($index / 26) - 1;
        }
        return $letter;
    }
}
