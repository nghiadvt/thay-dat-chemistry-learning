<?php

namespace Database\Seeders\Data;

class Grade12Questions
{
    use BuildsQuestions;

    /**
     * 100 câu hỏi Hóa 12: 40 trắc nghiệm lý thuyết + 10 polime/monome
     * + 10 nhận biết & dãy điện hóa + 15 khối lượng mol + 15 công thức + 10 cân bằng PT.
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

        $polymers = [
            ['etilen', 'polietilen (PE)'], ['propilen', 'polipropilen (PP)'], ['stiren', 'polistiren (PS)'],
            ['vinyl clorua', 'poli(vinyl clorua) (PVC)'], ['tetrafloetilen', 'teflon'],
            ['metyl metacrylat', 'thủy tinh hữu cơ (PMMA)'], ['acrilonitrin', 'tơ nitron (olon)'],
            ['caprolactam', 'nilon-6 (tơ capron)'], ['isopren', 'cao su isopren'], ['buta-1,3-đien', 'cao su buna'],
        ];
        $polymerNames = array_column($polymers, 1);
        foreach ($polymers as $k => [$monomer, $polymer]) {
            $distractors = array_values(array_diff($polymerNames, [$polymer]));
            $picked = [$distractors[$k % count($distractors)], $distractors[($k + 3) % count($distractors)], $distractors[($k + 6) % count($distractors)]];
            $questions[] = self::mc(
                'Trùng hợp (hoặc trùng ngưng) <strong>'.$monomer.'</strong> thu được polime nào?',
                array_merge([$polymer], array_unique($picked)),
                $i++,
                'Polime'
            );
        }

        foreach (self::recognition() as [$content, $options, $topic, $explanation]) {
            $questions[] = self::mc($content, $options, $i++, $topic, $explanation);
        }

        $masses = [
            ['CH₃COOC₂H₅', 88], ['HCOOCH₃', 60], ['C₁₂H₂₂O₁₁', 342], ['CH₃NH₂', 31], ['C₆H₅NH₂', 93],
            ['H₂NCH₂COOH', 75], ['CH₃CH(NH₂)COOH', 89], ['Fe₃O₄', 232], ['Al₂O₃', 102], ['BaSO₄', 233],
            ['FeSO₄', 152], ['Al(OH)₃', 78], ['NaHCO₃', 84], ['KOH', 56], ['CuO', 80],
        ];
        foreach ($masses as [$formula, $mass]) {
            $questions[] = self::molarMass($formula, (float) $mass, $i++, 'Khối lượng mol');
        }

        $formulas = [
            ['etyl axetat', 'CH3COOC2H5'], ['metyl fomat', 'HCOOCH3'], ['glucozơ', 'C6H12O6'],
            ['saccarozơ', 'C12H22O11'], ['metylamin', 'CH3NH2'], ['anilin', 'C6H5NH2'],
            ['glyxin', 'H2NCH2COOH'], ['alanin', 'CH3CH(NH2)COOH'],
            ['thành phần chính của quặng hematit', 'Fe2O3'], ['thành phần chính của quặng manhetit', 'Fe3O4'],
            ['thành phần chính của đá vôi', 'CaCO3'], ['xút ăn da', 'NaOH'], ['vôi tôi', 'Ca(OH)2'],
            ['criolit', 'Na3AlF6'], ['thạch cao khan', 'CaSO4'],
        ];
        foreach ($formulas as [$name, $formula]) {
            $questions[] = self::essay('Viết công thức của <strong>'.$name.'</strong>.', $formula, 'Công thức hóa học');
        }

        $equations = [
            ['Fe₂O₃ + CO → Fe + CO₂', 'Fe2O3 + 3CO -> 2Fe + 3CO2'],
            ['Al + Fe₂O₃ → Al₂O₃ + Fe', '2Al + Fe2O3 -> Al2O3 + 2Fe'],
            ['Fe + HCl → FeCl₂ + H₂', 'Fe + 2HCl -> FeCl2 + H2'],
            ['Fe + Cl₂ → FeCl₃ (nhiệt độ)', '2Fe + 3Cl2 -> 2FeCl3'],
            ['Na + H₂O → NaOH + H₂', '2Na + 2H2O -> 2NaOH + H2'],
            ['Al + O₂ → Al₂O₃', '4Al + 3O2 -> 2Al2O3'],
            ['Al + NaOH + H₂O → NaAlO₂ + H₂', '2Al + 2NaOH + 2H2O -> 2NaAlO2 + 3H2'],
            ['Cu + AgNO₃ → Cu(NO₃)₂ + Ag', 'Cu + 2AgNO3 -> Cu(NO3)2 + 2Ag'],
            ['Fe(OH)₃ → Fe₂O₃ + H₂O (nung)', '2Fe(OH)3 -> Fe2O3 + 3H2O'],
            ['Mg + FeCl₃ → MgCl₂ + FeCl₂', 'Mg + 2FeCl3 -> MgCl2 + 2FeCl2'],
        ];
        foreach ($equations as [$raw, $balanced]) {
            $questions[] = self::essay('Cân bằng phương trình hóa học sau: <strong>'.$raw.'</strong>', $balanced, 'Phương trình hóa học');
        }

        return $questions;
    }

    /**
     * @return list<array{0: string, 1: list<string>, 2: string, 3: string|null}>
     */
    private static function theory(): array
    {
        return [
            ['Este của axit axetic và ancol etylic có công thức là:', ['CH₃COOC₂H₅', 'HCOOCH₃', 'CH₃COOCH₃', 'C₂H₅COOCH₃'], 'Este – Lipit', null],
            ['Thủy phân este trong môi trường kiềm được gọi là phản ứng:', ['xà phòng hóa', 'este hóa', 'hiđro hóa', 'trùng ngưng'], 'Este – Lipit', null],
            ['Chất béo là trieste của glixerol với:', ['axit béo', 'axit vô cơ', 'ancol đơn chức', 'phenol'], 'Este – Lipit', null],
            ['Este thường có mùi đặc trưng nào?', ['thơm hoa quả', 'khai', 'trứng thối', 'hắc'], 'Este – Lipit', null],
            ['Metyl fomat có công thức là:', ['HCOOCH₃', 'CH₃COOCH₃', 'HCOOC₂H₅', 'CH₃COOH'], 'Este – Lipit', null],
            ['Phản ứng este hóa giữa axit và ancol là phản ứng:', ['thuận nghịch', 'một chiều', 'trùng hợp', 'phân hủy'], 'Este – Lipit', null],
            ['Chất béo lỏng chứa chủ yếu các gốc axit béo:', ['không no', 'no', 'thơm', 'tự do'], 'Este – Lipit', null],
            ['Xà phòng là muối natri hoặc kali của:', ['axit béo', 'axit vô cơ', 'phenol', 'glixerol'], 'Este – Lipit', null],
            ['Glucozơ có công thức phân tử là:', ['C₆H₁₂O₆', 'C₁₂H₂₂O₁₁', '(C₆H₁₀O₅)ₙ', 'C₂H₄O₂'], 'Cacbohiđrat', null],
            ['Chất nào sau đây tham gia phản ứng tráng bạc?', ['glucozơ', 'saccarozơ', 'tinh bột', 'xenlulozơ'], 'Cacbohiđrat', 'Glucozơ có nhóm -CHO nên tráng bạc được.'],
            ['Thủy phân hoàn toàn saccarozơ thu được:', ['glucozơ và fructozơ', 'chỉ glucozơ', 'chỉ fructozơ', 'hai phân tử glucozơ'], 'Cacbohiđrat', null],
            ['Chất nào tạo màu xanh tím với dung dịch iot?', ['hồ tinh bột', 'xenlulozơ', 'glucozơ', 'saccarozơ'], 'Cacbohiđrat', null],
            ['Xenlulozơ là thành phần chính của:', ['sợi bông', 'gạo', 'thịt', 'sữa'], 'Cacbohiđrat', null],
            ['Lên men glucozơ thu được sản phẩm chính là:', ['ancol etylic và CO₂', 'axit axetic', 'metan', 'glixerol'], 'Cacbohiđrat', null],
            ['Metylamin có công thức là:', ['CH₃NH₂', 'C₂H₅NH₂', 'C₆H₅NH₂', '(CH₃)₂NH'], 'Amin – Amino axit', null],
            ['Anilin có công thức là:', ['C₆H₅NH₂', 'CH₃NH₂', 'C₂H₅NH₂', '(CH₃)₃N'], 'Amin – Amino axit', null],
            ['Amino axit là hợp chất có tính:', ['lưỡng tính', 'axit', 'bazơ', 'trung tính'], 'Amin – Amino axit', 'Phân tử chứa đồng thời nhóm -NH₂ (bazơ) và -COOH (axit).'],
            ['Glyxin có công thức là:', ['H₂NCH₂COOH', 'CH₃CH(NH₂)COOH', 'C₆H₅NH₂', 'H₂N(CH₂)₂COOH'], 'Amin – Amino axit', null],
            ['Liên kết peptit là liên kết:', ['-CO-NH-', '-COO-', '-O-', '-NH₂'], 'Amin – Amino axit', null],
            ['Protein bị đông tụ khi:', ['đun nóng', 'làm lạnh', 'pha loãng với nước', 'khuấy đều'], 'Amin – Amino axit', null],
            ['Dung dịch metylamin làm quỳ tím:', ['hóa xanh', 'hóa đỏ', 'không đổi màu', 'mất màu'], 'Amin – Amino axit', null],
            ['Phản ứng màu biure dùng để nhận biết:', ['protein (peptit có từ 2 liên kết peptit)', 'amin', 'este', 'glucozơ'], 'Amin – Amino axit', null],
            ['PVC được điều chế từ monome nào?', ['vinyl clorua', 'etilen', 'propilen', 'stiren'], 'Polime', null],
            ['Tơ nào sau đây là tơ thiên nhiên?', ['tơ tằm', 'nilon-6', 'nilon-6,6', 'tơ nitron'], 'Polime', null],
            ['Cao su buna được điều chế bằng cách trùng hợp:', ['buta-1,3-đien', 'isopren', 'etilen', 'stiren'], 'Polime', null],
            ['Nilon-6,6 được điều chế bằng phản ứng:', ['trùng ngưng', 'trùng hợp', 'thế', 'cộng'], 'Polime', null],
            ['Tính chất vật lí chung của kim loại (dẻo, dẫn điện, dẫn nhiệt, ánh kim) gây ra bởi:', ['các electron tự do', 'các ion dương', 'các proton', 'các nơtron'], 'Đại cương kim loại', null],
            ['Kim loại dẫn điện tốt nhất là:', ['Ag', 'Cu', 'Al', 'Fe'], 'Đại cương kim loại', null],
            ['Nguyên tắc chung để điều chế kim loại là:', ['khử ion kim loại thành nguyên tử', 'oxi hóa ion kim loại', 'điện li muối', 'nhiệt phân muối'], 'Đại cương kim loại', null],
            ['Ăn mòn điện hóa xảy ra khi có:', ['hai điện cực khác bản chất tiếp xúc và cùng trong dung dịch điện li', 'nhiệt độ cao', 'ánh sáng mạnh', 'áp suất lớn'], 'Đại cương kim loại', null],
            ['Kim loại nhẹ nhất là:', ['Li', 'Na', 'Al', 'Fe'], 'Đại cương kim loại', null],
            ['Kim loại kiềm được điều chế bằng phương pháp:', ['điện phân nóng chảy', 'nhiệt luyện', 'thủy luyện', 'điện phân dung dịch'], 'Kim loại kiềm – kiềm thổ – Nhôm', null],
            ['Nước cứng là nước chứa nhiều ion:', ['Ca²⁺ và Mg²⁺', 'Na⁺ và K⁺', 'Fe²⁺ và Cu²⁺', 'Cl⁻ và SO₄²⁻'], 'Kim loại kiềm – kiềm thổ – Nhôm', null],
            ['Al(OH)₃ là hiđroxit có tính:', ['lưỡng tính', 'bazơ mạnh', 'axit mạnh', 'trung tính'], 'Kim loại kiềm – kiềm thổ – Nhôm', null],
            ['Phèn chua chứa những kim loại nào?', ['Al và K', 'Fe và Na', 'Cu và K', 'Zn và Na'], 'Kim loại kiềm – kiềm thổ – Nhôm', 'Phèn chua: K₂SO₄.Al₂(SO₄)₃.24H₂O.'],
            ['Kim loại kiềm được bảo quản bằng cách ngâm trong:', ['dầu hỏa', 'nước', 'cồn', 'không khí khô'], 'Kim loại kiềm – kiềm thổ – Nhôm', null],
            ['Sắt có các số oxi hóa phổ biến là:', ['+2 và +3', '+1 và +2', '+3 và +6', '+2 và +4'], 'Sắt – Crom', null],
            ['Quặng hematit chứa hợp chất nào của sắt?', ['Fe₂O₃', 'Fe₃O₄', 'FeS₂', 'FeCO₃'], 'Sắt – Crom', null],
            ['Gang là hợp kim của sắt với cacbon, trong đó %C khoảng:', ['2 – 5%', 'dưới 2%', '10 – 15%', '0%'], 'Sắt – Crom', null],
            ['Đốt sắt trong khí clo thu được:', ['FeCl₃', 'FeCl₂', 'hỗn hợp FeCl₂ và Fe', 'Fe(ClO)₂'], 'Sắt – Crom', 'Cl₂ là chất oxi hóa mạnh nên oxi hóa Fe lên số oxi hóa +3.'],
        ];
    }

