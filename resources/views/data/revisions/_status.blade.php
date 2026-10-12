@switch($revision->status)
    @case('done')
        <span class="status status-done">✓ Selesai</span>
        @break
    @case('processing')
        <span class="status status-processing">⟳ Diproses</span>
        @break
    @case('pending')
        <span class="status status-pending">○ Antre</span>
        @break
    @case('uploaded')
        <span class="status status-uploaded">○ Belum dikonfirmasi</span>
        @break
    @default
        <span class="status status-failed" title="{{ $revision->error_message }}">✕ Gagal</span>
@endswitch
