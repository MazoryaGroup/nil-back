<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\Blog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BlogController extends Controller
{
    /**
     * لیست عمومی بلاگ‌ها
     *
     * GET /api/v1/blogs?lang=fa
     * GET /api/v1/blogs?lang=en
     */
    public function index(Request $request): JsonResponse
    {
        $lang = $this->getLanguage($request);

        $blogs = Blog::query()
            ->where('status', 'published')
            ->with([
                'translations' => function ($query) use ($lang) {
                    $query->where('locale', $lang);
                },
            ])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->map(function (Blog $blog) use ($lang) {
                return $this->formatBlog($blog, $lang);
            })
            ->values();

        return response()->json([
            'success' => true,
            'lang' => $lang,
            'data' => $blogs,
        ]);
    }

    /**
     * نمایش یک بلاگ
     *
     * GET /api/v1/blogs/{id}?lang=fa
     * GET /api/v1/blogs/{id}?lang=en
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $lang = $this->getLanguage($request);

        $blog = Blog::query()
            ->where('status', 'published')
            ->with([
                'translations' => function ($query) use ($lang) {
                    $query->where('locale', $lang);
                },
            ])
            ->find($id);

        if (!$blog) {
            return response()->json([
                'success' => false,
                'message' => 'Blog not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'lang' => $lang,
            'data' => $this->formatBlog($blog, $lang),
        ]);
    }

    /**
     * بلاگ‌های هنرمند لاگین‌شده
     *
     * GET /api/v1/my-blogs?lang=fa
     */
    public function myBlogs(Request $request): JsonResponse
    {
        $client = auth('api')->user();

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $artist = $client->artist;

        if (!$artist) {
            return response()->json([
                'success' => false,
                'message' => 'Artist profile not found.',
            ], 404);
        }

        $lang = $this->getLanguage($request);

        $blogs = Blog::query()
            ->where('author_type', Artist::class)
            ->where('author_id', $artist->id)
            ->with([
                'translations' => function ($query) use ($lang) {
                    $query->where('locale', $lang);
                },
            ])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->map(function (Blog $blog) use ($lang) {
                return $this->formatBlog($blog, $lang);
            })
            ->values();

        return response()->json([
            'success' => true,
            'lang' => $lang,
            'data' => $blogs,
        ]);
    }

    /**
     * ایجاد بلاگ توسط Artist
     *
     * POST /api/v1/blogs
     */
    public function store(Request $request): JsonResponse
    {
        $client = auth('api')->user();

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $artist = $client->artist;

        if (!$artist) {
            return response()->json([
                'success' => false,
                'message' => 'Artist profile not found.',
            ], 404);
        }

        $validated = $request->validate([
            'date' => [
                'required',
                'date',
            ],

            'reading_time' => [
                'nullable',
                'string',
                'max:100',
            ],

            'image_1' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],

            'image_2' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],

            'status' => [
                'nullable',
                Rule::in([
                    'draft',
                    'published',
                ]),
            ],

            /*
             * فارسی
             */
            'fa.title_1' => [
                'required',
                'string',
                'max:255',
            ],

            'fa.description_1' => [
                'nullable',
                'string',
            ],

            'fa.title_2' => [
                'nullable',
                'string',
                'max:255',
            ],

            'fa.description_2' => [
                'nullable',
                'string',
            ],

            'fa.title_3' => [
                'nullable',
                'string',
                'max:255',
            ],

            'fa.description_3' => [
                'nullable',
                'string',
            ],

            /*
             * انگلیسی
             */
            'en.title_1' => [
                'required',
                'string',
                'max:255',
            ],

            'en.description_1' => [
                'nullable',
                'string',
            ],

            'en.title_2' => [
                'nullable',
                'string',
                'max:255',
            ],

            'en.description_2' => [
                'nullable',
                'string',
            ],

            'en.title_3' => [
                'nullable',
                'string',
                'max:255',
            ],

            'en.description_3' => [
                'nullable',
                'string',
            ],
        ]);

        $blog = new Blog();

        $blog->date = $validated['date'];
        $blog->reading_time = $validated['reading_time'] ?? null;

        /*
         * نویسنده همیشه Artist است
         * چون این endpoint برای Artist لاگین‌شده است.
         */
        $blog->author_type = Artist::class;
        $blog->author_id = $artist->id;

        $blog->status = $validated['status'] ?? 'draft';

        /*
         * Image 1
         */
        if ($request->hasFile('image_1')) {
            $blog->image_1 = $request
                ->file('image_1')
                ->store('blogs', 'public');
        }

        /*
         * Image 2
         */
        if ($request->hasFile('image_2')) {
            $blog->image_2 = $request
                ->file('image_2')
                ->store('blogs', 'public');
        }

        $blog->save();

        /*
         * فارسی
         */
        $blog->translations()->create([
            'locale' => 'fa',

            'title_1' => $validated['fa']['title_1'],

            'description_1' =>
                $validated['fa']['description_1'] ?? null,

            'title_2' =>
                $validated['fa']['title_2'] ?? null,

            'description_2' =>
                $validated['fa']['description_2'] ?? null,

            'title_3' =>
                $validated['fa']['title_3'] ?? null,

            'description_3' =>
                $validated['fa']['description_3'] ?? null,
        ]);

        /*
         * انگلیسی
         */
        $blog->translations()->create([
            'locale' => 'en',

            'title_1' => $validated['en']['title_1'],

            'description_1' =>
                $validated['en']['description_1'] ?? null,

            'title_2' =>
                $validated['en']['title_2'] ?? null,

            'description_2' =>
                $validated['en']['description_2'] ?? null,

            'title_3' =>
                $validated['en']['title_3'] ?? null,

            'description_3' =>
                $validated['en']['description_3'] ?? null,
        ]);

        $blog->load([
            'translations',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Blog created successfully.',
            'data' => $this->formatBlog($blog),
        ], 201);
    }

    /**
     * ویرایش بلاگ توسط Artist
     *
     * PUT /api/v1/blogs/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $client = auth('api')->user();

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $artist = $client->artist;

        if (!$artist) {
            return response()->json([
                'success' => false,
                'message' => 'Artist profile not found.',
            ], 404);
        }

        /*
         * فقط بلاگ‌های همین Artist
         */
        $blog = Blog::query()
            ->where('id', $id)
            ->where('author_type', Artist::class)
            ->where('author_id', $artist->id)
            ->first();

        if (!$blog) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Blog not found or you do not have permission to edit it.',
            ], 404);
        }

        $validated = $request->validate([
            'date' => [
                'sometimes',
                'date',
            ],

            'reading_time' => [
                'nullable',
                'string',
                'max:100',
            ],

            'image_1' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],

            'image_2' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],

            'status' => [
                'nullable',
                Rule::in([
                    'draft',
                    'published',
                ]),
            ],

            /*
             * فارسی
             */
            'fa.title_1' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'fa.description_1' => [
                'nullable',
                'string',
            ],

            'fa.title_2' => [
                'nullable',
                'string',
                'max:255',
            ],

            'fa.description_2' => [
                'nullable',
                'string',
            ],

            'fa.title_3' => [
                'nullable',
                'string',
                'max:255',
            ],

            'fa.description_3' => [
                'nullable',
                'string',
            ],

            /*
             * انگلیسی
             */
            'en.title_1' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'en.description_1' => [
                'nullable',
                'string',
            ],

            'en.title_2' => [
                'nullable',
                'string',
                'max:255',
            ],

            'en.description_2' => [
                'nullable',
                'string',
            ],

            'en.title_3' => [
                'nullable',
                'string',
                'max:255',
            ],

            'en.description_3' => [
                'nullable',
                'string',
            ],
        ]);

        /*
         * اطلاعات اصلی
         */
        if (array_key_exists('date', $validated)) {
            $blog->date = $validated['date'];
        }

        if (array_key_exists('reading_time', $validated)) {
            $blog->reading_time = $validated['reading_time'];
        }

        if (array_key_exists('status', $validated)) {
            $blog->status = $validated['status'];
        }

        /*
         * Image 1
         */
        if ($request->hasFile('image_1')) {

            if ($blog->image_1) {
                Storage::disk('public')
                    ->delete($blog->image_1);
            }

            $blog->image_1 = $request
                ->file('image_1')
                ->store('blogs', 'public');
        }

        /*
         * Image 2
         */
        if ($request->hasFile('image_2')) {

            if ($blog->image_2) {
                Storage::disk('public')
                    ->delete($blog->image_2);
            }

            $blog->image_2 = $request
                ->file('image_2')
                ->store('blogs', 'public');
        }

        $blog->save();

        /*
         * Translation فارسی
         */
        if (isset($validated['fa'])) {

            $faTranslation = $blog
                ->translations()
                ->where('locale', 'fa')
                ->first();

            $blog->translations()->updateOrCreate(
                [
                    'locale' => 'fa',
                ],
                [
                    'title_1' =>
                        $validated['fa']['title_1']
                        ?? $faTranslation?->title_1,

                    'description_1' =>
                        array_key_exists(
                            'description_1',
                            $validated['fa']
                        )
                            ? $validated['fa']['description_1']
                            : $faTranslation?->description_1,

                    'title_2' =>
                        array_key_exists(
                            'title_2',
                            $validated['fa']
                        )
                            ? $validated['fa']['title_2']
                            : $faTranslation?->title_2,

                    'description_2' =>
                        array_key_exists(
                            'description_2',
                            $validated['fa']
                        )
                            ? $validated['fa']['description_2']
                            : $faTranslation?->description_2,

                    'title_3' =>
                        array_key_exists(
                            'title_3',
                            $validated['fa']
                        )
                            ? $validated['fa']['title_3']
                            : $faTranslation?->title_3,

                    'description_3' =>
                        array_key_exists(
                            'description_3',
                            $validated['fa']
                        )
                            ? $validated['fa']['description_3']
                            : $faTranslation?->description_3,
                ]
            );
        }

        /*
         * Translation انگلیسی
         */
        if (isset($validated['en'])) {

            $enTranslation = $blog
                ->translations()
                ->where('locale', 'en')
                ->first();

            $blog->translations()->updateOrCreate(
                [
                    'locale' => 'en',
                ],
                [
                    'title_1' =>
                        $validated['en']['title_1']
                        ?? $enTranslation?->title_1,

                    'description_1' =>
                        array_key_exists(
                            'description_1',
                            $validated['en']
                        )
                            ? $validated['en']['description_1']
                            : $enTranslation?->description_1,

                    'title_2' =>
                        array_key_exists(
                            'title_2',
                            $validated['en']
                        )
                            ? $validated['en']['title_2']
                            : $enTranslation?->title_2,

                    'description_2' =>
                        array_key_exists(
                            'description_2',
                            $validated['en']
                        )
                            ? $validated['en']['description_2']
                            : $enTranslation?->description_2,

                    'title_3' =>
                        array_key_exists(
                            'title_3',
                            $validated['en']
                        )
                            ? $validated['en']['title_3']
                            : $enTranslation?->title_3,

                    'description_3' =>
                        array_key_exists(
                            'description_3',
                            $validated['en']
                        )
                            ? $validated['en']['description_3']
                            : $enTranslation?->description_3,
                ]
            );
        }

        $blog->load([
            'translations',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Blog updated successfully.',
            'data' => $this->formatBlog($blog),
        ]);
    }

    /**
     * حذف بلاگ توسط Artist
     *
     * DELETE /api/v1/blogs/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $client = auth('api')->user();

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $artist = $client->artist;

        if (!$artist) {
            return response()->json([
                'success' => false,
                'message' => 'Artist profile not found.',
            ], 404);
        }

        /*
         * فقط بلاگ‌های همین Artist
         */
        $blog = Blog::query()
            ->where('id', $id)
            ->where('author_type', Artist::class)
            ->where('author_id', $artist->id)
            ->first();

        if (!$blog) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Blog not found or you do not have permission to delete it.',
            ], 404);
        }

        /*
         * حذف تصاویر
         */
        if ($blog->image_1) {
            Storage::disk('public')
                ->delete($blog->image_1);
        }

        if ($blog->image_2) {
            Storage::disk('public')
                ->delete($blog->image_2);
        }

        /*
         * translations به دلیل
         * ON DELETE CASCADE حذف می‌شوند.
         */
        $blog->delete();

        return response()->json([
            'success' => true,
            'message' => 'Blog deleted successfully.',
        ]);
    }

    /**
     * فرمت خروجی بلاگ
     */
    private function formatBlog(
        Blog $blog,
        ?string $lang = null
    ): array {

        /*
         * نویسنده
         */
        $author = null;

        /*
         * Wachaar
         *
         * نکته مهم:
         * اینجا دیگر به $blog->author دست نمی‌زنیم.
         * بنابراین Laravel دنبال کلاس "wachaar" نمی‌گردد.
         */
        if ($blog->author_type === 'wachaar') {

            $author = 'Wachaar';

        }

        /*
         * Artist
         */
        elseif ($blog->author_type === Artist::class) {

            $artist = Artist::find($blog->author_id);

            if ($artist) {

                $author = trim(
                    ($artist->name ?? '') . ' ' .
                    ($artist->family ?? '')
                );

                if (!$author) {
                    $author = 'Artist #' . $blog->author_id;
                }
            }
        }

        /*
         * اطلاعات اصلی
         */
        $data = [
            'id' => $blog->id,

            'date' => $blog->date?->format('Y-m-d'),

            'reading_time' => $blog->reading_time,

            'image_1' => $blog->image_1
                ? Storage::disk('public')
                    ->url($blog->image_1)
                : null,

            'image_2' => $blog->image_2
                ? Storage::disk('public')
                    ->url($blog->image_2)
                : null,

            'status' => $blog->status,

            'author' => $author,
        ];

        /*
         * اگر زبان مشخص شده باشد
         * فقط همان زبان برگردانده می‌شود.
         */
        if ($lang) {

            $translation = $blog->translations
                ->firstWhere('locale', $lang);

            $data['lang'] = $lang;

            $data['title_1'] =
                $translation?->title_1;

            $data['description_1'] =
                $translation?->description_1;

            $data['title_2'] =
                $translation?->title_2;

            $data['description_2'] =
                $translation?->description_2;

            $data['title_3'] =
                $translation?->title_3;

            $data['description_3'] =
                $translation?->description_3;

            return $data;
        }

        /*
         * Create / Update
         *
         * هر دو زبان
         */
        $data['translations'] = $blog->translations
            ->map(function ($translation) {

                return [
                    'locale' =>
                        $translation->locale,

                    'title_1' =>
                        $translation->title_1,

                    'description_1' =>
                        $translation->description_1,

                    'title_2' =>
                        $translation->title_2,

                    'description_2' =>
                        $translation->description_2,

                    'title_3' =>
                        $translation->title_3,

                    'description_3' =>
                        $translation->description_3,
                ];
            })
            ->values()
            ->all();

        return $data;
    }

    /**
     * تعیین زبان
     */
    private function getLanguage(Request $request): string
    {
        $lang = $request->get('lang', 'fa');

        return in_array($lang, ['fa', 'en'])
            ? $lang
            : 'fa';
    }
}
