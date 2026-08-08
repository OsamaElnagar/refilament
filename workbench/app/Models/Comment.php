<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\Database\Factories\CommentFactory;

class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    /**
     * Demo model — allow mass assignment so relation actions can update records.
     *
     * @var array<int, string>
     */
    protected $fillable = ['post_id', 'title', 'content', 'is_visible', 'approved_at'];

    /**
     * The comment's post.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }
}