    /**
     * @return list<array{0: string, 1: list<string>, 2: string, 3: string|null}>
     */
    private static function recognition(): array
    {
        return [
            ['Kim loại nào sau đây không tác dụng với dung dịch HCl?', ['Cu', 'Fe', 'Zn', 'Mg'], 'Đại cương kim loại', 'Cu đứng sau H trong dãy điện hóa nên không khử được H⁺.'],
            ['Kim loại nào tác dụng với nước ở nhiệt độ thường?', ['Na', 'Fe', 'Cu', 'Al'], 'Đại cương kim loại', null],
            ['Ion kim loại nào có tính oxi hóa mạnh nhất trong các ion sau?', ['Ag⁺', 'Na⁺', 'Mg²⁺', 'Al³⁺'], 'Đại cương kim loại', null],
            ['Kim loại nào đẩy được Cu ra khỏi dung dịch CuSO₄?', ['Fe', 'Ag', 'Au', 'Pt'], 'Đại cương kim loại', null],
            ['Cặp chất nào sau đây xảy ra phản ứng?', ['Fe + CuSO₄', 'Cu + FeSO₄', 'Ag + CuSO₄', 'Cu + HCl loãng'], 'Đại cương kim loại', null],
            ['Để phân biệt dung dịch glucozơ và saccarozơ có thể dùng:', ['dung dịch AgNO₃/NH₃ đun nóng', 'quỳ tím', 'dung dịch NaOH', 'dung dịch BaCl₂'], 'Nhận biết chất', null],
            ['Thuốc thử để phân biệt anilin và ancol etylic là:', ['nước brom', 'quỳ tím', 'kim loại Na', 'dung dịch NaOH'], 'Nhận biết chất', 'Anilin tạo kết tủa trắng 2,4,6-tribromanilin với nước brom.'],
            ['Để nhận biết ion SO₄²⁻ trong dung dịch, dùng thuốc thử nào?', ['dung dịch BaCl₂', 'dung dịch AgNO₃', 'dung dịch NaOH', 'dung dịch HCl'], 'Nhận biết chất', 'Tạo kết tủa trắng BaSO₄ không tan trong axit.'],
            ['Kim loại cứng nhất là:', ['Cr', 'Fe', 'W', 'Cu'], 'Đại cương kim loại', null],
            ['Kim loại có nhiệt độ nóng chảy cao nhất là:', ['W', 'Fe', 'Cr', 'Hg'], 'Đại cương kim loại', null],
        ];
    }
}
