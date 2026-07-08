<div class="teacher-app admin-host-app">
<section class="teacher-screen active" data-screen="dashboard">
  <div class="teacher-layout">

    <div class="teacher-main">
      <div class="teacher-main-grid">

        <div class="teacher-room-card" id="teacherRoomCard">
          <header class="teacher-room-card-header" id="teacherRoomHeader">
            <div class="teacher-room-card-info" id="teacherRoomInfo">
              <div class="teacher-room-title-row">
                <a href="{{ route('admin.sessions.index') }}" class="teacher-room-back" title="Danh sách phòng">← Phòng chơi</a>
                <span class="teacher-join-room-label">Đang host</span>
              </div>
              <h1 class="teacher-join-room-name" id="teacherRoomName"></h1>
              <div class="teacher-room-meta-row" id="teacherRoomMetaRow">
                <span class="teacher-room-meta-chip teacher-room-meta-chip--quiz" id="teacherQuizName" hidden></span>
                <span class="teacher-room-meta-chip teacher-room-meta-chip--game" id="teacherGameName" hidden></span>
              </div>
              <span class="teacher-join-teacher" id="teacherTeacherName"></span>
            </div>
            <div class="teacher-room-card-actions">
              <span class="teacher-status-pill" id="teacherStatusPill">Đang chờ</span>
              <div class="teacher-room-quick-actions" id="teacherRoomQuickActions">
                <button type="button" class="teacher-room-quick-btn" id="teacherCopyPinBtn" title="Copy PIN">Copy PIN</button>
                <button type="button" class="teacher-room-quick-btn" id="teacherCopyLinkBtn" title="Copy link tham gia">Copy link HS</button>
              </div>
              <button type="button" class="btn-end-room" id="btnEndRoom" onclick="resetTeacherRoom()">Kết thúc phòng</button>
            </div>
          </header>

          <div class="teacher-room-card-body">
            <div class="teacher-waiting-view" id="teacherWaitingView">
              <div class="teacher-join-illustration-wrap">
                <img src="{{ asset('htd-admin/assets/waiting-room.png') }}" alt="" class="teacher-join-illustration">
                <p class="teacher-waiting-caption">Chia sẻ PIN hoặc QR để học sinh tham gia trên điện thoại</p>
              </div>

              <div class="teacher-join-bottom">
                <div class="teacher-join-card-pin">
                  <p class="teacher-pin-label">Mã PIN</p>
                  <div class="teacher-pin-digits" id="teacherPinDigits"></div>
                </div>

                <div class="teacher-join-card-qr">
                  <button type="button" class="teacher-qr-enlarge-btn" id="teacherQrEnlargeBtn" aria-label="Phóng to QR và PIN">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                      <path d="M15 3h6v6"/><path d="M9 21H3v-6"/><path d="M21 3l-7 7"/><path d="M3 21l7-7"/>
                    </svg>
                  </button>
                  <div class="qr-frame teacher-qr-presentation">
                    <img src="{{ $qrUrl }}" alt="QR tham gia {{ $joinUrl }}" class="qr-mock-img" data-join-url="{{ $joinUrl }}">
                    <span class="qr-bracket tl"></span>
                    <span class="qr-bracket tr"></span>
                    <span class="qr-bracket bl"></span>
                    <span class="qr-bracket br"></span>
                  </div>
                  <p class="teacher-qr-hint">Quét mã QR để tham gia</p>
                  <p class="teacher-qr-join-url" style="font-size:12px;word-break:break-all;opacity:.75;margin:8px 0 0">{{ $joinUrl }}</p>
                </div>
              </div>
            </div>

            <div class="teacher-game-view" id="teacherGameView" hidden>
              <div class="teacher-game-toolbar">
                <div class="teacher-game-controls">
                  <div class="teacher-game-controls-left">
                    <span class="teacher-q-progress" id="teacherQProgress">Câu 1</span>
                    <div class="teacher-q-timer-pill" id="teacherTimerPill">
                      <span class="teacher-q-timer-icon" aria-hidden="true">⏱</span>
                      <span id="teacherTimerText">00:00</span>
                    </div>
                    <span class="teacher-q-type" id="teacherQType">Trắc nghiệm</span>
                    <p class="teacher-submit-count" id="teacherSubmitCount" hidden></p>
                  </div>
                  <div class="teacher-game-controls-right">
                    <div class="teacher-zoom-controls" aria-label="Phóng to thu nhỏ đề bài">
                      <button type="button" class="teacher-zoom-btn" onclick="teacherZoomOut()" aria-label="Thu nhỏ chữ">A−</button>
                      <button type="button" class="teacher-zoom-btn" onclick="teacherZoomIn()" aria-label="Phóng to chữ">A+</button>
                    </div>
                    <div class="teacher-action-wrap">
                      <button type="button" class="teacher-action-btn" id="teacherActionBtn" aria-haspopup="true" aria-expanded="false">
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                        Hành động
                      </button>
                      <div class="teacher-action-menu" id="teacherActionMenu" hidden>
                        <button type="button" id="teacherPresentationMenuBtn" onclick="toggleTeacherPresentation()">Bật trình chiếu</button>
                        <button type="button" id="teacherToggleScoresMenuBtn" onclick="toggleTeacherScorePanel()">Hiện bảng điểm</button>
                        <button type="button" id="teacherPauseBtn" onclick="teacherTogglePause()">Tạm dừng</button>
                        <button type="button" onclick="teacherEndGame()">Kết thúc trò chơi</button>
                        <button type="button" onclick="teacherPlayAgain()">Chơi lại</button>
                        <button type="button" class="teacher-action-danger" onclick="resetTeacherRoom()">Kết thúc phòng</button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="teacher-game-body">
                <div class="teacher-game-phase" id="teacherQuestionPhase">
                  <div class="teacher-q-card" id="teacherQCard">
                    <div class="teacher-q-card-fit" id="teacherQCardFit">
                      <div class="teacher-q-card-inner" id="teacherQCardInner">
                        <div class="teacher-q-content" id="teacherQContent"></div>
                        <p class="teacher-q-prompt" id="teacherQPrompt" hidden></p>
                        <img class="teacher-q-media" id="teacherQImage" alt="" hidden>
                        <div class="teacher-q-video-wrap" id="teacherQVideoWrap" hidden>
                          <video id="teacherQVideo" controls playsinline></video>
                        </div>
                        <div class="teacher-q-answers" id="teacherQAnswers"></div>
                        <div class="teacher-q-eq" id="teacherQEq" hidden></div>
                      </div>
                    </div>
                  </div>
                  <aside class="teacher-q-side" id="teacherQSide">
                    <div class="teacher-q-barem" id="teacherQBarem" hidden></div>
                    <div class="teacher-q-timeline" id="teacherTimeline" aria-label="Tiến trình câu hỏi"></div>
                  </aside>
                </div>

                <div class="teacher-game-phase" id="teacherFinalPhase" hidden>
                  <div class="teacher-final-wrap">
                    <div class="teacher-final-banner">🎉 Kết thúc trò chơi</div>
                    <p class="teacher-final-hint">Học sinh đang xem bảng xếp hạng trên thiết bị của mình.</p>
                    <div class="teacher-final-lb" id="teacherFinalLeaderboard"></div>
                    <div class="teacher-final-actions">
                      @if ($session->status === 'ended' && $session->is_active)
                        <form
                          method="POST"
                          action="{{ route('admin.sessions.reset', $session) }}"
                          class="inline-form"
                          onsubmit="return confirm('Chơi lại với cùng PIN? Kết quả lần trước vẫn lưu trong báo cáo.');"
                        >
                          @csrf
                          <button type="submit" class="btn-primary">Chơi lại</button>
                        </form>
                      @else
                        <button type="button" class="btn-primary" onclick="teacherPlayAgain()">Chơi lại</button>
                      @endif
                      <button type="button" class="btn-primary" id="btnExportCsv" onclick="teacherExportCsv()">Tải CSV kết quả</button>
                      <a href="{{ route('admin.reports.index') }}" class="btn-secondary" target="_blank" rel="noopener">Xem báo cáo Admin</a>
                      <a href="{{ route('admin.sessions.index') }}" class="btn-secondary">← Danh sách phòng</a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="teacher-main-resizer" id="teacherMainResizer" role="separator" aria-orientation="vertical" aria-label="Điều chỉnh độ rộng">
          <span class="teacher-main-resizer-grip" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M8 8l-3 3 3 3"/><path d="M16 8l3 3-3 3"/>
            </svg>
          </span>
        </div>

        <section class="teacher-panel teacher-panel-players" id="teacherPlayersPanel">
          <div class="teacher-panel-head">
            <div>
              <h2 class="teacher-panel-title" id="teacherPanelTitle">Danh sách học sinh</h2>
              <p class="teacher-count" id="teacherCount">0 học sinh</p>
            </div>
            <button class="btn-primary teacher-btn-start" id="btnStartGame" onclick="teacherStartGame()" disabled>
              Bắt đầu trò chơi
            </button>
          </div>
          <div class="teacher-player-grid" id="teacherList"></div>
          <div class="teacher-score-list" id="teacherScoreList" hidden></div>
        </section>

      </div>
    </div>

  </div>
