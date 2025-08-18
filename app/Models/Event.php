<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;
    protected $table = 'Events';

       // Specify which attributes are mass assignable
       protected $fillable = [
        'title',
        'description',
        'type',
        'start_time',
        'end_time',
        'location',
        'platform',
        'organizer_name',
        'event_image',
        'user_id',
        'status',
    ];

}
