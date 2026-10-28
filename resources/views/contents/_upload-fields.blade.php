{{-- Upload fields (files only): $type (PostType). Caption/hashtags are handled by the SMM on the content page. --}}
@if ($type === App\Enums\PostType::Reel)
    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <x-input-label value="Reel video (mp4/mov, max 100 MB)" />
            <input type="file" name="video" accept="video/mp4,video/quicktime" required class="mt-1 block w-full text-sm">
            <x-input-error :messages="$errors->get('video')" class="mt-2" />
        </div>
        <div>
            <x-input-label value="Cover image (optional)" />
            <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm">
            <x-input-error :messages="$errors->get('cover')" class="mt-2" />
        </div>
    </div>
@else
    <div>
        <x-input-label :value="$type === App\Enums\PostType::Carousel ? 'Carousel images (2-10, the order you pick is the slide order)' : 'Image (1)'" />
        <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" required @if ($type === App\Enums\PostType::Carousel) multiple @endif class="mt-1 block w-full text-sm">
        <x-input-error :messages="$errors->get('images')" class="mt-2" />
        <x-input-error :messages="$errors->get('images.*')" class="mt-2" />
    </div>
@endif