</section>

<div class="teacher-qr-modal" id="teacherQrModal" aria-hidden="true">
  <div class="teacher-qr-modal-backdrop" id="teacherQrModalBackdrop"></div>
  <div class="teacher-qr-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="teacherQrModalTitle">
    <button type="button" class="teacher-qr-modal-close" id="teacherQrModalClose" aria-label="Đóng">×</button>
    <div class="teacher-qr-modal-body">
      <h2 class="teacher-qr-modal-title" id="teacherQrModalTitle">Quét QR để tham gia</h2>
      <div class="qr-frame teacher-qr-modal-frame">
        <img src="{{ $qrUrl }}" alt="QR tham gia {{ $joinUrl }}" class="qr-mock-img" data-join-url="{{ $joinUrl }}">
        <span class="qr-bracket tl"></span>
        <span class="qr-bracket tr"></span>
        <span class="qr-bracket bl"></span>
        <span class="qr-bracket br"></span>
      </div>
      <p class="teacher-qr-modal-hint">Quét để mở: {{ $joinUrl }}</p>
      <div class="teacher-qr-modal-pin">
        <div class="teacher-pin-digits teacher-pin-digits-large" id="teacherPinDigitsModal"></div>
      </div>
    </div>
  </div>
</div>

<div class="teacher-lb-modal" id="teacherLbModal" aria-hidden="true" hidden>
  <div class="teacher-lb-modal-backdrop" id="teacherLbModalBackdrop"></div>
  <div class="teacher-lb-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="teacherLbModalTitle">
    <div class="teacher-lb-modal-head">
      <h2 class="teacher-lb-modal-title" id="teacherLbModalTitle">🏆 Bảng xếp hạng</h2>
      <p class="teacher-lb-modal-countdown" id="teacherLbModalCountdown">Câu tiếp sau 5s...</p>
    </div>
    <div class="teacher-lb-modal-list" id="teacherLbModalList"></div>
  </div>
