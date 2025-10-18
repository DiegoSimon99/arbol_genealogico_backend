<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Person extends Model
{
    protected $table = 'people';
    protected $fillable = ['name'];

    public function ancestors(): BelongsToMany {
        return $this->belongsToMany(Person::class, 'person_closure', 'descendant_id', 'ancestor_id')
                    ->withPivot('depth');
    }

    public function descendants(): BelongsToMany {
        return $this->belongsToMany(Person::class, 'person_closure', 'ancestor_id', 'descendant_id')
                    ->withPivot('depth');
    }

    // Hijos directos (depth = 1)
    public function children(): BelongsToMany {
        return $this->descendants()->wherePivot('depth', 1);
    }
}
