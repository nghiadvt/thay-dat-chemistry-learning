<?php

namespace Database\Seeders;

use App\Models\Element;
use App\Models\ElementCategory;
use App\Models\PeriodicPreset;
use Illuminate\Database\Seeder;

/**
 * Nạp catalog nguyên tố + nhóm từ dữ liệu prototype (HTD_ELEMENTS,
 * HTD_ELEMENT_CATEGORIES) và tạo một phiên bản "Mặc định" đã xuất bản.
 * Idempotent: chạy lại chỉ cập nhật, không nhân đôi.
 */
class PeriodicTableSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->categories() as $i => $cat) {
            ElementCategory::updateOrCreate(
                ['slug' => $cat['slug']],
                ['name' => $cat['name'], 'color' => $cat['color'], 'deep_color' => $cat['deep'], 'sort_order' => $i]
            );
        }

        $catIds = ElementCategory::pluck('id', 'slug');

        // Thứ tự xuất hiện mặc định: trọng tâm (priority 1) trước, rồi theo z.
        $rows = $this->elements();
        usort($rows, fn ($a, $b) => [$a['priority'], $a['z']] <=> [$b['priority'], $b['z']]);

        foreach ($rows as $order => $el) {
            Element::updateOrCreate(
                ['z' => $el['z']],
                [
                    'symbol' => $el['symbol'],
                    'name_vi' => $el['nameVi'],
                    'name_en' => $el['nameEn'],
                    'mass' => $el['mass'],
                    'category_id' => $catIds[$el['category']] ?? null,
                    'phonetic' => $el['phonetic'],
                    'group_no' => $el['group'],
                    'period_no' => $el['period'],
                    'sort_order' => $order + 1,
                ]
            );
        }

        // Phiên bản mặc định: mọi nguyên tố hiện + sáng, không khoá pro (giữ
        // nguyên hành vi hiện tại). Giáo viên tự cấu hình về sau.
        $preset = PeriodicPreset::firstOrCreate(
            ['name' => 'Mặc định'],
            ['note' => 'Phiên bản khởi tạo từ dữ liệu chương trình.']
        );

        $attach = Element::pluck('id')->mapWithKeys(fn ($id) => [
            $id => ['is_lit' => true, 'is_visible' => true, 'requires_pro' => false, 'sort_override' => null],
        ])->all();
        $preset->elements()->syncWithoutDetaching($attach);

        if (! PeriodicPreset::where('is_live', true)->exists()) {
            $preset->publish();
        }
    }

    /** @return array<int, array<string, string>> */
    private function categories(): array
    {
        return [
            ['slug' => 'kiem',        'name' => 'Kim loại kiềm',  'color' => '#FF5DA2', 'deep' => '#D63384'],
            ['slug' => 'kiem-tho',    'name' => 'Kiềm thổ',       'color' => '#FFC93C', 'deep' => '#E8A100'],
            ['slug' => 'chuyen-tiep', 'name' => 'KL chuyển tiếp', 'color' => '#38C6FF', 'deep' => '#0E9BD8'],
            ['slug' => 'kim-loai',    'name' => 'Kim loại khác',  'color' => '#58CC02', 'deep' => '#3E9A00'],
            ['slug' => 'a-kim',       'name' => 'Á kim',          'color' => '#FF8A5C', 'deep' => '#E05A24'],
            ['slug' => 'phi-kim',     'name' => 'Phi kim',        'color' => '#8B5CF6', 'deep' => '#6D28D9'],
            ['slug' => 'halogen',     'name' => 'Halogen',        'color' => '#FF4B4B', 'deep' => '#D22F2F'],
            ['slug' => 'khi-hiem',    'name' => 'Khí hiếm',       'color' => '#00C2A8', 'deep' => '#00937F'],
            ['slug' => 'lantan',      'name' => 'Lanthanide',     'color' => '#2FBFA6', 'deep' => '#178C78'],
            ['slug' => 'actini',      'name' => 'Actinide',       'color' => '#F0607A', 'deep' => '#C43552'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function elements(): array
    {
        return [
            ['z' => 1,  'symbol' => 'H',  'nameVi' => 'Hiđro',     'nameEn' => 'Hydrogen',   'mass' => 1.008,  'category' => 'phi-kim',     'phonetic' => 'Hi-đrô',     'group' => 1,  'period' => 1, 'priority' => 1],
            ['z' => 2,  'symbol' => 'He', 'nameVi' => 'Heli',      'nameEn' => 'Helium',     'mass' => 4.003,  'category' => 'khi-hiem',    'phonetic' => 'Hê-li',      'group' => 18, 'period' => 1, 'priority' => 1],
            ['z' => 3,  'symbol' => 'Li', 'nameVi' => 'Liti',      'nameEn' => 'Lithium',    'mass' => 6.94,   'category' => 'kiem',        'phonetic' => 'Li-ti',      'group' => 1,  'period' => 2, 'priority' => 1],
            ['z' => 4,  'symbol' => 'Be', 'nameVi' => 'Beri',      'nameEn' => 'Beryllium',  'mass' => 9.012,  'category' => 'kiem-tho',    'phonetic' => 'Bê-ri',      'group' => 2,  'period' => 2, 'priority' => 1],
            ['z' => 5,  'symbol' => 'B',  'nameVi' => 'Bo',        'nameEn' => 'Boron',      'mass' => 10.81,  'category' => 'a-kim',       'phonetic' => 'Bo',         'group' => 13, 'period' => 2, 'priority' => 2],
            ['z' => 6,  'symbol' => 'C',  'nameVi' => 'Cacbon',    'nameEn' => 'Carbon',     'mass' => 12.011, 'category' => 'phi-kim',     'phonetic' => 'Các-bon',    'group' => 14, 'period' => 2, 'priority' => 1],
            ['z' => 7,  'symbol' => 'N',  'nameVi' => 'Nitơ',      'nameEn' => 'Nitrogen',   'mass' => 14.007, 'category' => 'phi-kim',     'phonetic' => 'Ni-tơ',      'group' => 15, 'period' => 2, 'priority' => 1],
            ['z' => 8,  'symbol' => 'O',  'nameVi' => 'Oxi',       'nameEn' => 'Oxygen',     'mass' => 15.999, 'category' => 'phi-kim',     'phonetic' => 'Ô-xi',       'group' => 16, 'period' => 2, 'priority' => 1],
            ['z' => 9,  'symbol' => 'F',  'nameVi' => 'Flo',       'nameEn' => 'Fluorine',   'mass' => 18.998, 'category' => 'halogen',     'phonetic' => 'Flo',        'group' => 17, 'period' => 2, 'priority' => 1],
            ['z' => 10, 'symbol' => 'Ne', 'nameVi' => 'Neon',      'nameEn' => 'Neon',       'mass' => 20.18,  'category' => 'khi-hiem',    'phonetic' => 'Nê-ông',     'group' => 18, 'period' => 2, 'priority' => 1],
            ['z' => 11, 'symbol' => 'Na', 'nameVi' => 'Natri',     'nameEn' => 'Sodium',     'mass' => 22.99,  'category' => 'kiem',        'phonetic' => 'Na-tri',     'group' => 1,  'period' => 3, 'priority' => 1],
            ['z' => 12, 'symbol' => 'Mg', 'nameVi' => 'Magie',     'nameEn' => 'Magnesium',  'mass' => 24.305, 'category' => 'kiem-tho',    'phonetic' => 'Ma-giê',     'group' => 2,  'period' => 3, 'priority' => 1],
            ['z' => 13, 'symbol' => 'Al', 'nameVi' => 'Nhôm',      'nameEn' => 'Aluminium',  'mass' => 26.982, 'category' => 'kim-loai',    'phonetic' => 'Nhôm',       'group' => 13, 'period' => 3, 'priority' => 1],
            ['z' => 14, 'symbol' => 'Si', 'nameVi' => 'Silic',     'nameEn' => 'Silicon',    'mass' => 28.085, 'category' => 'a-kim',       'phonetic' => 'Si-líc',     'group' => 14, 'period' => 3, 'priority' => 1],
            ['z' => 15, 'symbol' => 'P',  'nameVi' => 'Photpho',   'nameEn' => 'Phosphorus', 'mass' => 30.974, 'category' => 'phi-kim',     'phonetic' => 'Phốt-pho',   'group' => 15, 'period' => 3, 'priority' => 1],
            ['z' => 16, 'symbol' => 'S',  'nameVi' => 'Lưu huỳnh', 'nameEn' => 'Sulfur',     'mass' => 32.06,  'category' => 'phi-kim',     'phonetic' => 'Lưu huỳnh',  'group' => 16, 'period' => 3, 'priority' => 1],
            ['z' => 17, 'symbol' => 'Cl', 'nameVi' => 'Clo',       'nameEn' => 'Chlorine',   'mass' => 35.45,  'category' => 'halogen',     'phonetic' => 'Clo',        'group' => 17, 'period' => 3, 'priority' => 1],
            ['z' => 18, 'symbol' => 'Ar', 'nameVi' => 'Agon',      'nameEn' => 'Argon',      'mass' => 39.95,  'category' => 'khi-hiem',    'phonetic' => 'A-gông',     'group' => 18, 'period' => 3, 'priority' => 1],
            ['z' => 19, 'symbol' => 'K',  'nameVi' => 'Kali',      'nameEn' => 'Potassium',  'mass' => 39.098, 'category' => 'kiem',        'phonetic' => 'Ka-li',      'group' => 1,  'period' => 4, 'priority' => 1],
            ['z' => 20, 'symbol' => 'Ca', 'nameVi' => 'Canxi',     'nameEn' => 'Calcium',    'mass' => 40.078, 'category' => 'kiem-tho',    'phonetic' => 'Can-xi',     'group' => 2,  'period' => 4, 'priority' => 1],
            ['z' => 21, 'symbol' => 'Sc', 'nameVi' => 'Scandi',    'nameEn' => 'Scandium',   'mass' => 44.956, 'category' => 'chuyen-tiep', 'phonetic' => 'Scan-đi',    'group' => 3,  'period' => 4, 'priority' => 2],
            ['z' => 22, 'symbol' => 'Ti', 'nameVi' => 'Titan',     'nameEn' => 'Titanium',   'mass' => 47.867, 'category' => 'chuyen-tiep', 'phonetic' => 'Ti-tan',     'group' => 4,  'period' => 4, 'priority' => 2],
            ['z' => 23, 'symbol' => 'V',  'nameVi' => 'Vanadi',    'nameEn' => 'Vanadium',   'mass' => 50.942, 'category' => 'chuyen-tiep', 'phonetic' => 'Va-na-đi',   'group' => 5,  'period' => 4, 'priority' => 2],
            ['z' => 24, 'symbol' => 'Cr', 'nameVi' => 'Crom',      'nameEn' => 'Chromium',   'mass' => 51.996, 'category' => 'chuyen-tiep', 'phonetic' => 'Crôm',       'group' => 6,  'period' => 4, 'priority' => 1],
            ['z' => 25, 'symbol' => 'Mn', 'nameVi' => 'Mangan',    'nameEn' => 'Manganese',  'mass' => 54.938, 'category' => 'chuyen-tiep', 'phonetic' => 'Man-gan',    'group' => 7,  'period' => 4, 'priority' => 1],
            ['z' => 26, 'symbol' => 'Fe', 'nameVi' => 'Sắt',       'nameEn' => 'Iron',       'mass' => 55.845, 'category' => 'chuyen-tiep', 'phonetic' => 'Sắt',        'group' => 8,  'period' => 4, 'priority' => 1],
            ['z' => 27, 'symbol' => 'Co', 'nameVi' => 'Coban',     'nameEn' => 'Cobalt',     'mass' => 58.933, 'category' => 'chuyen-tiep', 'phonetic' => 'Cô-ban',     'group' => 9,  'period' => 4, 'priority' => 2],
            ['z' => 28, 'symbol' => 'Ni', 'nameVi' => 'Niken',     'nameEn' => 'Nickel',     'mass' => 58.693, 'category' => 'chuyen-tiep', 'phonetic' => 'Ni-ken',     'group' => 10, 'period' => 4, 'priority' => 2],
            ['z' => 29, 'symbol' => 'Cu', 'nameVi' => 'Đồng',      'nameEn' => 'Copper',     'mass' => 63.546, 'category' => 'chuyen-tiep', 'phonetic' => 'Đồng',       'group' => 11, 'period' => 4, 'priority' => 1],
            ['z' => 30, 'symbol' => 'Zn', 'nameVi' => 'Kẽm',       'nameEn' => 'Zinc',       'mass' => 65.38,  'category' => 'chuyen-tiep', 'phonetic' => 'Kẽm',        'group' => 12, 'period' => 4, 'priority' => 1],
            ['z' => 31, 'symbol' => 'Ga', 'nameVi' => 'Gali',      'nameEn' => 'Gallium',    'mass' => 69.723, 'category' => 'kim-loai',    'phonetic' => 'Ga-li',      'group' => 13, 'period' => 4, 'priority' => 2],
            ['z' => 32, 'symbol' => 'Ge', 'nameVi' => 'Gemani',    'nameEn' => 'Germanium',  'mass' => 72.63,  'category' => 'a-kim',       'phonetic' => 'Giéc-ma-ni', 'group' => 14, 'period' => 4, 'priority' => 2],
            ['z' => 33, 'symbol' => 'As', 'nameVi' => 'Asen',      'nameEn' => 'Arsenic',    'mass' => 74.922, 'category' => 'a-kim',       'phonetic' => 'A-sen',      'group' => 15, 'period' => 4, 'priority' => 2],
            ['z' => 34, 'symbol' => 'Se', 'nameVi' => 'Selen',     'nameEn' => 'Selenium',   'mass' => 78.971, 'category' => 'phi-kim',     'phonetic' => 'Sê-len',     'group' => 16, 'period' => 4, 'priority' => 2],
            ['z' => 35, 'symbol' => 'Br', 'nameVi' => 'Brom',      'nameEn' => 'Bromine',    'mass' => 79.904, 'category' => 'halogen',     'phonetic' => 'Brôm',       'group' => 17, 'period' => 4, 'priority' => 1],
            ['z' => 36, 'symbol' => 'Kr', 'nameVi' => 'Kripton',   'nameEn' => 'Krypton',    'mass' => 83.798, 'category' => 'khi-hiem',    'phonetic' => 'Críp-tôn',   'group' => 18, 'period' => 4, 'priority' => 2],
            ['z' => 38, 'symbol' => 'Sr', 'nameVi' => 'Stronti',   'nameEn' => 'Strontium',  'mass' => 87.62,  'category' => 'kiem-tho',    'phonetic' => 'Xtrôn-ti',   'group' => 2,  'period' => 5, 'priority' => 2],
            ['z' => 47, 'symbol' => 'Ag', 'nameVi' => 'Bạc',       'nameEn' => 'Silver',     'mass' => 107.87, 'category' => 'chuyen-tiep', 'phonetic' => 'Bạc',        'group' => 11, 'period' => 5, 'priority' => 1],
            ['z' => 50, 'symbol' => 'Sn', 'nameVi' => 'Thiếc',     'nameEn' => 'Tin',        'mass' => 118.71, 'category' => 'kim-loai',    'phonetic' => 'Thiếc',      'group' => 14, 'period' => 5, 'priority' => 2],
            ['z' => 53, 'symbol' => 'I',  'nameVi' => 'Iot',       'nameEn' => 'Iodine',     'mass' => 126.9,  'category' => 'halogen',     'phonetic' => 'I-ốt',       'group' => 17, 'period' => 5, 'priority' => 1],
            ['z' => 56, 'symbol' => 'Ba', 'nameVi' => 'Bari',      'nameEn' => 'Barium',     'mass' => 137.33, 'category' => 'kiem-tho',    'phonetic' => 'Ba-ri',      'group' => 2,  'period' => 6, 'priority' => 1],
            ['z' => 78, 'symbol' => 'Pt', 'nameVi' => 'Bạch kim',  'nameEn' => 'Platinum',   'mass' => 195.08, 'category' => 'chuyen-tiep', 'phonetic' => 'Bạch kim',   'group' => 10, 'period' => 6, 'priority' => 2],
            ['z' => 79, 'symbol' => 'Au', 'nameVi' => 'Vàng',      'nameEn' => 'Gold',       'mass' => 196.97, 'category' => 'chuyen-tiep', 'phonetic' => 'Vàng',       'group' => 11, 'period' => 6, 'priority' => 1],
            ['z' => 80, 'symbol' => 'Hg', 'nameVi' => 'Thủy ngân', 'nameEn' => 'Mercury',    'mass' => 200.59, 'category' => 'chuyen-tiep', 'phonetic' => 'Thủy ngân',  'group' => 12, 'period' => 6, 'priority' => 1],
            ['z' => 82, 'symbol' => 'Pb', 'nameVi' => 'Chì',       'nameEn' => 'Lead',       'mass' => 207.2,  'category' => 'kim-loai',    'phonetic' => 'Chì',        'group' => 14, 'period' => 6, 'priority' => 1],
        ];
    }
}
