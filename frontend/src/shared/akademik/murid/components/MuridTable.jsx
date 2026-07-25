import { Download, Eye, Filter, Pencil, Plus, Search, ShieldCheck, Trash2 } from 'lucide-react';

import PageHeader from '@/shared/components/PageHeader';

import { exportToExcel } from '@/shared/utils/exportExcel';

export default function MuridTable({
  data,
  searchQuery,
  onSearchChange,
  onView,
  statusFilter = '',
  onStatusFilterChange,
  kelasFilter = '',
  onKelasFilterChange,
  kelasOptions = [],
  onPromote,
  onDelete,
  onEdit,
  isFetching = false,
  readOnly = false,
  onAdd,
}) {
  const handleDownload = () => {
    const dataToExport = data.map(item => {
      const status = item.siswa?.status_siswa || 'aktif';
      const nama = item.siswa?.nama_siswa || item.pendaftaran?.nama_lengkap || '-';
      const jenisKelamin = item.siswa?.jenis_kelamin || item.pendaftaran?.jenis_kelamin;
      const jkelLabel = jenisKelamin === 'L' ? 'Laki-Laki' : jenisKelamin === 'P' ? 'Perempuan' : '-';

      return {
        'Nama Murid': nama,
        'NISN': item.siswa?.nisn || item.pendaftaran?.nisn || '-',
        'Jenis Kelamin': jkelLabel,
        'Kelas': item.siswa?.kelas?.nama_kelas || '-',
        'Tahun Lulus': item.siswa?.tahun_lulus || '-',
        'Status': statusLabel(status),
        'Alamat': item.siswa?.alamat || item.pendaftaran?.alamat || '-',
      };
    });
    exportToExcel('Data_Murid.xlsx', dataToExport);
  };

  return (
    <div className="animate-fade-in" style={{ background: 'var(--color-white)', minHeight: 'calc(100vh - 84px)', display: 'flex', flexDirection: 'column' }}>
      <PageHeader title="Data Murid" subtitle="Kelola data siswa MAS Aisyiyah Medan">
        <div style={{ display: 'flex', gap: '0.75rem' }}>
          {readOnly ? (
            <button type="button" onClick={handleDownload} className="btn-primary" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.5rem' }}>
              <Download size={16} />
              <span className="hidden sm:inline">Unduh Data</span>
            </button>
          ) : (
            <>
              <button type="button" onClick={handleDownload} className="btn-outline" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.5rem', background: '#fff' }}>
                <Download size={16} />
                <span className="hidden sm:inline">Unduh Data</span>
              </button>
              <button type="button" onClick={onAdd} className="btn-primary" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.5rem' }}>
                <Plus size={16} />
                <span className="hidden sm:inline">Tambah Murid</span>
                <span className="inline sm:hidden">Tambah</span>
              </button>
            </>
          )}
        </div>
      </PageHeader>

      <div className="flex flex-col sm:flex-row gap-4 mb-4 px-6 pt-4" style={{ alignItems: 'center', flexWrap: 'wrap' }}>
        <div style={{ position: 'relative', display: 'flex', alignItems: 'center', flex: 1, maxWidth: '400px' }}>
          <Search size={16} style={{ position: 'absolute', left: '0.85rem', color: 'var(--color-text-muted)', pointerEvents: 'none' }} />
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => onSearchChange && onSearchChange(e.target.value)}
            placeholder="Cari data murid..."
            style={{ paddingLeft: '2.5rem', height: '42px', border: '1px solid var(--color-border)', borderRadius: '10px', fontSize: '0.875rem', outline: 'none', width: '100%', background: '#fff', color: 'var(--color-text-dark)', boxShadow: '0 1px 2px rgba(0,0,0,0.05)' }}
          />
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', flexWrap: 'wrap' }}>
          <Filter size={16} style={{ color: 'var(--color-text-muted)' }} aria-hidden="true" />
          <select
            aria-label="Filter status murid"
            value={statusFilter}
            onChange={(event) => onStatusFilterChange?.(event.target.value)}
            className="form-control"
            style={{ width: '170px', height: '42px', background: '#fff' }}
          >
            <option value="">Semua Status</option>
            <option value="aktif">Aktif</option>
            <option value="lulus">Lulus</option>
            <option value="tidak_aktif">Tidak Aktif</option>
          </select>
          <select
            aria-label="Filter kelas murid"
            value={kelasFilter}
            onChange={(event) => onKelasFilterChange?.(event.target.value)}
            className="form-control"
            style={{ width: '200px', height: '42px', background: '#fff' }}
          >
            <option value="">Semua Kelas</option>
            {kelasOptions.map((kelas) => (
              <option key={kelas.id_kelas} value={kelas.id_kelas}>
                {kelas.nama_kelas}
                {kelas.tahun_ajaran ? ` — ${kelas.tahun_ajaran}` : ''}
              </option>
            ))}
          </select>
        </div>
      </div>

      {/* Table */}
      <div style={{ flex: 1, overflowX: 'auto' }}>
        <table className="data-table" style={{ width: '100%' }}>
          <thead>
            <tr>
              <th style={{ paddingLeft: '2rem' }}>No</th>
              <th>Nama Murid</th>
              <th>NISN</th>
              <th>Kelas</th>
              <th>Tahun Lulus</th>
              <th>Status</th>
              {onView || !readOnly ? <th style={{ textAlign: 'right', paddingRight: '2rem' }}>Aksi</th> : null}
            </tr>
          </thead>
          <tbody>
            {isFetching ? (
              <tr>
                <td colSpan={onView || !readOnly ? 7 : 6} style={{ textAlign: 'center', padding: '3rem', color: 'var(--color-text-muted)' }}>
                  <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.75rem' }}>
                    <div className="animate-spin" style={{ width: '20px', height: '20px', border: '2px solid var(--color-primary-light)', borderTopColor: 'var(--color-primary)', borderRadius: '50%' }} />
                    Memuat data murid...
                  </div>
                </td>
              </tr>
            ) : data.length > 0 ? (
              data.map((murid, idx) => {
                const status = murid.siswa?.status_siswa || 'aktif';
                const badge = statusBadge(status);
                const nama = murid.siswa?.nama_siswa || murid.pendaftaran?.nama_lengkap || '-';
                const nisn = murid.siswa?.nisn || murid.pendaftaran?.nisn || '-';
                return (
                  <tr key={murid.id_user}>
                    <td style={{ color: 'var(--color-text-muted)', fontWeight: 600, paddingLeft: '2rem' }}>{idx + 1}</td>
                    <td style={{ fontWeight: 600, color: 'var(--color-primary-dark)', whiteSpace: 'nowrap', minWidth: '180px' }}>{nama}</td>
                    <td style={{ whiteSpace: 'nowrap' }}>{nisn}</td>
                    <td style={{ whiteSpace: 'nowrap' }}>{murid.siswa?.kelas?.nama_kelas || '-'}</td>
                    <td style={{ whiteSpace: 'nowrap' }}>{murid.siswa?.tahun_lulus || '-'}</td>
                    <td style={{ whiteSpace: 'nowrap' }}>
                      <span style={{
                        display: 'inline-block',
                        padding: '0.25rem 0.75rem',
                        borderRadius: '50px',
                        fontSize: '0.75rem',
                        fontWeight: 700,
                        background: badge.background,
                        color: badge.color,
                        border: `1px solid ${badge.border}`,
                      }}>
                        {statusLabel(status)}
                      </span>
                    </td>
                    {onView || !readOnly ? (
                      <td style={{ paddingRight: '2rem' }}>
                        <div className="actions-cell">
                          {onView ? (
                            <button type="button" onClick={() => onView(murid)} className="btn-icon" title="Lihat detail" aria-label={`Lihat detail ${nama}`}>
                              <Eye size={15} />
                            </button>
                          ) : null}
                          {!readOnly ? (
                            <button type="button" onClick={() => onEdit && onEdit(murid)} className="btn-icon edit" title="Edit" aria-label={`Edit ${nama}`}>
                              <Pencil size={15} />
                            </button>
                          ) : null}
                          {!readOnly && onPromote && murid.role !== 'siswa' ? (
                            <button type="button" onClick={() => onPromote(murid)} className="btn-icon" title="Promosikan" style={{ background: 'var(--color-primary-soft)', borderColor: 'var(--color-primary-light)', color: 'var(--color-primary)' }}>
                              <ShieldCheck size={15} />
                            </button>
                          ) : null}
                          {!readOnly && status !== 'lulus' ? (
                            <button type="button" onClick={() => onDelete && onDelete(murid)} className="btn-icon delete" title="Hapus data salah/duplikat">
                              <Trash2 size={15} />
                            </button>
                          ) : null}
                        </div>
                      </td>
                    ) : null}
                  </tr>
                );
              })
            ) : (
              <tr>
                <td colSpan={onView || !readOnly ? 7 : 6} style={{ textAlign: 'center', padding: '3rem', color: 'var(--color-text-muted)' }}>
                  <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '0.5rem' }}>
                    <div style={{ fontSize: '2rem' }}>🎓</div>
                    <p style={{ fontWeight: 600 }}>Data murid tidak ditemukan</p>
                    <p style={{ fontSize: '0.875rem' }}>Coba ubah pencarian atau filter yang digunakan</p>
                  </div>
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      <div style={{ padding: '1rem 2rem', fontSize: '0.85rem', color: 'var(--color-text-muted)', borderTop: '1px solid var(--color-border)', background: '#f8fafc' }}>
        Menampilkan {data.length} data murid
      </div>
    </div>
  );
}

function statusLabel(status) {
  if (status === 'lulus') return 'Lulus';
  if (status === 'tidak_aktif') return 'Tidak Aktif';
  return 'Aktif';
}

function statusBadge(status) {
  if (status === 'lulus') {
    return { background: '#eff6ff', color: '#1d4ed8', border: '#bfdbfe' };
  }
  if (status === 'tidak_aktif') {
    return { background: '#f8fafc', color: '#475569', border: '#cbd5e1' };
  }
  return {
    background: 'var(--color-primary-soft)',
    color: 'var(--color-primary-dark)',
    border: 'var(--color-primary-light)',
  };
}
