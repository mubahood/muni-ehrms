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
    protected $table = 'admin_users';

    use HasFactory;
    use Notifiable;

    //is available on this day
    public function isAvailableOnDay($day)
    {
        $day = strtolower($day);
        $parsed_date = Carbon::parse($day);
        if (!$parsed_date) {
            return false; // Invalid date format
        }
        if ($this->status != 'Active') {
            return false; // User is not active
        }

        if ($this->start_working_date != null && strlen($this->start_working_date) > 5) {
            $start_working_date = Carbon::parse($this->start_working_date);
            if ($parsed_date->lt($start_working_date)) {
                return false; // User has not started working yet
            }
        }

        $days_of_work = $this->work_days;

        $day_of_week = $parsed_date->format('l'); // Get the day of the week (e.g., 'Monday', 'Tuesday', etc.)

        //check if $day_of_week not in $days_of_work
        if (!is_array($days_of_work) || !in_array($day_of_week, $days_of_work)) {
            return false; // Day of the week is not in the user's work days
        }


        //see if has leave on this day
        $leave = Leave::where('user_id', $this->id)
            ->where('start_date', '<=', $parsed_date)
            ->where('end_date', '>=', $parsed_date)
            ->first();
        if ($leave) {
            return false; // User has leave on this day
        }
        return true; // User is available on this day 
    }



    //sendEmailVerificationNotification
    public function sendEmailVerificationNotification()
    {
        return;
        $mail_verification_token = Utils::get_unique_text();
        $this->mail_verification_token = $mail_verification_token;
        $this->save();

        $url = url('verification-mail-verify?tok=' . $mail_verification_token);
        $from = env('APP_NAME') . " Team.";

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
            $data['subject'] = 'Email Verification - ' . env('APP_NAME') . ' - ' . $day . ".";
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
        $APP_NAME = env('APP_NAME', 'Vehcle Management System');
        $subject = "Welcome to " . env('APP_NAME') . " - Your Account Details";
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
}
