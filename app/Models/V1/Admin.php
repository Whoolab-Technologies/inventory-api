<?php

namespace App\Models\V1;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends BaseModel implements AuthenticatableContract
{
    use Authenticatable, HasApiTokens, Notifiable, HasFactory;

    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token', 'created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];

}
