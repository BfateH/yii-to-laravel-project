<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'copied_from_article_id',
        'title',
        'slug',
        'content',
        'sort_index',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ArticleCategory::class);
    }

    public function relatedArticles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_related', 'article_id', 'related_article_id');
    }

    public function relatedBy(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_related', 'related_article_id', 'article_id');
    }
}
