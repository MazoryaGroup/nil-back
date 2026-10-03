<?php

namespace App\Filament\Resources\BlogResource\Pages;

use App\Filament\Resources\BlogResource;
use App\Models\BlogTranslation;
use Filament\Resources\Pages\CreateRecord;

class CreateBlog extends CreateRecord
{
    protected static string $resource = BlogResource::class;

    protected array $translations = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // دریافت ترجمه‌ها
        $this->translations = [
            'fa' => $data['translation_fa'] ?? [],
            'en' => $data['translation_en'] ?? [],
        ];

        // این فیلدها مربوط به جدول blogs نیستند
        unset($data['translation_fa'], $data['translation_en']);

        return $data;
    }

    protected function afterCreate(): void
    {
        foreach ($this->translations as $locale => $translation) {

            // اگر هیچ اطلاعاتی برای این زبان وارد نشده بود، ذخیره نکن
            if (empty(array_filter($translation, fn ($value) => $value !== null && $value !== ''))) {
                continue;
            }

            BlogTranslation::create([
                'blog_id' => $this->record->id,
                'locale' => $locale,
                'title_1' => $translation['title_1'] ?? null,
                'description_1' => $translation['description_1'] ?? null,
                'title_2' => $translation['title_2'] ?? null,
                'description_2' => $translation['description_2'] ?? null,
                'title_3' => $translation['title_3'] ?? null,
                'description_3' => $translation['description_3'] ?? null,
            ]);
        }
    }
}
