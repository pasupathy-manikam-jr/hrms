<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A mobile device's Firebase push token.
 *
 * @property int $id
 * @property int $user_id
 * @property string $token
 */
#[Fillable(['user_id', 'personal_access_token_id', 'token', 'platform'])]
class DeviceToken extends Model {}
