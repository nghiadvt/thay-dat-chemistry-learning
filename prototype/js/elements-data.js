/* HTD_ELEMENTS — fake data module «Đọc nguyên tố»
 * 45 nguyên tố: Z 1–36 đầy đủ + các nguyên tố quen thuộc (Sr, Ag, Sn, I, Ba, Pt, Au, Hg, Pb).
 *
 * Field:
 *   z        — số hiệu nguyên tử
 *   symbol   — ký hiệu hóa học
 *   nameVi   — tên tiếng Việt (SGK cũ / tên quen gọi)
 *   nameEn   — tên IUPAC tiếng Anh (chương trình mới đọc theo tên này)
 *   mass     — nguyên tử khối (làm tròn)
 *   category — nhóm màu: kiem | kiem-tho | chuyen-tiep | kim-loai | a-kim | phi-kim | halogen | khi-hiem
 *   phonetic — cách đọc tiếng Việt cho TTS khi máy không có giọng vi
 *   group    — nhóm (cột 1–18), đặt ô trong bảng tuần hoàn
 *   period   — chu kỳ (hàng 1–6)
 *   priority — 1 = trọng tâm (học trước), 2 = mở rộng
 */
window.HTD_ELEMENTS = [
  { z: 1,  symbol: 'H',  nameVi: 'Hiđro',      nameEn: 'Hydrogen',   mass: 1.008,  category: 'phi-kim',     phonetic: 'Hi-đrô',     group: 1,  period: 1, priority: 1 },
  { z: 2,  symbol: 'He', nameVi: 'Heli',       nameEn: 'Helium',     mass: 4.003,  category: 'khi-hiem',    phonetic: 'Hê-li',      group: 18, period: 1, priority: 1 },
  { z: 3,  symbol: 'Li', nameVi: 'Liti',       nameEn: 'Lithium',    mass: 6.94,   category: 'kiem',        phonetic: 'Li-ti',      group: 1,  period: 2, priority: 1 },
  { z: 4,  symbol: 'Be', nameVi: 'Beri',       nameEn: 'Beryllium',  mass: 9.012,  category: 'kiem-tho',    phonetic: 'Bê-ri',      group: 2,  period: 2, priority: 1 },
  { z: 5,  symbol: 'B',  nameVi: 'Bo',         nameEn: 'Boron',      mass: 10.81,  category: 'a-kim',       phonetic: 'Bo',         group: 13, period: 2, priority: 2 },
  { z: 6,  symbol: 'C',  nameVi: 'Cacbon',     nameEn: 'Carbon',     mass: 12.011, category: 'phi-kim',     phonetic: 'Các-bon',    group: 14, period: 2, priority: 1 },
  { z: 7,  symbol: 'N',  nameVi: 'Nitơ',       nameEn: 'Nitrogen',   mass: 14.007, category: 'phi-kim',     phonetic: 'Ni-tơ',      group: 15, period: 2, priority: 1 },
  { z: 8,  symbol: 'O',  nameVi: 'Oxi',        nameEn: 'Oxygen',     mass: 15.999, category: 'phi-kim',     phonetic: 'Ô-xi',       group: 16, period: 2, priority: 1 },
  { z: 9,  symbol: 'F',  nameVi: 'Flo',        nameEn: 'Fluorine',   mass: 18.998, category: 'halogen',     phonetic: 'Flo',        group: 17, period: 2, priority: 1 },
  { z: 10, symbol: 'Ne', nameVi: 'Neon',       nameEn: 'Neon',       mass: 20.18,  category: 'khi-hiem',    phonetic: 'Nê-ông',     group: 18, period: 2, priority: 1 },
  { z: 11, symbol: 'Na', nameVi: 'Natri',      nameEn: 'Sodium',     mass: 22.99,  category: 'kiem',        phonetic: 'Na-tri',     group: 1,  period: 3, priority: 1 },
  { z: 12, symbol: 'Mg', nameVi: 'Magie',      nameEn: 'Magnesium',  mass: 24.305, category: 'kiem-tho',    phonetic: 'Ma-giê',     group: 2,  period: 3, priority: 1 },
  { z: 13, symbol: 'Al', nameVi: 'Nhôm',       nameEn: 'Aluminium',  mass: 26.982, category: 'kim-loai',    phonetic: 'Nhôm',       group: 13, period: 3, priority: 1 },
  { z: 14, symbol: 'Si', nameVi: 'Silic',      nameEn: 'Silicon',    mass: 28.085, category: 'a-kim',       phonetic: 'Si-líc',     group: 14, period: 3, priority: 1 },
  { z: 15, symbol: 'P',  nameVi: 'Photpho',    nameEn: 'Phosphorus', mass: 30.974, category: 'phi-kim',     phonetic: 'Phốt-pho',   group: 15, period: 3, priority: 1 },
  { z: 16, symbol: 'S',  nameVi: 'Lưu huỳnh',  nameEn: 'Sulfur',     mass: 32.06,  category: 'phi-kim',     phonetic: 'Lưu huỳnh',  group: 16, period: 3, priority: 1 },
  { z: 17, symbol: 'Cl', nameVi: 'Clo',        nameEn: 'Chlorine',   mass: 35.45,  category: 'halogen',     phonetic: 'Clo',        group: 17, period: 3, priority: 1 },
  { z: 18, symbol: 'Ar', nameVi: 'Agon',       nameEn: 'Argon',      mass: 39.95,  category: 'khi-hiem',    phonetic: 'A-gông',     group: 18, period: 3, priority: 1 },
  { z: 19, symbol: 'K',  nameVi: 'Kali',       nameEn: 'Potassium',  mass: 39.098, category: 'kiem',        phonetic: 'Ka-li',      group: 1,  period: 4, priority: 1 },
  { z: 20, symbol: 'Ca', nameVi: 'Canxi',      nameEn: 'Calcium',    mass: 40.078, category: 'kiem-tho',    phonetic: 'Can-xi',     group: 2,  period: 4, priority: 1 },
  { z: 21, symbol: 'Sc', nameVi: 'Scandi',     nameEn: 'Scandium',   mass: 44.956, category: 'chuyen-tiep', phonetic: 'Scan-đi',    group: 3,  period: 4, priority: 2 },
  { z: 22, symbol: 'Ti', nameVi: 'Titan',      nameEn: 'Titanium',   mass: 47.867, category: 'chuyen-tiep', phonetic: 'Ti-tan',     group: 4,  period: 4, priority: 2 },
  { z: 23, symbol: 'V',  nameVi: 'Vanadi',     nameEn: 'Vanadium',   mass: 50.942, category: 'chuyen-tiep', phonetic: 'Va-na-đi',   group: 5,  period: 4, priority: 2 },
  { z: 24, symbol: 'Cr', nameVi: 'Crom',       nameEn: 'Chromium',   mass: 51.996, category: 'chuyen-tiep', phonetic: 'Crôm',       group: 6,  period: 4, priority: 1 },
  { z: 25, symbol: 'Mn', nameVi: 'Mangan',     nameEn: 'Manganese',  mass: 54.938, category: 'chuyen-tiep', phonetic: 'Man-gan',    group: 7,  period: 4, priority: 1 },
  { z: 26, symbol: 'Fe', nameVi: 'Sắt',        nameEn: 'Iron',       mass: 55.845, category: 'chuyen-tiep', phonetic: 'Sắt',        group: 8,  period: 4, priority: 1 },
  { z: 27, symbol: 'Co', nameVi: 'Coban',      nameEn: 'Cobalt',     mass: 58.933, category: 'chuyen-tiep', phonetic: 'Cô-ban',     group: 9,  period: 4, priority: 2 },
  { z: 28, symbol: 'Ni', nameVi: 'Niken',      nameEn: 'Nickel',     mass: 58.693, category: 'chuyen-tiep', phonetic: 'Ni-ken',     group: 10, period: 4, priority: 2 },
  { z: 29, symbol: 'Cu', nameVi: 'Đồng',       nameEn: 'Copper',     mass: 63.546, category: 'chuyen-tiep', phonetic: 'Đồng',       group: 11, period: 4, priority: 1 },
  { z: 30, symbol: 'Zn', nameVi: 'Kẽm',        nameEn: 'Zinc',       mass: 65.38,  category: 'chuyen-tiep', phonetic: 'Kẽm',        group: 12, period: 4, priority: 1 },
  { z: 31, symbol: 'Ga', nameVi: 'Gali',       nameEn: 'Gallium',    mass: 69.723, category: 'kim-loai',    phonetic: 'Ga-li',      group: 13, period: 4, priority: 2 },
  { z: 32, symbol: 'Ge', nameVi: 'Gemani',     nameEn: 'Germanium',  mass: 72.63,  category: 'a-kim',       phonetic: 'Giéc-ma-ni', group: 14, period: 4, priority: 2 },
  { z: 33, symbol: 'As', nameVi: 'Asen',       nameEn: 'Arsenic',    mass: 74.922, category: 'a-kim',       phonetic: 'A-sen',      group: 15, period: 4, priority: 2 },
  { z: 34, symbol: 'Se', nameVi: 'Selen',      nameEn: 'Selenium',   mass: 78.971, category: 'phi-kim',     phonetic: 'Sê-len',     group: 16, period: 4, priority: 2 },
  { z: 35, symbol: 'Br', nameVi: 'Brom',       nameEn: 'Bromine',    mass: 79.904, category: 'halogen',     phonetic: 'Brôm',       group: 17, period: 4, priority: 1 },
  { z: 36, symbol: 'Kr', nameVi: 'Kripton',    nameEn: 'Krypton',    mass: 83.798, category: 'khi-hiem',    phonetic: 'Críp-tôn',   group: 18, period: 4, priority: 2 },
  { z: 38, symbol: 'Sr', nameVi: 'Stronti',    nameEn: 'Strontium',  mass: 87.62,  category: 'kiem-tho',    phonetic: 'Xtrôn-ti',   group: 2,  period: 5, priority: 2 },
  { z: 47, symbol: 'Ag', nameVi: 'Bạc',        nameEn: 'Silver',     mass: 107.87, category: 'chuyen-tiep', phonetic: 'Bạc',        group: 11, period: 5, priority: 1 },
  { z: 50, symbol: 'Sn', nameVi: 'Thiếc',      nameEn: 'Tin',        mass: 118.71, category: 'kim-loai',    phonetic: 'Thiếc',      group: 14, period: 5, priority: 2 },
  { z: 53, symbol: 'I',  nameVi: 'Iot',        nameEn: 'Iodine',     mass: 126.9,  category: 'halogen',     phonetic: 'I-ốt',       group: 17, period: 5, priority: 1 },
  { z: 56, symbol: 'Ba', nameVi: 'Bari',       nameEn: 'Barium',     mass: 137.33, category: 'kiem-tho',    phonetic: 'Ba-ri',      group: 2,  period: 6, priority: 1 },
  { z: 78, symbol: 'Pt', nameVi: 'Bạch kim',   nameEn: 'Platinum',   mass: 195.08, category: 'chuyen-tiep', phonetic: 'Bạch kim',   group: 10, period: 6, priority: 2 },
  { z: 79, symbol: 'Au', nameVi: 'Vàng',       nameEn: 'Gold',       mass: 196.97, category: 'chuyen-tiep', phonetic: 'Vàng',       group: 11, period: 6, priority: 1 },
  { z: 80, symbol: 'Hg', nameVi: 'Thủy ngân',  nameEn: 'Mercury',    mass: 200.59, category: 'chuyen-tiep', phonetic: 'Thủy ngân',  group: 12, period: 6, priority: 1 },
  { z: 82, symbol: 'Pb', nameVi: 'Chì',        nameEn: 'Lead',       mass: 207.2,  category: 'kim-loai',    phonetic: 'Chì',        group: 14, period: 6, priority: 1 },
];

