<?php

namespace App\Exports;

use App\Models\Client;
use App\Models\Waitlist;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UsersExport implements FromCollection, WithHeadings, WithMapping
{
    protected $filter; // all, waiting, has_order, no_order

    public function __construct($filter = 'all')
    {
        $this->filter = $filter;
    }

    public function collection()
    {
        switch ($this->filter) {
            case 'waiting':
                // داده‌ها از جدول waitlist
                return Waitlist::with('client')->get();

            case 'has_order':
                return Client::with('addresses', 'orders')->has('orders')->get();

            case 'no_order':
                return Client::with('addresses', 'orders')->doesntHave('orders')->get();

            case 'all':
            default:
                return Client::with('addresses', 'orders')->get();
        }
    }

    public function map($record): array
    {
        if ($this->filter === 'waiting') {
            // اگر داده‌ها از waitlist اومده
            $client = $record->client;
            return [
                $client->id ?? '',
                $client->first_name ?? '',
                $client->last_name ?? '',
                $client->email ?? '',
                $client->phone ?? '',
                $record->note ?? '', // مثلا ستون توضیح یا یادداشت در waitlist
                '', // تعداد سفارش‌ها خالی چون هنوز ثبت‌نام نکرده
            ];
        }

        // حالت کاربران ثبت‌نام شده
        $address = $record->addresses->first()?->address ?? '';
        $orders_count = $record->orders->count();

        return [
            $record->id,
            $record->first_name,
            $record->last_name,
            $record->email,
            $record->phone,
            $address,
            $orders_count,
        ];
    }

    public function headings(): array
    {
        return [
            'ID',
            'نام',
            'فامیلی',
            'ایمیل',
            'شماره موبایل',
            'آدرس / توضیح',
            'تعداد سفارش‌ها',
        ];
    }
}
