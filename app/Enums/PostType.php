<?php

namespace App\Enums;

enum PostType: string
{
    case Post = 'post';
    case Carousel = 'carousel';
    case Reel = 'reel';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
