<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Keyword extends Model
{
    use HasFactory;

    protected $fillable = ['keyword', 'response'];

    public function getResponseForMessage($message)
    {
        // Retrieve response for the keyword from the database
        $keywordEntry = Keyword::whereRaw('LOWER(keyword) LIKE ?', ["%".strtolower($message)."%"])->first();

        if ($keywordEntry) {
            return $keywordEntry->response;
        }

        return "I'm sorry, I couldn't find a response for that message.";
    }
}
