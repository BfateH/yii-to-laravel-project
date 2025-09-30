<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArticleRelated extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id',
        'related_article_id',
    ];

    public function article(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_id');
    }

    public function relatedArticle(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Article::class, 'related_article_id');
    }
}
