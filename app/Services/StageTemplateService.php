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
     * Build the Excel Spreadsheet for a given stage and list of students
     */
    public function buildSpreadsheet(string $stage, array $students): Spreadsheet
    {
        $stage = self::normalizeStage($stage);
        $spreadsheet = new Spreadsheet();

        $this->buildInstructionsSheet($spreadsheet, $stage);
        $this->buildEvaluationsSheet($spreadsheet, $stage, $students);

        return $spreadsheet;
    }

    protected function buildInstructionsSheet(Spreadsheet $spreadsheet, string $stage): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $stageInfo = self::getStages()[$stage];
        $sheet->setTitle('Instructions & Rules');

        $title = "HPCAssist - {$stageInfo['title']} ({$stageInfo['grade_range']}) Assessment Template";
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(32);

        $rules = [
            ['Rule #', 'Section / Stage', 'Field Type', 'Rule Description', 'Permitted Values / Guidance'],
            ['1', 'Student Roster', 'Locked String', 'APAAR ID, HPC Login ID, Student Name, and Status are pre-populated from the PARAKH portal.', 'Read-only locked cells.'],
            ['2', 'Status Protection', 'System Rule', 'Students marked "HPC Completed" are protected and will be skipped during automated portal sync.', 'HPC Completed (skipped), HPC In Progress (updated).'],
            ['3', 'Stage Integrity', 'Stage Rule', "This template is strictly tailored for {$stageInfo['title']} ({$stageInfo['grade_range']}).", "Valid grades: " . implode(', ', $stageInfo['grades'])],
            ['4', 'Monthly Attendance', 'Integers & Formulas', 'Working Days and Present Days for each month (Apr-Mar). Total Working, Present, and Attendance % calculate automatically.', 'Present <= Working days per month.'],
            ['5', 'Abilities & Rubrics', 'Dragdown List', 'Evaluation across 3 core abilities: Awareness, Sensitivity, Creativity.', 'Options: Beginner, Proficient, Advanced.'],
            ['6', 'Marks Grid', 'Integer', 'Summative marks scored in core subjects (0 to Max Marks).', 'Max Marks (default 50), Obtained (0 <= Obtained <= Max).'],
            ['7', 'Declaration', 'Dragdown', 'Teacher certification of evaluation data accuracy.', 'Options: Yes, No.'],
        ];

        foreach ($rules as $rIdx => $row) {
            $rowNum = $rIdx + 3;
            foreach ($row as $cIdx => $val) {
                $colLetter = chr(65 + $cIdx);
                $sheet->setCellValue("{$colLetter}{$rowNum}", $val);
            }
            if ($rIdx === 0) {
                $sheet->getStyle("A{$rowNum}:E{$rowNum}")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF334155']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getRowDimension($rowNum)->setRowHeight(24);
            } else {
                $sheet->getStyle("A{$rowNum}:E{$rowNum}")->applyFromArray([
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension($rowNum)->setRowHeight(22);
            }
        }

        $sheet->getColumnDimension('A')->setWidth(10);
        $sheet->getColumnDimension('B')->setWidth(26);
        $sheet->getColumnDimension('C')->setWidth(22);
        $sheet->getColumnDimension('D')->setWidth(55);
        $sheet->getColumnDimension('E')->setWidth(45);
    }

    protected function buildEvaluationsSheet(Spreadsheet $spreadsheet, string $stage, array $students): void
    {
        $evalSheet = $spreadsheet->createSheet();
        $evalSheet->setTitle('Student Evaluations');

        $columns  = $this->getStageColumns($stage);
        $sections = $this->getStageSections($stage);

        // Row 1: Category Super-Headers
        foreach ($sections as $sec) {
            $startCol = $this->indexToColumnLetter($sec['start']);
            $endCol   = $this->indexToColumnLetter($sec['end']);
            $range    = "{$startCol}1:{$endCol}1";

            $evalSheet->mergeCells($range);
            $evalSheet->setCellValue("{$startCol}1", $sec['title']);
            $evalSheet->getStyle($range)->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 10],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $sec['bg']]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFFFFFFF']]],
            ]);
        }
        $evalSheet->getRowDimension(1)->setRowHeight(30);

        // Row 2: Field Names & Row 3: Guidance Notes
        foreach ($columns as $idx => $col) {
            $colLetter = $this->indexToColumnLetter($idx);
            $isObt    = str_ends_with($col['key'], '_obt');
            $isLocked = in_array($col['type'], ['locked', 'calc']);

            $label = $isObt ? '✏️ ' . $col['label'] : $col['label'];
            $evalSheet->setCellValue("{$colLetter}2", $label);
            $evalSheet->setCellValue("{$colLetter}3", $col['note'] ?? '');
            $evalSheet->getColumnDimension($colLetter)->setWidth($col['width']);

            $headerBg    = $isObt ? 'FFFEF08A' : ($isLocked ? 'FFE2E8F0' : 'FF1E293B');
            $headerColor = $isObt ? 'FF854D0E' : ($isLocked ? 'FF334155' : 'FFFFFFFF');

            $evalSheet->getStyle("{$colLetter}2")->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['argb' => $headerColor], 'size' => 9.5],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $headerBg]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']]],
            ]);

            $evalSheet->getStyle("{$colLetter}3")->applyFromArray([
                'font'      => ['italic' => true, 'color' => ['argb' => 'FF64748B'], 'size' => 8.5],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8FAFC']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
            ]);
        }
        $evalSheet->getRowDimension(2)->setRowHeight(26);
        $evalSheet->getRowDimension(3)->setRowHeight(20);

        // Rows 4 onwards: Real Enrolled Students
        $defaultWd = SchoolWorkingDay::getDefault();
        $defaultWorkMap = $defaultWd->toMonthMap();
        $defaultMax = SchoolMaxMark::getDefault()->toSubjectMap();

        foreach ($students as $sIdx => $st) {
            $rowNum = $sIdx + 4;
            $starterRow = $this->getStarterRowForStage($stage, $sIdx, $st, $defaultWorkMap, $defaultMax, $rowNum);

            foreach ($columns as $cIdx => $col) {
                $colLetter = $this->indexToColumnLetter($cIdx);
                $val = $starterRow[$cIdx] ?? '';

                $evalSheet->setCellValue("{$colLetter}{$rowNum}", $val);

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
        $evalSheet->freezePane('E4');
    }

    /**
     * Generate realistic pre-filled starter values for a student row
     */
    public function getStarterRowForStage(string $stage, int $sIdx, array $st, array $defaultWorkMap, array $defaultMax, int $rowNum): array
    {
        $stage = self::normalizeStage($stage);
        $columns = $this->getStageColumns($stage);

        $apaarId = $st['apaar_id'] ?? '';
        $loginId = $st['login_id'] ?? '';
        $name    = $st['name'] ?? ('Student ' . ($sIdx + 1));
        $status  = $st['status'] ?? 'HPC In Progress';
        $admNo   = $st['admission_no'] ?? '';
        if (preg_match('/^S[0-9A-Z]{6,16}$/i', (string)$admNo) || $admNo === $loginId || $admNo === $apaarId) {
            $admNo = '';
        }
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
            $p = max(0, $w - ($sIdx % 3 == 0 ? 1 : 2));

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
        $vals[] = 14 + ($sIdx % 5);
        $vals[] = 'Brief seasonal viral cold / domestic family engagement';
        $vals[] = 'Reading';

        // Stage-Specific Fillers
        $remainingCount = count($columns) - count($vals);
        for ($i = 0; $i < $remainingCount; $i++) {
            $colDef = $columns[count($vals)];
            $k = $colDef['key'];
            $type = $colDef['type'];

            if ($type === 'calc') {
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
