<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MtprotoCampaign extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'account_ids' => 'array',
        'template_ids' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function list()
    {
        return $this->belongsTo(MtprotoContactList::class, 'list_id');
    }

    public function template()
    {
        return $this->belongsTo(MtprotoTemplate::class, 'template_id');
    }

    public function account()
    {
        return $this->belongsTo(MtprotoAccount::class, 'account_id');
    }
}
