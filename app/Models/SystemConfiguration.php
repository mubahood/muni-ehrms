<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemConfiguration extends Model
{
    use HasFactory;

    //boot
    protected static function boot()
    {
        parent::boot();

        // Automatically set the start date to today if not set
        static::creating(function ($model) {
            $otherConfigurations = self::where([])->get();
            if ($otherConfigurations->isNotEmpty()) {
                throw new \Exception('Only one system configuration can exist at a time.');
            }

            if (empty($model->start_date)) {
                $model->start_date = now()->toDateString();
            }
        });
    }
}
