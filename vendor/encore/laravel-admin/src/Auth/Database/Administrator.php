<?php

namespace Encore\Admin\Auth\Database;

use App\Models\Campus;
use App\Models\Company;
use App\Models\Departmet;
use App\Models\User;
use App\Models\UserHasProgram;
use Carbon\Carbon;
use Encore\Admin\Traits\DefaultDatetimeFormat;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * Class Administrator.
 *
 * @property Role[] $roles
 */
class Administrator extends Model implements AuthenticatableContract, JWTSubject
{
    use Authenticatable;
    use HasPermissions;
    use DefaultDatetimeFormat;

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    //belongs todepartment
    public function department()
    {
        return $this->belongsTo(Departmet::class, 'department_id');
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

    protected $fillable = ['username', 'password', 'name', 'avatar', 'created_at_text'];

    /**
     * Create a new Eloquent model instance.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $connection = config('admin.database.connection') ?: config('database.default');

        $this->setConnection($connection);

        $this->setTable(config('admin.database.users_table'));

        parent::__construct($attributes);
    }

    public static function boot()
    {
        parent::boot();

        self::creating(function ($m) {
            $n = $m->first_name . " " . $m->last_name;
            if (strlen(trim($n)) > 1) {
                $m->name = trim($n);
            }
        });

        //created
        self::created(function ($m) {
            if($m->notify_account_created_by_email == 'Yes') {
                $user = User::find($m->id);
                if ($user != null) {
                    try {
                        $user->sendWelcomeMessage();  
                    } catch (\Throwable $th) {
                        //throw $th;
                    }
                }
            }
        }); 

        self::updating(function ($m) {
            $n = $m->first_name . " " . $m->last_name;
            if (strlen(trim($n)) > 1) {
                $m->name = trim($n);
            }
        });
    }

    //company
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }


    /**
     * Get avatar attribute.
     *
     * @param string $avatar
     *
     * @return string
     */
    public function getAvatarAttribute($avatar)
    {
        if (url()->isValidUrl($avatar)) {
            return $avatar;
        }

        $disk = config('admin.upload.disk');

        if ($avatar && array_key_exists($disk, config('filesystems.disks'))) {
            return Storage::disk(config('admin.upload.disk'))->url($avatar);
        }

        $default = config('admin.default_avatar') ?: '/assets/images/user.jpg';

        return admin_asset($default);
    }


    public function programs()
    {
        return $this->hasMany(UserHasProgram::class, 'user_id');
    }

    public function program()
    {
        $p = UserHasProgram::where(['user_id' => $this->id])->first();
        if ($p == null) {
            $p = new UserHasProgram();
            $p->name = "No program";
        }
        return $p;
    }


    public function campus()
    {
        return $this->belongsTo(Campus::class, 'campus_id');
    }

    public function getCreatedAtTextAttribute($name)
    {
        return Carbon::parse($this->created_at)->diffForHumans();
    }


    /**
     * A user has and belongs to many roles.
     *
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        $pivotTable = config('admin.database.role_users_table');

        $relatedModel = config('admin.database.roles_model');

        return $this->belongsToMany($relatedModel, $pivotTable, 'user_id', 'role_id');
    }

    /**
     * A User has and belongs to many permissions.
     *
     * @return BelongsToMany
     */
    public function permissions(): BelongsToMany
    {
        $pivotTable = config('admin.database.user_permissions_table');

        $relatedModel = config('admin.database.permissions_model');

        return $this->belongsToMany($relatedModel, $pivotTable, 'user_id', 'permission_id');
    }
}