</div>

<div class="teacher-presentation" id="teacherPresentation" hidden>
  <div class="teacher-presentation-inner">
    <div class="teacher-presentation-topbar">
      <div class="teacher-presentation-topbar-right">
        <div class="teacher-presentation-zoom" aria-label="Phóng to thu nhỏ chữ trình chiếu">
          <button type="button" class="teacher-zoom-btn" onclick="teacherZoomOut()" aria-label="Thu nhỏ chữ trình chiếu">A−</button>
          <button type="button" class="teacher-zoom-btn" onclick="teacherZoomIn()" aria-label="Phóng to chữ trình chiếu">A+</button>
        </div>
        <button type="button" class="teacher-presentation-exit" id="teacherPresentationExit" onclick="toggleTeacherPresentation()">Thoát trình chiếu</button>
      </div>
    </div>

    <section class="teacher-presentation-screen" id="teacherPresentationQuestion" hidden>
      <div class="teacher-presentation-question-head">
        <div class="teacher-presentation-countdown teacher-presentation-countdown-q" id="teacherPresentationQuestionCountdown">
          <svg viewBox="0 0 120 120" aria-hidden="true">
            <circle class="teacher-presentation-ring-bg" cx="60" cy="60" r="52"></circle>
            <circle class="teacher-presentation-ring-fg teacher-presentation-ring-fg-q" id="teacherPresentationQuestionRing" cx="60" cy="60" r="52"></circle>
          </svg>
          <span id="teacherPresentationQuestionCountdownText">0</span>
        </div>
      </div>
      <div class="teacher-presentation-question-wrap">
        <p class="teacher-presentation-progress" id="teacherPresentationProgress">Câu 1/1</p>
        <div class="teacher-presentation-content" id="teacherPresentationContent"></div>
        <p class="teacher-presentation-prompt" id="teacherPresentationPrompt" hidden></p>
        <img class="teacher-presentation-media" id="teacherPresentationImage" alt="" hidden>
        <div class="teacher-presentation-video-wrap" id="teacherPresentationVideoWrap" hidden>
          <video id="teacherPresentationVideo" playsinline controls></video>
        </div>
        <div class="teacher-presentation-answers" id="teacherPresentationAnswers"></div>
        <div class="teacher-presentation-eq" id="teacherPresentationEq" hidden></div>
      </div>
    </section>

    <section class="teacher-presentation-screen" id="teacherPresentationLeaderboard" hidden>
      <div class="teacher-presentation-leader-head">
        <h2>Bảng điểm hiện tại</h2>
        <div class="teacher-presentation-countdown" id="teacherPresentationCountdown">
          <svg viewBox="0 0 120 120" aria-hidden="true">
            <circle class="teacher-presentation-ring-bg" cx="60" cy="60" r="52"></circle>
            <circle class="teacher-presentation-ring-fg" id="teacherPresentationRing" cx="60" cy="60" r="52"></circle>
          </svg>
          <span id="teacherPresentationCountdownText">5</span>
        </div>
      </div>
      <p class="teacher-presentation-next" id="teacherPresentationNext">Vào câu tiếp theo sau 5 giây</p>
      <div class="teacher-presentation-score-list" id="teacherPresentationScoreList"></div>
    </section>
  </div>
</div>
</div>
