<?php

namespace Database\Seeders\Data;

class Grade10Questions
{
    use BuildsQuestions;

    /**
     * 100 câu hỏi Hóa 10: 32 trắc nghiệm lý thuyết + 25 ký hiệu nguyên tố
     * + 13 cấu hình electron + 15 khối lượng mol + 10 cân bằng PT + 5 công thức.
     *
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        $questions = [];
        $i = 0;

        foreach (self::theory() as [$content, $options, $topic, $explanation]) {
            $questions[] = self::mc($content, $options, $i++, $topic, $explanation);
        }

        $symbols = ['H', 'He', 'Li', 'C', 'N', 'O', 'F', 'Na', 'Mg', 'Al', 'Si', 'P', 'S', 'Cl', 'K', 'Ca', 'Fe', 'Cu', 'Zn', 'Ag', 'Ba', 'Mn', 'Br', 'I', 'Pb'];
        $names = ['Hiđro', 'Heli', 'Liti', 'Cacbon', 'Nitơ', 'Oxi', 'Flo', 'Natri', 'Magie', 'Nhôm', 'Silic', 'Photpho', 'Lưu huỳnh', 'Clo', 'Kali', 'Canxi', 'Sắt', 'Đồng', 'Kẽm', 'Bạc', 'Bari', 'Mangan', 'Brom', 'Iot', 'Chì'];
        foreach ($symbols as $k => $symbol) {
            $questions[] = self::symbol($names[$k], $symbol, $symbols, $i++, 'Bảng tuần hoàn');
        }

        $configs = [['Liti', 3], ['Cacbon', 6], ['Nitơ', 7], ['Oxi', 8], ['Flo', 9], ['Natri', 11], ['Magie', 12], ['Nhôm', 13], ['Photpho', 15], ['Lưu huỳnh', 16], ['Clo', 17], ['Kali', 19], ['Canxi', 20]];
        foreach ($configs as [$name, $z]) {
            $questions[] = self::configQuestion($name, $z, $i++, 'Nguyên tử');
        }

        $masses = [
            ['H₂O', 18], ['CO₂', 44], ['NaCl', 58.5], ['H₂SO₄', 98], ['CaCO₃', 100],
            ['NaOH', 40], ['HCl', 36.5], ['KMnO₄', 158], ['Fe₂O₃', 160], ['CuSO₄', 160],
            ['NH₃', 17], ['CaO', 56], ['MgCl₂', 95], ['Na₂CO₃', 106], ['AgNO₃', 170],
        ];
        foreach ($masses as [$formula, $mass]) {
            $questions[] = self::molarMass($formula, (float) $mass, $i++, 'Khối lượng mol');
        }

        $equations = [
            ['H₂ + O₂ → H₂O', '2H2 + O2 -> 2H2O'],
            ['Fe + O₂ → Fe₃O₄', '3Fe + 2O2 -> Fe3O4'],
            ['Na + Cl₂ → NaCl', '2Na + Cl2 -> 2NaCl'],
            ['Al + HCl → AlCl₃ + H₂', '2Al + 6HCl -> 2AlCl3 + 3H2'],
            ['Zn + HCl → ZnCl₂ + H₂', 'Zn + 2HCl -> ZnCl2 + H2'],
            ['KClO₃ → KCl + O₂', '2KClO3 -> 2KCl + 3O2'],
            ['Fe + Cl₂ → FeCl₃', '2Fe + 3Cl2 -> 2FeCl3'],
            ['H₂S + O₂ → SO₂ + H₂O', '2H2S + 3O2 -> 2SO2 + 2H2O'],
            ['SO₂ + O₂ → SO₃', '2SO2 + O2 -> 2SO3'],
            ['Cu + H₂SO₄ đặc → CuSO₄ + SO₂ + H₂O', 'Cu + 2H2SO4 -> CuSO4 + SO2 + 2H2O'],
        ];
        foreach ($equations as [$raw, $balanced]) {
            $questions[] = self::essay('Cân bằng phương trình hóa học sau: <strong>'.$raw.'</strong>', $balanced, 'Phương trình hóa học');
        }

        $formulas = [
            ['muối ăn', 'NaCl'],
            ['axit sunfuric', 'H2SO4'],
            ['vôi sống', 'CaO'],
            ['khí cacbonic', 'CO2'],
            ['amoniac', 'NH3'],
        ];
        foreach ($formulas as [$name, $formula]) {
            $questions[] = self::essay('Viết công thức hóa học của <strong>'.$name.'</strong>.', $formula, 'Công thức hóa học');
        }

        return $questions;
    }

    /**
     * Trắc nghiệm lý thuyết: [nội dung, [đáp án đúng trước, 3 nhiễu], chủ đề, giải thích|null].
     *
     * @return list<array{0: string, 1: list<string>, 2: string, 3: string|null}>
     */
    private static function theory(): array
    {
        return [
            ['Hạt mang điện tích âm trong nguyên tử là hạt nào?', ['Electron', 'Proton', 'Nơtron', 'Hạt nhân'], 'Nguyên tử', 'Electron mang điện tích âm, proton mang điện dương, nơtron không mang điện.'],
            ['Nguyên tử Na có Z = 11. Số proton trong hạt nhân là:', ['11', '12', '22', '23'], 'Nguyên tử', 'Số hiệu nguyên tử Z bằng số proton.'],
            ['Đồng vị là những nguyên tử có cùng:', ['số proton, khác số nơtron', 'số nơtron, khác số proton', 'khối lượng nguyên tử', 'số electron lớp ngoài cùng'], 'Nguyên tử', null],
            ['Lớp electron ngoài cùng của khí hiếm (trừ He) có bao nhiêu electron?', ['8', '2', '6', '4'], 'Nguyên tử', null],
            ['Trong bảng tuần hoàn, các nguyên tố được sắp xếp theo chiều tăng dần của:', ['điện tích hạt nhân', 'khối lượng nguyên tử', 'bán kính nguyên tử', 'độ âm điện'], 'Bảng tuần hoàn', null],
            ['Nhóm IA trong bảng tuần hoàn được gọi là nhóm:', ['kim loại kiềm', 'kim loại kiềm thổ', 'halogen', 'khí hiếm'], 'Bảng tuần hoàn', null],
            ['Trong một chu kì, theo chiều tăng điện tích hạt nhân, tính kim loại của các nguyên tố:', ['giảm dần', 'tăng dần', 'không thay đổi', 'tăng rồi giảm'], 'Bảng tuần hoàn', null],
            ['Nguyên tố có độ âm điện lớn nhất là:', ['F', 'O', 'Cl', 'N'], 'Bảng tuần hoàn', 'Flo có độ âm điện lớn nhất (3,98).'],
            ['Liên kết ion thường được hình thành giữa:', ['kim loại điển hình và phi kim điển hình', 'hai phi kim giống nhau', 'hai kim loại', 'kim loại và khí hiếm'], 'Liên kết hóa học', null],
            ['Phân tử N₂ chứa liên kết:', ['ba', 'đơn', 'đôi', 'ion'], 'Liên kết hóa học', 'Hai nguyên tử N dùng chung 3 cặp electron tạo liên kết ba N≡N.'],
            ['NaCl là hợp chất có kiểu liên kết:', ['ion', 'cộng hóa trị không cực', 'cộng hóa trị có cực', 'kim loại'], 'Liên kết hóa học', null],
            ['Số oxi hóa của S trong H₂SO₄ là:', ['+6', '+4', '-2', '+2'], 'Phản ứng oxi hóa – khử', null],
            ['Chất khử là chất:', ['nhường electron', 'nhận electron', 'nhận proton', 'nhường proton'], 'Phản ứng oxi hóa – khử', 'Chất khử nhường electron nên số oxi hóa tăng sau phản ứng.'],
            ['Số oxi hóa của Mn trong KMnO₄ là:', ['+7', '+2', '+4', '+6'], 'Phản ứng oxi hóa – khử', null],
            ['Halogen nào là chất lỏng ở điều kiện thường?', ['Brom', 'Clo', 'Flo', 'Iot'], 'Halogen', null],
            ['Nước Gia-ven được dùng chủ yếu để:', ['tẩy trắng và sát trùng', 'khử chua đất trồng', 'làm phân bón', 'bảo quản thực phẩm'], 'Halogen', null],
            ['Trong dãy HF, HCl, HBr, HI, axit yếu nhất là:', ['HF', 'HCl', 'HBr', 'HI'], 'Halogen', 'Tính axit tăng dần từ HF đến HI; HF là axit yếu.'],
            ['Thuốc thử dùng để nhận biết ion Cl⁻ trong dung dịch là:', ['dung dịch AgNO₃', 'quỳ tím', 'phenolphtalein', 'dung dịch BaCl₂'], 'Halogen', 'AgNO₃ tạo kết tủa trắng AgCl không tan trong axit.'],
            ['Ozon có công thức phân tử là:', ['O₃', 'O₂', 'O', 'H₂O₂'], 'Oxi – Lưu huỳnh', null],
            ['H₂S thể hiện tính chất hóa học đặc trưng nào?', ['tính khử mạnh', 'tính oxi hóa mạnh', 'tính trung tính', 'tính lưỡng tính'], 'Oxi – Lưu huỳnh', 'S trong H₂S có số oxi hóa -2 thấp nhất nên chỉ có thể tăng: tính khử.'],
            ['SO₂ làm mất màu dung dịch nào sau đây?', ['nước brom', 'dung dịch NaCl', 'dung dịch CuSO₄', 'dung dịch NaOH'], 'Oxi – Lưu huỳnh', null],
            ['H₂SO₄ đặc, nguội không tác dụng với cặp kim loại nào?', ['Al và Fe', 'Cu và Ag', 'Zn và Mg', 'Na và K'], 'Oxi – Lưu huỳnh', 'Al, Fe bị thụ động hóa trong H₂SO₄ đặc nguội.'],
            ['Yếu tố nào sau đây làm tăng tốc độ phản ứng?', ['tăng nhiệt độ', 'giảm nồng độ chất phản ứng', 'giảm diện tích tiếp xúc', 'giảm áp suất (với chất khí)'], 'Tốc độ phản ứng', null],
            ['Chất xúc tác là chất:', ['làm tăng tốc độ phản ứng nhưng không bị tiêu hao', 'làm giảm tốc độ phản ứng', 'bị tiêu hao hoàn toàn sau phản ứng', 'làm thay đổi chiều phản ứng'], 'Tốc độ phản ứng', null],
            ['Cân bằng hóa học là trạng thái mà:', ['tốc độ phản ứng thuận bằng tốc độ phản ứng nghịch', 'phản ứng dừng hẳn', 'các chất phản ứng hết', 'nồng độ các chất bằng nhau'], 'Tốc độ phản ứng', null],
            ['Ion Na⁺ có cấu hình electron giống khí hiếm nào?', ['Ne', 'Ar', 'He', 'Kr'], 'Nguyên tử', 'Na (Z=11) mất 1 electron còn 10 electron, giống Ne.'],
            ['Số electron tối đa ở lớp thứ hai (lớp L) là:', ['8', '2', '18', '32'], 'Nguyên tử', null],
            ['Trong một nhóm A, theo chiều tăng điện tích hạt nhân, bán kính nguyên tử:', ['tăng dần', 'giảm dần', 'không thay đổi', 'giảm rồi tăng'], 'Bảng tuần hoàn', null],
            ['Công thức oxit cao nhất của clo (nhóm VIIA) là:', ['Cl₂O₇', 'ClO₂', 'Cl₂O', 'Cl₂O₅'], 'Bảng tuần hoàn', null],
            ['Clo ẩm có tính tẩy màu vì tạo ra:', ['HClO', 'HCl', 'O₂', 'Cl₂O'], 'Halogen', 'Cl₂ + H₂O ⇌ HCl + HClO; HClO có tính oxi hóa mạnh.'],
            ['Phản ứng nào sau đây là phản ứng oxi hóa – khử?', ['Fe + CuSO₄ → FeSO₄ + Cu', 'NaOH + HCl → NaCl + H₂O', 'CaCO₃ → CaO + CO₂', 'BaCl₂ + Na₂SO₄ → BaSO₄ + 2NaCl'], 'Phản ứng oxi hóa – khử', null],
            ['Lưu huỳnh có số oxi hóa -2 trong hợp chất nào?', ['H₂S', 'SO₂', 'SO₃', 'H₂SO₄'], 'Oxi – Lưu huỳnh', null],
        ];
    }
}
