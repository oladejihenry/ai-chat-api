<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasUuids;

    protected $fillable = [
        'conversation_id',
        'content',
        'role',
        'model_name',
        'image_data',
    ];

    protected $casts = [
        'role' => 'string',
        'image_data' => 'array',
    ];

    /**
     * Get the conversation that owns the message.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Check if message has images
     */
    public function hasImages(): bool
    {
        return !empty($this->getImageDataArray());
    }

    /**
     * Get image count
     */
    public function getImageCount(): int
    {
        $imageData = $this->getImageDataArray();
        return is_array($imageData) ? count($imageData) : 0;
    }

    /**
     * Get image data as array (handles both string and array cases)
     */
    protected function getImageDataArray(): array
    {
        if (empty($this->image_data)) {
            return [];
        }

        // If it's already an array (from casting), return it
        if (is_array($this->image_data)) {
            return $this->image_data;
        }

        // If it's a string, try to decode it as JSON
        if (is_string($this->image_data)) {
            $decoded = json_decode($this->image_data, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * Get image URLs for display
     */
    public function getImageUrls(): array
    {
        $imageData = $this->getImageDataArray();
        $urls = [];

        foreach ($imageData as $item) {
            if (isset($item['image_url']['url'])) {
                $urls[] = $item['image_url']['url'];
            }
        }

        return $urls;
    }
}