window.HTD_ELEMENT_CATEGORIES = {
  'kiem':        { label: 'Kim loại kiềm',   color: '#FF5DA2', deep: '#D63384' },
  'kiem-tho':    { label: 'Kiềm thổ',        color: '#FFC93C', deep: '#E8A100' },
  'chuyen-tiep': { label: 'KL chuyển tiếp',  color: '#38C6FF', deep: '#0E9BD8' },
  'kim-loai':    { label: 'Kim loại khác',   color: '#58CC02', deep: '#3E9A00' },
  'a-kim':       { label: 'Á kim',           color: '#FF8A5C', deep: '#E05A24' },
  'phi-kim':     { label: 'Phi kim',         color: '#8B5CF6', deep: '#6D28D9' },
  'halogen':     { label: 'Halogen',         color: '#FF4B4B', deep: '#D22F2F' },
  'khi-hiem':    { label: 'Khí hiếm',        color: '#00C2A8', deep: '#00937F' },
  'lantan':      { label: 'Lanthanide',      color: '#2FBFA6', deep: '#178C78' },
  'actini':      { label: 'Actinide',        color: '#F0607A', deep: '#C43552' },
};

/* HTD_PERIODIC_TABLE — khung bảng tuần hoàn đầy đủ 118 nguyên tố.
 *
 * Đây CHỈ là dữ liệu bố cục để vẽ đúng hình dáng bảng. Nội dung học (tên tiếng
 * Việt, cách đọc, trọng tâm, luyện tập) vẫn nằm ở HTD_ELEMENTS — ô nào có trong
 * HTD_ELEMENTS thì sáng và bấm được, ô còn lại chỉ hiển thị mờ để HS thấy đúng
 * vị trí nhóm/chu kỳ mà không tưởng là phải học hết.
 *
 * Field: [z, symbol, nameEn, mass, category, group, period]
 * period 'La' / 'Ac' = hai hàng f-block tách riêng dưới bảng chính.
 * Nguyên tử khối theo IUPAC 2021; nguyên tố phóng xạ không bền lấy số khối của
 * đồng vị bền nhất (Tc 98, Pm 145, Po 209, At 210, Rn 222, Fr 223…).
 */
