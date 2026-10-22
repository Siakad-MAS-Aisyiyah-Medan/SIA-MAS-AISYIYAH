import { Download, Pencil, Plus, Search, Trash2, Users } from 'lucide-react';

import PageHeader from '@/shared/components/PageHeader';

import { exportToExcel } from '@/shared/utils/exportExcel';

function statusLabel(kelas) {
  const status = String(kelas.status || '').toLowerCase();
  if (status === 'aktif') return 'Aktif';
  if (status === 'nonaktif') return 'Nonaktif';
  return Number(kelas.jumlah_siswa || 0) > 0 ? 'Aktif' : 'Nonaktif';
}

export default function KelasTable({
  filteredData,
  searchQuery,
  onSearchChange,
  onAdd,
  onEdit,
  onDelete,
  onViewStudents,
  isFetching = false,
  readOnly = false,
}) {
  const handleDownload = () => {
    const dataToExport = filteredData.map(item => ({
      'Tahun Ajaran': item.tahun_ajaran || '-',
      'Nama Kelas': item.nama_kelas || '-',
      'Tingkatan': item.tingkat || '-',
      'Jurusan': item.jurusan || '-',
      'Wali Kelas': item.wali_kelas?.nama_guru || 'Belum ditentukan',
      'Jumlah Murid': item.jumlah_siswa || 0,
      'Status': statusLabel(item),
    }));
    exportToExcel('Data_Kelas.xlsx', dataToExport);
  };

  return (
    <div className="animate-fade-in" style={{ background: 'var(--color-white)', minHeight: 'calc(100vh - 84px)', display: 'flex', flexDirection: 'column' }}>
      <PageHeader title="Data Kelas" subtitle="Kelola data kelas MAS Aisyiyah Medan">
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
                <span className="hidden sm:inline">Tambah Kelas</span>
                <span className="inline sm:hidden">Tambah</span>
              </button>
            </>
          )}
        </div>
      </PageHeader>

      <div className="flex flex-col sm:flex-row gap-4 mb-4 px-6 pt-4">
        <div style={{ position: 'relative', display: 'flex', alignItems: 'center', flex: 1, maxWidth: '400px' }}>
          <Search size={16} style={{ position: 'absolute', left: '0.85rem', color: 'var(--color-text-muted)', pointerEvents: 'none' }} />
          <input
            type="text"
            placeholder="Cari data kelas..."
            value={searchQuery}
            onChange={(e) => onSearchChange && onSearchChange(e.target.value)}
            style={{ paddingLeft: '2.5rem', height: '42px', border: '1px solid var(--color-border)', borderRadius: '10px', fontSize: '0.875rem', outline: 'none', width: '100%', background: '#fff', color: 'var(--color-text-dark)', boxShadow: '0 1px 2px rgba(0,0,0,0.05)' }}
          />
        </div>
      </div>

      {/* Table */}
      <div style={{ flex: 1, overflowX: 'auto' }}>
        <table className="data-table" style={{ width: '100%' }}>
          <thead>
            <tr>
              <th style={{ paddingLeft: '2rem' }}>No</th>
              <th>Tahun Ajaran</th>
              <th>Nama Kelas</th>
              <th>Tingkatan</th>
              <th>Jurusan</th>
              <th>Wali Kelas</th>
              <th style={{ textAlign: 'center' }}>Jumlah Murid</th>
              <th>Status</th>
              <th style={{ textAlign: 'right', paddingRight: '2rem' }}>Aksi</th>
            </tr>
          </thead>
          <tbody>
            {isFetching ? (
              <tr>
                <td colSpan={9} style={{ textAlign: 'center', padding: '3rem', color: 'var(--color-text-muted)' }}>
                  <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '0.75rem' }}>
                    <div className="animate-spin" style={{ width: '20px', height: '20px', border: '2px solid var(--color-primary-light)', borderTopColor: 'var(--color-primary)', borderRadius: '50%' }} />
                    Memuat data kelas...
                  </div>
                </td>
              </tr>
            ) : filteredData.length > 0 ? (
              filteredData.map((kelas, idx) => {
                const isAktif = statusLabel(kelas) === 'Aktif';
                return (
                  <tr key={kelas.id_kelas}>
                    <td style={{ color: 'var(--color-text-muted)', fontWeight: 600, paddingLeft: '2rem' }}>{idx + 1}</td>
                    <td>{kelas.tahun_ajaran || '-'}</td>
                    <td style={{ fontWeight: 600, color: 'var(--color-primary-dark)' }}>{kelas.nama_kelas}</td>
                    <td>
                      <span style={{ display: 'inline-block', padding: '0.2rem 0.6rem', borderRadius: '6px', fontSize: '0.8rem', fontWeight: 700, background: 'var(--color-primary-soft)', color: 'var(--color-primary-dark)' }}>
                        {kelas.tingkat || '-'}
                      </span>
                    </td>
                    <td>{kelas.jurusan || '-'}</td>
                    <td>{kelas.wali_kelas?.nama_guru || 'Belum ditentukan'}</td>
                    <td style={{ textAlign: 'center', fontWeight: 600, color: 'var(--color-text-dark)' }}>
                      {kelas.jumlah_siswa || 0} Siswa
                    </td>
                    <td>
                      <span style={{
                        display: 'inline-block',
                        padding: '0.25rem 0.75rem',
                        borderRadius: '50px',
                        fontSize: '0.75rem',
                        fontWeight: 700,
                        background: isAktif ? 'var(--color-primary-soft)' : '#fef2f2',
                        color: isAktif ? 'var(--color-primary-dark)' : '#991b1b',
                        border: `1px solid ${isAktif ? 'var(--color-primary-light)' : '#fecaca'}`,
                      }}>
                        {statusLabel(kelas)}
                      </span>
                    </td>
                    <td style={{ paddingRight: '2rem' }}>
                      <div className="actions-cell">
                        <button 
                          type="button" 
                          onClick={() => onViewStudents && onViewStudents(kelas)} 
                          className="btn-icon edit" 
                          title="Lihat Daftar Murid"
                          style={{
                            color: 'var(--color-primary)',
                            borderColor: 'var(--color-primary-light)',
                            background: 'var(--color-primary-soft)'
                          }}
                        >
                          <Users size={15} />
                        </button>
                        {!readOnly && (
                          <>
                            <button type="button" onClick={() => onEdit && onEdit(kelas)} className="btn-icon edit" title="Edit">
                              <Pencil size={15} />
                            </button>
                            <button type="button" onClick={() => onDelete && onDelete(kelas.id_kelas)} className="btn-icon delete" title="Hapus">
                              <Trash2 size={15} />
                            </button>
                          </>
                        )}
                      </div>
                    </td>
                  </tr>
                );
              })
            ) : (
              <tr>
                <td colSpan={readOnly ? 8 : 9} style={{ textAlign: 'center', padding: '3rem', color: 'var(--color-text-muted)' }}>
                  <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '0.5rem' }}>
                    <div style={{ fontSize: '2rem' }}>🏫</div>
                    <p style={{ fontWeight: 600 }}>Tidak ada data kelas</p>
                    <p style={{ fontSize: '0.875rem' }}>Tambah kelas baru untuk memulai</p>
                  </div>
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      <div style={{ padding: '1rem 2rem', fontSize: '0.85rem', color: 'var(--color-text-muted)', borderTop: '1px solid var(--color-border)', background: '#f8fafc' }}>
        Menampilkan {filteredData.length} data kelas
      </div>
    </div>
  );
}
