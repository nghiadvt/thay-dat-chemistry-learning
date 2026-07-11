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
 */
window.HTD_ELEMENTS = [
  { z: 1,  symbol: 'H',  nameVi: 'Hiđro',      nameEn: 'Hydrogen',   mass: 1.008,  category: 'phi-kim',     phonetic: 'Hi-đrô' },
  { z: 2,  symbol: 'He', nameVi: 'Heli',       nameEn: 'Helium',     mass: 4.003,  category: 'khi-hiem',    phonetic: 'Hê-li' },
  { z: 3,  symbol: 'Li', nameVi: 'Liti',       nameEn: 'Lithium',    mass: 6.94,   category: 'kiem',        phonetic: 'Li-ti' },
  { z: 4,  symbol: 'Be', nameVi: 'Beri',       nameEn: 'Beryllium',  mass: 9.012,  category: 'kiem-tho',    phonetic: 'Bê-ri' },
  { z: 5,  symbol: 'B',  nameVi: 'Bo',         nameEn: 'Boron',      mass: 10.81,  category: 'a-kim',       phonetic: 'Bo' },
  { z: 6,  symbol: 'C',  nameVi: 'Cacbon',     nameEn: 'Carbon',     mass: 12.011, category: 'phi-kim',     phonetic: 'Các-bon' },
  { z: 7,  symbol: 'N',  nameVi: 'Nitơ',       nameEn: 'Nitrogen',   mass: 14.007, category: 'phi-kim',     phonetic: 'Ni-tơ' },
  { z: 8,  symbol: 'O',  nameVi: 'Oxi',        nameEn: 'Oxygen',     mass: 15.999, category: 'phi-kim',     phonetic: 'Ô-xi' },
  { z: 9,  symbol: 'F',  nameVi: 'Flo',        nameEn: 'Fluorine',   mass: 18.998, category: 'halogen',     phonetic: 'Flo' },
  { z: 10, symbol: 'Ne', nameVi: 'Neon',       nameEn: 'Neon',       mass: 20.18,  category: 'khi-hiem',    phonetic: 'Nê-ông' },
  { z: 11, symbol: 'Na', nameVi: 'Natri',      nameEn: 'Sodium',     mass: 22.99,  category: 'kiem',        phonetic: 'Na-tri' },
  { z: 12, symbol: 'Mg', nameVi: 'Magie',      nameEn: 'Magnesium',  mass: 24.305, category: 'kiem-tho',    phonetic: 'Ma-giê' },
  { z: 13, symbol: 'Al', nameVi: 'Nhôm',       nameEn: 'Aluminium',  mass: 26.982, category: 'kim-loai',    phonetic: 'Nhôm' },
  { z: 14, symbol: 'Si', nameVi: 'Silic',      nameEn: 'Silicon',    mass: 28.085, category: 'a-kim',       phonetic: 'Si-líc' },
  { z: 15, symbol: 'P',  nameVi: 'Photpho',    nameEn: 'Phosphorus', mass: 30.974, category: 'phi-kim',     phonetic: 'Phốt-pho' },
  { z: 16, symbol: 'S',  nameVi: 'Lưu huỳnh',  nameEn: 'Sulfur',     mass: 32.06,  category: 'phi-kim',     phonetic: 'Lưu huỳnh' },
  { z: 17, symbol: 'Cl', nameVi: 'Clo',        nameEn: 'Chlorine',   mass: 35.45,  category: 'halogen',     phonetic: 'Clo' },
  { z: 18, symbol: 'Ar', nameVi: 'Agon',       nameEn: 'Argon',      mass: 39.95,  category: 'khi-hiem',    phonetic: 'A-gông' },
  { z: 19, symbol: 'K',  nameVi: 'Kali',       nameEn: 'Potassium',  mass: 39.098, category: 'kiem',        phonetic: 'Ka-li' },
  { z: 20, symbol: 'Ca', nameVi: 'Canxi',      nameEn: 'Calcium',    mass: 40.078, category: 'kiem-tho',    phonetic: 'Can-xi' },
  { z: 21, symbol: 'Sc', nameVi: 'Scandi',     nameEn: 'Scandium',   mass: 44.956, category: 'chuyen-tiep', phonetic: 'Scan-đi' },
  { z: 22, symbol: 'Ti', nameVi: 'Titan',      nameEn: 'Titanium',   mass: 47.867, category: 'chuyen-tiep', phonetic: 'Ti-tan' },
  { z: 23, symbol: 'V',  nameVi: 'Vanadi',     nameEn: 'Vanadium',   mass: 50.942, category: 'chuyen-tiep', phonetic: 'Va-na-đi' },
  { z: 24, symbol: 'Cr', nameVi: 'Crom',       nameEn: 'Chromium',   mass: 51.996, category: 'chuyen-tiep', phonetic: 'Crôm' },
  { z: 25, symbol: 'Mn', nameVi: 'Mangan',     nameEn: 'Manganese',  mass: 54.938, category: 'chuyen-tiep', phonetic: 'Man-gan' },
  { z: 26, symbol: 'Fe', nameVi: 'Sắt',        nameEn: 'Iron',       mass: 55.845, category: 'chuyen-tiep', phonetic: 'Sắt' },
  { z: 27, symbol: 'Co', nameVi: 'Coban',      nameEn: 'Cobalt',     mass: 58.933, category: 'chuyen-tiep', phonetic: 'Cô-ban' },
  { z: 28, symbol: 'Ni', nameVi: 'Niken',      nameEn: 'Nickel',     mass: 58.693, category: 'chuyen-tiep', phonetic: 'Ni-ken' },
  { z: 29, symbol: 'Cu', nameVi: 'Đồng',       nameEn: 'Copper',     mass: 63.546, category: 'chuyen-tiep', phonetic: 'Đồng' },
  { z: 30, symbol: 'Zn', nameVi: 'Kẽm',        nameEn: 'Zinc',       mass: 65.38,  category: 'chuyen-tiep', phonetic: 'Kẽm' },
  { z: 31, symbol: 'Ga', nameVi: 'Gali',       nameEn: 'Gallium',    mass: 69.723, category: 'kim-loai',    phonetic: 'Ga-li' },
  { z: 32, symbol: 'Ge', nameVi: 'Gemani',     nameEn: 'Germanium',  mass: 72.63,  category: 'a-kim',       phonetic: 'Giéc-ma-ni' },
  { z: 33, symbol: 'As', nameVi: 'Asen',       nameEn: 'Arsenic',    mass: 74.922, category: 'a-kim',       phonetic: 'A-sen' },
  { z: 34, symbol: 'Se', nameVi: 'Selen',      nameEn: 'Selenium',   mass: 78.971, category: 'phi-kim',     phonetic: 'Sê-len' },
  { z: 35, symbol: 'Br', nameVi: 'Brom',       nameEn: 'Bromine',    mass: 79.904, category: 'halogen',     phonetic: 'Brôm' },
  { z: 36, symbol: 'Kr', nameVi: 'Kripton',    nameEn: 'Krypton',    mass: 83.798, category: 'khi-hiem',    phonetic: 'Críp-tôn' },
  { z: 38, symbol: 'Sr', nameVi: 'Stronti',    nameEn: 'Strontium',  mass: 87.62,  category: 'kiem-tho',    phonetic: 'Xtrôn-ti' },
  { z: 47, symbol: 'Ag', nameVi: 'Bạc',        nameEn: 'Silver',     mass: 107.87, category: 'chuyen-tiep', phonetic: 'Bạc' },
  { z: 50, symbol: 'Sn', nameVi: 'Thiếc',      nameEn: 'Tin',        mass: 118.71, category: 'kim-loai',    phonetic: 'Thiếc' },
  { z: 53, symbol: 'I',  nameVi: 'Iot',        nameEn: 'Iodine',     mass: 126.9,  category: 'halogen',     phonetic: 'I-ốt' },
  { z: 56, symbol: 'Ba', nameVi: 'Bari',       nameEn: 'Barium',     mass: 137.33, category: 'kiem-tho',    phonetic: 'Ba-ri' },
  { z: 78, symbol: 'Pt', nameVi: 'Bạch kim',   nameEn: 'Platinum',   mass: 195.08, category: 'chuyen-tiep', phonetic: 'Bạch kim' },
  { z: 79, symbol: 'Au', nameVi: 'Vàng',       nameEn: 'Gold',       mass: 196.97, category: 'chuyen-tiep', phonetic: 'Vàng' },
  { z: 80, symbol: 'Hg', nameVi: 'Thủy ngân',  nameEn: 'Mercury',    mass: 200.59, category: 'chuyen-tiep', phonetic: 'Thủy ngân' },
  { z: 82, symbol: 'Pb', nameVi: 'Chì',        nameEn: 'Lead',       mass: 207.2,  category: 'kim-loai',    phonetic: 'Chì' },
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
};
