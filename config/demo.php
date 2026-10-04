<?php

/*
|--------------------------------------------------------------------------
| The demonstration university (demo sandbox)
|--------------------------------------------------------------------------
|
| `php artisan ehrms:demo-seed` builds a self-contained university: three
| faculties, fourteen departments and about seventy staff, with fifteen
| sign-in accounts covering every role, three months of clock-ins and leave
| in every state. Every row
| it creates is marked is_demo. Demo accounts see only the sandbox and real
| staff never see it (App\Services\Scope), and demo accounts can never
| change anything university-wide (App\Services\AccessPolicy::DEMO_DENIED).
|
| The System Administrator shows or hides the demo sign-in panel on the login
| page and deletes or rebuilds the sandbox under Administration → Demo data.
|
| Usernames all start with "demo." and share one password. Codes carry a
| DEMO- prefix so they never collide with real faculties or departments.
| 'persona' sets how each person's attendance behaves day to day.
|
*/

return [

    'password' => env('DEMO_LOGIN_PASSWORD', 'Demo@2026'),

    // Rebuild the sandbox every Sunday night, so it always shows the last
    // three months and every leave stage, whatever visitors did during the week.
    'weekly_reset' => env('DEMO_WEEKLY_RESET', true),

    'days' => 90,

    'faculties' => [
        'DEMO-FOS' => 'Faculty of Science',
        'DEMO-FHS' => 'Faculty of Health Sciences',
        'DEMO-FOE' => 'Faculty of Education',
    ],

    /*
     | code => [name, faculty code (null = administrative unit), extra staff, their job titles, extra head?]
     | "Extra staff" are demo people without a listed sign-in, so the demo
     | university has a realistic size. With 'head' => true the first of them
     | heads the department; Biological Sciences is left without a Head to
     | show that stage being passed over.
     */
    'departments' => [
        'DEMO-CS' => ['Computer Science', 'DEMO-FOS', 5, ['Senior Lecturer', 'Lecturer', 'Lecturer', 'Assistant Lecturer', 'Teaching Assistant'], false],
        'DEMO-MATH' => ['Mathematics', 'DEMO-FOS', 4, ['Senior Lecturer', 'Lecturer', 'Assistant Lecturer', 'Teaching Assistant'], false],
        'DEMO-BIO' => ['Biological Sciences', 'DEMO-FOS', 4, ['Senior Lecturer', 'Lecturer', 'Lecturer', 'Laboratory Technician'], false],
        'DEMO-NUR' => ['Nursing and Midwifery', 'DEMO-FHS', 5, ['Senior Lecturer', 'Lecturer', 'Clinical Instructor', 'Clinical Instructor', 'Nursing Officer'], false],
        'DEMO-PH' => ['Public Health', 'DEMO-FHS', 4, ['Senior Lecturer', 'Lecturer', 'Lecturer', 'Assistant Lecturer'], true],
        'DEMO-SCED' => ['Science Education', 'DEMO-FOE', 5, ['Senior Lecturer', 'Lecturer', 'Lecturer', 'Assistant Lecturer', 'Teaching Assistant'], true],
        'DEMO-HUED' => ['Humanities Education', 'DEMO-FOE', 4, ['Senior Lecturer', 'Lecturer', 'Assistant Lecturer', 'Teaching Assistant'], true],
        'DEMO-HRD' => ['Human Resources', null, 2, ['Records Assistant', 'Human Resource Assistant'], false],
        'DEMO-USO' => ['Office of the University Secretary', null, 2, ['Administrative Assistant', 'Personal Secretary'], false],
        'DEMO-ICT' => ['Information Technology', null, 3, ['Network Administrator', 'Systems Analyst', 'ICT Assistant'], false],
        'DEMO-FIN' => ['Finance', null, 4, ['Senior Accountant', 'Accounts Assistant', 'Accounts Assistant', 'Cashier'], true],
        'DEMO-LIB' => ['Library', null, 4, ['Senior Librarian', 'Librarian', 'Library Assistant', 'Library Assistant'], true],
        'DEMO-REG' => ['Academic Registrar', null, 4, ['Senior Assistant Registrar', 'Assistant Registrar', 'Records Officer', 'Records Officer'], true],
        'DEMO-EST' => ['Estates', null, 5, ['Estates Officer', 'Electrician', 'Plumber', 'Driver', 'Driver'], true],
    ],

    // Faculties whose Dean is one of the extra staff (the others are sign-in accounts).
    'extra_deans' => ['DEMO-FOE' => 'DEMO-SCED'],

    // How the extra staff behave: share of people per persona (adds up to 100).
    'persona_mix' => ['punctual' => 60, 'sometimes_late' => 22, 'no_signout' => 8, 'chronic_late' => 5, 'absentee' => 5],

    'first_names' => ['Moses', 'Patrick', 'Emmanuel', 'Ronald', 'Isaac', 'Samuel', 'Martin', 'Alfred', 'Richard', 'Robert', 'Paul', 'Julius',
        'Francis', 'Peter', 'Kenneth', 'David', 'Joseph', 'Andrew', 'Collins', 'Ambrose', 'Sarah', 'Lillian', 'Christine', 'Agnes', 'Doreen',
        'Evelyn', 'Prossy', 'Jane', 'Beatrice', 'Dorcas', 'Ruth', 'Annet', 'Brenda', 'Irene', 'Rose', 'Juliet', 'Night', 'Sharon', 'Phiona', 'Hellen'],
    'surnames' => ['Adriko', 'Asiimwe', 'Draku', 'Eriku', 'Letaru', 'Ocen', 'Opio', 'Nakato', 'Namukasa', 'Mugisha', 'Tumusiime', 'Kato',
        'Ssemakula', 'Alezuyo', 'Acidri', 'Ayikoru', 'Ojok', 'Atim', 'Onzima', 'Andama', 'Yikii', 'Aluma', 'Anyama', 'Munguci',
        'Ejoku', 'Feta', 'Ondia', 'Abiria', 'Amandua', 'Drate', 'Aliku', 'Chandiru', 'Okuonzi', 'Bayo', 'Wadri', 'Avako'],

    /*
     | The fifteen accounts. 'heads' = department code they head, 'dean_of' =
     | faculty code they lead. Personas: punctual, sometimes_late,
     | chronic_late, absentee (misses days), no_signout (often not seen leaving).
     */
    'accounts' => [
        ['username' => 'demo.admin', 'title' => 'Mr', 'first' => 'Ivan', 'last' => 'Ezama', 'sex' => 'Male',
            'roles' => ['admin'], 'department' => 'DEMO-ICT', 'position' => 'Systems Administrator', 'persona' => 'punctual'],
        ['username' => 'demo.us', 'title' => 'Dr', 'first' => 'Florence', 'last' => 'Anguyo', 'sex' => 'Female',
            'roles' => ['us'], 'department' => 'DEMO-USO', 'position' => 'University Secretary', 'persona' => 'punctual'],
        ['username' => 'demo.hr', 'title' => 'Ms', 'first' => 'Harriet', 'last' => 'Letaru', 'sex' => 'Female',
            'roles' => ['hr'], 'department' => 'DEMO-HRD', 'position' => 'Human Resource Manager', 'persona' => 'punctual'],
        ['username' => 'demo.hrofficer', 'title' => 'Mr', 'first' => 'Simon', 'last' => 'Obitre', 'sex' => 'Male',
            'roles' => ['hr'], 'department' => 'DEMO-HRD', 'position' => 'Human Resource Officer', 'persona' => 'sometimes_late'],
        ['username' => 'demo.dean', 'title' => 'Prof', 'first' => 'Charles', 'last' => 'Onzima', 'sex' => 'Male',
            'roles' => ['dean'], 'department' => 'DEMO-CS', 'dean_of' => 'DEMO-FOS', 'position' => 'Dean, Faculty of Science', 'persona' => 'punctual'],
        ['username' => 'demo.dean.health', 'title' => 'Assoc. Prof', 'first' => 'Esther', 'last' => 'Ayikoru', 'sex' => 'Female',
            'roles' => ['dean'], 'department' => 'DEMO-NUR', 'dean_of' => 'DEMO-FHS', 'position' => 'Dean, Faculty of Health Sciences', 'persona' => 'punctual'],
        ['username' => 'demo.hod', 'title' => 'Dr', 'first' => 'Geoffrey', 'last' => 'Drani', 'sex' => 'Male',
            'roles' => ['hod'], 'department' => 'DEMO-CS', 'heads' => 'DEMO-CS', 'position' => 'Head of Department, Computer Science', 'persona' => 'punctual'],
        ['username' => 'demo.hod.maths', 'title' => 'Dr', 'first' => 'Grace', 'last' => 'Eyotaru', 'sex' => 'Female',
            'roles' => ['hod'], 'department' => 'DEMO-MATH', 'heads' => 'DEMO-MATH', 'position' => 'Head of Department, Mathematics', 'persona' => 'sometimes_late'],
        ['username' => 'demo.hod.nursing', 'title' => 'Ms', 'first' => 'Joyce', 'last' => 'Candiru', 'sex' => 'Female',
            'roles' => ['hod'], 'department' => 'DEMO-NUR', 'heads' => 'DEMO-NUR', 'position' => 'Head of Department, Nursing and Midwifery', 'persona' => 'punctual'],
        ['username' => 'demo.employee', 'title' => 'Mr', 'first' => 'Patrick', 'last' => 'Ondoga', 'sex' => 'Male',
            'roles' => ['employee'], 'department' => 'DEMO-CS', 'position' => 'Lecturer', 'persona' => 'sometimes_late'],
        ['username' => 'demo.lecturer', 'title' => 'Ms', 'first' => 'Scovia', 'last' => 'Driciru', 'sex' => 'Female',
            'roles' => ['employee'], 'department' => 'DEMO-CS', 'position' => 'Assistant Lecturer', 'persona' => 'chronic_late'],
        ['username' => 'demo.maths', 'title' => 'Mr', 'first' => 'Denis', 'last' => 'Asiku', 'sex' => 'Male',
            'roles' => ['employee'], 'department' => 'DEMO-MATH', 'position' => 'Lecturer', 'persona' => 'absentee'],
        ['username' => 'demo.nurse', 'title' => 'Ms', 'first' => 'Mercy', 'last' => 'Akello', 'sex' => 'Female',
            'roles' => ['employee'], 'department' => 'DEMO-NUR', 'position' => 'Clinical Instructor', 'persona' => 'no_signout'],
        ['username' => 'demo.ict', 'title' => 'Mr', 'first' => 'Brian', 'last' => 'Okello', 'sex' => 'Male',
            'roles' => ['employee'], 'department' => 'DEMO-ICT', 'position' => 'ICT Officer', 'persona' => 'punctual'],
        ['username' => 'demo.accountant', 'title' => 'Ms', 'first' => 'Winnie', 'last' => 'Ayiko', 'sex' => 'Female',
            'roles' => ['employee'], 'department' => 'DEMO-FIN', 'position' => 'Accountant', 'persona' => 'sometimes_late'],
    ],
];
