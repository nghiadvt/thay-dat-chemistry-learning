/**
 * Backend integration bridge — wires Socket.io events to UI callbacks.
 */
const HTDBridge = (function () {
  const listeners = {
    roomJoined: [],
    roomError: [],
    playersUpdate: [],
    gameStarted: [],
    newQuestion: [],
    questionResult: [],
    leaderboardUpdate: [],
    submitCountUpdate: [],
    gameEnded: [],
    answerFeedback: [],
    raceUpdate: [],
    playerFinished: [],
    roomClosed: [],
    themeUpdate: [],
  };

  let roomMeta = null;
  let isHost = false;

  function on(event, fn) {
    if (!listeners[event]) throw new Error(`Unknown bridge event: ${event}`);
    listeners[event].push(fn);
  }

  function emitLocal(event, payload) {
    (listeners[event] || []).forEach(fn => {
      try {
        fn(payload);
      } catch (err) {
        console.error(`HTDBridge listener error (${event})`, err);
      }
    });
  }

  function bindSocketEvents() {
    HTDSocket.on('room_joined', data => emitLocal('roomJoined', data));
    HTDSocket.on('room_error', data => emitLocal('roomError', data));
    HTDSocket.on('players_update', data => emitLocal('playersUpdate', data));
    HTDSocket.on('game_started', data => emitLocal('gameStarted', data));
    HTDSocket.on('new_question', data => emitLocal('newQuestion', data));
    HTDSocket.on('question_result', data => emitLocal('questionResult', data));
    HTDSocket.on('leaderboard_update', data => emitLocal('leaderboardUpdate', data));
    HTDSocket.on('submit_count_update', data => emitLocal('submitCountUpdate', data));
    HTDSocket.on('game_ended', data => emitLocal('gameEnded', data));
    HTDSocket.on('answer_feedback', data => emitLocal('answerFeedback', data));
    HTDSocket.on('race_update', data => emitLocal('raceUpdate', data));
    HTDSocket.on('player_finished', data => emitLocal('playerFinished', data));
    HTDSocket.on('room_closed', data => emitLocal('roomClosed', data));
    HTDSocket.on('theme_update', data => emitLocal('themeUpdate', data));
  }

  async function init() {
    if (!window.HTD_CONFIG?.useBackend) return false;
    HTDSocket.connect();
    bindSocketEvents();
    return true;
  }

  function playerTokenStorageKey(pin, name) {
    return `htd_player_token:${pin}:${name}`;
  }

  function getStoredPlayerToken(pin, name) {
    try { return sessionStorage.getItem(playerTokenStorageKey(pin, name)); } catch { return null; }
  }

  function storePlayerToken(pin, name, token) {
    try { sessionStorage.setItem(playerTokenStorageKey(pin, name), token); } catch { /* ignore */ }
  }

  async function joinRoom({ pin, name, isHost = false, avatar = null }) {
    isHost = Boolean(isHost);
    await HTDSocket.syncNtp();
    const payload = { pin, name, is_host: isHost };
    if (!isHost && avatar) payload.avatar = avatar;
    const storedToken = getStoredPlayerToken(pin, name);
    if (storedToken) payload.player_token = storedToken;
    const data = await HTDSocket.emit('join_room', payload);
    if (data.player_token) storePlayerToken(pin, name, data.player_token);
    roomMeta = { pin, name, avatar: avatar || null, ...data };
    return data;
  }

  async function hostStartGame() {
    return HTDSocket.emit('host_start_game', {});
  }

  async function hostFinalizeQuestion() {
    return HTDSocket.emit('host_finalize_question', {});
  }

  async function hostNextQuestion() {
    return HTDSocket.emit('host_next_question', {});
  }

  async function hostEndGame() {
    return HTDSocket.emit('host_end_game', {});
  }

  async function submitAnswer(questionId, answer) {
    return HTDSocket.emit('submit_answer', {
      question_id: questionId,
      answer,
      hybrid_timestamp: HTDSocket.hybridTimestamp(),
    });
  }

  function getRoomMeta() {
    return roomMeta;
  }

  function setRoomMeta(meta) {
    roomMeta = meta;
  }

  return {
    init,
    on,
    joinRoom,
    hostStartGame,
    hostFinalizeQuestion,
    hostNextQuestion,
    hostEndGame,
    submitAnswer,
    getRoomMeta,
    setRoomMeta,
    isBackendEnabled() {
      return Boolean(window.HTD_CONFIG?.useBackend);
    },
  };
})();