window.HTD_PERIODIC_TABLE = [
  [1, 'H', 'Hydrogen', 1.008, 'phi-kim', 1, 1],
  [2, 'He', 'Helium', 4.003, 'khi-hiem', 18, 1],

  [3, 'Li', 'Lithium', 6.94, 'kiem', 1, 2],
  [4, 'Be', 'Beryllium', 9.012, 'kiem-tho', 2, 2],
  [5, 'B', 'Boron', 10.81, 'a-kim', 13, 2],
  [6, 'C', 'Carbon', 12.011, 'phi-kim', 14, 2],
  [7, 'N', 'Nitrogen', 14.007, 'phi-kim', 15, 2],
  [8, 'O', 'Oxygen', 15.999, 'phi-kim', 16, 2],
  [9, 'F', 'Fluorine', 18.998, 'halogen', 17, 2],
  [10, 'Ne', 'Neon', 20.180, 'khi-hiem', 18, 2],

  [11, 'Na', 'Sodium', 22.990, 'kiem', 1, 3],
  [12, 'Mg', 'Magnesium', 24.305, 'kiem-tho', 2, 3],
  [13, 'Al', 'Aluminium', 26.982, 'kim-loai', 13, 3],
  [14, 'Si', 'Silicon', 28.085, 'a-kim', 14, 3],
  [15, 'P', 'Phosphorus', 30.974, 'phi-kim', 15, 3],
  [16, 'S', 'Sulfur', 32.06, 'phi-kim', 16, 3],
  [17, 'Cl', 'Chlorine', 35.45, 'halogen', 17, 3],
  [18, 'Ar', 'Argon', 39.95, 'khi-hiem', 18, 3],

  [19, 'K', 'Potassium', 39.098, 'kiem', 1, 4],
  [20, 'Ca', 'Calcium', 40.078, 'kiem-tho', 2, 4],
  [21, 'Sc', 'Scandium', 44.956, 'chuyen-tiep', 3, 4],
  [22, 'Ti', 'Titanium', 47.867, 'chuyen-tiep', 4, 4],
  [23, 'V', 'Vanadium', 50.942, 'chuyen-tiep', 5, 4],
  [24, 'Cr', 'Chromium', 51.996, 'chuyen-tiep', 6, 4],
  [25, 'Mn', 'Manganese', 54.938, 'chuyen-tiep', 7, 4],
  [26, 'Fe', 'Iron', 55.845, 'chuyen-tiep', 8, 4],
  [27, 'Co', 'Cobalt', 58.933, 'chuyen-tiep', 9, 4],
  [28, 'Ni', 'Nickel', 58.693, 'chuyen-tiep', 10, 4],
  [29, 'Cu', 'Copper', 63.546, 'chuyen-tiep', 11, 4],
  [30, 'Zn', 'Zinc', 65.38, 'chuyen-tiep', 12, 4],
  [31, 'Ga', 'Gallium', 69.723, 'kim-loai', 13, 4],
  [32, 'Ge', 'Germanium', 72.630, 'a-kim', 14, 4],
  [33, 'As', 'Arsenic', 74.922, 'a-kim', 15, 4],
  [34, 'Se', 'Selenium', 78.971, 'phi-kim', 16, 4],
  [35, 'Br', 'Bromine', 79.904, 'halogen', 17, 4],
  [36, 'Kr', 'Krypton', 83.798, 'khi-hiem', 18, 4],

  [37, 'Rb', 'Rubidium', 85.468, 'kiem', 1, 5],
  [38, 'Sr', 'Strontium', 87.62, 'kiem-tho', 2, 5],
  [39, 'Y', 'Yttrium', 88.906, 'chuyen-tiep', 3, 5],
  [40, 'Zr', 'Zirconium', 91.224, 'chuyen-tiep', 4, 5],
  [41, 'Nb', 'Niobium', 92.906, 'chuyen-tiep', 5, 5],
  [42, 'Mo', 'Molybdenum', 95.95, 'chuyen-tiep', 6, 5],
  [43, 'Tc', 'Technetium', 98, 'chuyen-tiep', 7, 5],
  [44, 'Ru', 'Ruthenium', 101.07, 'chuyen-tiep', 8, 5],
  [45, 'Rh', 'Rhodium', 102.91, 'chuyen-tiep', 9, 5],
  [46, 'Pd', 'Palladium', 106.42, 'chuyen-tiep', 10, 5],
  [47, 'Ag', 'Silver', 107.87, 'chuyen-tiep', 11, 5],
  [48, 'Cd', 'Cadmium', 112.41, 'chuyen-tiep', 12, 5],
  [49, 'In', 'Indium', 114.82, 'kim-loai', 13, 5],
  [50, 'Sn', 'Tin', 118.71, 'kim-loai', 14, 5],
  [51, 'Sb', 'Antimony', 121.76, 'a-kim', 15, 5],
  [52, 'Te', 'Tellurium', 127.60, 'a-kim', 16, 5],
  [53, 'I', 'Iodine', 126.90, 'halogen', 17, 5],
  [54, 'Xe', 'Xenon', 131.29, 'khi-hiem', 18, 5],

  [55, 'Cs', 'Caesium', 132.91, 'kiem', 1, 6],
  [56, 'Ba', 'Barium', 137.33, 'kiem-tho', 2, 6],
  [72, 'Hf', 'Hafnium', 178.49, 'chuyen-tiep', 4, 6],
  [73, 'Ta', 'Tantalum', 180.95, 'chuyen-tiep', 5, 6],
  [74, 'W', 'Tungsten', 183.84, 'chuyen-tiep', 6, 6],
  [75, 'Re', 'Rhenium', 186.21, 'chuyen-tiep', 7, 6],
  [76, 'Os', 'Osmium', 190.23, 'chuyen-tiep', 8, 6],
  [77, 'Ir', 'Iridium', 192.22, 'chuyen-tiep', 9, 6],
  [78, 'Pt', 'Platinum', 195.08, 'chuyen-tiep', 10, 6],
  [79, 'Au', 'Gold', 196.97, 'chuyen-tiep', 11, 6],
  [80, 'Hg', 'Mercury', 200.59, 'chuyen-tiep', 12, 6],
  [81, 'Tl', 'Thallium', 204.38, 'kim-loai', 13, 6],
  [82, 'Pb', 'Lead', 207.2, 'kim-loai', 14, 6],
  [83, 'Bi', 'Bismuth', 208.98, 'kim-loai', 15, 6],
  [84, 'Po', 'Polonium', 209, 'kim-loai', 16, 6],
  [85, 'At', 'Astatine', 210, 'halogen', 17, 6],
  [86, 'Rn', 'Radon', 222, 'khi-hiem', 18, 6],

  [87, 'Fr', 'Francium', 223, 'kiem', 1, 7],
  [88, 'Ra', 'Radium', 226, 'kiem-tho', 2, 7],
  [104, 'Rf', 'Rutherfordium', 267, 'chuyen-tiep', 4, 7],
  [105, 'Db', 'Dubnium', 268, 'chuyen-tiep', 5, 7],
  [106, 'Sg', 'Seaborgium', 269, 'chuyen-tiep', 6, 7],
  [107, 'Bh', 'Bohrium', 270, 'chuyen-tiep', 7, 7],
  [108, 'Hs', 'Hassium', 269, 'chuyen-tiep', 8, 7],
  [109, 'Mt', 'Meitnerium', 278, 'chuyen-tiep', 9, 7],
  [110, 'Ds', 'Darmstadtium', 281, 'chuyen-tiep', 10, 7],
  [111, 'Rg', 'Roentgenium', 282, 'chuyen-tiep', 11, 7],
  [112, 'Cn', 'Copernicium', 285, 'chuyen-tiep', 12, 7],
  [113, 'Nh', 'Nihonium', 286, 'kim-loai', 13, 7],
  [114, 'Fl', 'Flerovium', 289, 'kim-loai', 14, 7],
  [115, 'Mc', 'Moscovium', 290, 'kim-loai', 15, 7],
  [116, 'Lv', 'Livermorium', 293, 'kim-loai', 16, 7],
  [117, 'Ts', 'Tennessine', 294, 'halogen', 17, 7],
  [118, 'Og', 'Oganesson', 294, 'khi-hiem', 18, 7],

  [57, 'La', 'Lanthanum', 138.91, 'lantan', 3, 'La'],
  [58, 'Ce', 'Cerium', 140.12, 'lantan', 4, 'La'],
  [59, 'Pr', 'Praseodymium', 140.91, 'lantan', 5, 'La'],
  [60, 'Nd', 'Neodymium', 144.24, 'lantan', 6, 'La'],
  [61, 'Pm', 'Promethium', 145, 'lantan', 7, 'La'],
  [62, 'Sm', 'Samarium', 150.36, 'lantan', 8, 'La'],
  [63, 'Eu', 'Europium', 151.96, 'lantan', 9, 'La'],
  [64, 'Gd', 'Gadolinium', 157.25, 'lantan', 10, 'La'],
  [65, 'Tb', 'Terbium', 158.93, 'lantan', 11, 'La'],
  [66, 'Dy', 'Dysprosium', 162.50, 'lantan', 12, 'La'],
  [67, 'Ho', 'Holmium', 164.93, 'lantan', 13, 'La'],
  [68, 'Er', 'Erbium', 167.26, 'lantan', 14, 'La'],
  [69, 'Tm', 'Thulium', 168.93, 'lantan', 15, 'La'],
  [70, 'Yb', 'Ytterbium', 173.05, 'lantan', 16, 'La'],
  [71, 'Lu', 'Lutetium', 174.97, 'lantan', 17, 'La'],

  [89, 'Ac', 'Actinium', 227, 'actini', 3, 'Ac'],
  [90, 'Th', 'Thorium', 232.04, 'actini', 4, 'Ac'],
  [91, 'Pa', 'Protactinium', 231.04, 'actini', 5, 'Ac'],
  [92, 'U', 'Uranium', 238.03, 'actini', 6, 'Ac'],
  [93, 'Np', 'Neptunium', 237, 'actini', 7, 'Ac'],
  [94, 'Pu', 'Plutonium', 244, 'actini', 8, 'Ac'],
  [95, 'Am', 'Americium', 243, 'actini', 9, 'Ac'],
  [96, 'Cm', 'Curium', 247, 'actini', 10, 'Ac'],
  [97, 'Bk', 'Berkelium', 247, 'actini', 11, 'Ac'],
  [98, 'Cf', 'Californium', 251, 'actini', 12, 'Ac'],
  [99, 'Es', 'Einsteinium', 252, 'actini', 13, 'Ac'],
  [100, 'Fm', 'Fermium', 257, 'actini', 14, 'Ac'],
  [101, 'Md', 'Mendelevium', 258, 'actini', 15, 'Ac'],
  [102, 'No', 'Nobelium', 259, 'actini', 16, 'Ac'],
  [103, 'Lr', 'Lawrencium', 266, 'actini', 17, 'Ac'],
];

