<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Manager = 'manager';                       // Operation Manager
    case SocialMediaManager = 'social_media_manager'; // calendar, assign, approve, schedule
    case GraphicDesigner = 'graphic_designer';      // Post / Carousel
    case VideoEditor = 'video_editor';              // Reel
    case Client = 'client';                         // Customer

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Manager => 'Operation Manager',
            self::SocialMediaManager => 'Social Media Manager',
            self::GraphicDesigner => 'Graphic Designer',
            self::VideoEditor => 'Video Editor',
            self::Client => 'Customer',
        };
    }

    /** Content banane wale: inhe tasks milte hain */
    public function isCreator(): bool
    {
        return in_array($this, [self::GraphicDesigner, self::VideoEditor], true);
    }

    /** Calendar, assign, approvals, schedule sambhalne wale */
    public function isPlanner(): bool
    {
        return in_array($this, [self::Admin, self::Manager, self::SocialMediaManager], true);
    }
}
