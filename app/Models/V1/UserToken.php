<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserToken extends BaseModel
{
    use HasFactory;

    protected $table = 'user_tokens';

    protected $hidden = ['created_by', 'created_type', 'updated_by', 'updated_type', 'created_at', 'updated_at'];

    protected $fillable = [
        'user_id',
        'user_role',
        'fcm_token',
        'device_model',
        'device_brand',
        'os_version',
        'platform',
        'device_id',
        'sdk',
    ];


}