/* HTD_loadElements — nạp bảng nguyên tố từ phiên bản đang live ở admin.
 *
 * Ghi đè HTD_ELEMENTS / HTD_ELEMENT_CATEGORIES bằng dữ liệu server (chỉ các
 * nguyên tố is_visible của phiên bản live). Nếu fetch lỗi (offline) thì GIỮ
 * NGUYÊN dữ liệu tĩnh phía trên làm fallback — app vẫn chạy được.
 *
 * priority được suy ra từ isLit (sáng = trọng tâm) để tương thích code cũ:
 * filter/luyện tập/tiến độ vẫn dùng field priority như trước.
 * HTD_PERIODIC_TABLE giữ nguyên tĩnh (khung nền 118 ô).
 */
window.HTD_loadElements = async function () {
  try {
    var data = window.HTDApi && HTDApi.elementsTable
      ? await HTDApi.elementsTable()
      : await fetch('/api/elements/table', { credentials: 'include' })
          .then(function (r) { return r.json(); })
          .then(function (j) { return j && j.data; });

    if (data && Array.isArray(data.elements) && data.elements.length) {
      window.HTD_ELEMENTS = data.elements.map(function (e) {
        if (e.priority == null) e.priority = e.isLit ? 1 : 2;
        return e;
      });
      if (data.categories && Object.keys(data.categories).length) {
        window.HTD_ELEMENT_CATEGORIES = data.categories;
      }
    }
  } catch (e) {
    if (window.console) console.warn('[elements] dùng dữ liệu tĩnh (fetch lỗi):', e && e.message);
  }
  return window.HTD_ELEMENTS;
};
