<?php

namespace Database\Seeders\Data;

class Grade11Questions
{
    use BuildsQuestions;

    /**
     * 100 câu hỏi Hóa 11: 40 trắc nghiệm lý thuyết + 10 gọi tên ankan
     * + 10 đếm đồng phân + 15 khối lượng mol + 15 công thức phân tử + 10 cân bằng PT.
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

        $alkanes = [
            ['CH₄', 'Metan'], ['C₂H₆', 'Etan'], ['C₃H₈', 'Propan'], ['C₄H₁₀', 'Butan'],
            ['C₅H₁₂', 'Pentan'], ['C₆H₁₄', 'Hexan'], ['C₇H₁₆', 'Heptan'], ['C₈H₁₈', 'Octan'],
            ['C₉H₂₀', 'Nonan'], ['C₁₀H₂₂', 'Đecan'],
        ];
        $alkaneNames = array_column($alkanes, 1);
        foreach ($alkanes as $k => [$formula, $name]) {
            $distractors = array_values(array_diff($alkaneNames, [$name]));
            $picked = [$distractors[$k % count($distractors)], $distractors[($k + 3) % count($distractors)], $distractors[($k + 6) % count($distractors)]];
            $questions[] = self::mc(
                'Ankan có công thức phân tử <strong>'.$formula.'</strong> (mạch không nhánh) có tên gọi là:',
                array_merge([$name], array_unique($picked)),
                $i++,
                'Hiđrocacbon'
            );
        }

        $isomers = [
            ['C₄H₁₀', 2], ['C₅H₁₂', 3], ['C₆H₁₄', 5], ['C₃H₇Cl', 2], ['C₄H₉Cl', 4],
            ['C₃H₈O', 3], ['C₄H₁₀O (chỉ tính ancol)', 4], ['C₄H₈ (anken, mạch hở)', 3], ['C₂H₆O', 2], ['C₂H₄Cl₂', 2],
        ];
        foreach ($isomers as [$formula, $count]) {
            $questions[] = self::mc(
                'Số đồng phân cấu tạo của <strong>'.$formula.'</strong> là:',
                [(string) $count, (string) ($count + 1), (string) max(1, $count - 1), (string) ($count + 2)],
                $i++,
                'Đồng phân – Đồng đẳng'
            );
        }

        $masses = [
            ['CH₄', 16], ['C₂H₄', 28], ['C₂H₂', 26], ['C₂H₅OH', 46], ['CH₃COOH', 60],
            ['C₆H₆', 78], ['C₆H₁₂O₆', 180], ['CH₃CHO', 44], ['C₃H₈', 44], ['C₄H₁₀', 58],
            ['CH₃OH', 32], ['C₂H₆', 30], ['C₆H₅OH', 94], ['HCHO', 30], ['HCOOH', 46],
        ];
        foreach ($masses as [$formula, $mass]) {
            $questions[] = self::molarMass($formula, (float) $mass, $i++, 'Khối lượng mol');
        }

        $formulas = [
            ['metan', 'CH4'], ['etan', 'C2H6'], ['etilen', 'C2H4'], ['axetilen', 'C2H2'],
            ['benzen', 'C6H6'], ['ancol etylic', 'C2H5OH'], ['axit axetic', 'CH3COOH'],
            ['anđehit axetic', 'CH3CHO'], ['glixerol', 'C3H5(OH)3'], ['metanol', 'CH3OH'],
            ['propan', 'C3H8'], ['butan', 'C4H10'], ['toluen', 'C6H5CH3'],
            ['phenol', 'C6H5OH'], ['anđehit fomic', 'HCHO'],
        ];
        foreach ($formulas as [$name, $formula]) {
            $questions[] = self::essay('Viết công thức của <strong>'.$name.'</strong>.', $formula, 'Công thức hóa học');
        }

        $equations = [
            ['N₂ + H₂ → NH₃', 'N2 + 3H2 -> 2NH3'],
            ['NH₃ + O₂ → NO + H₂O', '4NH3 + 5O2 -> 4NO + 6H2O'],
            ['CH₄ + O₂ → CO₂ + H₂O', 'CH4 + 2O2 -> CO2 + 2H2O'],
            ['C₂H₅OH + O₂ → CO₂ + H₂O', 'C2H5OH + 3O2 -> 2CO2 + 3H2O'],
            ['C₂H₂ + O₂ → CO₂ + H₂O', '2C2H2 + 5O2 -> 4CO2 + 2H2O'],
            ['Cu + HNO₃ loãng → Cu(NO₃)₂ + NO + H₂O', '3Cu + 8HNO3 -> 3Cu(NO3)2 + 2NO + 4H2O'],
            ['CaCO₃ + HCl → CaCl₂ + CO₂ + H₂O', 'CaCO3 + 2HCl -> CaCl2 + CO2 + H2O'],
            ['CO₂ + NaOH → Na₂CO₃ + H₂O', 'CO2 + 2NaOH -> Na2CO3 + H2O'],
            ['P + O₂ → P₂O₅', '4P + 5O2 -> 2P2O5'],
            ['CO + O₂ → CO₂', '2CO + O2 -> 2CO2'],
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
            ['Chất nào sau đây là chất điện li mạnh?', ['NaCl', 'CH₃COOH', 'H₂O', 'Mg(OH)₂'], 'Sự điện li', null],
            ['Dung dịch nào sau đây có pH = 7?', ['NaCl', 'HCl', 'NaOH', 'CH₃COOH'], 'Sự điện li', 'NaCl là muối của axit mạnh và bazơ mạnh nên không bị thủy phân.'],
            ['Dung dịch HCl 0,01M có pH bằng:', ['2', '1', '12', '7'], 'Sự điện li', 'pH = -log[H⁺] = -log(10⁻²) = 2.'],
            ['Theo thuyết A-rê-ni-ut, chất nào sau đây là axit?', ['HNO₃', 'NaOH', 'NaCl', 'C₂H₅OH'], 'Sự điện li', null],
            ['Phản ứng trao đổi ion trong dung dịch xảy ra khi sản phẩm có:', ['chất kết tủa, chất khí hoặc chất điện li yếu', 'muối tan', 'ion mới', 'nước bay hơi'], 'Sự điện li', null],
            ['Dung dịch NaOH 0,001M có pH bằng:', ['11', '3', '7', '13'], 'Sự điện li', '[OH⁻] = 10⁻³ → pOH = 3 → pH = 14 - 3 = 11.'],
            ['Muối nào sau đây khi thủy phân tạo môi trường bazơ?', ['Na₂CO₃', 'NaCl', 'NH₄Cl', 'KNO₃'], 'Sự điện li', null],
            ['Hiđroxit nào sau đây có tính lưỡng tính?', ['Al(OH)₃', 'NaOH', 'Ba(OH)₂', 'KOH'], 'Sự điện li', null],
            ['Nitơ khá trơ về mặt hóa học ở nhiệt độ thường vì:', ['phân tử có liên kết ba rất bền', 'phân tử nhẹ', 'nitơ không màu', 'nitơ ít tan trong nước'], 'Nitơ – Photpho', null],
            ['HNO₃ đặc, nguội làm thụ động hóa cặp kim loại nào?', ['Al và Fe', 'Cu và Ag', 'Zn và Mg', 'Na và K'], 'Nitơ – Photpho', null],
            ['Phân đạm cung cấp cho cây trồng nguyên tố dinh dưỡng nào?', ['N', 'P', 'K', 'Ca'], 'Nitơ – Photpho', null],
            ['NH₃ thể hiện tính chất nào trong dung dịch nước?', ['bazơ yếu', 'axit mạnh', 'trung tính', 'oxi hóa mạnh'], 'Nitơ – Photpho', null],
            ['Muối amoni tác dụng với dung dịch kiềm đun nóng sinh ra khí:', ['NH₃', 'N₂', 'NO₂', 'H₂'], 'Nitơ – Photpho', null],
            ['Photpho trắng được bảo quản bằng cách ngâm trong:', ['nước', 'dầu hỏa', 'cồn', 'axit sunfuric'], 'Nitơ – Photpho', 'Photpho trắng rất độc, dễ bốc cháy trong không khí nên phải ngâm trong nước.'],
            ['Độ dinh dưỡng của phân lân được đánh giá theo hàm lượng:', ['%P₂O₅', '%P', '%K₂O', '%N'], 'Nitơ – Photpho', null],
            ['Cu tác dụng với HNO₃ loãng sinh ra khí nào?', ['NO', 'NO₂', 'H₂', 'N₂'], 'Nitơ – Photpho', null],
            ['CO thể hiện tính chất hóa học đặc trưng nào?', ['tính khử', 'tính oxi hóa mạnh', 'tính axit', 'tính bazơ'], 'Cacbon – Silic', null],
            ['Khí nào là nguyên nhân chính gây hiệu ứng nhà kính?', ['CO₂', 'N₂', 'O₂', 'H₂'], 'Cacbon – Silic', null],
            ['NaHCO₃ được dùng làm:', ['thuốc muối (baking soda)', 'phân bón', 'thuốc trừ sâu', 'chất tẩy rửa mạnh'], 'Cacbon – Silic', null],
            ['Silic là nguyên tố phổ biến thứ mấy trong vỏ Trái Đất?', ['2', '1', '3', '5'], 'Cacbon – Silic', 'Sau oxi, silic là nguyên tố phổ biến thứ hai trong vỏ Trái Đất.'],
            ['Nguyên tố bắt buộc phải có trong hợp chất hữu cơ là:', ['cacbon', 'oxi', 'hiđro', 'nitơ'], 'Đại cương hữu cơ', null],
            ['Đồng phân là những chất có cùng:', ['công thức phân tử nhưng khác công thức cấu tạo', 'công thức cấu tạo', 'tính chất hóa học', 'khối lượng riêng'], 'Đồng phân – Đồng đẳng', null],
            ['Các chất đồng đẳng hơn kém nhau một hay nhiều nhóm:', ['CH₂', 'CH₃', 'OH', 'CH'], 'Đồng phân – Đồng đẳng', null],
            ['Liên kết hóa học chủ yếu trong hợp chất hữu cơ là liên kết:', ['cộng hóa trị', 'ion', 'kim loại', 'hiđro'], 'Đại cương hữu cơ', null],
            ['Công thức chung của dãy đồng đẳng ankan là:', ['CₙH₂ₙ₊₂ (n≥1)', 'CₙH₂ₙ (n≥2)', 'CₙH₂ₙ₋₂ (n≥2)', 'CₙH₂ₙ₋₆ (n≥6)'], 'Hiđrocacbon', null],
            ['Công thức chung của dãy đồng đẳng anken là:', ['CₙH₂ₙ (n≥2)', 'CₙH₂ₙ₊₂ (n≥1)', 'CₙH₂ₙ₋₂ (n≥2)', 'CₙH₂ₙ₋₆ (n≥6)'], 'Hiđrocacbon', null],
            ['Phản ứng đặc trưng của metan là:', ['phản ứng thế với clo (ánh sáng)', 'phản ứng cộng brom', 'phản ứng trùng hợp', 'phản ứng thủy phân'], 'Hiđrocacbon', null],
            ['Chất nào sau đây làm mất màu dung dịch brom?', ['etilen', 'metan', 'etan', 'benzen'], 'Hiđrocacbon', 'Etilen có liên kết đôi C=C nên cộng được với Br₂.'],
            ['Axetilen có công thức phân tử là:', ['C₂H₂', 'C₂H₄', 'C₂H₆', 'C₆H₆'], 'Hiđrocacbon', null],
            ['Trùng hợp etilen thu được polime nào?', ['polietilen (PE)', 'poli(vinyl clorua) (PVC)', 'polipropilen (PP)', 'polistiren (PS)'], 'Hiđrocacbon', null],
            ['Benzen có công thức phân tử là:', ['C₆H₆', 'C₆H₁₂', 'C₆H₁₄', 'C₂H₆'], 'Hiđrocacbon', null],
            ['Khí nào được dùng trong đèn xì để hàn, cắt kim loại?', ['axetilen', 'metan', 'etan', 'hiđro'], 'Hiđrocacbon', null],
            ['Ancol etylic có công thức là:', ['C₂H₅OH', 'CH₃OH', 'C₃H₇OH', 'C₆H₅OH'], 'Dẫn xuất hữu cơ', null],
            ['Ancol tác dụng với Na sinh ra khí nào?', ['H₂', 'O₂', 'CO₂', 'CH₄'], 'Dẫn xuất hữu cơ', null],
            ['Phenol làm quỳ tím:', ['không đổi màu', 'hóa đỏ', 'hóa xanh', 'mất màu'], 'Dẫn xuất hữu cơ', 'Phenol có tính axit rất yếu, không đủ làm đổi màu quỳ tím.'],
            ['Anđehit fomic có công thức là:', ['HCHO', 'CH₃CHO', 'HCOOH', 'CH₃OH'], 'Dẫn xuất hữu cơ', null],
            ['Phản ứng tráng bạc dùng để nhận biết nhóm chức nào?', ['anđehit (-CHO)', 'ancol (-OH)', 'axit (-COOH)', 'ete (-O-)'], 'Dẫn xuất hữu cơ', null],
            ['Axit axetic có nhiều trong:', ['giấm ăn', 'nước chanh', 'sữa tươi', 'nước biển'], 'Dẫn xuất hữu cơ', null],
            ['Oxi hóa không hoàn toàn ancol etylic bằng CuO (t°) thu được:', ['CH₃CHO', 'CH₃COOH', 'C₂H₄', 'CO₂'], 'Dẫn xuất hữu cơ', null],
            ['Glixerol có bao nhiêu nhóm -OH trong phân tử?', ['3', '1', '2', '4'], 'Dẫn xuất hữu cơ', null],
        ];
    }
}
