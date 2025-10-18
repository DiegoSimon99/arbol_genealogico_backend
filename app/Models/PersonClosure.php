<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonClosure extends Model
{
    protected $table = 'person_closure';
    public $timestamps = false;

    protected $fillable = [
        'ancestor_id',
        'descendant_id',
        'depth',
    ];

    public function ancestor()
    {
        return $this->belongsTo(Person::class, 'ancestor_id');
    }

    public function descendant()
    {
        return $this->belongsTo(Person::class, 'descendant_id');
    }
}
