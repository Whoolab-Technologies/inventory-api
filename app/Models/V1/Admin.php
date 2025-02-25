<?php

namespace App\Models\V1;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Auth;

class Admin extends Authenticatable
{
    use HasApiTokens, Notifiable, HasFactory;

    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token', 'created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {

            \Log::info(Auth::check());
            if (Auth::check()) {
                $user = Auth::user();
                $model->created_by = $user->id;
                $model->created_type = optional($user->currentAccessToken())->name; // Get token name if exists
                $model->updated_by = $user->id;
                $model->updated_type = optional($user->currentAccessToken())->name;
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $user = Auth::user();
                $model->updated_by = $user->id;
                $model->updated_type = optional($user->currentAccessToken())->name;
            }
        });
    }

}
