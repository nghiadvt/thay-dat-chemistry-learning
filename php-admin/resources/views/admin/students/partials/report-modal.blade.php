{{-- Modal xem bài làm của một học sinh (mở bằng openStudentReports(url)).
     Style nằm trong admin-students.css (.stat-modal, .stat-rec). --}}
<div class="stat-modal" id="studentReportModal" onclick="if (event.target === this) closeStudentReports()">
    <div class="stat-modal__box">
        <div class="stat-modal__head">
            <div>
                <h3 style="margin:0" id="reportModalTitle">Thống kê</h3>
                <p class="page-header__meta" id="reportModalMeta"></p>
            </div>
            <button type="button" class="btn" onclick="closeStudentReports()">Đóng</button>
        </div>
        <div id="reportModalBody">Đang tải…</div>
    </div>
</div>

@push('scripts')
<script>
const reportModal = document.getElementById('studentReportModal');

function closeStudentReports() {
    reportModal.classList.remove('is-open');
}

async function openStudentReports(url) {
    reportModal.classList.add('is-open');
    document.getElementById('reportModalBody').textContent = 'Đang tải…';
    document.getElementById('reportModalTitle').textContent = 'Thống kê';
    document.getElementById('reportModalMeta').textContent = '';

    try {
        const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const json = await res.json();
        if (!res.ok || json.success === false) throw new Error(json.error || 'Không tải được thống kê.');
        renderStudentReports(json.data);
    } catch (err) {
        document.getElementById('reportModalBody').textContent = err.message;
    }
}

function renderStudentReports(data) {
    document.getElementById('reportModalTitle').textContent = 'Bài làm của ' + data.student.display_name;
    document.getElementById('reportModalMeta').textContent =
        data.student.username + (data.student.class_name ? ' · ' + data.student.class_name : '');

    const body = document.getElementById('reportModalBody');
    if (!data.records.length) {
        body.textContent = 'Học sinh chưa có bài làm nào.';
        return;
    }

    body.innerHTML = data.records.map((r) => `
        <div class="stat-rec stat-rec--${r.grade}">
            <span class="stat-rec__no">${r.index}</span>
            <span class="stat-rec__name">${escapeHtml(r.label)}
                <span class="stat-rec__meta">${r.type === 'solo' ? '· tự luyện' : '· phòng chơi'}</span>
            </span>
            <span class="stat-rec__meta">Đúng <strong>${r.correct}/${r.total}</strong></span>
            <span class="stat-rec__meta">${r.duration}</span>
            <span class="stat-rec__meta">${r.played_at_text ?? ''}</span>
            <a class="btn" href="${r.detail_url}">Xem chi tiết</a>
        </div>
    `).join('');
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeStudentReports();
});
</script>
@endpush
