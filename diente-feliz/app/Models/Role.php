<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Role extends Model { public $timestamps = false; protected $guarded = ['id']; protected function casts(): array { return ['permissions'=>'array']; } }
