/* HTD_BALANCE_LEVELS — dữ liệu «⚖️ Cân bằng phương trình»
 *
 * 3 cấp độ × 8 màn, bám chương trình Hóa 10–12 (GDPT 2018):
 * - t1 «Khởi động»: phản ứng hóa hợp / thế cơ bản
 * - t2 «Thử thách»: trao đổi, phân hủy, đốt cháy hữu cơ đơn giản
 * - t3 «Cao thủ»: oxi hóa – khử hệ số lớn (trọng tâm Hóa 10)
 *
 * Mỗi màn: lhs/rhs = công thức chất (parser hỗ trợ ngoặc đơn),
 * ans = hệ số tối giản (lhs nối rhs) — dùng cho nút gợi ý,
 * cond = điều kiện ghi trên mũi tên, max = hệ số tối đa của stepper.
 */
window.HTD_BALANCE_LEVELS = [
  {
    id: 't1',
    name: 'Khởi động',
    emoji: '🌱',
    desc: 'Làm quen: đếm nguyên tử hai vế rồi chỉnh hệ số',
    levels: [
      { id: 't1-1', title: 'Tạo ra nước',        emoji: '💧', lhs: ['H2', 'O2'],   rhs: ['H2O'],            ans: [2, 1, 2],    cond: 't°' },
      { id: 't1-2', title: 'Muối ăn ra đời',     emoji: '🧂', lhs: ['Na', 'Cl2'],  rhs: ['NaCl'],           ans: [2, 1, 2],    cond: 't°' },
      { id: 't1-3', title: 'Pháo sáng magie',    emoji: '✨', lhs: ['Mg', 'O2'],   rhs: ['MgO'],            ans: [2, 1, 2],    cond: 't°' },
      { id: 't1-4', title: 'Đốt cháy nhôm',      emoji: '🔥', lhs: ['Al', 'O2'],   rhs: ['Al2O3'],          ans: [4, 3, 2],    cond: 't°' },
      { id: 't1-5', title: 'Photpho bốc cháy',   emoji: '💥', lhs: ['P', 'O2'],    rhs: ['P2O5'],           ans: [4, 5, 2],    cond: 't°' },
      { id: 't1-6', title: 'Sắt cháy trong oxi', emoji: '🧲', lhs: ['Fe', 'O2'],   rhs: ['Fe3O4'],          ans: [3, 2, 1],    cond: 't°' },
      { id: 't1-7', title: 'Kẽm gặp axit',       emoji: '🫧', lhs: ['Zn', 'HCl'],  rhs: ['ZnCl2', 'H2'],    ans: [1, 2, 1, 1] },
      { id: 't1-8', title: 'Điện phân nước',     emoji: '⚡', lhs: ['H2O'],        rhs: ['H2', 'O2'],       ans: [2, 2, 1],    cond: 'điện phân' },
    ],
  },
  {
    id: 't2',
    name: 'Thử thách',
    emoji: '🚀',
    desc: 'Phản ứng trao đổi, phân hủy và đốt cháy hữu cơ',
    levels: [
      { id: 't2-1', title: 'Nhôm tan trong axit',  emoji: '🧪', lhs: ['Al', 'HCl'],          rhs: ['AlCl3', 'H2'],           ans: [2, 6, 2, 3] },
      { id: 't2-2', title: 'Luyện gang',           emoji: '🏭', lhs: ['Fe2O3', 'CO'],        rhs: ['Fe', 'CO2'],             ans: [1, 3, 2, 3],  cond: 't°' },
      { id: 't2-3', title: 'Kết tủa nâu đỏ',       emoji: '🟠', lhs: ['NaOH', 'FeCl3'],      rhs: ['Fe(OH)3', 'NaCl'],       ans: [3, 1, 1, 3] },
      { id: 't2-4', title: 'Nhôm + axit sunfuric', emoji: '⚗️', lhs: ['Al', 'H2SO4'],        rhs: ['Al2(SO4)3', 'H2'],       ans: [2, 3, 1, 3] },
      { id: 't2-5', title: 'Phân lân',             emoji: '🌾', lhs: ['Ca(OH)2', 'H3PO4'],   rhs: ['Ca3(PO4)2', 'H2O'],      ans: [3, 2, 1, 6] },
      { id: 't2-6', title: 'Điều chế oxi',         emoji: '🎈', lhs: ['KClO3'],              rhs: ['KCl', 'O2'],             ans: [2, 2, 3],     cond: 't°, MnO₂' },
      { id: 't2-7', title: 'Đốt khí ethylene',     emoji: '🕯️', lhs: ['C2H4', 'O2'],         rhs: ['CO2', 'H2O'],            ans: [1, 3, 2, 2],  cond: 't°' },
      { id: 't2-8', title: 'Đốt ammonia',          emoji: '💨', lhs: ['NH3', 'O2'],          rhs: ['NO', 'H2O'],             ans: [4, 5, 4, 6],  cond: 't°, Pt' },
    ],
  },
  {
    id: 't3',
    name: 'Cao thủ',
    emoji: '👑',
    desc: 'Oxi hóa – khử hệ số lớn — thử thách dân chuyên Hóa',
    levels: [
      { id: 't3-1', title: 'Đốt gas propane',   emoji: '🍳', lhs: ['C3H8', 'O2'],     rhs: ['CO2', 'H2O'],                          ans: [1, 5, 3, 4],        cond: 't°' },
      { id: 't3-2', title: 'Đốt quặng pyrite',  emoji: '⛏️', lhs: ['FeS2', 'O2'],     rhs: ['Fe2O3', 'SO2'],                        ans: [4, 11, 2, 8],       cond: 't°' },
      { id: 't3-3', title: 'Phản ứng nhiệt nhôm', emoji: '🌋', lhs: ['Al', 'Fe3O4'],  rhs: ['Al2O3', 'Fe'],                         ans: [8, 3, 4, 9],        cond: 't°' },
      { id: 't3-4', title: 'Đồng + HNO₃ đặc',   emoji: '🟤', lhs: ['Cu', 'HNO3'],     rhs: ['Cu(NO3)2', 'NO2', 'H2O'],              ans: [1, 4, 1, 2, 2],     cond: 'đặc' },
      { id: 't3-5', title: 'Đồng + HNO₃ loãng', emoji: '🥉', lhs: ['Cu', 'HNO3'],     rhs: ['Cu(NO3)2', 'NO', 'H2O'],               ans: [3, 8, 3, 2, 4],     cond: 'loãng' },
      { id: 't3-6', title: 'Điều chế clo',      emoji: '🟢', lhs: ['KMnO4', 'HCl'],   rhs: ['KCl', 'MnCl2', 'Cl2', 'H2O'],          ans: [2, 16, 2, 2, 5, 8], cond: 'đặc', max: 18 },
      { id: 't3-7', title: 'Sắt + H₂SO₄ đặc',   emoji: '🛡️', lhs: ['Fe', 'H2SO4'],    rhs: ['Fe2(SO4)3', 'SO2', 'H2O'],             ans: [2, 6, 1, 3, 6],     cond: 'đặc, t°' },
      { id: 't3-8', title: 'Lên men rượu',      emoji: '🍇', lhs: ['C6H12O6'],        rhs: ['C2H5OH', 'CO2'],                       ans: [1, 2, 2],           cond: 'men, 30–35°C' },
    ],
  },
];
