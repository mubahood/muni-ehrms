<?php

namespace App\Models;

use Carbon\Carbon;
use Encore\Admin\Auth\Database\Administrator;
use Encore\Admin\Form\Field\BelongsToMany;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany as RelationsBelongsToMany;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;



class User extends Administrator implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;
    protected $table = 'users';

    protected $hidden = ['password', 'remember_token', 'mail_verification_token'];

    protected $casts = [
        'is_demo' => 'boolean',
        'must_change_password' => 'boolean',
        'password_changed_at' => 'datetime',
    ];

    /** An account of the demonstration university (see App\Services\Scope). */
    public function isDemo(): bool
    {
        return (bool) $this->is_demo;
    }

    use HasFactory;
    use Notifiable;

    /**
     * Whether this person is expected at work on $day: active, already started,
     * one of their working days, not a public holiday, and not on leave that is
     * in force (approved, or recalled after they resumed).
     */
    public function isAvailableOnDay($day)
    {
        $date = Carbon::parse($day)->startOfDay();
        $engine = app(\App\Services\AttendanceEngine::class);
        if (!$engine->isExpected($this, $date)) {
            return false;
        }
        if (!(new \App\Services\WorkCalendar($date, $date))->isWorkingDayFor($this, $date)) {
            return false;
        }

        return !Leave::inForceOn($date)->where('user_id', $this->id)->exists();
    }



    //sendEmailVerificationNotification
    public function sendEmailVerificationNotification()
    {
        return;
        $mail_verification_token = Utils::get_unique_text();
        $this->mail_verification_token = $mail_verification_token;
        $this->save();

        $url = url('verification-mail-verify?tok=' . $mail_verification_token);
        $from = config('app.name') . " Team.";

        $mail_body =
            <<<EOD
        <p>Dear <b>$this->name</b>,</p>
        <p>Please click the link below to verify your email address.</p>
        <p><a href="{$url}">Verify Email Address</a></p>
        <p>Best regards,</p>
        <p>{$from}</p>
        EOD;

        // $full_mail = view('mails/mail-1', ['body' => $mail_body, 'title' => 'Email Verification']);

        try {
            $day = date('Y-m-d');
            $data['body'] = $mail_body;
            $data['data'] = $data['body'];
            $data['name'] = $this->name;
            $data['email'] = $this->email;
            $data['subject'] = 'Email Verification - ' . config('app.name') . ' - ' . $day . ".";
            Utils::mail_sender($data);
        } catch (\Throwable $th) {
            throw $th;
        }
    }



    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
    public function getJWTCustomClaims()
    {
        return [];
    }




    //email getter
    public function getEmailAttribute($value)
    {
        if ($value == null || strlen($value) < 1) {
            $username = $this->username;
            if ($username != null && strlen($username) > 1) {
                //validate email
                if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
                    $email = $username;
                    $this->email = $email;
                    $this->save();
                    $value = $email;
                }
            }
        }
        return $value;
    }

    //Belongs to company
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    //public send welcom message
    public function sendWelcomeMessage()
    {
        $user = $this;
        if ($user == null) {
            throw new \Exception("User not found.");
        }
        $newPassword = rand(100000, 999999); // Generate a random 6-digit number
        $user->password = password_hash($newPassword, PASSWORD_BCRYPT);
        $user->save();
        //send email
        $APP_NAME = config('app.name');
        $subject = "Welcome to " . config('app.name') . " - Your Account Details";
        $LOGIN_URL = admin_url();
        $message = <<<HTML
                    <h3>Hello {$user->name},</h3>
                    <p>Welcome to <strong> {$APP_NAME} </strong>!</p>
                    <p><strong>Your login details:</strong></p>
                    <ul>
                        <li><strong>Email:</strong> {$user->email}</li>
                        <li><strong>Temporary Password:</strong> {$newPassword}</li>
                    </ul>
                    <p>Log in here: <a href="{$LOGIN_URL}">Login</a></p>
                    <p>Please change your password after logging in.</p>
                    <p>Thank you.</p>
                    HTML;

        try {
            $data['body'] = $message;
            $data['name'] = $user->name;
            $data['email'] = $user->email;
            $data['subject'] = $subject;
            Utils::mail_sender($data);
        } catch (\Throwable $th) {
            // Handle email sending failure
            throw new \Exception("Failed to send email to {$user->email}. Error: " . $th->getMessage());
        }
    }

    // setter for work_days
    public function setWorkDaysAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['work_days'] = json_encode($value, JSON_UNESCAPED_UNICODE);
        } elseif (is_string($value)) {
            // Try to decode to check if it's already JSON
            json_decode($value);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->attributes['work_days'] = $value;
            } else {
                // fallback: wrap string in array and encode
                $this->attributes['work_days'] = json_encode([$value], JSON_UNESCAPED_UNICODE);
            }
        } else {
            // fallback for other types (e.g. null)
            $this->attributes['work_days'] = json_encode([], JSON_UNESCAPED_UNICODE);
        }
    }

    // getter for work_days
    public function getWorkDaysAttribute($value)
    {
        if ($value) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    //attendanceRecords
    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class, 'user_id');
    }

    /**
     * Get the department this user belongs to.
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Get general reports created by this user.
     */
    public function generalReports()
    {
        return $this->hasMany(GeneralReport::class, 'user_id');
    }

    /**
     * Get reports targeted specifically for this user.
     */
    public function targetedReports()
    {
        return $this->hasMany(GeneralReport::class, 'target_user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    | Slugs are the laravel-admin role slugs, listed from most to least
    | authority: a person holding several roles is described by the first.
    */

    public const ROLE_LABELS = [
        'admin' => 'System Administrator',
        'us' => 'University Secretary',
        'hr' => 'Human Resource',
        'dean' => 'Faculty Dean',
        'hod' => 'Head of Department',
        'employee' => 'Employee',
    ];

    /** @return string[] role slugs this user holds */
    public function roleSlugs(): array
    {
        return $this->roles->pluck('slug')->all();
    }

    public function hasAnyRole(string ...$slugs): bool
    {
        return (bool) array_intersect($slugs, $this->roleSlugs());
    }

    /** The highest role held; every signed-in person is at least an employee. */
    public function primaryRole(): string
    {
        $held = $this->roleSlugs();
        foreach (array_keys(self::ROLE_LABELS) as $slug) {
            if (in_array($slug, $held, true)) {
                return $slug;
            }
        }

        return 'employee';
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->primaryRole()];
    }

    /*
    |--------------------------------------------------------------------------
    | Organisation
    |--------------------------------------------------------------------------
    */

    /** Departments this person heads (set on the department). */
    public function headedDepartments()
    {
        return $this->hasMany(Department::class, 'hod_id');
    }

    /** Faculties this person is Dean of (set on the faculty). */
    public function deanOfFaculties()
    {
        return $this->hasMany(Faculty::class, 'dean_id');
    }

    public function leaves()
    {
        return $this->hasMany(Leave::class, 'user_id');
    }

    public function leaveEntitlements()
    {
        return $this->hasMany(LeaveEntitlement::class, 'user_id');
    }

    /**
     * ISO weekday numbers this person is expected at work: their own work days
     * if set on the staff record, otherwise the institution's working days.
     *
     * @return int[]
     */
    public function expectedWeekdays(): array
    {
        $numbers = [
            'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4,
            'friday' => 5, 'saturday' => 6, 'sunday' => 7,
        ];
        $own = [];
        foreach ($this->work_days as $day) {
            $key = strtolower(trim((string) $day));
            if (isset($numbers[$key])) {
                $own[] = $numbers[$key];
            }
        }

        return $own ?: SystemConfiguration::current()->workingWeekdays();
    }

    /** Late from the first minute after this time. */
    public function lateTime(): string
    {
        return $this->custom_late_time ?: SystemConfiguration::current()->defaultLateTime();
    }

    public function displayName(): string
    {
        $name = trim((string) $this->name);
        if ($name === '') {
            $name = trim($this->first_name . ' ' . $this->last_name);
        }

        return $name !== '' ? $name : (string) $this->username;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', $this->displayName());
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';

        return mb_strtoupper($first . $last) ?: '?';
    }
}
