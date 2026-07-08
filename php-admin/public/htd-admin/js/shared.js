/** Shared storage & helpers — đồng bộ giữa index.html và teacher.html */
const HTD = {
  LS_ROOM: 'htd_room',
  LS_PLAYERS: 'htd_players',

  ELEMENTS: ['H','O','C','N','Na','Cl','K','Ca','Mg','Al','Fe','Zn','Cu','Ag','Ba'],

  FAKE_QUESTIONS: [
    {
      id: '100001',
      type: 'mc',
      media: 'text',
      prompt:
        'Trong chương trình Hóa học lớp 10, oxit được phân loại theo tính chất axit-bazơ. ' +
        'Oxit bazơ là oxit khi tác dụng với nước tạo ra bazơ, hoặc tác dụng với axit tạo muối và nước.\n\n' +
        'Chất nào sau đây là oxit bazơ?',
      options: ['CO₂ (khí làm đục nước vôi trong)', 'SO₃ (khí có mùi hắc)', 'CaO (vôi sống)', 'P₂O₅ (oxit axit mạnh)'],
      correct: 2,
      timeLimit: 25,
    },
    {
      id: '100002',
      type: 'mc',
      media: 'image',
      mediaUrl: 'assets/rocket.png',
      prompt:
        'Giáo viên cho mẫu dung dịch trong ống nghiệm như hình. Dung dịch được chuẩn bị bằng cách hòa tan ' +
        'một chất tinh thể màu hồng trong nước cất, có thể dùng để nhận biết axit yếu trong phòng thí nghiệm.\n\n' +
        'Dung dịch trong hình có màu gì?',
      options: ['Không màu (trong suốt)', 'Xanh lam đậm', 'Hồng / tím phenolphtalein', 'Vàng cam'],
      correct: 2,
      timeLimit: 30,
    },
    {
      id: '100003',
      type: 'mc',
      media: 'video',
      mediaUrl: 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
      mediaPoster: 'assets/teacher-hero.png',
      prompt:
        'Quan sát video thí nghiệm: cho bột đồng(II) nitrat (Cu(NO₃)₂) nung trong ống nghiệm. ' +
        'Khi nhiệt độ cao, muối phân hủy sinh ra khí màu nâu đỏ, chất rắn màu đen bám thành ống nghiệm ' +
        'và khí giúp đuối tắt ngọn tàn đuốc cháy.\n\n' +
        'Chất khí màu nâu trong thí nghiệm là gì?',
      options: ['Cl₂ (khí màu vàng lục)', 'NO₂ (khí màu nâu đỏ)', 'SO₂ (khí có mùi hắc)', 'CO₂ (không màu)'],
      correct: 1,
      timeLimit: 35,
    },
    {
      id: '200001',
      type: 'input',
      inputMode: 'product',
      media: 'text',
      prompt:
        'Thí nghiệm: Trong phòng thí nghiệm, giáo viên hướng dẫn đi ống dẫn khí NH₃ từ bình chứa qua dung dịch ' +
        'HCl loãng trong ống nghiệm đã đánh dấu. Học sinh quan sát thấy khói trắng dày bám quanh miệng ống ' +
        '(do tạo thành các hạt muối bám ẩm rất nhỏ), mùi khai thoang thoảng và dung dịch không đổi màu đỏ lam ' +
        'của quỳ tím.\n\n' +
        'Viết đầy đủ sản phẩm hữu cơ vô cơ ở vế phải của phương trình sau:',
      template: [
        { t: 'chem', text: 'NH3' },
        { t: 'txt', text: ' + ' },
        { t: 'chem', text: 'HCl' },
        { t: 'txt', text: ' → ' },
        { t: 'blank', id: 'b0' },
      ],
      correct: { blank: { b0: 'NH4Cl' } },
      timeLimit: 40,
    },
    {
      id: '200002',
      type: 'input',
      inputMode: 'balance',
      media: 'text',
      prompt:
        'Thí nghiệm: Một thanh nhôm sạch được đốt trong bình chứa khí oxi. Quan sát thấy tia lửa rực sáng, ' +
        'nhiệt độ tăng mạnh và lớp bột trắng bám trên bề mặt nhôm (oxit nhôm Al₂O₃) — đây là phản ứng oxi hóa ' +
        'mạnh, được ứng dụng trong hàn nhôm (phương pháp Thermit).\n\n' +
        'Cân bằng hệ số cho phương trình sau (điền số vào các ô trống trước mỗi chất):',
      template: [
        { t: 'coef', id: 'c0' },
        { t: 'chem', text: 'Al' },
        { t: 'txt', text: ' + ' },
        { t: 'coef', id: 'c1' },
        { t: 'chem', text: 'O2' },
        { t: 'txt', text: ' → ' },
        { t: 'coef', id: 'c2' },
        { t: 'chem', text: 'Al2O3' },
      ],
      correct: { coef: { c0: '4', c1: '3', c2: '2' } },
      timeLimit: 45,
    },
    {
      id: '200003',
      type: 'input',
      inputMode: 'blank',
      media: 'text',
      prompt:
        'Thí nghiệm: Cho bột sắt (Fe) tác dụng với khí oxi trong không khí khi nung ở nhiệt độ cao trên bản đế ' +
        'gốm. Sau thí nghiệm thu được bột nâu đỏ, không còn tính chất từ tính của sắt — đó là oxit sắt(III) Fe₂O₃ ' +
        '(gỉ sắt). Phản ứng này xảy ra tự nhiên khi sắt bị ăn mòn trong môi trường ẩm có oxi.\n\n' +
        'Điền chất thiếu (công thức hóa học) vào phương trình:',
      template: [
        { t: 'chem', text: 'Fe' },
        { t: 'txt', text: ' + ' },
        { t: 'blank', id: 'b0' },
        { t: 'txt', text: ' → ' },
        { t: 'chem', text: 'Fe2O3' },
      ],
      correct: { blank: { b0: 'O2' } },
      timeLimit: 40,
    },
    {
      id: '200004',
      type: 'input',
      inputMode: 'blank_balance',
      media: 'text',
      prompt:
        'Thí nghiệm: Khí hydro (H₂) được đốt cháy trong không khí bằng ngọn lửa ngoài. Ngọn lửa màu xanh nhạt, ' +
        'trên thành ống nghiệm lạnh xuất hiện giọt nước ngưng tụ — chứng tỏ sản phẩm có H₂O. Phản ứng tỏa nhiều nhiệt ' +
        'và được ứng dụng làm nhiên liệu tên lửa (phản ứng với oxi líquid).\n\n' +
        'Cân bằng hệ số VÀ điền chất thiếu ở vế trái:',
      template: [
        { t: 'coef', id: 'c0' },
        { t: 'blank', id: 'b0' },
        { t: 'txt', text: ' + ' },
        { t: 'coef', id: 'c1' },
        { t: 'chem', text: 'O2' },
        { t: 'txt', text: ' → ' },
        { t: 'coef', id: 'c2' },
        { t: 'chem', text: 'H2O' },
      ],
      correct: { coef: { c0: '2', c1: '1', c2: '2' }, blank: { b0: 'H2' } },
      timeLimit: 50,
    },
    {
      id: '200005',
      type: 'input',
      inputMode: 'balance',
      media: 'text',
      prompt:
        'Thí nghiệm mở rộng: Cho axit sunfuric đặc tác dụng với đồng(II) oxit trong điều kiện đun nóng. ' +
        'Dung dịch thu được có màu xanh lam (ion Cu²⁺), khí bay ra làm đục nước vôi trong và có mùi hắc đặc trưng.\n\n' +
        'Cân bằng hệ số phương trình phản ứng oxi hóa-khử sau:',
      template: [
        { t: 'coef', id: 'c0' },
        { t: 'chem', text: 'CuO' },
        { t: 'txt', text: ' + ' },
        { t: 'coef', id: 'c1' },
        { t: 'chem', text: 'H2SO4' },
        { t: 'txt', text: ' → ' },
        { t: 'coef', id: 'c2' },
        { t: 'chem', text: 'CuSO4' },
        { t: 'txt', text: ' + ' },
        { t: 'coef', id: 'c3' },
        { t: 'chem', text: 'H2O' },
      ],
      correct: { coef: { c0: '1', c1: '1', c2: '1', c3: '1' } },
      timeLimit: 50,
    },
  ],

  INPUT_MODE_LABELS: {
    product: 'Điền sản phẩm',
    balance: 'Cân bằng hệ số',
    blank: 'Điền chỗ thiếu',
    blank_balance: 'Cân bằng + điền thiếu',
    essay: 'Tự luận ngắn',
    formula: 'Công thức hóa học',
  },

  getRoom() {
    try { return JSON.parse(localStorage.getItem(this.LS_ROOM)); } catch { return null; }
  },
  setRoom(r) { localStorage.setItem(this.LS_ROOM, JSON.stringify(r)); },

  getPlayers() {
    try { return JSON.parse(localStorage.getItem(this.LS_PLAYERS)) || []; } catch { return []; }
  },
  setPlayers(p) { localStorage.setItem(this.LS_PLAYERS, JSON.stringify(p)); },

  genId() { return 'p_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6); },
  genPin() { return String(Math.floor(100000 + Math.random() * 900000)); },

  FAKE_EMOJIS: ['😀','🤓','😎','🥳','😊','🧑‍🎓','👩‍🔬','🧪','⚗️','🔬','💡','⭐','🎯','🏆','🌟','😄','🙂','😁','🤩','😇','🥰','😺','🦊','🐻','🐼','🦁','🐯','🐸','🐵','🐶','🐱','🐭','🐹','🐰','🦉','🐝','🌸','🍀','🎨','🎵','📚','✏️','🔥','💎','🌈','🚀','⚡','🎪','🎭','🎬'],

  FAKE_NAMES: [
    'Minh Anh','Hoàng Bảo','Thanh Chi','Đức Dũng','Ngọc Hà','Quang Huy','Phương Linh',
    'Tuấn Minh','Thu Ngân','Văn Phúc','Bích Quân','Đình Sơn','Hương Trang','Xuân Việt',
    'Yến Nhi','Anh Khoa','Bảo Châu','Cẩm Ly','Duy Khánh','Gia Hân','Hải Đăng','Khánh Vy',
    'Lê Quân','Mai Phương','Nam Khánh','Oanh Thơ','Phúc An','Quỳnh Anh','Sơn Tùng',
    'Thảo My','Trung Kiên','Uyên Phương','Việt Hoàng','Xuân Mai','Yên Vy','An Bình',
    'Bảo Ngọc','Chí Thành','Diệu Linh','Đăng Khoa','Gia Bảo','Hà My','Kiên Đức',
    'Lan Anh','Minh Quân','Ngọc Bích','Phương Anh','Quốc Bảo','Thanh Tùng','Thúy Hằng',
    'Trọng Nghĩa','Vân Anh','Xuân Đạt',
  ],

  buildDisplayPlayers(realPlayers, myId, targetCount = 50) {
    const real = realPlayers.filter(p => p.id !== myId);
    const me = realPlayers.find(p => p.id === myId);
    const list = me ? [me] : [];
    list.push(...real);

    let i = 0;
    while (list.length < targetCount) {
      list.push({
        id: 'fake_' + i,
        name: this.FAKE_NAMES[i % this.FAKE_NAMES.length] + (i >= this.FAKE_NAMES.length ? ' ' + (i + 1) : ''),
        avatarEmoji: this.FAKE_EMOJIS[i % this.FAKE_EMOJIS.length],
        isFake: true,
      });
      i++;
    }
    return list.slice(0, targetCount);
  },

  showScreen(id) {
    document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
    document.querySelector(`[data-screen="${id}"]`)?.classList.add('active');
  },

  LEADERBOARD_MS: 5000,

  formatTimer(sec) {
    const m = Math.floor(sec / 60);
    const s = sec % 60;
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
  },

  initGame(room) {
    const q = this.FAKE_QUESTIONS[0];
    room.game = {
      phase: 'question',
      questionIndex: 0,
      timerEndsAt: Date.now() + q.timeLimit * 1000,
      phaseEndsAt: null,
      paused: false,
      pauseRemainingMs: null,
    };
    return room.game;
  },

  getQuestionTimeRemaining(game) {
    if (!game || game.phase !== 'question') return 0;
    if (game.paused) return Math.ceil((game.pauseRemainingMs || 0) / 1000);
    if (!game.timerEndsAt) return 0;
    return Math.max(0, Math.ceil((game.timerEndsAt - Date.now()) / 1000));
  },

  getPhaseTimeRemaining(game) {
    if (!game?.phaseEndsAt) return 0;
    return Math.max(0, Math.ceil((game.phaseEndsAt - Date.now()) / 1000));
  },

  startQuestionPhase(room, index) {
    const q = this.FAKE_QUESTIONS[index];
    if (!room.game) this.initGame(room);
    room.game.questionIndex = index;
    room.game.phase = 'question';
    room.game.timerEndsAt = Date.now() + q.timeLimit * 1000;
    room.game.phaseEndsAt = null;
    room.game.paused = false;
    room.game.pauseRemainingMs = null;
  },

  startLeaderboardPhase(room) {
    if (!room.game) return;
    room.game.phase = 'leaderboard';
    room.game.timerEndsAt = null;
    room.game.phaseEndsAt = Date.now() + this.LEADERBOARD_MS;
    room.game.paused = false;
    room.game.pauseRemainingMs = null;
  },

  startFinalPhase(room) {
    if (!room.game) return;
    room.game.phase = 'final';
    room.game.timerEndsAt = null;
    room.game.phaseEndsAt = null;
    room.game.paused = false;
    room.game.pauseRemainingMs = null;
  },

  pauseGame(room) {
    if (!room?.game || room.game.phase !== 'question' || room.game.paused) return;
    room.game.pauseRemainingMs = Math.max(0, room.game.timerEndsAt - Date.now());
    room.game.paused = true;
    room.game.timerEndsAt = null;
  },

  resumeGame(room) {
    if (!room?.game?.paused) return;
    room.game.timerEndsAt = Date.now() + room.game.pauseRemainingMs;
    room.game.paused = false;
    room.game.pauseRemainingMs = null;
  },

  resetGame(room) {
    delete room.game;
    room.status = 'waiting';
    delete room.startedAt;
  },
};
