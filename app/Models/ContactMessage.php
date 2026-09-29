<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'email', 'subject', 'message', 'status'])]
class ContactMessage extends Model
{
    /** Mirrors the demo's contact inquiry statuses. */
    public const STATUSES = ['new', 'contacted', 'qualified', 'converted', 'closed'];
}
