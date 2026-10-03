<?php

namespace App\Filament\Resources\BlogResource\Pages;

use App\Filament\Resources\BlogResource;
use App\Models\BlogTranslation;
use Filament\Resources\Pages\EditRecord;

class EditBlog extends EditRecord
{
    protected static string $resource = BlogResource::class;

    protected array $translations = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $fa = $this->record->translations()
            ->where('locale', 'fa')
            ->first();

        $en = $this->record->translations()
            ->where('locale', 'en')
            ->first();

        $data['translation_fa'] = [
            'title_1' => $fa?->title_1,
            'description_1' => $fa?->description_1,
            'title_2' => $fa?->title_2,
            'description_2' => $fa?->description_2,
            'title_3' => $fa?->title_3,
            'description_3' => $fa?->description_3,
        ];

        $data['translation_en'] = [
            'title_1' => $en?->title_1,
            'description_1' => $en?->description_1,
            'title_2' => $en?->title_2,
            'description_2' => $en?->description_2,
            'title_3' => $en?->title_3,
            'description_3' => $en?->description_3,
        ];

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->translations = [
            'fa' => $data['translation_fa'] ?? [],
            'en' => $data['translation_en'] ?? [],
        ];

        unset($data['translation_fa'], $data['translation_en']);

        return $data;
    }

    protected function afterSave(): void
    {
        foreach ($this->translations as $locale => $translation) {
            BlogTranslation::updateOrCreate(
                [
                    'blog_id' => $this->record->id,
                    'locale' => $locale,
                ],
                [
                    'title_1' => $translation['title_1'] ?? null,
                    'description_1' => $translation['description_1'] ?? null,
                    'title_2' => $translation['title_2'] ?? null,
                    'description_2' => $translation['description_2'] ?? null,
                    'title_3' => $translation['title_3'] ?? null,
                    'description_3' => $translation['description_3'] ?? null,
                ]
            );
        }
    }
}
