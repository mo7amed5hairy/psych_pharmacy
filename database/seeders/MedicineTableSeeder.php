<?php

namespace Database\Seeders;

use App\Models\Medicine;
use Illuminate\Database\Seeder;

class MedicineTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $medicines = [
            'ايبكسيدون ١ مجم',
            'ايبكسيدون ٣ مجم',
            'اريبيرابرازول ١٠ مجم',
            'اريبيرابرازول ٣٠ مجم',
            'انفيجا ١٠٠ مجم',
            'اولابكس ١٠ مجم',
            'افيجاد XR ٧٥',
            'بريانيل ٤٠٠ مجم CR',
            'تريتكو ٥٠ مجم',
            'ديباكين كرونو ٥٠٠ م',
            'ديلوكستين ٣٠',
            'ديلوكستين ٦٠',
            'ريميرون ٣٠ مجم',
            'ستاكومين ١٠٠ مجم',
            'ستيلاسيل ٥ مجم',
            'كلوبيكسول ديبو',
            'كلوزابكس ١٠٠ مجم',
            'كلوزبين ٢٥ مجم',
            'كوجنتول ٢',
            'كويتابكس ١٠٠ مجم',
            'كويتابكس ٢٥ مجم',
            'كويتابكس ٤٠٠ XR',
            'كويتبازيك ١٥٠ مجم XR',
            'كويتبازيك ٣٠٠ مجم XR',
            'لاموترين ٢٥ مجم',
            'لاموترين ٥٠ مجم',
            'موبيكس ٥٠ مجم',
            'ليبراكس ١٠ مجم (سيكويا)',
            'نيوروتين ٢ مجم',
            'نيروتين ٥ مجم',
        ];

        // Using fake varying prices
        foreach ($medicines as $name) {
            $hotline = rand(50, 450);
            Medicine::updateOrCreate(
                ['name' => $name],
                [
                    'unit_type_id' => 1,
                    'price_hotline' => $hotline,
                    'price_contract' => $hotline * 1.1,
                    'price_clinic' => $hotline * 0.9,
                ]
            );
        }

    }
}
