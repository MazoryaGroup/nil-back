<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    protected $table = 'expense_categories';
    protected static ?string $model = ExpenseCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationLabel = 'دسته‌بندی هزینه‌ها';

    protected static ?string $modelLabel = 'دسته‌بندی هزینه';

    protected static ?string $pluralModelLabel = 'دسته‌بندی هزینه‌ها';

    protected static ?string $navigationGroup = 'حسابداری';

    protected static ?int $navigationSort = 2;

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function expenses(): HasMany
    {
        return $this->hasMany(
            Expense::class,
            'expense_category_id'
        );
    }
}
