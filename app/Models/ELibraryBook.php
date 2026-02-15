<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ELibraryBook extends Model
{
  protected $table = 'e_library_books';

 protected $fillable = [
  'school_id',
  'uploaded_by_user_id',
  'term_subject_id',
  'subject_id',
  'education_level',
  'title',
  'author',
  'description',
  'file_path',
  'original_name',
  'mime_type',
  'size',
];

}
